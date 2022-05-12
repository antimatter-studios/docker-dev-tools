<?php declare(strict_types=1);

namespace DDT\Config;

use DDT\Docker\DockerRunProfile;

class DockerConfig
{
    /** @var SystemConfig $config */
    private $config;

    private $key = 'docker';

    public function __construct(SystemConfig $config)
    {
        $this->config = $config;
    }

    public function listProfile(): array
    {
        $list = $this->config->getKey("$this->key.profile") ?? [];

        foreach($list as $index => $profile){
            $list[$index] = new DockerRunProfile(
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

    public function readProfile(string $name): DockerRunProfile
    {
        $list = $this->listProfile();

        if(array_key_exists($name, $list)){
            return $list[$name];
        }

        throw new \Exception("Docker Run Profile named '$name' does not exist");
    }

    public function writeProfile(DockerRunProfile $profile): bool
    {
        $list = $this->listProfile();
        $data = $profile->get();

        $list[$data['name']] = $data;
        
        $this->config->setKey("$this->key.profile", $list);

        return $this->config->write();
    }

    public function deleteProfile(string $name): bool
    {
        $list = $this->listProfile();

        if(array_key_exists($name, $list)){
            unset($list[$name]);
        
            $this->config->setKey("$this->key.profile", $list);
    
            return $this->config->write();
        }

        throw new \Exception("Docker Run Profile named '$name' does not exist");
    }
}