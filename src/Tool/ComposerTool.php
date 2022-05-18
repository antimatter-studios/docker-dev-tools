<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI;
use DDT\Config\PhpComposerConfig;
use DDT\Services\DockerService;
use DDT\Docker\DockerContainer;
use DDT\Docker\DockerVolume;
use DDT\Exceptions\Docker\DockerMissingException;
use DDT\Exceptions\Docker\DockerVolumeNotFoundException;

class ComposerTool extends Tool
{
    /** @var PhpComposerConfig */
    private $config;

    /** @var Docker */
    private $docker;

    public function __construct(CLI $cli, PhpComposerConfig $config, DockerService $docker)
    {
        $this->config = $config;
        $this->docker = $docker;

    	parent::__construct('composer', $cli);
        $this->setToolCommand('__call', null, true);
        $this->setToolCommand('--enable-docker-volume-cache', 'enableDockerVolumeCache');
        $this->setToolCommand('--set-container-name', 'setContainerName');
    }
    
    public function getToolMetadata(): array
    {
        return [
            'title' => 'A totally useless composer wrapper',
            'short_description' => 'A docker wrapper for composer for people who cannot install dependencies because of insufficient php version',
            'description' => [
                'The only reason this tool exists is because some people do not have the required PHP version install on their',
                'computer and it cannot or they do not want to upgrade this php for some reason. This leaves a big problem when',
                'wanting to work with projects that require higher levels of PHP, installing packages will not work or running',
                'composer scripts.',
                '',
                'However, this tool uses docker to augment the users local system so composer will run in a container, with a slight',
                'performance overhead, but with the benefit of being able to run composer without local computer or user restrictions.',
            ],
            'options' => [
                'This tool provides a passthrough-like interface to composer, whatever the user puts into the command line, is passed to composer',
                'As such, all composer functionality applies here, run `ddt composer -h` for information on what is available',
            ]
        ];
    }

    public function enableDockerVolumeCache(bool $state, ?string $volumeName=null): void
    {
        if($this->config->enableCache($state, $volumeName)){
            $this->cli->print("Enabled: " . ($this->config->isCacheEnabled() ? "yes" : "no") . "\n");
            $this->cli->print("Volume Name: " . $this->config->getCacheName() . "\n");

            if($state === false){
                try{
                    $name = $this->config->getCacheName();
                    $volume = DockerVolume::instance($name, false);
                    if($volume->delete()){
                        $this->cli->print("{yel}Docker Cache Volume{end}: successfully deleted '$name'\n");
                    }else{
                        $this->cli->print("{red}Docker Cache Volume{end}: failed to delete\n");
                    }
                }catch(DockerVolumeNotFoundException $e){
                    // don't do anything, nothing to delete
                    $this->cli->debug("php composer", "No volume named '$name' to delete\n");
                }
            }

            $this->cli->success("Successfully set docker volume cache information");
        }else{
            $this->cli->failure("Failed to set docker volume cache information");
        }
    }

    public function setContainerName(string $name): void
    {
        if($this->config->setContainerName($name)){
            $this->cli->print("Container Name: " . $this->config->getContainerName() . "\n");
            $this->cli->success("Successfully set the docker container name");
        }else{
            $this->cli->failure("Failed to set docker container name information");
        }
    }

    public function run($params)
    {
        try{
            $name = $this->config->getContainerName();

            $volumes = [
                '$HOME/.ssh:/root/.ssh',
                getcwd().':/app:delegated'
            ];
    
            if($this->config->isCacheEnabled()){
                $this->cli->print("{yel}DDT Docker Composer Cache{end}: enabled\n");
                $volumes[] = $this->config->getCacheName(). ":/tmp:delegated";
            }else{
                $this->cli->print("{yel}DDT Docker Composer Cache{end}: disabled\n");
            }
    
            $options = [
                '--entrypoint "sh"',
                '--rm'
            ];
    
            $env = [
                'COMPOSER_PROCESS_TIMEOUT=2000'
            ];
    
            $image = 'composer:latest';
            $command = "-c 'php -d memory_limit=-1 $(which composer) $params 2>&1'";
    
            $this->docker->delete($name, true);
    
            DockerContainer::foreground($name, $command, $image, $volumes, $options, $env);
        }catch(DockerMissingException $e){
            $this->cli->failure($e->getMessage());
        }
    }

    public function __call($name, $input)
    {
        $args = array_reduce($input[0], function($a, $i){
            $i = array_merge(['value' => ''], $i);
            $a[] = rtrim("{$i['name']}={$i['value']}", '=');
            return $a;
        }, []);

        $params = implode(' ', $args);

        return $this->run($params) . "\n";
    }
}