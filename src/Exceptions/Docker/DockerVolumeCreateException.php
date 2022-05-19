<?php declare(strict_types=1);

namespace DDT\Exceptions\Docker;

class DockerVolumeCreateException extends DockerException
{
    public function __construct(string $name, int $code = 0, \Throwable $previous = null)
    {
        $message = trim($previous ? $previous->getMessage() : 'no previous exception given');

        parent::__construct("Docker volume '$name' could not be created ($message)", $code, $previous);
    }
}