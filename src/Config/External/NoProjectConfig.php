<?php declare(strict_types=1);

namespace DDT\Config\External;

class NoProjectConfig extends StandardProjectConfig
{
    public function __construct(?string $filename, string $project, ?string $group=null)
	{
		$this->group = $group;
		$this->project = $project;
        $this->setKey('.', []);
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