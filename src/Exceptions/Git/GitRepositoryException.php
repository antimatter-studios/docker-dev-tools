<?php declare(strict_types=1);

namespace DDT\Exceptions\Git;

class GitRepositoryException extends \Exception
{
    public function __construct(string $message, $code = 0, \Throwable $previous = null)
    {
        parent::__construct("There was a git repository error: '$message'", $code, $previous);
    }
}
