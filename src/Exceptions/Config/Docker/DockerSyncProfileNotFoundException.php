<?php declare(strict_types=1);

namespace DDT\Exceptions\Config\Docker;

use DDT\Exceptions\Config\ConfigException;

class DockerSyncProfileNotFoundException extends ConfigException 
{
    public function __construct(string $name, int $code = 0, \Throwable $previous = null)
    {
        parent::__construct("Docker Sync Profile named '$name' does not exist", $code, $previous);
    }
}