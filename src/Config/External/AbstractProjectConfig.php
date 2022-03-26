<?php declare(strict_types=1);

namespace DDT\Config\External;

use DDT\Config\BaseConfig;
use DDT\Contract\External\ProjectConfigInterface;

abstract class AbstractProjectConfig extends BaseConfig implements ProjectConfigInterface
{
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
		return $this->getKey(".scripts.$name");
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

	public function getDependencies2(string $script): array
	{
		$list = $this->getKey('.dependencies') ?? [];

		// I know I could clean this up to be more compact
		// But sometimes clear and simple statments are better than complex compound ones :)
		$list = array_filter($list, function($config) use ($script) {
			// This would also be an invalid configuration
			if(!is_array($config)) return false;

			// No scripts defined at all, then consider this the same as no dependencies to process
			if(!array_key_exists('scripts', $config)) return false;

			if($config['scripts'] === true) return true;
			if($config['scripts'] === false) return false;

			// This would also be an invalid configuration
			if(!is_array($config['scripts'])) return false;
			
			if(array_key_exists($script, $config['scripts'])){
				$value = $config['scripts'][$script];
				if($value === true) return true;
				if($value === false) return false;
				if(is_string($value) && !empty($value)) return true;
				if(is_array($value) && !empty($value)) return true;
			}

			// We arrived here then none of the filtering above matches, so lets consider it the same as no-dependencies
			return false;
		});

		// Resolve all true values into actual objects with proper values
		$list = array_map(function($config) use ($script) {
			if($config['scripts'] === true){
				$config['scripts'] = [$script => $script];
			}
			if($config['scripts'][$script] === true){
				$config['scripts'][$script] = $script;
			}
			return $config;
		}, $list);

		return $list;
	}
}