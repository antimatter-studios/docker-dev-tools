<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI;
use DDT\Config\ProxyConfig;
use DDT\Exceptions\Docker\DockerContainerNotFoundException;
use DDT\Exceptions\Docker\DockerNetworkNotFoundException;
use DDT\Exceptions\Docker\DockerInspectException;
use DDT\Network\Proxy;
use DDT\Services\ConfigGeneratorService;
use DDT\Text\Table;

class ProxyTool extends Tool
{
    /** @var ProxyConfig */
    private $config;

    /** @var Proxy */
    private $proxy;

    /** @var ConfigGeneratorService */
    private $configGeneratorService;

    public function __construct(CLI $cli, ProxyConfig $config, Proxy $proxy, ConfigGeneratorService $configGeneratorService)
    {
        parent::__construct('proxy', $cli);

        $this->config = $config;
        $this->proxy = $proxy;
        $this->configGeneratorService = $configGeneratorService;

        foreach([
            'start', 'stop', 'restart', 'reload',
            'logs', 'logs-f', 
            'add-network', 'remove-network', 
            'nginx-config', 'status', 
            'container-name', 'docker-image'
        ] as $command){
            $this->setToolCommand($command);
        }
    }

    public function getToolMetadata(): array
    {
        return [
            'title' => 'Frontend Proxy Tool',
            'short_description' => 'A tool to control how the local proxy is configured and control whether it is running or not',
            'description' => [
                "This tool will start a docker container and listen on DNS Port 53 and handle",
                "requests for your local development networks. Whilst pushing upstream all",
                "other requests it can't resolve to an online DNS server",
            ],
            'options' => [
                "{cyn}Running of the NGINX Front End Proxy Container:{end}",
                "\tstart: Run the Nginx proxy, with an optional assignment for the network name to use",
                "\tstop: Stop the Nginx proxy",
                "\trestart: Restart the proxy",
                "\treload: Reload the NGINX Configuration\n",

                "{cyn}Logging:{end}",
                "\tlogs: View the logs from the Nginx proxy container",
                "\tlogs-f: View and follow the logs from the Nginx proxy container\n",

                "{cyn}Network Configuration:{end}",
                "\tadd-network <network-name>: Add a new network to a running proxy without needing to restart it",
                "\tremove-network <network-name>: Remove an existing network from the proxy container so it stops monitoring it\n",

                "{cyn}Configuration:{end}",
                "\tnginx-config: Output the raw /etc/nginx/conf.d/default.conf which is generated when containers start and stop",
                "\tstatus: Show the domains that the Nginx proxy will respond to",
                "\tcontainer-name: Get/Set the name to give to this container. Pass a second parameter for the container name if you wish to set it",
                "\tdocker-image: Get/Set the docker image name to run. Pass a second parameter for the docker image if you with to set it",
            ],
            'examples' => [
                "{yel}Usage Example:{end} ddt proxy logs-f {grn}- follow the log output for the proxy{end}",
                "{yel}Usage Example:{end} ddt proxy start {grn}- start the proxy{end}",
            ],
        ];
    }

    public function isRunning(): bool 
    {
        throw new \Exception('Proxy is not running');
    }

    public function start()
    {
        $this->cli->print("{blu}Starting the Frontend Proxy:{end} ".$this->dockerImage()."\n");
        $this->configGeneratorService->start();
        $this->proxy->start();

        // FIXME: perhaps this should call the docker object to do this
        $this->cli->print("{blu}Running Containers:{end}\n");
        $this->cli->passthru('docker ps');
    }

    public function stop()
    {
        try{
            $this->cli->print("{blu}Stopping the Frontend Proxy:{end} ".$this->dockerImage()."\n");
            $this->proxy->stop();
            // TODO: should I stop this when I stop the proxy?
            // TODO: what if it's used by another service?
            $this->configGeneratorService->stop();

            // FIXME: perhaps this should call the docker object to do this
            $this->cli->print("{blu}Running Containers:{end}\n");
            $this->cli->passthru('docker ps');
        }catch(DockerContainerNotFoundException $e){
            $this->cli->failure("The Proxy Container is not running");
        }
    }

