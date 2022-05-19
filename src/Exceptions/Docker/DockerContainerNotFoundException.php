<?php declare(strict_types=1);

namespace DDT\Exceptions\Docker;

class DockerContainerNotFoundException extends DockerException
{
    private $name;
    
    public function __construct(string $name, int $code = 0, \Throwable $previous = null)
    {
        parent::__construct("Docker could not find the container '$name'", $code, $previous);
        $this->name = $name;
    }

    public function getContainerName(): string
    {
        return $this->name;
    }
}