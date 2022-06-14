<?php declare(strict_types=1);

namespace DDT\Model\Project;

use ArrayIterator;
use DDT\Model\ListModel;

class ProjectPathListModel extends ListModel
{
    public function __construct(...$variousPaths)
    {
        $this->list = [];

        foreach($variousPaths as $listPaths){
            foreach($listPaths as $path){
                if(is_array($path)){
                    $this->list[] = ProjectPathModel::fromArray($path);
                }
            }
        }

        parent::__construct($this->list, ProjectPathModel::class);
    }

    public function remove($path): ListModel
    {
        /** @var ProjectPathModel $item */
        foreach($this->list as $index => $item){
            if($item->getPath() === $path){
                unset($this->list[$index]);
                $this->list = array_values($this->list);
                return $this;
            }
        }

        throw new \InvalidArgumentException("The path '$path' was not found in the list");
    }

    public function listProjects(): ProjectListModel
    {
        $list = [];

        foreach($this->list as $item){
            $list[] = $item->listProjects();
        }

        return ProjectListModel::fromArray(...$list);
    }
}