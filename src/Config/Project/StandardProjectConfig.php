<?php declare(strict_types=1);

namespace DDT\Config\Project;

use DDT\Contract\Project\ProjectConfigInterface;

class StandardProjectConfig extends AbstractProjectConfig implements ProjectConfigInterface
{
	const defaultFilename = 'ddt-project.json';

	static public function getDefaultFilename(): string
    {
        return self::defaultFilename;
    }
}