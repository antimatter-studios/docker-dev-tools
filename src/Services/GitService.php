<?php
namespace DDT\Services;

use DDT\CLI\CLI;
use DDT\Exceptions\Filesystem\DirectoryExistsException;
use DDT\Exceptions\Git\GitRepositoryCloneException;
use DDT\Model\Git\GitRepositoryModel;
use DDT\Model\Vcs\VcsModel;
use InvalidArgumentException;

class GitService
{
	/** @var CLI */
	private $cli;

	public function __construct(CLI $cli)
	{
		$this->cli = $cli;
	}

	// Eventually remove this method completely
	static public function fromPath(string $path): VcsModel
    {
		$service = container(self::class);
		$repo = $service->getRepository($path);
		$url = $repo->remote();
		$branch = $repo->branch();

		return container(VcsModel::class, ['url' => $url, 'branch' => $branch]);
    }

	public function exists(string $url): bool
	{
		$this->cli->exec("git ls-remote -h $url");
		return $this->cli->getExitCode() === 0;	
	}

	public function getRepository(string $path): GitRepositoryModel
	{
		return container(GitRepositoryModel::class, ['path' => $path]);
	}

	/**
	 * @param string $url
	 * @param string $path
	 * @return GitRepositoryModel
	 * @throws DirectoryExistsException
	 */
	public function clone(string $url, string $path): GitRepositoryModel
	{
		if(is_dir($path)){
			throw new DirectoryExistsException($path);
		}

		if($this->exists($url) === false){
			throw new InvalidArgumentException("The url '$url' is not a valid git repository");
		}

		if($this->cli->passthru("git clone $url $path") !== 0){
			throw new GitRepositoryCloneException($url, $path);
		}

		return container(GitRepositoryModel::class, ['path' => $path]);
	}
}
