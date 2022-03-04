<?php
namespace DDT\Exceptions\Project;

class ProjectExistsException extends \Exception
{
    public function __construct(string $project, string $path, string $reason, $code = 0, \Throwable $previous = null)
    {
        parent::__construct("The project '$project' ($path) already exists: $reason", $code, $previous);
    }
};
