<?php declare(strict_types=1);

namespace DDT\Docker;

use DDT\CLI;
use DDT\Exceptions\Docker\DockerInspectException;
use DDT\Exceptions\Docker\DockerVolumeCreateException;
use DDT\Exceptions\Docker\DockerVolumeExistsException;
use DDT\Exceptions\Docker\DockerVolumeNotFoundException;

class DockerVolume
{
    /** @var CLI */
    private $cli;

    /** @var Docker */
    private $docker;

    /** @var string the name of this docker volume */
    private $name;

    /** @var string the docker volume id */
    private $id;

    public function __construct(CLI $cli, Docker $docker, string $name, ?bool $create=true)
    {
        $this->cli = $cli;
        $this->docker = $docker;
        $this->name = $name;

        try{
            $this->id = $this->getId();
        }catch(DockerVolumeNotFoundException $e){
            if($create === false){
                throw $e;
            }

            $this->id = $this->create($this->name);
        }
    }

    static public function instance(string $name, ?bool $create=true): DockerVolume
    {
        return container(DockerVolume::class, [
            'name' => $name, 
            'create' => $create
        ]);
    }

    public function create(string $name): string
    {
        try{
			$this->docker->inspect('volume', $name);

			// The network already exists, we can't create it again!
			throw new DockerVolumeExistsException($name);
		}catch(DockerInspectException $e){
			// The volume does not exist, lets try to create it
		}
		
		try{
			$r = $this->docker->exec("volume create $name 2>&1");
			
			return current($r);
		}catch(\Exception $e){
			$this->cli->debug("The docker volume '$name' failed to create with error:\n".$e->getMessage());

			throw new DockerVolumeCreateException($name);
		}
    }

    public function delete(): bool
    {
        try{
            $id = $this->getId();
            $this->docker->exec("volume rm $id");

            return true;
        }catch(DockerVolumeNotFoundException $e){
            return false;
        }
    }

    public function getId(): string
    {
        try{
            $id = $this->docker->inspect('volume', $this->name, '{{json .Name }}');

            return $id[0];
        }catch(\Exception $e){
            throw new DockerVolumeNotFoundException($this->name);
        }
    }
}