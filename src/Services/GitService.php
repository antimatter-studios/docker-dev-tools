<?php
namespace DDT\Services;

use DDT\CLI;
use DDT\Exceptions\Filesystem\DirectoryExistsException;
use DDT\Exceptions\Filesystem\DirectoryNotExistException;
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
		try{
			$this->cli->exec("git ls-remote -h $url");
			return true;
		}catch(\Exception $e) {
			return false;
		}
	}

	public function getRemote(string $dir, string $remote): string
	{
		return implode("\n", $this->cli->exec("git -C $dir remote get-url origin"));
	}

	/**
	 * @param string $url
	 * @param string $dir
	 * @return bool
	 * @throws DirectoryExistsException
	 */
	public function clone(string $url, string $dir): bool
	{
		if(is_dir($dir)){
			throw new DirectoryExistsException($dir);
		}

		if($this->exists($url) === false){
			throw new InvalidArgumentException("The url '$url' is not a valid git repository");
		}

		return $this->cli->passthru("git clone $url $dir") === 0;
	}

	/**
	 * @param string $dir
	 * @return bool
	 * @throws DirectoryNotExistException
	 */
	public function pull(string $dir, bool $quiet=false): bool
	{
		if(!is_dir($dir)){
			throw new DirectoryNotExistException($dir);
		}

		$quiet = $quiet ? "&>/dev/null": "";

		return $this->cli->passthru("git -C $dir pull $quiet") === 0;
	}

	public function push(string $dir, bool $quiet=false): bool
	{
		if(!is_dir($dir)){
			throw new DirectoryNotExistException($dir);
		}

		$quiet = $quiet ? "&>/dev/null": "";

		return $this->cli->passthru("git -C $dir push $quiet") === 0;
	}

	public function status(string $dir): string
	{
		$output = implode("\n", $this->cli->exec("git -C $dir status -s"));
		$output = trim($output);

		return $output;
	}

	public function branch(string $dir): string
	{
		return $this->cli->exec("git -C $dir rev-parse --abbrev-ref HEAD", true);
	}

	public function remote(string $dir, string $name='origin'): string
	{
		return $this->cli->exec("git -C $dir remote get-url $name", true);
	}

	public function fetch(string $dir, bool $prune=false): bool
	{
		$prune = $prune ? "-p" : "";

		$this->cli->exec("git -C $dir fetch $prune");

		return $this->cli->getExitCode() === 0;
	}
}
