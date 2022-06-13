<?php declare(strict_types=1);

namespace DDT\Config;

use Exception;

class SystemConfig extends JsonConfig
{
	const defaultFilename = '.ddt-system.json';

	static public function instance(?string $filename=null, ?bool $readonly=false): SystemConfig
	{
		/** @var SystemConfig */
		$config = container(SystemConfig::class);
		$config->setReadonly($readonly);
		
		if(!empty($filename)){
			$config->read($filename);
		}

		return $config;
	}

	public function getDescription(): string
	{
		return $this->getKey('description');
	}

	static public function getDefaultFilename(): string
	{
		return self::defaultFilename;
	}

	public function setPath(string $name, string $path): void
	{
		$this->setKey("path.$name", $path);
	}

	public function getPath(string $name, ?string $subpath=''): string
	{
		$path = $this->getKey("path.$name");

		if(empty($path)){
			throw new Exception("The path named '$name' could not be found in the configuration");
		}

		return $path . $subpath;
	}

    public function readModel(string $key, string $className)
    {
        $data = $this->getKey($key) ?? [];

        if(is_callable("$className::fromArray")){
            return $className::fromArray($data);
        }

        throw new Exception("Cannot read data into model because no static fromArray method was defined on it to accept the data");
    }
}