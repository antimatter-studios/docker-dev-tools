<?php declare(strict_types=1);

namespace DDT\Docker;

use DDT\CLI;
use DDT\Exceptions\Docker\DockerImageBuildFailureException;
use DDT\Exceptions\Docker\DockerImageNotFoundException;

class DockerImage
{
    /** @var CLI */
    private $cli;

    /** @var Docker */
    private $docker;

    /** @var string the name of this docker volume */
    private $name;

    public function __construct(CLI $cli, Docker $docker, string $name, ?string $dockerFile=null)
    {
        $this->cli = $cli;
        $this->docker = $docker;
        $this->name = $name;

        try{
            $this->findImage($name);
        }catch(DockerImageNotFoundException $e){
            if(empty($dockerFile)){
                throw $e;
            }

            // otherwise build the image
            if($this->buildImage($name, $dockerFile) === false){
                throw new DockerImageBuildFailureException($name);
            }
        }
    }

    static public function get(string $name): DockerImage
    {
        return container(DockerImage::class, [
            'name' => $name,
        ]);
    }

    static public function build(string $name, string $dockerFile): DockerImage
    {
        return container(DockerImage::class, [
            'name' => $name,
            'dockerFile' => $dockerFile,
        ]);
    }

    public function getName(): string
    {
        return $this->name;
    }

    private function findImage(string $name): bool
    {
        $result = $this->docker->exec("images $name --format \"{{.Repository}}:{{.Tag}}\"");

        if($result !== $name){
            throw new DockerImageNotFoundException($name);
        }

        return true;
    }

    private function buildImage(string $name, string $dockerFile): bool
    {
        $command = implode("\n", [
            "build -t $name . -f - <<EOF",
            $dockerFile,
            "EOF\n"
        ]);
        
        return $this->docker->passthru($command) === 0;
    }
}