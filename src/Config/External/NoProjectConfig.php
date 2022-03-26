<?php declare(strict_types=1);

namespace DDT\Config\External;

use DDT\Contract\External\ProjectConfigInterface;

class NoProjectConfig extends AbstractProjectConfig implements ProjectConfigInterface
{
    public function __construct()
	{
        $this->setKey('.', []);
	}

    public function getDefaultFilename(): string
    {
        return '';
    }

    public function read(string $filename): void
    {
        // Do nothing, just because there is no file too read
    }

    public function write(?string $filename = null): bool
    {
        // Do nothing, just because there is no file to write
        return true;
    }
}