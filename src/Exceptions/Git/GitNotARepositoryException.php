<?php declare(strict_types=1);

namespace DDT\Exceptions\Git;

class GitNotARepositoryException extends \Exception
{
    public function __construct(string $path, $code = 0, \Throwable $previous = null)
    {
        parent::__construct("The path '$path' was not a usable repository", $code, $previous);
    }
}
