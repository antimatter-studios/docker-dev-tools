<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI;
use DDT\Config\SystemConfig;
use DDT\Exceptions\Project\ProjectConfigUpgradeFailureException;
use DDT\Text\Text;

class ConfigTool extends Tool
{
	/** @var Text */
	private $text;

	private $defaultConfig;
	private $systemConfig;

    public function __construct(CLI $cli, Text $text)
    {
    	parent::__construct('config', $cli);

		$this->defaultConfig = config('file.default');
		$this->systemConfig = config('file.system');

		$this->text = $text;

		foreach(['filename', 'reset', 'get', 'delete', 'set', 'validate', 'version'] as $command){
			$this->setToolCommand($command);
		}

		foreach(['upgrade-projects'] as $command){
			$this->setToolCommand($command);
		}
    }

	public function getToolMetadata(): array
	{
		$entrypoint = $this->getEntrypoint() . " " . $this->getToolName();

		return [
			'title' => 'Configuration',
			'description' => 'This tool will manipulate the configuration or query part of it for use in other tools',
			'options' => [
				"filename: Returns a single string containing the filename",
				"reset: Will reset your configuration file to the default 'empty' configuration, {red}it will destroy any setup you already have{end}",
				"get: Will retrieve a specific key, if no key is specified, the entire config is shown",
				"validate: Only validate the file can be read without errors",
				"version: Output some information about the configuration that is deemed useful",
				"help: This information, also if no sub command is given help is automatically shown",
			],
			'examples' => array_merge([
				"Basic commands are simple to understand:",
				"\t- {$entrypoint} filename (will output where the system configuration file is located)",
				"\t- {$entrypoint} version (will output version information, etc)\n",

				"To query parts of the configuration:",
				"\t- {$entrypoint} get (with no specific key mentioned, will output entire configuration)",
				"\t- {$entrypoint} get .type",
				"\t- {$entrypoint} get .this.0.must.be.3.valid\n",

				"The last one will do a recursive lookup drilling down each level that are split by the dots",
				"\t- key(this) -> index(0) -> key(must) -> key(be) -> index(3) -> key(valid)\n",

				"The json for the above example could be:",
				"{cyn}"
			], explode("\n", str_replace("    ", "\t", json_encode([
					"this" => [
						[
							"must" => [
								"be" => [
									"not this",
									"or this",
									"neither this",
									[
										"valid" => "this one! this is index 3",
										"json" => "doesn't care if you mix strings with objects or sub-arrays"
									],
									"ignore this",
								]
							]
						]
					]
			], JSON_PRETTY_PRINT))), [
				"{end}",
				"bash# {$entrypoint} get this.0.must.be.3.valid",
				"\"this one! this is index 3\"",
			]),
			'notes' => [
				"All keys begin with '.' (dot), e.g: '.description'",
				"Keys are a dotted syntax that allows you to pluck out a segment of the configuration",
				"If you ask for an invalid heirarchy. This function will return null",
			],
		];
	}

	public function filename(SystemConfig $config): string
	{
		return $config->getFilename();
	}

	public function reset(SystemConfig $config): ?SystemConfig
	{
		// Test if system configuration exists, if yes then you'll be asked to reset it
		if(file_exists($this->systemConfig)){
			$reply = $this->cli->ask('Are you sure you want to reset your configuration?', ['yes', 'no']);

			if($reply !== 'yes'){
				$this->cli->box("The request to reset was refused", "wht", "red");
				return null;
			}
		}
		
		$config->read($this->defaultConfig);
		$config->setReadonly(false);
		
		if($config->write($this->systemConfig)){
			$this->cli->box("The file '{$this->systemConfig}' file was overwritten", "blk", "grn");
		}else{
			$this->cli->box("The file '{$this->systemConfig}' could not be written, the state of the file is unknown, please manually check it", "wht", "red");
		}

		return $config;
	}

	public function get(SystemConfig $config, ?string $key='.', ?bool $raw=null): string
	{
		$value = $config->getKeyAsJson($key);

		return $value . "\n";
	}

	public function delete(SystemConfig $config, string $key): void
	{
		$config->deleteKey($key);
		$config->write();
	}

	public function set(SystemConfig $config, string $key, string $value): void
	{
		$json = json_decode($value, true);
		if($json !== null) $value = $json;

		if(!empty($value)){
			$config->setKey($key, $value);
			$config->write();
		}else{
			$this->cli->debug("config", "Attempting to set an empty config key '$key' value '$value'");
		}
	}

	public function validate(SystemConfig $config): string
	{
		// TODO: the reason this is imploding an array with a string string
		// TODO: is because it should be validating other things too
		return implode("\n", [
			// FIXME: add extensions to this output
			// FIXME: add projects to this output
			$this->text->box("The system configuration in file '{$config->getFilename()}' was valid", 'blk', 'grn'),
		]);
	}

	public function version(SystemConfig $config): string
	{
		return $config->getVersion() . "\n";
	}

	public function upgradeProjects(SystemConfig $config)
	{
		$upgradeSequence = [
			'projects' => 'projects-v2',
		];
		
		foreach($upgradeSequence as $before => $after){
			$beforeConfig = $config->getKey($before);
			$afterConfig = $config->getKey($after);

			if($beforeConfig && $afterConfig){
				if($this->cli->ask("This upgrade between '$before' and '$after' has run before, but the old key was left in the configuration file, do you want to delete it?", ['yes', 'no']) === 'yes'){
					$config->deleteKey($before);
					$config->write();
					continue;
				}else{
					$this->cli->failure("You have chosen to not delete it, but we cannot continue, either remove it yourself, or agree to delete it and try again");
				}
			}

			if(!$beforeConfig){
				$this->cli->print("Upgrade between '$before' and '$after' was run previously, skipping...\n");
				continue;
			}

			if($afterConfig){
				throw new \Exception("Cannot upgrade between '$before' to '$after' because the '$after' key already exists, have you manually edited the file? Please (re)move this key and try again");
			}

			$this->cli->print("{blu}Upgrading{end}: from '$before' to '$after'\n");

			switch($before) {
				case "projects":
					$afterConfig = [];

					foreach($beforeConfig as $group => $projectList){
						foreach($projectList as $name => $project){
							$project['path'] = rtrim($project['path'], '/');

							if(array_key_exists($project['path'], $afterConfig)){
								$afterConfig[$project['path']]['group'][] = $group;
							}else{
								$newProject = [
									'name' => $name,
									'type' => $project['type'],
									'path' => $project['path'],
									'group' => [$group],
								];
	
								if(array_key_exists('repo', $project)){
									if(is_string($project['repo'])){
										$repo = ['vcs' => $project['repo'], 'remote' => 'origin'];
									}else if(is_array($project['repo'])){
										$repo = ['vcs' => $project['repo']['url'], 'remote' => $project['repo']['remote']];
									}else{
										$repo = [];
									}
	
									$newProject = array_merge($newProject, $repo);
								}
	
								$afterConfig[$project['path']] = $newProject;
							}
						}
					}

					$config->setKey($after, $afterConfig);
					if(!$config->write()){
						throw new ProjectConfigUpgradeFailureException($before, $after);
					}else{
						$this->cli->print("{grn}Upgrading '$before' to '$after' was apparently succesful{end}\n");
					}
					break;
			}
		}

		$this->cli->success("All Upgrades were apparently completed\n");
	}
}
