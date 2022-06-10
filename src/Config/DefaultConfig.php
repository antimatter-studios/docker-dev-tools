<?php declare(strict_types=1);

namespace DDT\Config;

class DefaultConfig extends JsonConfig
{
	const defaultFilename = 'default.ddt-system.json';

    public function __construct ()
    {
        parent::__construct(config('tools.path') . '/' . $this->getDefaultFilename(), true);
    }

	public function getDefaultFilename(): string
	{
		return self::defaultFilename;
	}

	static public function instance(): DefaultConfig
	{
		return container(DefaultConfig::class);
	}
}