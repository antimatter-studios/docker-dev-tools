<?php
namespace DDT\Services;

use DDT\CLI;
use DDT\Exceptions\Filesystem\DirectoryExistsException;
use DDT\Exceptions\Filesystem\DirectoryNotExistException;
use DDT\Exceptions\Git\GitNotARepositoryException;
use InvalidArgumentException;

class GitService
{
	/** @var CLI */
	private $cli;

	public function __construct(CLI $cli)
	{
		$this->cli = $cli;
	}

	public function exists(string $url): bool
	{
		$this->cli->exec("git ls-remote -h $url");
		return $this->cli->getExitCode() === 0;	
	}

	public function isRepository(string $path): bool
	{
		$this->cli->exec("git -C $path remote -v");

		return $this->cli->getExitCode() === 0;
	}

	/**
	 * @param string $url
	 * @param string $path
	 * @return bool
	 * @throws DirectoryExistsException
	 */
	public function clone(string $url, string $path): bool
	{
		if(is_dir($path)){
			throw new DirectoryExistsException($path);
		}

		if($this->exists($url) === false){
			throw new InvalidArgumentException("The url '$url' is not a valid git repository");
		}

		return $this->cli->passthru("git clone $url $path") === 0;
	}

	/**
	 * @param string $path
	 * @return bool
	 * @throws DirectoryNotExistException
	 */
	public function pull(string $path, bool $quiet=false): bool
	{
		if(!is_dir($path)){
			throw new DirectoryNotExistException($path);
		}

		$quiet = $quiet ? "&>/dev/null": "";

		return $this->cli->passthru("git -C $path pull $quiet") === 0;
	}

	public function push(string $path, bool $quiet=false): bool
	{
		if(!is_dir($path)){
			throw new DirectoryNotExistException($path);
		}

		$quiet = $quiet ? "&>/dev/null": "";

		return $this->cli->passthru("git -C $path push $quiet") === 0;
	}

	public function status(string $path): string
	{
		$output = $this->cli->exec("git -C $path status -s");
		$output = trim($output);

		return $output;
	}

	public function branch(string $path): string
	{
		return $this->cli->exec("git -C $path rev-parse --abbrev-ref HEAD");
	}

	public function remote(string $path, string $name='origin'): string
	{
		return $this->cli->exec("git -C $path remote get-url $name");
	}

	// FIXME: exec no longer throws exceptions, so how can we manage errors now?
	public function getRemote(string $path, string $remote): string
	{
		try{
			return $this->cli->exec("git -C $path remote get-url $remote");
		}catch(\Exception $e){
			if(strpos($e->getMessage(), "not a git repository") !== false){
				throw new GitNotARepositoryException($path);
			}
		}
	}

	public function fetch(string $path, bool $prune=false): bool
	{
		$prune = $prune ? "-p" : "";

		$this->cli->exec("git -C $path fetch $prune");

		return $this->cli->getExitCode() === 0;
	}
}
