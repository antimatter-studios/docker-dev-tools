<?php declare(strict_types=1);

namespace DDT\Config;

class DefaultConfig extends BaseConfig
{
	const defaultFilename = 'default.ddt-system.json';

    public function __construct ()
    {
        parent::__construct(container('config.tools.path') . '/' . $this->getDefaultFilename(), true);
    }

	public function getDefaultFilename(): string
	{
		return self::defaultFilename;
	}
}