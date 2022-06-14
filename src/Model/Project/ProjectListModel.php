<?php declare(strict_types=1);

namespace DDT\Model\Project;

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

    public function findProjectByPath(string $path, ?string $name=null): ProjectModel
    {
        if(array_key_exists($path, $this->list)){
            return $this->list[$path];
        }

        throw new ProjectNotFoundException($name ?? 'not specified', "project with given path \'$path\' was not found");
    }
}