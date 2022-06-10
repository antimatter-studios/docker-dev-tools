<?php declare(strict_types=1);

namespace DDT\Config\Services;

use DDT\Config\DefaultConfig;
use DDT\Config\SystemConfig;

class ProxyConfig
{
	/** @var SystemConfig */
	private $config;

	private $keys = [
		'docker_image'		=> 'proxy.docker_image',
		'container_name'	=> 'proxy.container_name',
		'network'			=> 'proxy.network',
	];

    public function __construct(SystemConfig $config, DefaultConfig $defaults)
    {
        $this->config = $config;

		if($this->config->getKey($this->keys['docker_image']) === null){
			$this->setDockerImage($defaults->getKey($this->keys['docker_image']));
		}

		if($this->config->getKey($this->keys['container_name']) === null){
			$this->setContainerName($defaults->getKey($this->keys['container_name']));
		}

		if($this->config->getKey($this->keys['network']) === null){
			$this->setNetworkList($defaults->getKey($this->keys['network']));
		}

		// TODO: remove after 01/07/2022
		if(strpos($this->getDockerImage(), 'christhomas') !== false){
			$this->setDockerImage($defaults->getKey($this->keys['docker_image']));
		}
    }

	public function getDockerImage(): string
	{
		return $this->config->getKey($this->keys['docker_image']);
	}

	public function setDockerImage(string $image): bool
	{
		if(empty($image)) return false;

		$this->config->setKey($this->keys['docker_image'], $image);
		
		return $this->config->write();
	}

	public function getContainerName(): string
	{
		return $this->config->getKey($this->keys['container_name']);
	}

	public function setContainerName(string $name): bool
	{
		if(empty($name)) return false;
		
		$this->config->setKey($this->keys['container_name'], $name);

		return $this->config->write();
	}

	public function listNetworks(): array
	{
		return $this->config->getKey($this->keys['network']);
	}

	public function setNetworkList(array $list): bool
	{
		if(empty($list)) return false;
		
		$this->config->setKey($this->keys['network'], $list);

		return $this->config->write();
	}

	public function addNetwork(string $network): bool
	{
		$list = $this->config->getKey($this->keys['network']);
		$list[] = $network;
		$list = array_unique(array_values($list));

		$this->config->setKey($this->keys['network'], $list);

		return $this->config->write();
	}

	public function removeNetwork(string $network): bool
	{
		$list = $this->config->getKey($this->keys['network']);

		foreach($list as $key => $compare){
			if($compare === $network) unset($list[$key]);
		}

		$this->config->setKey($this->keys['network'], $list);

		return $this->config->write();
	}
}