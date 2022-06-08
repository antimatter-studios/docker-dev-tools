<?php declare(strict_types=1);

namespace DDT\Model\Project;

use ArrayIterator;
use DDT\Model\ListModel;

class ProjectPathListModel extends ListModel
{
    private $list;

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

        parent::__construct(new ArrayIterator($this->list));
    }

    static public function fromArray(...$variousPaths): self
    {
        return new self(...$variousPaths);
    }

    public function getData()
    {
        return $this->list;
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