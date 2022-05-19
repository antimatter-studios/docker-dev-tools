<?php declare(strict_types=1);

namespace DDT\Exceptions\Docker;

class DockerVolumeExistsException extends DockerException
{
    public function __construct(string $name, int $code = 0, \Throwable $previous = null)
    {
        parent::__construct("Docker volume '$name' already exists, cannot create it again", $code, $previous);
    }
}