<?php declare(strict_types=1);

namespace DDT\Exceptions\Git;

class GitRepositoryCloneException extends GitRepositoryException
{
    public function __construct(string $url, string $path, $code = 0, \Throwable $previous = null)
    {
        parent::__construct("The repository at '$url' could not be cloned to '$path' successfully", $code, $previous);
    }
}
