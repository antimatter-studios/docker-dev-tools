<?php
namespace DDT\Exceptions\Project;

class ProjectNotFoundException extends \Exception
{
    /** @var string */
    private $project;

    public function __construct(string $project, string $reason=null, $code = 0, \Throwable $previous = null)
    {
        $reason = $reason ?? 'no reason given';
        parent::__construct("Could not find project '$project', $reason", $code, $previous);

        $this->project = $project;
    }

    public function getProject(): string
    {
        return $this->project;
    }
};
