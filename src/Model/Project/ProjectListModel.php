<?php declare(strict_types=1);

namespace DDT\Model\Project;

use DDT\Contract\ModelInterface;
use DDT\Exceptions\Project\ProjectFoundMultipleException;
use InvalidArgumentException;
use DDT\Exceptions\Project\ProjectNotFoundException;
use DDT\Model\ListModel;

class ProjectListModel extends ListModel
{
    public function __construct(...$projectList)
    {
        $this->list = [];

        foreach($projectList as $arg){
            if($arg instanceof ProjectPathListModel){
                // returns ProjectListModel
                $arg = $arg->listProjects();
            }

            if($arg instanceof ProjectPathModel){
                // returns ProjectListModel
                $arg = $arg->listProjects();
            }
            
            if(!is_iterable($arg)){
                throw new InvalidArgumentException("Argument must be iterable in order to process it");
            }

            foreach($arg as $item){
                if($item instanceof ProjectModel){
                    $this->addProject($item);
                }else if(is_array($item)){
                    $this->addProject(ProjectModel::fromArray($item));
                }else{
                    $type = gettype($item);
                    $type = $type !== 'object' ?: get_class($item);
                    throw new InvalidArgumentException("Argument inside an array was not a supported type; array or ProjectModel, type was '$type'");
                }
            }
        }

        parent::__construct($this->list, ProjectModel::class);
    }

    public function addProject(ProjectModel $project): void
    {
        $this->list[$project->getPath()] = $project;
    }

    public function removeProject(ProjectModel $project): bool
    {
        if(array_key_exists($project->getPath(), $this->list)){
            unset($this->list[$project->getPath()]);
            return true;
        }

        return false;
    }

    public function listProjects(): array
    {
        return $this->getData();
    }

    public function listProjectsByScript(string $script): self
    {
        return $this->filter(function(ProjectModel $project) use ($script) {
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

    public function listProjectsByFilter(array $filter): self
    {
        return $this->filter(function($project) use ($filter) {
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

    public function findProjectByPath(string $path): ModelInterface
    {
        $list = $this->listProjectsByFilter(['path' => $path]);

        if($list->count() === 0){
            throw new ProjectNotFoundException($path);
        }

        if($list->count() > 1){
            throw new ProjectFoundMultipleException($path);
        }

        return $list->first();
    }

    public function findProject(string $name, ?string $path=null, ?string $group=null): ModelInterface
    {
        $list = $this->listProjectsByFilter(['name' => $name, 'path' => null, 'group' => $group]);

        if($list->count() === 0){
            throw new ProjectNotFoundException($name);
        }

        if($list->count() > 1){
            throw new ProjectFoundMultipleException($name);
        }

        return $list->first();
    }
}