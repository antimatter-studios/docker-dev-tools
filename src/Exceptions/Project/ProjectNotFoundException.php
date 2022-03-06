<?php
namespace DDT\Exceptions\Project;

class ProjectNotFoundException extends \Exception
{
    /** @var string */
    private $project;

    public function __construct(string $project, $code = 0, \Throwable $previous = null)
    {
        parent::__construct("Could not find project '$project' with given parameters", $code, $previous);

        $this->project = $project;
    }

    public function getProject(): string
    {
        return $this->project;
    }
};
