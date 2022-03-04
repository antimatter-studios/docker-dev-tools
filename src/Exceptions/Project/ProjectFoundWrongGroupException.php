<?php
namespace DDT\Exceptions\Project;

class ProjectFoundWrongGroupException extends \Exception
{
    public function __construct(string $project, string $group, $code = 0, \Throwable $previous = null)
    {
        parent::__construct("The project '$project' was found, but did not exist in group '$group'", $code, $previous);
    }
};
