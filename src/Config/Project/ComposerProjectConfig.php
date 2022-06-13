<?php declare(strict_types=1);

namespace DDT\Config\Project;

use DDT\Contract\Project\ProjectConfigInterface;

class ComposerProjectConfig extends AbstractProjectConfig implements ProjectConfigInterface
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