<?php
namespace DDT\Exceptions\Filesystem;

class DirectoryNotExistException extends \Exception
{
    public function __construct(string $path, $code = 0, \Throwable $previous = null)
    {
        parent::__construct("The directory '$path' does not exist", $code, $previous);
    }
};
