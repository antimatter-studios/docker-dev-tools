<?php declare(strict_types=1);

namespace DDT\Config\Sections;

use DDT\Config\SystemConfig;

class PhpComposerConfig
{
    private $config;
    private $key = ".php_composer";
    private $defaultVolumeName = "ddt_composer";
    private $defaultContainerName = "ddt-composer";

    public function __construct(SystemConfig $config)
    {
        $this->config = $config;
    }

    public function enableCache(bool $status, ?string $name=null): bool
    {
        $name = $name ?? $this->defaultVolumeName;
        $this->config->setKey("$this->key.volume.enabled", $status);
        $this->config->setKey("$this->key.volume.name", $name);
        return $this->config->write();
    }

    public function getCacheName(): string
    {
        return $this->config->getKey("$this->key.volume.name") ?? $this->defaultVolumeName;
    }

    public function isCacheEnabled(): bool
    {
        return $this->config->getKey("$this->key.volume.enabled") ?? false;
    }

    public function setContainerName(string $name): bool
    {
        $this->config->setKey("$this->key.container_name", $name);
        return $this->config->write();
    }

    public function getContainerName(): string
    {
        return $this->config->getKey("$this->key.container_name") ?? $this->defaultContainerName;
    }
}