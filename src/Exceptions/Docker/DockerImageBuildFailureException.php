<?php declare(strict_types=1);

namespace DDT\Exceptions\Docker;

class DockerImageBuildFailureException extends \Exception
{
    public function __construct(string $name, int $code = 0, \Throwable $previous = null)
    {
        parent::__construct("Docker could not build the image '$name'", $code, $previous);
    }
}