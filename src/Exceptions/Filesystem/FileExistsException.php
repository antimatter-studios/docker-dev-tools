<?php
namespace DDT\Exceptions\Filesystem;

class FileExistsException extends \Exception
{
    public function __construct(string $path, int $code = 0, \Throwable $previous = null)
    {
        parent::__construct("The file '$path' already exists", $code, $previous);
    }
};
