<?php declare(strict_types=1);

namespace DDT\Model\Project;

class ProjectList implements \JsonSerializable
{
    private $list = [];

    public function __construct(array $projectList=[])
    {
        foreach($projectList as $project){
            $this->list[] = Project::fromArray($project);
        }
    }

    static public function fromArray(array $data): ProjectList
    {
        return new self($data);
    }

	public function __toString(): string
	{
		return json_encode($this->get(), JSON_PRETTY_PRINT);
	}

	public function jsonSerialize(): array
	{
		return $this->get();
	}

	public function get(): array
	{
		return [];
	}
}