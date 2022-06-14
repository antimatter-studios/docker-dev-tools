<?php declare(strict_types=1);

namespace DDT\Config\Sections;

use DDT\Config\Project\ComposerProjectConfig;
use DDT\Config\Project\NodeProjectConfig;
use DDT\Config\Project\StandardProjectConfig;
use DDT\Contract\Project\ProjectConfigInterface;
use DDT\Config\SystemConfig;
use DDT\Exceptions\Project\ProjectConfigUpgradeException;
use DDT\Exceptions\Project\ProjectExistsException;
use DDT\Exceptions\Project\ProjectFoundMultipleException;
use DDT\Exceptions\Project\ProjectNotFoundException;
use DDT\Model\Project\ProjectListModel;
use DDT\Model\Project\ProjectModel;
use DDT\Model\Project\ProjectPathListModel;
use DDT\Model\Project\ProjectPathModel;

class ProjectConfig
{
	const LIST_PATHS = 1;
	const LIST_PROJECTS = 2;
	const LIST_ALL = 3;

	private $version = 3;
	private $pathKey = '.projects.paths';
	private $listKey = '.projects.list';

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

	public function listProjects(int $mode=self::LIST_ALL): ProjectListModel
	{
		$path = $list = [];

		if($mode & self::LIST_PATHS){
			$path = $this->config->readModel($this->pathKey, ProjectPathListModel::class);
		}

		if($mode & self::LIST_PROJECTS){
			$list = $this->config->readModel($this->listKey, ProjectListModel::class);
		}

		return ProjectListModel::fromArray($path, $list);
	}

	public function listProjectsByFilter(array $filter): ProjectListModel
	{
		return $this->listProjects()->filter(function($project) use ($filter) {
			foreach($filter as $key => $value){
				if($key === 'name' && $project->getName() !== $value){
					return false;
				}

				if($key === 'path' && $project->getPath() !== $value){
					return false;
				}

				if($key === 'group' && !$project->hasGroup($value)){
					return false;
				}
			}

			return true;
		});
	}

    public function listProjectsByScript(string $script): ProjectListModel
    {
        return $this->listProjects()->filter(function(ProjectModel $project) use ($script) {
            try{
                $projectConfig = $this->getProjectConfig($project->getName(), $project->getPath());

                foreach($projectConfig->listScripts() as $scriptName => $scriptCommand){
                    if($script !== $scriptName){
                        continue;
                    }

                    return true;
                }
            }catch(ProjectNotFoundException $e){
                // Any project configuration that isn't found, we just skip over
            }

            return false;
        });
    }

	public function addGroup(string $project, string $group, ?string $path=null): bool
	{
		$projectList = $this->listProjects();

		if(!empty($path)){
			$p = $projectList->findProjectByPath($path, $project);

			if($p->getName() !== $project){
				throw new ProjectNotFoundException($project, 'project name does not match path');
			}

			$p->addGroup($group);
			$projectList->addProject($p);

			$this->config->setKey($this->listKey, $projectList);
			return $this->config->write();
		}

		$filteredList = $projectList->filter(function($v) use ($project) {
			return $v->getName() === $project;
		});

		if(count($filteredList) > 1){
			throw new ProjectFoundMultipleException($project);
		}

		$first = $filteredList->first();

		return $this->addGroup($project, $group, $first->getPath());
	}

	public function removeGroup(string $project, string $group, ?string $path=null): bool
	{
		$projectList = $this->listProjects();

		if(!empty($path)){
			$p = $projectList->findProjectByPath($path, $project);

			if($p->getName() !== $project){
				throw new ProjectNotFoundException($project, 'project name does not match path');
			}

			$p->removeGroup($group);
			$projectList->addProject($p);

			$this->config->setKey($this->listKey, $projectList);
			return $this->config->write();
		}

		$filteredList = $projectList->filter(function($v) use ($project) {
			return $v->getName() === $project;
		});

		if(count($filteredList) > 1){
			throw new ProjectFoundMultipleException($project);
		}

		$first = $filteredList->first();

		return $this->removeGroup($project, $group, $first->getPath());
	}

