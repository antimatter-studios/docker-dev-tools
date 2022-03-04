<?php
namespace DDT\Exceptions\Project;

class ProjectNotFoundException extends \Exception
{
    public function __construct(string $project, $code = 0, \Throwable $previous = null)
    {
        parent::__construct("Could not find project '$project' with given parameters", $code, $previous);
    }
};
