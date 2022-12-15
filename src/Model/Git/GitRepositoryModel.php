<?php declare(strict_types=1);

namespace DDT\Model\Git;

use DDT\CLI\CLI;
use DDT\CLI\Output\CustomChannel;
use DDT\Exceptions\Git\GitRepositoryCommandException;
use DDT\Exceptions\Git\GitRepositoryNotFoundException;
use DDT\Exceptions\Git\GitRepositoryPermissionDeniedException;

class GitRepositoryModel
{
    /** @var CLI The interface to the command line */
    private $cli;

    /** @var string The location on disk for this repository */
    private $path;

    public function __construct(CLI $cli, string $path)
    {
        $this->path = $path;
        $this->cli = $cli;

        if(!$this->isRepository()){
			throw new GitRepositoryNotFoundException($path);
		}
    }

    static public function fromPath(string $path): self
    {
		return container(GitRepositoryModel::class, ['path' => $path]);
    }

    public function exec($command): string
    {
        $command = "git -C $this->path $command";

        $output = $this->cli->exec($command);

		if($this->cli->getExitCode() === 0){
            return $output;
        }

        throw new GitRepositoryCommandException($command);
    }

    public function passthru($command): bool
    {
        $command = "git -C $this->path $command";

        $stderr = new CustomChannel('git', true);
        $stderr->attach($this->cli->getChannel('stderr'));

        $this->cli->passthru($command, null, $stderr);

		if($this->cli->getExitCode() === 0){
            return true;
        }

        $output = implode("\n", $stderr->getRendered());

        GitRepositoryPermissionDeniedException::throwIfMatches($output);

        throw new GitRepositoryCommandException($command);
    }

    public function isRepository(): bool
	{
        try{
            $this->exec("status");
            return true;
        }catch(\Exception $e){
            return false;
        }
	}

    public function status(): string
	{
		$output = $this->exec("status -s");
		$output = trim($output);

		return $output;
	}

    public function checkout(string $branch): self
    {
        $this->exec("checkout $branch");

        return $this;
    }

    public function pull(): self
    {
        $this->passthru("pull");

        return $this;
    }

    public function push(): self
    {
        $this->passthru("push");

        return $this;
    }

    public function branch(): string
	{
		return $this->exec("rev-parse --abbrev-ref HEAD");
	}

	public function remote(string $name='origin'): string
	{
        return $this->exec("remote get-url $name");
    }

    public function fetch(bool $prune=false): self
	{
		$this->exec("fetch -a " . ($prune ? "-p" : ""));

		return $this;
	}
}