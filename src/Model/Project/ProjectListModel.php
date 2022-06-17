<?php declare(strict_types=1);

namespace DDT\Model\Project;

use DDT\Contract\CollectionInterface;
use DDT\Contract\ModelInterface;
use DDT\Exceptions\Filesystem\DirectoryNotExistException;
use DDT\Exceptions\Project\ProjectFoundMultipleException;
use InvalidArgumentException;
use DDT\Exceptions\Project\ProjectNotFoundException;
use DDT\Model\ListModel;

class ProjectListModel extends ListModel
{
    public function __construct(iterable $args)
    {
        $data = [];

        foreach($args as $item){
            if($item instanceof ProjectPathListModel){
                $item = $item->listProjects();
            }

            if($item instanceof ProjectPathModel){
                $item = $item->listProjects();
            }

            if($item instanceof ProjectModel){
                $data[$item->getPath()] = $item;
                continue;
            }

            try {
                $model = ProjectModel::fromArray($item);
                $data[$model->getPath()] = $model;
                continue;
            }catch(DirectoryNotExistException $e){
                // Reject configurations for directories that do not exist
                continue;
            }catch(\Throwable $e){
                // item was not a project model, so lets continue
            }

            if(!is_iterable($item)){
                throw new InvalidArgumentException("Argument must be iterable in order to process it");
            }

            foreach($item as $project){
                if($project instanceof ProjectModel){
                    $data[$project->getPath()] = $project;
                }else if(is_array($project)){
                    $project = ProjectModel::fromArray($project);
                    $data[$project->getPath()] = $project;
                }else{
                    $type = gettype($project);
                    $type = $type !== 'object' ? $type : get_class($project);
                    $type = is_scalar($project) ? "$type($project)" : $type;
                    throw new InvalidArgumentException("Argument inside an array was not a supported type; array or ProjectModel, type was '$type'");
                }
            }
        }

        parent::__construct($data, ProjectModel::class);
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

    public function listProjectsByScript(string $script): CollectionInterface
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

    public function listProjectsByFilter(array $filter): CollectionInterface
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
        if(is_dir($path)){
            $path = realpath($path);
        }

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
        $filter = ['name' => $name];
        if(!empty($path)) $filter['path'] = $path;
        if(!empty($group)) $filter['group'] = $group;

        $list = $this->listProjectsByFilter($filter);

        if($list->count() === 0){
            throw new ProjectNotFoundException($name);
        }

        if($list->count() > 1){
            throw new ProjectFoundMultipleException($name);
        }

        return $list->first();
    }

    public function filter(callable $callback): CollectionInterface
    {
        return self::fromArray(parent::filter($callback));
    }

    public function map(callable $callback): CollectionInterface
    {
        return self::fromArray(parent::map($callback));
    }
}