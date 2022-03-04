<?php declare(strict_types=1);

namespace DDT\Config;

use DDT\Config\External\ComposerProjectConfig;
use DDT\Config\External\NodeProjectConfig;
use DDT\Config\External\StandardProjectConfig;
use DDT\Exceptions\Project\ProjectConfigUpgradeException;
use DDT\Exceptions\Project\ProjectExistsException;
use DDT\Exceptions\Project\ProjectFoundMultipleException;
use DDT\Exceptions\Project\ProjectNotFoundException;

class ProjectConfig
{
	private $key = 'projects-v2';

	/** @var SystemConfig $config */
	private $config;

	public function __construct(SystemConfig $config)
	{
		$this->config = $config;

		if($this->config->getKey($this->key) === null){
			throw new ProjectConfigUpgradeException('upgrade-projects');
		}
	}

	public function listProjects(): array
	{
		return $this->config->getKey($this->key);
	}

	public function listProjectsInGroup(string $group): array
	{
		return array_filter($this->listProjects(), function($config) use ($group) {
			return in_array($group, $config['group']);
		});
	}

	public function listProjectsByName(string $project): array
	{
		return array_filter($this->listProjects(), function($config) use ($project) {
			return $project === $config['name'];
		});
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

	public function addProject(string $path, string $project, string $type, ?string $group=null, ?string $vcs=null, ?string $remote='origin'): bool
	{
		// Convert group into an array
		$group = array_filter(explode(',', $group ?? ''));

		$projectList = $this->listProjects();

		if(array_key_exists($path, $projectList)){
			throw new ProjectExistsException($project, $path, 'Cannot add same project twice');
		}

		array_filter($projectList, function($config) use ($project, $path, $group) {
			// If the name doesn't match, skip over this config
			if($config['name'] !== $project){
				return false;
			}

			if(empty($group)){
				throw new ProjectExistsException($project, $config['path'], 'Duplicate projects cannot have empty groups');
			}

			// If this duplicate group, overlaps one of it's groups, it would create a situation where you'd have the same project
			// existing in multiple groups with the same name, if you tried to operate upon it, which one would you target? Since
			// They both have the same name, but different directories, so could have different code too. Hence this situation
			// Is not possible to tolerate
			if(count(array_intersect($config['group'], $group)) > 0){
				throw new ProjectExistsException($project, $path, 'Duplicate projects cannot overlap Groups');
			}

			return false;
		});

		$projectList[$path] = [
			'name' => $project,
			'type' => $type,
			'path' => $path,
			'group' => $group,
			'vcs' => $vcs,
			'remote' => $remote,
		];

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

	public function getProjectConfig(string $project, ?string $path=null): StandardProjectConfig
	{
		$projectList = $this->listProjects();

		if(!empty($path)){
			if(array_key_exists($path, $projectList)){
				if($projectList[$path]['name'] !== $project){
					throw new ProjectNotFoundException($project);
				}
					
				// FIXME: what would be the group here??
				$group = "MONKEY";

				$type = $projectList[$path]['type'];
				$args = ['filename' => $path, 'group' => $group, 'project' => $project];

				if($type === 'ddt'){
					return container(StandardProjectConfig::class, $args);
				}
				
				if($type === 'node'){
					return container(NodeProjectConfig::class, $args);
				}
				
				if($type === 'composer'){
					return container(ComposerProjectConfig::class, $args);
				}
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

		if(count($filteredList) === 0){
			throw new ProjectNotFoundException($project);
		}

		$first = array_shift($filteredList);

		return $this->getProjectConfig($project, $first['path']);
	}
}