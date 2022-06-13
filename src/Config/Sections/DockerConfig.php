<?php declare(strict_types=1);

namespace DDT\Config\Sections;

use DDT\Config\SystemConfig;
use DDT\Model\Docker\RunProfileModel;
use DDT\Model\Docker\SyncProfileModel;

class DockerConfig
{
    /** @var SystemConfig $config */
    private $config;

    private $key = [
        'run' => 'docker.run-profile', 
        'sync' => 'docker.sync-profile'
    ];

    public function __construct(SystemConfig $config)
    {
        $this->config = $config;
    }

    public function listRunProfile(): array
    {
        $list = $this->config->getKey($this->key['run']) ?? [];

        foreach($list as $index => $profile){
            $list[$index] = RunProfileModel::fromArray($profile);
        }

        return $list;
    }

    public function readRunProfile(string $name): RunProfileModel
    {
        $list = $this->listRunProfile();

        if(array_key_exists($name, $list)){
            return $list[$name];
        }

        throw new \Exception("Docker Run Profile named '$name' does not exist");
    }

    public function writeRunProfile(RunProfileModel $profile): bool
    {
        $list = $this->listRunProfile();
        $list[$profile->getName()] = $profile;
        
        $this->config->setKey($this->key['run'], $list);

        return $this->config->write();
    }

    public function deleteRunProfile(string $name): bool
    {
        $list = $this->listRunProfile();

        if(array_key_exists($name, $list)){
            unset($list[$name]);
        
            $this->config->setKey($this->key['run'], $list);
    
            return $this->config->write();
        }

        throw new \Exception("Docker Run Profile named '$name' does not exist");
    }

    public function listSyncProfile(): array
    {
        $list = $this->config->getKey($this->key['sync']) ?? [];

        foreach($list as $index => $profile){
            $list[$index] = SyncProfileModel::fromArray($profile);
        }

        return $list;
    }

    public function readSyncProfile(string $name): SyncProfileModel
    {
        $list = $this->listSyncProfile();

        if(array_key_exists($name, $list)){
            return $list[$name];
        }

        throw new \Exception("Docker Sync Profile named '$name' does not exist");
    }

    public function writeSyncProfile(SyncProfileModel $profile): bool
    {
        $list = $this->listSyncProfile();
        $list[$profile->getName()] = $profile;

        $this->config->setKey($this->key['sync'], $list);

        return $this->config->write();
    }

    public function deleteSyncProfile(string $name): bool
    {
        $list = $this->listSyncProfile();

        if(array_key_exists($name, $list)){
            unset($list[$name]);
        
            $this->config->setKey($this->key['sync'], $list);
    
            return $this->config->write();
        }

        throw new \Exception("Docker Sync Profile named '$name' does not exist");
    }
}