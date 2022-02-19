<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI;
use DDT\Config\SystemConfig;
use DDT\Exceptions\Config\ConfigMissingException;
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

		$this->defaultConfig = container('config.file.default');
		$this->systemConfig = container('config.file.system');

		$this->text = $text;

		foreach(['filename', 'reset', 'get', 'delete', 'set', 'validate', 'version'] as $command){
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
			'examples' => trim(
				"Basic commands are simple to understand:\n".
				"\t- {$entrypoint} filename (will output where the system configuration file is located)\n".
				"\t- {$entrypoint} version (will output version information, etc)\n".
				"\n".
				"To query parts of the configuration:\n".
				"\t- {$entrypoint} get (with no specific key mentioned, will output entire configuration)\n".
				"\t- {$entrypoint} get --key=.type\n".
				"\t- {$entrypoint} get --key=.this.0.must.be.3.valid\n".
				"\n".	
				"The last one will do a recursive lookup drilling down each level that are split by the dots\n".
				"\t- key(this) -> index(0) -> key(must) -> key(be) -> index(3) -> key(valid)\n".
				"\n".
				"The json for the above example could be:\n".
				"{cyn}".str_replace("    ", "\t", json_encode([
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
				], JSON_PRETTY_PRINT))."{end}\n".
				"bash# {$entrypoint} get=.this.0.must.be.3.valid\n".
				"\"this one! this is index 3\"\n"
			),
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

	public function exists(SystemConfig $config): bool
	{
		try{
			return is_file($this->filename($config));
		}catch(ConfigMissingException $e){
			return false;
		}
	}

	public function reset(SystemConfig $config): ?SystemConfig
	{
		// Test if system configuration exists, if yes then you'll be asked to reset it
		if($this->exists($config)){
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
		$value = json_decode($value, true);

		if(!empty($value)){
			$config->setKey($key, $value);
			$config->write();
		}else{
			$this->cli->debug("Attempting to set an empty config key '$key'");
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
}
