<?php declare(strict_types=1);

namespace DDT\Exceptions\Docker;

class DockerImageBuildFailureException extends DockerException
{
    private $imageName;
    
    public function __construct(string $name, int $code = 0, \Throwable $previous = null)
    {
        parent::__construct("Docker could not build the image '$name'", $code, $previous);

        $this->setImageName($name);
    }

    public function setImageName(string $name): void
    {
        $this->imageName = $name;
    }

    public function getImageName(): string
    {
        return $this->imageName;
    }
}