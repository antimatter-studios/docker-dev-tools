<?php
namespace DDT\Exceptions\Project;

class ProjectFoundMultipleException extends \Exception
{
    public function __construct(string $project, $code = 0, \Throwable $previous = null)
    {
        parent::__construct("Multiple projects called '$project' were found, add the path to the parameters to reduce ambiguity", $code, $previous);
    }
};
