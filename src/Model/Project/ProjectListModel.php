<?php declare(strict_types=1);

namespace DDT\Model\Project;

use DDT\Model\Model;

class ProjectListModel extends Model
{
    private $list = [];

    public function __construct(array $projectList=[])
    {
        foreach($projectList as $project){
            $this->list[] = ProjectModel::fromArray($project);
        }
    }

    static public function fromArray(array $data): ProjectListModel
    {
        return new self($data);
    }

	public function toArray(): array
	{
		return $this->list;
	}
}