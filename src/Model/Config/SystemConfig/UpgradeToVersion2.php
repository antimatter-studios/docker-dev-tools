<?php declare(strict_types=1);

namespace DDT\Model\Config\SystemConfig;

use DDT\CLI\CLI;
use DDT\Config\SystemConfig;
use DDT\Exceptions\Project\ProjectConfigUpgradeFailureException;

class UpgradeToVersion2 {
	private $cli;

	public function __construct(CLI $cli)
	{
		$this->cli = $cli;
	}

    public function upgrade(SystemConfig $config, callable $print): bool
	{
		$version = $config->getVersion();
		$before = 'projects';
		$after = 'projects-v2';
		
		$beforeConfig = $config->getKey($before);
		$afterConfig = $config->getKey($after);

		if($version > 1){
			$print("This upgrade only applies to version 1\n");
			return false;
		}

		if($beforeConfig && $afterConfig){
			if($this->cli->ask("This upgrade between '$before' and '$after' has run before, but the old key was left in the configuration file, do you want to delete it?", ['yes', 'no']) === 'yes'){
				$config->deleteKey($before);
				$config->write();
			}else{
				$print("{red}You have chosen to not delete it, but we cannot continue, either remove it yourself, or agree to delete it and try again{end}\n");
			}
			return false;
		}

		if(!$beforeConfig){
			$print("Upgrade between '$before' and '$after' was run previously\n");
			return false;
		}

		if($afterConfig){
			throw new \Exception("Cannot upgrade between '$before' to '$after' because the '$after' key already exists, have you manually edited the file? Please (re)move this key and try again");
		}

		$print("{blu}Upgrading{end}: from '$before' to '$after'\n");

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
		}

		return true;
	}
}