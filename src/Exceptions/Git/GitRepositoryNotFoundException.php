<?php declare(strict_types=1);

namespace DDT\Exceptions\Git;

class GitRepositoryNotFoundException extends GitRepositoryException
{
    public function __construct(string $path, $code = 0, \Throwable $previous = null)
    {
        parent::__construct("The path '$path' was not a usable repository", $code, $previous);
    }
}
