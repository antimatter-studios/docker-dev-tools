<?php
namespace DDT\Exceptions\Project;

class ProjectScriptInvalidException extends \Exception
{
    private $group;
    private $project;
    private $script;

    public function __construct(?string $group, string $project, string $script, $code = 0, \Throwable $previous = null)
    {
        parent::__construct("The project '$project' does not have a valid commandline for script '$script'", $code, $previous);

        $this->group = $group;
        $this->project = $project;
        $this->script = $script;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function getProject(): string
    {
        return $this->project;
    }

    public function getScript(): string
    {
        return $this->script;
    }
};
