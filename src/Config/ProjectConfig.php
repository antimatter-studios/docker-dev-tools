<?php declare(strict_types=1);

namespace DDT\Config;

use DDT\Config\External\ComposerProjectConfig;
use DDT\Config\External\NodeProjectConfig;
use DDT\Config\External\StandardProjectConfig;
use DDT\Contract\External\ProjectConfigInterface;
use DDT\Exceptions\Project\ProjectConfigUpgradeException;
use DDT\Exceptions\Project\ProjectExistsException;
use DDT\Exceptions\Project\ProjectFoundMultipleException;
use DDT\Exceptions\Project\ProjectNotFoundException;
use DDT\Model\Project;

class ProjectConfig
{
	private $key = 'projects-v2';

	/** @var SystemConfig $config */
	private $config;

	public function __construct(SystemConfig $config)
	{
		$this->config = $config;

		// Detect v1 projects and push the user to upgrade the configuration
		if($this->config->getKey('projects')){
			throw new ProjectConfigUpgradeException('upgrade-projects');
		}
	}

	public function listProjects(): array
	{
		$list = $this->config->getKey($this->key) ?? [];

		return array_map(function($item){
			return container(Project::class, $item);
		}, $list);
	}

	public function listProjectsByScript(string $script): array
	{
		$list = [];

		foreach($this->listProjects() as $path => $config){
            $projectConfig = $this->getProjectConfig($config['name'], $path);
            foreach($projectConfig->listScripts() as $scriptName => $scriptCommand){
				if($script !== $scriptName){
					continue;
				}

				$list[] = $config;
			}
		}

		return $list;
	}

	public function listProjectsInGroup(string $group): array
	{
		return $this->listProjectsByKey('group', $group);
	}

	public function listProjectsByName(string $project): array
	{
		return $this->listProjectsByKey('name', $project);
	}

	public function listProjectsByKey(string $key, string $value): array
	{
		return $this->listProjectsByFilter([$key => $value]);
	}

	public function listProjectsByFilter(array $filter): array
	{
		return array_filter($this->listProjects(), function($config) use ($filter) {
			foreach($filter as $key => $value){
				if(!array_key_exists($key, $config)) {
					return false;
				}else if(is_scalar($config[$key]) && $config[$key] !== $value){
					return false;
				}else if(is_array($config[$key]) && !in_array($value, $config[$key])){
					// But additionally; if you requested an empty group and the group list is empty, then this is a match
					if(!(empty($config[$key]) && empty($value))){
						return false;
					}
				}
			}

			return true;
		});
	}

	public function setType(string $project, ?string $group, ?string $path, string $type): bool
	{
		$projectList = $this->listProjects();

		if(!empty($path)){
			if(array_key_exists($path, $projectList)){
				// If path is specified, you don't need to care about the project name
				// If path is specified, you don't need to care about the group either
				// Just change the type of whatever project is here
				$projectList[$path]['type'] = $type;
				$this->config->setKey($this->key, $projectList);
				return $this->config->write();
			}else{
				throw new ProjectNotFoundException($project);
			}
		}

		$filteredList = array_filter($projectList, function($v) use ($project, $group) {
			if($v['name'] !== $project) return false;
			if(is_string($group) && !in_array($group, $v['group'])) return false;
			return true;
		});

		if(count($filteredList) > 1){
			throw new ProjectFoundMultipleException($project);
		}

		$first = array_shift($filteredList);

		$projectList[$first['path']]['type'] = $type;
		$this->config->setKey($this->key, $projectList);
		return $this->config->write();
	}

	public function addGroup(string $project, string $group, ?string $path=null): bool
	{
		$projectList = $this->listProjects();

		if(!empty($path)){
			if(array_key_exists($path, $projectList)){
				if($projectList[$path]['name'] !== $project){
					throw new ProjectNotFoundException($project);
				}

				$projectList[$path]['group'][] = $group;
				$projectList[$path]['group'] = array_unique($projectList[$path]['group']);
				$this->config->setKey($this->key, $projectList);
				return $this->config->write();
			}else{
				throw new ProjectNotFoundException($project);
			}
		}

		$filteredList = array_filter($projectList, function($v) use ($project) {
			return $v['name'] === $project;
		});

		if(count($filteredList) > 1){
			throw new ProjectFoundMultipleException($project);
		}

		$first = array_shift($filteredList);

		return $this->addGroup($project, $group, $first['path']);
	}

