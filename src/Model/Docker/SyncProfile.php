<?php declare(strict_types=1);

namespace DDT\Model\Docker;

use Exception;

class SyncProfile implements \JsonSerializable
{
    private $name;
    private $containerName;
    private $localDir;
    private $remoteDir;

    public function __construct(string $name, string $containerName, string $localDir, string $remoteDir)
    {        
        $namePattern = "^[a-z][a-z0-9\-\_]+$";
        $containerPattern = "^[a-z][a-z0-9\-\_]+$";
        $remoteDirPattern = "^\/[a-z0-9\-\_\/\.]+$";

		if(preg_match("/$namePattern/", $name)){
            $this->name = $name;
        }else{
		    throw new Exception("The profile name '$name' must follow the pattern '$namePattern'");
        }

        if(preg_match("/$containerPattern/", $containerName)){
            $this->containerName = $containerName;
        }else{
            throw new Exception("The profile named '$name' with the container name '$containerName' must follow the pattern '$containerPattern'");
        }

		if(is_dir($localDir)){
            $this->localDir = rtrim($localDir, '/');
        }else{
		    throw new Exception("The profile named '$name' with local directory '$localDir' did not exist");
        }

        if(preg_match("/$remoteDirPattern/", $remoteDir)) {
            $this->remoteDir = rtrim($remoteDir, '/');
        }else{
            throw new Exception("The profile named '$name' with remote directory '$remoteDir' must follow the pattern '$remoteDirPattern'");
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContainerName(): string
    {
        return $this->containerName;
    }

    public function getLocalDir(): string
    {
        return $this->localDir;
    }

    public function getRemoteDir(): string
    {
        return $this->remoteDir;
    }

    public function toRemoteFilename(string $localFilename): string
    {
        return $this->remoteDir . str_replace($this->localDir, '', $localFilename);
    }

    public function __toString(): string
	{
		return json_encode($this->get(), JSON_PRETTY_PRINT);
	}

	public function jsonSerialize(): array
	{
		return $this->get();
	}

	public function get(): array
	{
		return [
            'name' => $this->name,
            'container_name' => $this->containerName,
            'local_dir' => $this->localDir,
            'remote_dir' => $this->remoteDir,
        ];
	}
}