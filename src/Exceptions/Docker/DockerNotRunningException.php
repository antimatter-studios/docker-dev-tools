<?php declare(strict_types=1);

namespace DDT\Exceptions\Docker;

class DockerNotRunningException extends DockerException
{
    public function __construct($message = "", $code = 0, \Throwable $previous = null)
    {
        parent::__construct("Docker is not running. $message", $code, $previous);
    }

    static public function match($input): bool
    {
        return strpos($input, 'Cannot connect to the Docker daemon at unix:///var/run/docker.sock') !== false;
    }
}