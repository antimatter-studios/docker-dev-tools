<?php declare(strict_types=1);

namespace DDT\Model\Docker;

use DDT\Model\Model;
use Exception;

class SyncProfileModel extends Model
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

    static public function fromArray(array $data): self 
    {
        return new self($data['name'], $data['container_name'], $data['local_dir'], $data['remote_dir']);
    }

	public function getData()
	{
		return [
            'name' => $this->name,
            'container_name' => $this->containerName,
            'local_dir' => $this->localDir,
            'remote_dir' => $this->remoteDir,
        ];
	}
}