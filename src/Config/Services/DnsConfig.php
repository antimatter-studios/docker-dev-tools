<?php declare(strict_types=1);

namespace DDT\Config\Services;

use DDT\Config\SystemConfig;

class DnsConfig
{
    private $keys = [
		'docker_image'		=> 'dns.docker_image',
		'container_name'	=> 'dns.container_name',
        'domains'           => 'dns.domains',
		'device'			=> 'dns.device',
		'upstream'			=> 'dns.upstream',
	];

    public function __construct(SystemConfig $config)
    {
        $this->config = $config;
	
		if($this->config->getKey($this->keys['docker_image']) === null){
			$this->setDockerImage(container('defaults.dns.docker_image'));
		}
	
		if($this->config->getKey($this->keys['container_name']) === null){
			$this->setContainerName(container('defaults.dns.container_name'));
		}

		if($this->config->getKey($this->keys['domains']) === null){
			$this->config->setKey($this->keys['domains'], []);
		}

		if($this->config->getKey($this->keys['upstream']) === null){
			$this->config->setKey($this->keys['upstream'], []);
		}
    }

	public function setDockerImage(string $image): string
	{
		$this->config->setKey($this->keys['docker_image'], $image);
		
		if($this->config->write()){
			return $image;
		}

		throw new \Exception('failed to write new dns docker image');
	}

	public function getDockerImage(): string
	{
		return $this->config->getKey($this->keys['docker_image']);
	}

	public function setContainerName(string $containerName): string
	{
		$this->config->setKey($this->keys['container_name'], $containerName);
		
		if($this->config->write()){
			return $containerName;
		}

		throw new \Exception('failed to write new dns container name');
	}

	public function getContainerName(): string
	{
		return $this->config->getKey($this->keys['container_name']);
	}

	public function listDomains(): array
	{
		return $this->config->getKey($this->keys['domains']);
	}

	public function addDomain(string $domain, string $ipAddress): bool
	{
		$list = $this->config->getKey($this->keys['domains']);
		
		if(array_key_exists($ipAddress, $list) === false){
			$list[$ipAddress] = [];
		}

		$list[$ipAddress][] = $domain;
		$list[$ipAddress] = array_unique(array_values($list[$ipAddress]));

		$this->config->setKey($this->keys['domains'], $list);

		return $this->config->write();
	}

	public function removeDomain(string $domain, ?string $ipAddress=null): bool
	{
		$list = $this->config->getKey($this->keys['domains']);

		foreach($list as $key => $domainList){
			// if ip address was given, but it doesn't match the current key, skip processing it
			if($ipAddress !== null && $key !== $ipAddress){
				continue;
			}

			$index = array_search($domain, $domainList);
			if($index !== false){
				unset($domainList[$index]);
			}
			$list[$key] = array_unique(array_values($domainList));

			// if the resulting list of domains is empty, remove the entire ip address from the domain config
			if(empty($list[$key])) unset($list[$key]);
		}

		$this->config->setKey($this->keys['domains'], $list);

		return $this->config->write();
	}

	public function getUpstreamList(): array
	{
		return $this->config->getKey($this->keys['upstream']);
	}

	public function addUpstream(string $address): bool 
	{
		$list = $this->getUpstreamList();

		$list[] = $address;
		$list = array_values(array_unique($list));

		$this->config->setKey($this->keys['upstream'], $list);

		return $this->config->write();
	}

	public function removeUpstream(string $address): bool 
	{
		$list = $this->getUpstreamList();
		
		$index = array_search($address, $list);
		if($index !== false){
			unset($list[$index]);
		}

		$list = array_values(array_unique($list));

		$this->config->setKey($this->keys['upstream'], $list);
		
		return $this->config->write();
	}

	public function setDevice(string $name, string $device): bool 
	{
		$this->config->setKey($this->keys['device'], ['name' => $name, 'device' => $device]);

		return $this->config->write();
	}

	public function getDevice(): array
	{
		return $this->config->getKey($this->keys['device']);
	}

	public function removeDevice(): bool
	{
		$this->config->deleteKey($this->keys['device']);
		
		return $this->config->write();
	}
}