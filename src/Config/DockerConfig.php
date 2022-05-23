<?php declare(strict_types=1);

namespace DDT\Config;

use DDT\Model\Docker\RunProfile;
use DDT\Model\Docker\SyncProfile;

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
            $list[$index] = new RunProfile(
                $profile['name'], 
                $profile['host'], 
                $profile['port'], 
                $profile['tlscacert'], 
                $profile['tlscert'], 
                $profile['tlskey'],
                $profile['tlsverify']
            );
        }

        return $list;
    }

    public function readRunProfile(string $name): RunProfile
    {
        $list = $this->listRunProfile();

        if(array_key_exists($name, $list)){
            return $list[$name];
        }

        throw new \Exception("Docker Run Profile named '$name' does not exist");
    }

    public function writeRunProfile(RunProfile $profile): bool
    {
        $list = $this->listRunProfile();
        $data = $profile->get();

        $list[$data['name']] = $data;
        
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
            $list[$index] = new SyncProfile($profile['name'], $profile['container_name'], $profile['local_dir'], $profile['remote_dir']);
        }

        return $list;
    }

    public function readSyncProfile(string $name): SyncProfile
    {
        $list = $this->listSyncProfile();

        if(array_key_exists($name, $list)){
            return $list[$name];
        }

        throw new \Exception("Docker Sync Profile named '$name' does not exist");
    }

    public function writeSyncProfile(SyncProfile $profile): bool
    {
        $list = $this->listSyncProfile();
        $data = $profile->get();

        $list[$data['name']] = $data;
        
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