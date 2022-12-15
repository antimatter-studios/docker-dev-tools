<?php declare(strict_types=1);

namespace DDT\Exceptions\Git;

class GitRepositoryPermissionDeniedException extends GitRepositoryException
{
    const MESSAGE = "Permission denied";
    const COULD_NOT_READ = "Could not read from remote repository";
    const CORRECT_ACCESS_RIGHTS = "correct access rights";

    public function __construct($code = 0, \Throwable $previous = null)
    {
        parent::__construct(self::MESSAGE, $code, $previous);
    }

    /**
     * Throw if input contains a matching condition
     *
     * @param string $input
     * @return void
     */
    static public function throwIfMatches(string $input): void
    {
        if(strpos($input, self::MESSAGE) === false) {
            return;
        }

        if(strpos($input, self::COULD_NOT_READ) === false) {
            return;
        }

        if(strpos($input, self::CORRECT_ACCESS_RIGHTS) === false) {
            return;
        }

        throw new self();
    }
}
