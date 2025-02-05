<?php
namespace DDT\Services;

use DDT\CLI;
use DDT\CLI\ArgumentList;
use DDT\Config\Sections\ProjectConfig;
use DDT\Contract\Project\ProjectConfigInterface;
use DDT\Exceptions\Project\ProjectScriptInvalidException;
use DDT\Model\Project\ProjectModel;
use DDT\Model\Script\RunConfigurationModel;

class RunService
{
	/** @var CLI */
	private $cli;

	/** @var ProjectConfig */
	private $projectConfig;

    /** @var ProjectService */
    private $projectService;

	/** @var array The stack of scripts running and used to detect circular dependencies */
	private $stack = [];

	public function __construct(CLI $cli, ProjectService $projectService, ProjectConfig $config)
	{
		$this->cli = $cli;
        $this->projectService = $projectService;
		$this->projectConfig = $config;
	}

	public function reset(): void
	{
		$this->stack = [];
	}

	private function makeKey(ProjectConfigInterface $projectConfig, string $script): string
	{
		return $projectConfig->getPath() . "@" . $script;
	}

	private function isRunning(ProjectConfigInterface $projectConfig, string $script): bool
	{
		// does this project have a script named this?
		// is this project already running this script? (prevent infinite loops)
		$key = $this->makeKey($projectConfig, $script);

		return in_array($key, $this->stack);
	}

	private function buildCommandLine(string $commandLine, ?string $extraArgs=null): string
	{
		$index = 1;
		$extraArgs = explode(' ', $extraArgs ?? '');

		while(strpos($commandLine, '$'.$index) !== false) {
			$commandLine = str_replace('$'.$index, array_shift($extraArgs) ?? '', $commandLine);
			$index++;
		}

		// reimplode the rest of the args into a string to append afterwards
		$extraArgs = implode(' ', $extraArgs);

		if(strpos($commandLine, '$@') !== false) {
			$commandLine = str_replace('$@', $extraArgs, $commandLine);
			$extraArgs = '';
		}

		return "$commandLine $extraArgs";
	}

	private function pushJob(ProjectConfigInterface $projectConfig, string $script): bool
	{
		// if not, add it to the stack and return true;
		$key = $this->makeKey($projectConfig, $script);

		// TODO: do I need to keep track of any runtime data here?
		$this->stack[] = $key;
		$this->cli->debug('runservice', "\n{cyn}Stack(push = $key):\n".implode("\n", $this->stack)."{end}\n");
		// I don't know how to handle failure yet
		return true;
	}

	public function resolve(string $script, iterable $projectList, ?array $stack=[]): array
	{
		$list = [];

        /** @var ProjectModel $p */
        foreach($projectList as $p){
			$name = $p->getName();
			$group = current($p->getGroups()->getData());

			$this->cli->debug('runservice', "resolve: '$script', project: '$name', group: '$group'\n");

			$key = "{$p->getPath()}@{$script}";
			if(in_array($key, $stack)){
				continue;
			}
			$stack[] = $key;

			/** @var ProjectConfigInterface $projectConfig */
            $projectConfig = $p->getConfig();
			
			$command = $this->resolveCommandList($script, $projectConfig);

			$subtree = [];
			$dependencies = $projectConfig->getDependencies($script);
			foreach($dependencies as $depName => $depData){
				$this->cli->debug('runservice', "resolve dependency: '$depName'\n");
				$depProjectList = $this->projectConfig->listProjectsByFilter(['name' => $depName, 'group' => $group]);

				foreach(array_keys($command) as $cmdName){
					// If the dependency script is not found, don't try to process it
					if(!array_key_exists($cmdName, $depData['scripts'])){
						continue;
					}

					$depScript = $depData['scripts'][$cmdName];
					$depScript = is_string($depScript) ? [$depScript] : $depScript;

					if(!is_array($depScript)){
						continue;
					}
	
					foreach($depScript as $ds){
						[$st, $stack] = $this->resolve($ds, $depProjectList, $stack);
						$subtree = array_merge($subtree, $st);
					}
				}
			}

			$list[] = new RunConfigurationModel($name, $group, array_filter($command), $subtree);
		}

		return [$list, $stack];
	}

