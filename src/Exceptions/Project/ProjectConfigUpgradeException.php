<?php
namespace DDT\Exceptions\Project;

class ProjectConfigUpgradeException extends \Exception
{
    public function __construct($code = 0, \Throwable $previous = null)
    {
        parent::__construct("The configuration contains an out of date format that must be upgraded, please run 'ddt config upgrade'", $code, $previous);
    }
};