	public function removeGroup(string $project, string $group, ?string $path=null): bool
	{
		$projectList = $this->listProjects();

		if(!empty($path)){
			if(array_key_exists($path, $projectList)){
				if($projectList[$path]['name'] !== $project){
					throw new ProjectNotFoundException($project);
				}

				$projectList[$path]['group'] = array_filter(
					$projectList[$path]['group'], 
					function($v) use ($group) { return $group !== $v; }
				);

				$this->config->setKey($this->key, $projectList);
				return $this->config->write();
			}else{
				throw new ProjectNotFoundException($project);
			}
		}

		$filteredList = array_filter($projectList, function($v) use ($project) {
			return $v['name'] === $project;
		});

		if(count($filteredList) > 1){
			throw new ProjectFoundMultipleException($project);
		}

		$first = array_shift($filteredList);

		return $this->removeGroup($project, $group, $first['path']);
	}

	public function addProject(string $path, ?string $name=null, ?string $group=null): bool
	{
		// Convert group into an array
		$group = array_filter(explode(',', $group ?? ''));

		// Ensure the name is valid
		$name = $name ?? basename($path);

		$projectList = $this->listProjects();

		if(array_key_exists($path, $projectList)){
			throw new ProjectExistsException($name, $path, 'Cannot add same project twice');
		}

		array_filter($projectList, function($project) use ($name, $path, $group) {
			// If the name doesn't match, skip over this config
			if($project->getName() !== $project){
				return false;
			}

			if(empty($group)){
				throw new ProjectExistsException($project, $project->getPath(), 'Duplicate projects cannot have empty groups');
			}

			// If this duplicate group, overlaps one of it's groups, it would create a situation where you'd have the same project
			// existing in multiple groups with the same name, if you tried to operate upon it, which one would you target? Since
			// They both have the same name, but different directories, so could have different code too. Hence this situation
			// Is not possible to tolerate
			if(count(array_intersect($project->getGroups(), $group)) > 0){
				throw new ProjectExistsException($project, $path, 'Duplicate projects cannot overlap Groups');
			}

			return false;
		});

		$projectList[$path] = container(Project::class, [
			'path' => $path, 
			'name' => $name,
			'group' => $group, 
		]);

		$this->config->setKey($this->key, $projectList);
		
		return $this->config->write();
	}

	public function removeProject(string $project, ?string $path=null): bool
	{
		$projectList = $this->listProjects();

		if(!empty($path)){
			if(array_key_exists($path, $projectList)){
				if($projectList[$path]['name'] !== $project){
					throw new ProjectNotFoundException($project);
				}

				unset($projectList[$path]);
				$this->config->setKey($this->key, $projectList);
				return $this->config->write();
			}else{
				throw new ProjectNotFoundException($project);
			}
		}

		$filteredList = array_filter($projectList, function($v) use ($project) {
			return $v['name'] === $project;
		});

		if(count($filteredList) > 1){
			throw new ProjectFoundMultipleException($project);
		}

		$first = array_shift($filteredList);

		return $this->removeProject($project, $first['path']);
	}

	public function getProjectConfig(string $project, ?string $path=null, ?string $group=null): ProjectConfigInterface
	{
		$projectList = $this->listProjects();

		if(!empty($path)){
			if(array_key_exists($path, $projectList)){
				if($projectList[$path]['name'] !== $project){
					throw new ProjectNotFoundException($project);
				}
					
				$class = null;
				$type = $projectList[$path]['type'];
				$args = ['filename' => $path, 'project' => $project, 'group' => $group];

				if($type === 'ddt'){
					$class = StandardProjectConfig::class;
					$filename = $path . '/' . StandardProjectConfig::defaultFilename;
				}
				
				if($type === 'node'){
					$class = NodeProjectConfig::class;
					$filename = $path . '/' . NodeProjectConfig::defaultFilename;
				}
				
				if($type === 'composer'){
					$class = ComposerProjectConfig::class;
					$filename = $path . '/' . ComposerProjectConfig::defaultFilename;
				}

				if($class instanceof ProjectConfigInterface){
					return container($class, array_merge($args, ['filename' => $filename]));
				}
			}

			throw new ProjectNotFoundException($project);
		}

		$filteredList = array_filter($projectList, function($v) use ($project) {
			return $v['name'] === $project;
		});

		if(count($filteredList) > 1){
			throw new ProjectFoundMultipleException($project);
		}

		if(count($filteredList) === 0){
			throw new ProjectNotFoundException($project);
		}

		$first = array_shift($filteredList);

		return $this->getProjectConfig($project, $first['path'], $group);
	}
}