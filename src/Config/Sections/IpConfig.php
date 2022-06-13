<?php declare(strict_types=1);

namespace DDT\Config\Sections;

use DDT\Config\SystemConfig;

class IpConfig
{
	private $keys = [
		'ip_address' => '.ip_address'
	];

    public function __construct(SystemConfig $config)
    {
        $this->config = $config;

		if($this->config->getKey($this->keys['ip_address']) === null){
			$this->set(container('defaults.ip_address'));
		}
    }

	public function get(): string
	{
		return $this->config->getKey($this->keys['ip_address']);
	}

	public function set(string $ipAddress): bool
	{
		$this->config->setKey($this->keys['ip_address'], $ipAddress);
		
		return $this->config->write();
	}
}