	public function addProject(string $path, ?string $name=null, ?string $group=null): bool
	{
		// Convert group into an array
		$group = array_filter(explode(',', $group ?? ''));

		// Ensure the name is valid
		$name = $name ?? basename($path);

		$projectList = $this->listProjects();

        try{
            if($projectList->findProjectByPath($path, $name)){
                throw new ProjectExistsException($name, $path, 'Cannot add same project twice');
            }
        }catch(ProjectNotFoundException $e){
            // If the project is not found, this means we can add it
        }

		$projectList->filter(function($project) use ($name, $path, $group) {
			// If the name doesn't match, skip over this config
			if($project->getName() !== $name){
				return false;
			}

			if(empty($group)){
				throw new ProjectExistsException($name, $project->getPath(), 'Duplicate projects cannot have empty groups');
			}

			// If this duplicate group, overlaps one of it's groups, it would create a situation where you'd have the same project
			// existing in multiple groups with the same name, if you tried to operate upon it, which one would you target? Since
			// They both have the same name, but different directories, so could have different code too. Hence this situation
			// Is not possible to tolerate
			if(count(array_intersect($project->getGroups()->getData(), $group)) > 0){
				throw new ProjectExistsException($name, $path, 'Duplicate projects cannot overlap Groups');
			}

			return false;
		});

		$projectList->addProject(ProjectModel::fromArray([
			'path' => $path, 
			'name' => $name,
			'group' => $group, 
		]));

		$this->config->setKey($this->listKey, $projectList);
		
		return $this->config->write();
	}

	public function removeProject(string $project, ?string $path=null): bool
	{
		$projectList = $this->listProjects();

		if(!empty($path)){
			$p = $projectList->findProjectByPath($path, $project);

			$projectList->removeProject($p);
			
			$this->config->setKey($this->listKey, $projectList);
			return $this->config->write();
		}

		$filteredList = $projectList->filter(function($v) use ($project) {
			return $v->getName() === $project;
		});

		if(count($filteredList) > 1){
			throw new ProjectFoundMultipleException($project);
		}

		$first = $filteredList->first();

		return $this->removeProject($project, $first->getPath());
	}

	public function getProjectConfig(string $project, ?string $path=null, ?string $group=null): ProjectConfigInterface
	{
		$projectList = $this->listProjects();

		if(!empty($path)){
			$p = $projectList->findProjectByPath($path, $project);

			$type = $p->getType();
            $typeMap = ['ddt' => StandardProjectConfig::class, 'node' => NodeProjectConfig::class, 'composer' => ComposerProjectConfig::class];

            if(array_key_exists($type, $typeMap) && is_subclass_of($typeMap[$type], ProjectConfigInterface::class)){
                return $typeMap[$type]::fromPath($path, $project, $group);
            }

			$reason = "Project type '$type' does not match any allowed type";

			throw new ProjectNotFoundException($project, $reason);
		}

		$filteredList = $projectList->filter(function($v) use ($project) {
			return $v->getName() === $project;
		});

		if(count($filteredList) > 1){
			throw new ProjectFoundMultipleException($project);
		}

		if(count($filteredList) === 0){
			throw new ProjectNotFoundException($project);
		}

		$first = $filteredList->first();

		return $this->getProjectConfig($project, $first->getPath(), $group);
	}

	public function listPaths(): array
	{
		$list = $this->config->getKey("$this->pathKey") ?? [];

		return array_map(function($item){
			return ProjectPathModel::fromArray($item);
		}, $list);
	}

	public function addPath(string $path, ?string $group=null): bool
	{
		$list = $this->listPaths();
		$list[$path] = ProjectPathModel::fromPath($path, $group);

        $this->config->setKey($this->pathKey, $list);

        return $this->config->write();
	}

	public function removePath(string $path): bool
	{
		$list = $this->listPaths();

        if(array_key_exists($path, $list)){
            unset($list[$path]);
        
            $this->config->setKey($this->pathKey, $list);
    
            return $this->config->write();
        }

        throw new \Exception("Project path '$path' does not exist");
	}
}