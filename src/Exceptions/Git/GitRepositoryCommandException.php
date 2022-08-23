<?php declare(strict_types=1);

namespace DDT\Exceptions\Git;

class GitRepositoryCommandException extends \Exception
{
    public function __construct(string $command, $code = 0, \Throwable $previous = null)
    {
        parent::__construct("The git command '$command' has failed", $code, $previous);
    }
}
