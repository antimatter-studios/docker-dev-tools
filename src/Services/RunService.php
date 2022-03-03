<?php
namespace DDT\Services;

use DDT\CLI;
use DDT\CLI\ArgumentList;
use DDT\Config\External\StandardProjectConfig;
use DDT\Config\ProjectGroupConfig;
use DDT\Exceptions\Project\ProjectScriptInvalidException;

class RunService
{
	/** @var CLI */
	private $cli;

	/** @var ProjectGroupConfig */
	private $projectGroupConfig;

	/** @var array The stack of scripts running and used to detect circular dependencies */
	private $stack;

	public function __construct(CLI $cli, ProjectGroupConfig $config)
	{
		$this->cli = $cli;
		$this->projectGroupConfig = $config;
	}

	public function reset(): void
	{
		$this->stack = [];
	}

	private function makeKey(StandardProjectConfig $projectConfig, string $script): string
	{
		return $projectConfig->getPath() . "@" . $script;
	}

	private function isRunning(StandardProjectConfig $projectConfig, string $script): bool
	{
		// does this project have a script named this?
		// is this project already running this script? (prevent infinite loops)
		$key = $this->makeKey($projectConfig, $script);

		return in_array($key, $this->stack);
	}

	private function pushJob(StandardProjectConfig $projectConfig, string $script): bool
	{
		// if not, add it to the stack and return true;
		$key = $this->makeKey($projectConfig, $script);

		// TODO: do I need to keep track of any runtime data here?
		$this->stack[] = $key;
		$this->cli->debug("{red}[RUNSERVICE]:{end}\n{cyn}Stack(push = $key):\n".implode("\n", $this->stack)."{end}\n");
		// I don't know how to handle failure yet
		return true;
	}

	public function getProject(string $group, string $project): StandardProjectConfig
	{
		//	TODO: how to handle when a project is not found, it'll throw exceptions?
		return $this->projectGroupConfig->getProjectConfig($group, $project);
	}

	public function run(string $script, string $group, ?string $project=null, ?ArgumentList $extraArgs=null)
	{
		try{
			$this->cli->debug("{red}[RUNSERVICE]:{end} Running: $group, $project, $script\n");
			// Obtain the project configuration
			$projectConfig = $this->getProject($group, $project);
		
			// Check if script is already running, we refuse to run scripts if 
			// it's already run since it might lead to infinite loops
			if($this->isRunning($projectConfig, $script) === false){
				// Push job onto stack, blocking it from future duplicate execution
				$this->pushJob($projectConfig, $script);

				// Before attempting to run the script required, process it's dependencies
				if($this->runDependencies($projectConfig, $script, $extraArgs) === true){
					// Now all dependencies are run, obtain the actual commandline to run
					$this->runCommand($projectConfig, $script, $extraArgs);
				}
			}else{
				// show an error about non-entrant scripts, so we don't do any infinite loops
				$key = $this->makeKey($projectConfig, $script);
				$this->cli->debug("{red}[RUNSERVICE]:{end} Script already running: $key\n");
			}
		}catch(ProjectScriptInvalidException $e){
			$this->cli->debug("{red}".get_class($e)."{end} => {$e->getMessage()}\n");
			$this->cli->debug("{yel}No Script Found:{end} group: {yel}{$e->getGroup()}{end}, project: {yel}{$e->getProject()}{end}, script: {yel}{$e->getScript()}{end}, extra args: {yel}'$extraArgs'{end}");
		}catch(\Exception $e){
			// Oh, exception happened :( oopsie
			$this->cli->print("{red}".get_class($e)."{end} => {$e->getMessage()}\n");
			return false;
		}
	}

	public function runCommand(StandardProjectConfig $projectConfig, string $script, ?ArgumentList $extraArgs=null)
	{
		$group		= $projectConfig->getGroup();
		$project	= $projectConfig->getProject();
		$path		= $projectConfig->getPath();
		$command	= $projectConfig->getScript($script);

		// If the command line is empty, this is a probable bug in the configuration, the value is set incorrectly
		if(empty($command)){
			throw new ProjectScriptInvalidException($group, $project, $script);
		}

		// Otherwise, cd into the project path and run the script as specified
		$this->cli->print("\n{blu}Run Script:{end} group: {yel}$group{end}, project: {yel}$project{end}, script: {yel}$script{end}, extra args: {yel}'$extraArgs'{end}\n");

		if(is_string($command)){
			$command = [$command];
		}else{
			$command = array_map(function($c) use ($projectConfig) {
				return $projectConfig->getScript($c);
			}, $command);
		}

		foreach($command as $commandLine){
			// If we find a parameterised command line, try to follow it
			[$commandLine, $extraArgs] = $this->buildCommandLine($commandLine, $extraArgs);
			
			// TODO: how to handle when a script fails?
			// TODO: how to handle when a script returns important information?
			$this->cli->passthru("cd $path; $commandLine $extraArgs");
		}
	}

	private function buildCommandLine(string $commandLine, string $extraArgs): array
	{
		$index = 1;
		$extraArgs = explode(' ', $extraArgs);

		while(strpos($commandLine, '$'.$index) !== false) {
			$commandLine = str_replace('$'.$index, array_shift($extraArgs) ?? '', $commandLine);
			$index++;
		}

		// reimplode the rest of the args into a string to append afterwards
		$extraArgs = implode(' ', $extraArgs);

		return [$commandLine, $extraArgs];
	}

	public function runDependencies(StandardProjectConfig $projectConfig, string $script, ?ArgumentList $extraArgs=null): bool
	{
		if($projectConfig->shouldRunDependencies($script) === false){
			$this->cli->debug("{red}[RUNSERVICE]{end}: Found token to skip dependencies\n");
			return true;
		}

		// First, get all this projects dependencies, so you can loop through them
		$dependencies = $projectConfig->getDependencies($script);

		foreach($dependencies as $project => $d){
			// We make copies of these variables because they can be overridden per dependency
			// We don't want to alter the original variables, 
			// because each dependency can have a different group and script to the parent
			$depGroup = array_key_exists('group', $d) ? $d['group'] : $projectConfig->getGroup();
			$depScript = $script;

			// Make some debugging text easier to read like this
			$t = array_map(function($k, $v) { return $k===$v ? $k : "$k=$v"; }, array_keys($d['scripts']), array_values($d['scripts']));
			$this->cli->debug("{red}[RUNSERVICE]{end}: Dependencies($project@$depGroup): [".implode(",", $t)."]\n");

			// Does this dependency overload the script with an alternative script name?
			if(array_key_exists('scripts', $d)){
				if(array_key_exists($script, $d['scripts'])){
					$depScript = $d['scripts'][$script];
				}
			}

			if(is_string($depScript)){
				$depScript = [$depScript];
			}

			if(is_array($depScript)){
				foreach($depScript as $s){
					// Run the script for this dependency
					// TODO: how to handle a the return value from this?
					$this->run($s, $depGroup, $project, $extraArgs);
				}
			}else{
				$this->cli->debug("{red}[RUNSERVICE]{end}: Unexpected script configuration, must be a string|string[] value\n");
			}
		}
		
		// Lol, I don't know how to deal with all the return values yet
		return true;
	}
}
