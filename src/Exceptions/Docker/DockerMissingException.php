<?php declare(strict_types=1);

namespace DDT\Exceptions\Docker;

class DockerMissingException extends DockerException
{
    public function __construct($message = "", $code = 0, \Throwable $previous = null)
    {
        parent::__construct("Docker is required to run this tool, please install it. $message", $code, $previous);
    }
}