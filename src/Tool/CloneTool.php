<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI;
use DDT\CLI\ArgumentList;
use DDT\Services\GitService;

class CloneTool extends Tool
{
	/** @var GitService */
	private $gitService;

    public function __construct(CLI $cli, GitService $gitService)
    {
    	parent::__construct('clone', $cli);

        $this->cli = $cli;
		$this->gitService = $gitService;

		$this->setToolCommand('clone', null, true);
    }

	public function getToolMetadata(): array
	{
		return [
			'title' => 'Orchestrated project clone tool',
			'short_description' => 'A tool to orchestrate cloning git repositories in more advanced ways targetted at supporting complex multi component softwares',
			'description' => [
				"Git is a fantastic software and it's purpose is to provide a simple way to do distributed version control.",
				"It does an amazing job. One area that this tool enhances is more awareness of what should happen when",
				"whole distributed systems of services are supposed to rely on each other and need to be working together.",
				"Obviously Git does not get involved with these issues because they are specialised. But this tool tries to",
				"help by providing some common functionalities required, which go beyond what git is supposed to do"				
			],
			'options' => [
				"This command has the exact same arguments as 'git clone', see 'git clone --help' for more details",
			],
			'notes' => [
				"This tool only supports Git repositories just because there is no demand yet to provide",
				"support for other VCS systems",
			],
		];
	}

	public function clone(string $repo, string $path): void
	{
		$arguments = new ArgumentList($this->cli->getArgList());
		
		$this->cli->passthru("git clone $arguments\n");
	}
}
