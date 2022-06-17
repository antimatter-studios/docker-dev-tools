<?php declare(strict_types=1);

namespace DDT\Model\Project;

use DDT\Model\ListModel2;

class ProjectPathListModel extends ListModel2
{
    public function __construct(...$variousPaths)
    {
        $data = [];

        foreach($variousPaths as $listPaths){
            foreach($listPaths as $item){
                if(is_array($item)) {
                    $model = ProjectPathModel::fromArray($item);
                    $data[$model->getPath()] = $model;
                }else if($item instanceof ProjectPathModel) {
                    $model = $item;
                    $data[$model->getPath()] = $model;
                }
            }
        }

        parent::__construct($data, ProjectPathModel::class);
    }

    public function listProjects(): ProjectListModel
    {
        $list = $this->map(function($item) {
            return $item->listProjects();
        });

        return ProjectListModel::fromArray(...$list);
    }
}