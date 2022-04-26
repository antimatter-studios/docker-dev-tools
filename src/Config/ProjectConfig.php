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
	private $version = 3;
	private $key = 'projects';

	/** @var SystemConfig $config */
	private $config;

	public function __construct(SystemConfig $config)
	{
		$this->config = $config;

		// If this object is a higher version than the configuration
		if($this->version > $this->config->getVersion()){
			throw new ProjectConfigUpgradeException();
		}
	}

	public function listProjects(): array
	{
		$list = $this->config->getKey("$this->key.list") ?? [];

		return array_map(function($item){
			return container(Project::class, $item);
		}, $list);
	}

	public function listProjectsByScript(string $script): array
	{
		$list = [];

		foreach($this->listProjects() as $path => $config){
			try{
				$projectConfig = $this->getProjectConfig($config->getName(), $path);
				foreach($projectConfig->listScripts() as $scriptName => $scriptCommand){
					if($script !== $scriptName){
						continue;
					}
	
					$list[] = $config;
				}
			}catch(ProjectNotFoundException $e){
				// Any project configuration that isn't found, we just skip over
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
			$config = $config->toArray();

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

	public function addGroup(string $project, string $group, ?string $path=null): bool
	{
		$projectList = $this->listProjects();

		if(!empty($path)){
			if(array_key_exists($path, $projectList)){
				if($projectList[$path]->getName() !== $project){
					throw new ProjectNotFoundException($project, 'project name does not match path');
				}

				$projectList[$path]->addGroup($group);
				$this->config->setKey($this->key, $projectList);
				return $this->config->write();
			}else{
				throw new ProjectNotFoundException($project, 'project with given path \'$path\' was not found');
			}
		}

		$filteredList = array_filter($projectList, function($v) use ($project) {
			return $v->getName() === $project;
		});

		if(count($filteredList) > 1){
			throw new ProjectFoundMultipleException($project);
		}

		$first = array_shift($filteredList);

		return $this->addGroup($project, $group, $first->getPath());
	}

	public function removeGroup(string $project, string $group, ?string $path=null): bool
	{
		$projectList = $this->listProjects();

		if(!empty($path)){
			if(array_key_exists($path, $projectList)){
				if($projectList[$path]->getName() !== $project){
					throw new ProjectNotFoundException($project, 'project name does not match path');
				}

				$projectList[$path]->removeGroup($group);

				$this->config->setKey($this->key, $projectList);
				return $this->config->write();
			}else{
				throw new ProjectNotFoundException($project, 'project with given path \'$path\' was not found');
			}
		}

		$filteredList = array_filter($projectList, function($v) use ($project) {
			return $v->getName() === $project;
		});

		if(count($filteredList) > 1){
			throw new ProjectFoundMultipleException($project);
		}

		$first = array_shift($filteredList);

		return $this->removeGroup($project, $group, $first->getPath());
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
				if($projectList[$path]->getName() !== $project){
					throw new ProjectNotFoundException($project, 'project name does not match path');
				}

				unset($projectList[$path]);
				$this->config->setKey($this->key, $projectList);
				return $this->config->write();
			}else{
				throw new ProjectNotFoundException($project, 'project with given path \'$path\' was not found');
			}
		}

		$filteredList = array_filter($projectList, function($v) use ($project) {
			return $v->getName() === $project;
		});

		if(count($filteredList) > 1){
			throw new ProjectFoundMultipleException($project);
		}

		$first = array_shift($filteredList);

		return $this->removeProject($project, $first->getPath());
	}

	public function getProjectConfig(string $project, ?string $path=null, ?string $group=null): ProjectConfigInterface
	{
		$projectList = $this->listProjects();

		$reason = null;

		if(!empty($path)){
			if(array_key_exists($path, $projectList)){
				if($projectList[$path]->getName() !== $project){
					throw new ProjectNotFoundException($project, 'project name does not match path');
				}
					
				$class = null;
				$type = $projectList[$path]->getType();
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

				if(is_subclass_of($class, ProjectConfigInterface::class)){
					return container($class, array_merge($args, ['filename' => $filename]));
				}
				
				$reason = "Project type '$type' does not match any allowed type";
			}else{
				$reason = "project with given path '$path' was not found";
			}

			throw new ProjectNotFoundException($project, $reason);
		}

		$filteredList = array_filter($projectList, function($v) use ($project) {
			return $v->getName() === $project;
		});

		if(count($filteredList) > 1){
			throw new ProjectFoundMultipleException($project);
		}

		if(count($filteredList) === 0){
			throw new ProjectNotFoundException($project);
		}

		$first = array_shift($filteredList);

		return $this->getProjectConfig($project, $first->getPath(), $group);
	}
}