    public function reload()
    {
        try{
            if($this->proxy->reload()){
                $this->cli->success("The proxy has reloaded the configuration\n");
            }else{
                $this->cli->failure("The proxy failed to reload\n");
            }
		}catch(DockerContainerNotFoundException $e){
			$this->cli->print("{red}".$e->getMessage."{end}\n");
			return false;
		}
    }

    public function restart()
    {
        $this->stop();
        $this->start();
    }

    public function logs(?string $since=null)
    {
        $this->proxy->logs(false, $since);
    }

    public function logsF(?string $since=null)
    {
        $this->proxy->logs(true, $since);
    }

    public function addNetwork(string $network)
    {
        if(empty($network)){
            throw new \Exception('Network must be a non-empty string');
        }

        $network = $network;

        $this->cli->print("{blu}Connecting to a new network '$network' to the proxy container '{$this->containerName()}'{end}\n");

        $this->proxy->addNetwork($network);
        $this->status();
    }

    public function removeNetwork(string $network)
    {
        if(empty($network)){
            throw new \Exception('Network must be a non-empty string');
        }

        $network = $network;

        $this->cli->print("{blu}Disconnecting the network '$network' from the proxy container '{$this->containerName()}'{end}\n");

        $this->proxy->removeNetwork($network);
        $this->status();
    } 

    public function nginxConfig(?bool $colour=true)
    {
        if($this->proxy->isRunning()){
            $output = $this->proxy->getConfig();
            if($colour){
                $output = "\n{cyn}".$output."{end}\n\n";
            }
            $this->cli->print($output);
        }else{
            $this->cli->print('{red}Proxy is not running{end}');
        }
    }

    public function status()
    {
        $this->cli->print("{blu}Registered proxy services:{end}\n");
        $table = container(Table::class);

        $table->addRow([
            '{yel}Docker Network{end}',
            '{yel}Container{end}',
            '{yel}Host{end}',
            '{yel}Port{end}',
            '{yel}Path{end}',
            '{yel}Nginx Status{end}',
        ]);

        foreach($this->proxy->getNetworks(true) as $network){
            try{
                $containerList = $this->proxy->getContainersOnNetwork($network);
            }catch(DockerNetworkNotFoundException $e){
                $this->cli->debug("{red}[PROXY]{end}: Network '$network' was not found\n");
                $containerList = [];
            }

            if(empty($containerList)){
                $table->addRow([$network, "{yel}There are no containers{end}"]);
            }

            foreach($containerList as $container){
                try{
                    $configurations = $this->proxy->getContainerProxyEnv($container['name']);
                    foreach($configurations as $config){
                        $tag = array_key_exists('tag', $config) ? "(tag: {$config['tag']})" : "";
                        $table->addRow([
                            $network, 
                            $container['name'] . " $tag", 
                            $config['proto'].'://'.$config['host'], 
                            $config['port'], 
                            $config['path'], 
                            $container['nginx_status']]
                        );
                    }
                }catch(\Exception $e){
                    $this->cli->debug("{red}[PROXY]{end}: Could not read proxy config for container '{$container['name']}'\n");
                }
            }
        }

        $this->cli->print($table->render());
    }

    public function containerName(?string $name=null)
    {
        if(empty($name)){
            return $this->config->getContainerName();
        }

        $this->cli->print("{blu}Setting ContainerName {end}: $name\n");
        if($this->config->setContainerName($name)){
            $this->cli->success("Succeeded to set container name to '$name'. Please restart proxy to see changes");
        }else{
            $this->cli->failure("Failed to set container name\n");
        }
    }

    public function dockerImage(?string $image=null)
    {
        if(empty($image)){
            return $this->config->getDockerImage();
        }

        $this->cli->print("{blu}Setting Docker Image{end}: $image\n");
        if($this->config->setDockerImage($image)){
            $this->cli->success("Succeeded to set docker image name to '$image'. Please restart proxy to see changes");
        }else{
            $this->cli->failure("Failed to set docker image\n");
        }
    }
}