	private function resolveCommandList(string $command, ProjectConfigInterface $projectConfig, array $output=[]): array
	{
		if(array_key_exists($command, $output)) {
			return $output;
		}

		$script = $projectConfig->getScript($command);

		if(is_string($script)){
			$output[$command] = $script;
		}
		if(is_array($script)){
			// We need to set a null value here as a method to stop infinite loops
			// Say we found key "one" and it was an array of sub-commands, but in that tree one subcommands
			// one of them loops back to "one". Then you'd be in an infinite loop. So this null key effectively
			// tells the code "we are already processing this key, don't try to process it again"
			$output[$command] = null;

			foreach($script as $s){
				$output = $this->resolveCommandList($s, $projectConfig, $output);
			}
		}

		return $output;
	}

	public function run(RunConfigurationModel $runConfig, ?ArgumentList $extraArgs=null)
	{
		try{
			$project = $runConfig->getName();
			$group = $runConfig->getGroup();

			foreach($runConfig->getDependencies() as $dependency){
				$this->run($dependency, $extraArgs);
			}

			// Obtain the project configuration
            /** @var ProjectModel $project */
            $project = $this->projectService->findProject($project, null, $group);
            /** @var ProjectConfigInterface $projectConfig */
            $projectConfig = $project->getConfig();

			foreach($runConfig->getCommandList() as $script => $commandLine){
				$this->cli->debug("runservice", "Running: '{$script}', '{$project}', '".($group ?? 'none')."'\n");

				// Check if script is already running, we refuse to run scripts if
				// it's already run since it might lead to infinite loops
				if($this->isRunning($projectConfig, $script) === false){
					// Push job onto stack, blocking it from future duplicate execution
					$this->pushJob($projectConfig, $script);

					// Now all dependencies are run, obtain the actual commandline to run
					$this->runCommand($projectConfig, $script, $commandLine, $extraArgs);
				}else{
					// show an error about non-entrant scripts, so we don't do any infinite loops
					$key = $this->makeKey($projectConfig, $script);
					$this->cli->debug("runservice", "Script already running: $key\n");
				}
			}
		}catch(ProjectScriptInvalidException $e){
			$this->cli->debug("runservice", "{red}".get_class($e)."{end} => {$e->getMessage()}\n");
			$this->cli->debug("runservice", "{yel}No Script Found:{end} group: {yel}{$e->getGroup()}{end}, project: {yel}{$e->getProject()}{end}, script: {yel}{$e->getScript()}{end}, extra args: {yel}'$extraArgs'{end}");
		}catch(\Exception $e){
            $this->cli->debug('runservice', $e->getTraceAsString());
			// Oh, exception happened :( oopsie
			$this->cli->print("{red}".get_class($e)."{end} => {$e->getMessage()}\n");
			return false;
		}
	}

	public function runCommand(ProjectConfigInterface $projectConfig, string $script, string $commandLine, ?ArgumentList $extraArgs=null)
	{
		$group		= $projectConfig->getGroup();
		$project	= $projectConfig->getProject();
		$path		= $projectConfig->getPath();

		$groupText = !empty($group) ? ", group: {yel}{$group->toCsv()}{end}" : "";
		$extraArgsText = !empty((string)$extraArgs) ? ", extra args: {yel}'$extraArgs'{end}" : "";

		// Otherwise, cd into the project path and run the script as specified
		$this->cli->print("\n{blu}Run Script:{end} script: {yel}$script{end}, project: {yel}$project{end}{$groupText}{$extraArgsText}\n");

		// If we find a parameterised command line, try to follow it
		$commandLine = $this->buildCommandLine($commandLine, $extraArgs);
		$commandLine = "cd $path; $commandLine";
		
		// TODO: how to handle when a script fails?
		// TODO: how to handle when a script returns important information?
		$this->cli->passthru($commandLine);
		$this->cli->debug('runservice' ,"$commandLine\n");
	}
}
