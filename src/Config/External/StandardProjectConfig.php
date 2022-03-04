<?php declare(strict_types=1);

namespace DDT\Config\External;

use DDT\Config\BaseConfig;

class StandardProjectConfig extends BaseConfig
{
	const defaultFilename = 'ddt-project.json';
	const no_deps = 'DDT_NO_DEPS=true';

	/** @var string The path to the project the config represents */
	private $path;

	/** @var string The group this project belongs to */
	private $group;

	/** @var string The name of this project */
	private $project;

	public function __construct(string $filename, string $project, ?string $group=null)
	{
		parent::__construct($filename);

		$this->setPath($filename);
		$this->initDataStore();

		$this->group = $group;
		$this->project = $project;
	}

	public function getGroup(): ?string
	{
		return $this->group;
	}

	public function getProject(): string
	{
		return $this->project;
	}

	public function getDefaultFilename(): string
    {
        return self::defaultFilename;
    }

	protected function initDataStore(): void
	{
		// do nothing
	}

	public function write(?string $filename=null): bool
	{
		// do nothing, these files cannot be saved, but pretend everything is ok ;)
		return true;
	}

	private function setPath(string $filename): void
	{
		$this->path = is_dir($filename) ? $filename : dirname($filename);
	}

	public function getPath(): string
	{
		return $this->path;
	}

	public function listScripts(): array
	{
		return $this->getKey('.scripts') ?? [];
	}

	public function getScript(string $name)
	{
		$script = $this->getKey(".scripts.$name");

		if(!empty($script) && is_string($script)){
			$script = trim(str_replace(self::no_deps, '', $script));
		}

		return $script;
	}

	public function shouldRunDependencies(?string $script = null): bool
	{
		// Don't use $this->getScript here, because for external purposes, it's stripping out the no_deps string before passing it back
		// I don't want to change this behaviour, so we sidestep it by using the getKey function directly instead.
		$command = $this->getKey(".scripts.$script");

		if(!empty($command) && is_string($command) && strpos($command, self::no_deps) !== false) {
			return false;
		}

		return true;
	}

	public function getDependencies(?string $script = null): array
	{
		$dependencies = $this->getKey('.dependencies') ?? [];

		foreach($dependencies as $project => $config){
			if(!array_key_exists('scripts', $config)){
				// If a dependency has no predefined config
				// It means it'll accept any script request
				// If the project provides that script name
				// So we just setup a structure here with 
				// predefined data in it

				$dependencies[$project]['scripts'] = [
					$script => $script,
				];
			}else if(array_key_exists($script, $config['scripts'])){
				$value = $config['scripts'][$script];

				if($value === false){
					// script was found, but it's value was false, 
					// this means to block running scripts on this dependency
					// so we have to remove the entire dependency
					unset($dependencies[$project]);
				}else{
					// script exists in this service, filter the others out
					$dependencies[$project]['scripts'] = [
						$script => $value
					];
				}
			}else if($script){
				// script is not null, but not found, remove this entire dependency
				unset($dependencies[$project]);
			}
		}

		return $dependencies;
	}
}