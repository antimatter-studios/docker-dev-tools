<?php declare(strict_types=1);

namespace DDT\Services;

use DDT\CLI;
use DDT\Config\Sections\ProjectConfig;
use DDT\Contract\ModelInterface;
use DDT\Exceptions\Project\ProjectNotFoundException;
use DDT\Model\Project\ProjectListModel;
use DDT\Model\Project\ProjectModel;
use DDT\Model\Project\ProjectPathListModel;

class ProjectService
{
    /** @var ProjectConfig */
    private $config;

    public function __construct(ProjectConfig $config)
    {
        $this->config = $config;
    }

    public function listProjects(): ProjectListModel
    {
        return $this->config->listProjects();
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
                foreach($project->listScripts() as $scriptName => $scriptCommand){
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

    public function findProject(string $name, ?string $path=null, ?string $group=null): ModelInterface
    {
        $list = $this->listProjectsByFilter(['name' => $name, 'path' => null, 'group' => $group]);

        if($list->count() > 1){
            throw new \Exception("The project '$name', in path '$path', with group '$group' returned multiple results");
        }

        return $list->first();
    }

    public function addGroup(string $project, string $group, ?string $path=null): bool
    {
        return $this->config->addGroup($project, $group, $path);
    }

    public function removeGroup(string $project, string $group, ?string $path=null): bool
    {
        return $this->config->removeGroup($project, $group, $path);
    }

    public function addProject(string $path, ?string $name=null, ?string $group=null): bool
    {
        return $this->config->addProject($path, $name, $group);
    }

    public function removeProject(string $project, ?string $path=null): bool
    {
        return $this->config->removeProject($project, $path);
    }

    public function addPath(string $path, ?string $group=null): bool
    {
        return $this->config->addPath($path, $group);
    }

    public function removePath(string $path): bool
    {
        return $this->config->removePath($path);
    }

    public function listPaths(): ProjectPathListModel
    {
        return $this->config->listPaths();
    }
}