<?php declare(strict_types=1);

namespace DDT\Model\Config\SystemConfig;

use DDT\Config\SystemConfig;
use DDT\Exceptions\Project\ProjectConfigUpgradeFailureException;

class UpgradeToVersion3 {
    public function upgrade(SystemConfig $config, callable $print): bool
	{
		$version = $config->getVersion();

		// This upgrade only applies to versions less than 3
		if($version >= 3){
			$print("This upgrade only applies to less than version 3\n");
			return false;
		}

		$before = '.projects-v2';
		$after = '.projects.list';
		
		$beforeConfig = $config->getKey($before);

		// we put the entire contents of .projects-v2 into .projects.list
		$config->setKey($after, $beforeConfig);
		$config->deleteKey($before);
		$config->setKey('.version', 3);

		if(!$config->write()){
			throw new ProjectConfigUpgradeFailureException($before, $after);
		}

		return true;
	}
}