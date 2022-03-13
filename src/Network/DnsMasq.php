<?php declare(strict_types=1);

namespace DDT\Network;

use DDT\CLI;
use DDT\Config\DnsConfig;
use DDT\Services\DockerService;
use DDT\Docker\DockerContainer;
use DDT\Exceptions\Docker\DockerContainerNotFoundException;

class DnsMasq
{
    /** @var CLI */
    private $cli;

    /** @var DnsConfig */
    private $config;

    /** @var Docker */
    private $docker;

    public function __construct(CLI $cli, DnsConfig $config, DockerService $docker)
    {
        $this->cli = $cli;
        $this->config = $config;
        $this->docker = $docker;
    }

    public function isRunning(): bool
    {
        try{
            $this->getContainer();
            return true;
        }catch(DockerContainerNotFoundException $e){
            return false;
        }
    }

    public function getContainer(): DockerContainer 
    {
        return DockerContainer::get($this->config->getContainerName());
    }

    public function startContainer(): DockerContainer
    {
        return DockerContainer::background(
            $this->config->getContainerName(), 
            '',
            $this->config->getDockerImage(),
            [],
            [],
            [],
            ["53:53/udp"],
        );
    }

	public function listDomains(): array
	{    
        $domains = [];

        $container = $this->getContainer();

        $list = explode("\n", $container->exec("find /etc/dnsmasq.d -name \"*.conf\" -type f"));
        $list = array_map('trim', $list);
        $list = array_filter($list);  

        foreach($list as $file){
            $file = trim($file);
            
            if(empty($file)){
                $this->cli->debug('{red}[DNSMASQ]{end}: cannot view file inside container as it was empty string, skipping');
                continue;
            }

            $contents = $container->exec("cat $file");
            if(preg_match("/^[^\/]+\/(?P<domain>[^\/]+)\/(?P<ip_address>[^\/]+)/", $contents, $matches)){
                $domains[] = ['domain' => $matches['domain'], 'ip_address' => $matches['ip_address']];
            }
        }

        return $domains;
	}

	public function addDomain(string $domain, string $ipAddress): bool
	{
        $container = $this->getContainer();

        $container->exec("/bin/sh -c 'echo 'address=/$domain/$ipAddress' > /etc/dnsmasq.d/$domain.conf'");

        return $container->getExitCode() === 0;
	}

	public function removeDomain(string $domain, string $ipAddress): bool
	{
        $container = $this->getContainer();

        $container->exec("/bin/sh -c 'f=/etc/dnsmasq.d/$domain.conf && [ -f \$f ] && rm \$f'");

        return $container->getExitCode() === 0;
	}

    public function reload()
    {
        $container = $this->getContainer();

        $container->exec("kill -s SIGHUP 1");

        sleep(2);
    }

	public function pull(): void
	{
		$dockerImage = $this->config->getDockerImage();
		
        $this->cli->print("{blu}Docker:{end} Pulling '{yel}$dockerImage{end}' before changing the dns\n");

		$this->docker->pull($dockerImage);
	}

    public function start(): string
    {
        $container = $this->startContainer();
        
        if($container->isRunning() === false){
            $this->cli->debug("{red}[DNSMASQ]{end}: Container was found, but doesn't appear to be running, delete it and try again");
            // we found the container, but it's stopped or exited in some way, so we need to destroy it and recreate it
            $container->stop();
            $container->delete();

            // Now create a brand new container
            $container = $this->startContainer();
        }

		sleep(2);

        return $container->getid();
    }

	public function stop(bool $delete=true): bool
	{
        try{
            /** @var DockerContainer $container */
            $container = $this->getContainer();

            if($container->stop()){
                if($delete === true){
                    if($container->delete()){
                        return true;
                    }else{
                        $this->cli->print("{red}Container {$container->getId()} has failed to delete{end}");        
                    }
                }else{
                    return true;
                }
            }else{
                $this->cli->print("{red}Container {$container->getId()} has failed to stop{end}");
                return false;
            }
        }catch(\Exception $e){
            $this->cli->print("Exception: " . $e->getMessage());
        }

        return false;
	}

    public function refresh()
    {
        $this->cli->sudo();
    }

    private function getContainerId()
    {
        return null;
    }

    public function getContainerName(): string
    {
        return $this->config->getContainerName();
    }

    public function logs(bool $follow, ?string $since=null)
	{
        $container = $this->getContainer();
        $container->logs($follow, $since);
	}
}