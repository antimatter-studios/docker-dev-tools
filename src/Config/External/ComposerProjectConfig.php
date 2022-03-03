<?php declare(strict_types=1);

namespace DDT\Config\External;

class ComposerProjectConfig extends StandardProjectConfig
{
    const defaultFilename = 'composer.json';

	protected function initDataStore(): void
	{
        $this->setKey('.', $this->getKey('docker-dev-tools') ?? []);
	}

    public function getDefaultFilename(): string
    {
        return self::defaultFilename;
    }
}