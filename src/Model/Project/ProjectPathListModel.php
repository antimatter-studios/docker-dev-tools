<?php declare(strict_types=1);

namespace DDT\Model\Project;

use DDT\Model\ListModel;

class ProjectPathListModel extends ListModel
{
    public function __construct(iterable $args)
    {
        $data = [];

        foreach($args as $item){
            if($item instanceof ProjectPathModel) {
                $model = $item;
                $data[$model->getPath()] = $model;
            }else if(is_array($item)) {
                $model = ProjectPathModel::fromArray($item);
                $data[$model->getPath()] = $model;
            }
        }

        parent::__construct($data, ProjectPathModel::class);
    }

    public function listProjects(): ProjectListModel
    {
        $list = $this->map(function($item) {
            return $item->listProjects();
        });

        return ProjectListModel::fromArray($list);
    }
}