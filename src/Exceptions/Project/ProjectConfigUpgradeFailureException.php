<?php
namespace DDT\Exceptions\Project;

class ProjectConfigUpgradeFailureException extends \Exception
{
    public function __construct($before, $after, $code = 0, \Throwable $previous = null)
    {
        parent::__construct("An attempt to write a new upgraded configuration between '$before' and '$after' formats has failed, for safety, all execution will stop until the problem can be determined and fixed\n", $code, $previous);
    }
};
