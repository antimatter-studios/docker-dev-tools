<?php declare(strict_types=1);

namespace DDT\Docker;

use DDT\CLI;
use DDT\Exceptions\Docker\DockerException;
use DDT\Exceptions\Docker\DockerInspectException;
use DDT\Exceptions\Docker\DockerNetworkAlreadyAttachedException;
use DDT\Exceptions\Docker\DockerNetworkCreateException;
use DDT\Exceptions\Docker\DockerNetworkExistsException;
use DDT\Exceptions\Docker\DockerNetworkFailedAttachException;
use DDT\Exceptions\Docker\DockerNetworkFailedDetachException;
use DDT\Exceptions\Docker\DockerNetworkNotFoundException;
use DDT\Services\DockerService;

class DockerNetwork
{
    /** @var CLI */
    private $cli;

    /** @var DockerService */
    private $docker;

    /** @var string the name of this docker network */
    private $name;

    /** @var string the docker network id */
    private $id;

    public function __construct(CLI $cli, DockerService $docker, string $name, ?bool $create=false)
    {
        $this->cli = $cli;
        $this->docker = $docker;
        $this->name = $name;

        try{
            $this->id = $this->getId();
        }catch(DockerNetworkNotFoundException $e){
            if($create === false){
                throw $e;
            }

            $this->id = $this->create($name);
        }
    }

    static public function instance(string $name, ?bool $create=false): DockerNetwork
    {
        return container(DockerNetwork::class, [
            'name' => $name, 
            'create' => $create
        ]);
    }

    public function getId(): string
    {
        try{
            $id = $this->docker->inspect('network', $this->name, '{{json .Id }}');

            if(empty($id)){
                throw new DockerNetworkNotFoundException($this->name);
            }
    
            return $id[0];
        }catch(DockerInspectException $e){
            throw new DockerNetworkNotFoundException($this->name);
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function listContainers(): array
    {
        try{
            $result = $this->docker->inspect('network', $this->name, '{{json .Containers }}');
        
            return array_reduce(array_keys($result), function($a, $c) use ($result) {
                $a[$c] = $result[$c]['Name'];
                return $a;
            }, []);    
        }catch(DockerInspectException $e){
            throw new DockerNetworkNotFoundException($this->name);
        }
    }

    public function create(string $name): string
    {
        try{
			$this->docker->inspect('network', $name);

			// The network already exists, we can't create it again!
			throw new DockerNetworkExistsException($name);
		}catch(DockerInspectException $e){
			// The network does not exist, lets try to create it
		}

		try{
			$r = $this->docker->exec("network create $name 2>&1");
			
			return $r[0];
		}catch(\Exception $e){
			$this->cli->debug("docker network", "The docker network '$name' failed to create with error:\n".$e->getMessage());
			throw new DockerNetworkCreateException($name);
		}
    }

    public function delete(): bool
    {
        // TODO: attempt to delete docker network
        // TODO: if fails to delete, throw exception
        // TODO: if succeeds to delete, return true

        return true;
    }

	public function attach($containerId): void
	{
        try{
			// TODO: how can I detect whether the network already has this container before doing this?
			// TODO: It throws exceptions when this fails for various reasons
			$this->docker->exec("network connect $this->name $containerId");

            $containers = $this->listContainers();

            foreach($containers as $id => $name){
                if($id === $containerId) return;
            }

            throw new DockerNetworkFailedAttachException($this->name, $containerId);
		}catch(DockerException $e){
            if(!!preg_match(DockerService::DOCKER_NETWORK_ALREADY_ATTACHED, $e->getMessage())){
                throw new DockerNetworkAlreadyAttachedException($this->name, $containerId);
            }

            $this->cli->debug("docker network", 'Uncaught exception: ' . $e->getMessage());
        }
	}

	public function detach(string $containerId): void
	{
        // TODO: how can I detect whether the network does not have this container, before trying to delete it
		// TODO: It throws exceptions when this fails for various reasons
		$this->docker->exec("network disconnect $this->name $containerId");

        $containers = $this->listContainers();

        foreach($containers as $id => $name){
            if($id === $containerId) {
                throw new DockerNetworkFailedDetachException($this->name, $containerId);
            }
        }
	}
}