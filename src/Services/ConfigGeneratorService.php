<?php declare(strict_types=1);

namespace DDT\Services;

use DDT\CLI;
use DDT\Debug;
use DDT\Config\Services\ConfigGeneratorConfig;
use DDT\Docker\DockerContainer;
use DDT\Docker\DockerNetwork;
use DDT\Exceptions\Docker\DockerContainerNotFoundException;
use DDT\Exceptions\Docker\DockerInspectException;

class ConfigGeneratorService
{
    /** @var CLI */
    private $cli;

	/** @var ConfigGeneratorConfig */
    private $config;

	/** @var DockerService */
	private $dockerService;

    public function __construct(CLI $cli, ConfigGeneratorConfig $config, DockerService $dockerService)
    {
        $this->cli = $cli;
        $this->config = $config;
		$this->dockerService = $dockerService;
    }

    public function getContainer(): DockerContainer
	{
		return DockerContainer::get($this->config->getContainerName());
	}

	public function pull()
	{
		$this->dockerService->pull($this->config->getDockerImage());
	}

    public function start()
    {
        try{
            $image = $this->config->getDockerImage();
            $name = $this->config->getContainerName();
            $env = ['CONFIG_PATH=/config'];

            if(Debug::$enabled){
                $env[] = "DEBUG=true";
            }

			$container = DockerContainer::background(
				$name, 
				'',
				$image, 
				[
                    '/var/run/docker.sock:/var/run/docker.sock:ro',
					'ddt_config_gen:/config',
				],
				[], // options
				$env, // env
			);
            $id = $container->getId();

			if(empty($networkList)){
				// use the networks from the configuration
				$networkList = $this->getNetworks();
			}
	
			foreach($networkList as $network){
				$this->cli->print("Connecting container '$name' to network '$network'\n");
				$network = DockerNetwork::instance($network, true);
				$network->attach($id);
			}

			$this->cli->print("Running image '$image' as '$name' using container id '$id'\n");
			return true;
		}catch(DockerContainerNotFoundException $e){
			$this->cli->failure("The container '$name' did not start correctly\n");
			return false;
        }
    }

    public function stop(): bool
	{
		try{
			$container = $this->getContainer();
			$container->stop();
			$container->delete();
			return true;
		}catch(DockerInspectException $e){
			$this->cli->print("{red}".$e->getMessage."{end}\n");
			return false;
		}
	}

    public function getNetworks(): array
	{
		$container = $this->getContainer();
		
		$list = array_merge(array_keys($container->listNetworks()), $this->config->listNetworks());
		$list = array_unique($list);
		$list = array_filter($list, function($a){ return $a !== 'bridge'; });
		
		return $list;
	}
}