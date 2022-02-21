<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI;
use DDT\Config\DnsConfig;
use DDT\Config\IpConfig;
use DDT\Contract\DnsServiceInterface;
use DDT\Network\Address;
use DDT\Network\DnsMasq;
use DDT\Text\Table;
use DDT\Text\Text;

class DnsTool extends Tool
{
    /** @var DnsConfig */
    private $dnsConfig;

    /** @var IpConfig */
    private $ipConfig;

    /** @var DnsMasq */
    private $dnsMasq;
    
    /** @var DnsServiceInterface */
    private $dnsService;

    public function __construct(CLI $cli, DnsConfig $dnsConfig, IpConfig $ipConfig, DnsMasq $dnsMasq, DnsServiceInterface $dnsService)
    {
    	parent::__construct('dns', $cli);

        $this->dnsConfig = $dnsConfig;
        $this->ipConfig = $ipConfig;

        $this->dnsMasq = $dnsMasq;
        $this->dnsService = $dnsService;

        foreach([
            'enable', 'disable', 'refresh', 'start', 'stop', 'restart',
            'logs', 'logs-f',
            'add-domain', 'remove-domain',
            'ip', 'ping', 'status',
            'container-name', 'docker-image',
            // These are commands I think I need to re-add in the future
            // 'list-devices', 'set-device', 'remove-device'
        ] as $command){
            $this->setToolCommand($command);
        }
    }

    public function getToolMetadata(): array
    {
        return [
            'title' => 'DNS Tool',
            'description' => '',
            'short_description' => 'A tool to manage the DNS configuration used when the system is running',
            'description' => trim(
                "This tool will start a docker container and listen on DNS Port 53 and handle\n".
                "requests for your local development networks. Whilst pushing upstream all\n".
                "other requests it can't resolve to an online DNS server"
            ),
            'options' => trim(
                "{cyn}Configuring IP Address and domains:{end}\n".
                "    add-domain yourdomain.com: Add a domain to the running DNS server\n".
                "    remove-domain yourdomain.com: Remove a domain to the running DNS server\n".
                "    ip 10.254.254.254: Use this ip address when configuring the server instead of the default one\n".
                "\n".
                "{cyn}Toggling the DNS Server:{end}\n".
                "    enable: Enable the DNS Server\n".
                "    disable: Disable the DNS Server\n".
                "    refresh: Toggles the DNS Server as disabled then enabled as well as refreshing the dns cache\n".
                "\n".
                "{cyn}Running of the DNS Server Container:{end}\n".
                "    start: Setup the DNS servers and start the DNS container\n".
                "    restart: Restart the DNS Container\n".
                "    stop: Stop the DNS container\n".
                "\n".    
                "{cyn}Logging:{end}\n".
                "    logs: View the logs from the DNS container\n".
                "    logs-f: View and follow the logs from the DNS container\n".
                "\n".
                "{cyn}Configuration:{end}\n".
                "    status: View a list of all the domains that are registered with the DNS server\n".
                "    container-name ddt-dnsmasq: Get the name of this container to use, if passed a parameter it will update the settings with that value\n".
                "    docker-image ddt-dnsmasq: Get the docker image name to use, if passed a parameter it will update the settings with that value\n"
            ),
            'notes' => trim(
                "{yel}Enabling, disable, and resetting{end} the DNS Server doesn't change the running status of the\n".
                "DNS Container. It's just changing your system configuration from using the DNS Server or\n".
                "going back to your default computer DNS settings. It's useful when you need to quickly toggle back\n".
                "to the system defaults because the DNS Server might interfere with running a VPN. So you can quickly\n".
                "disable it, do your other work. Then re-enable it when you need to get back to working with the\n".
                "development environment. It's like a soft reset of your DNS configuration so you can temporarily\n".
                "do something.\n".
                "\n".
                "{yel}Starting, Stopping, and Restarting{end} implies also {yel}enabling and disabling{end} the\n".
                "DNS Server like explained above. However it does the extra step of Starting or Stopping the\n".
                "docker container as well. So it's more like a hard reset.\n"
            )
        ];
    }

    public function enable()
    {
        $this->cli->print("{grn}Enabling:{end} DNS...\n");

        $dnsIpAddress = $this->ipConfig->get();

        if(empty($dnsIpAddress)){
            throw new \Exception('The system configuration had no usable ip address for this system configured');
        }

        $this->dnsService->enable($dnsIpAddress);
    }

    public function disable()
    {
        $this->cli->print("{red}Disabling:{end} DNS...\n");
        $this->dnsService->disable();
    }

    public function refresh()
    {
        $this->cli->print("{yel}Refreshing:{end} DNS...\n");
        
        $this->disable();
        $this->enable();
        $this->dnsMasq->reload();
    }

    public function start()
    {
        $this->cli->print("{blu}Starting:{end} DNS...\n");

        $this->dnsMasq->start();
        $this->enable();

        $domainGroup = $this->dnsConfig->listDomains();

        foreach($domainGroup as $ipAddress => $domainList){
            foreach($domainList as $domain){
                $this->dnsMasq->addDomain($domain, $ipAddress);
            }
        }

        $this->dnsMasq->reload();

        $this->ping();
    }

    public function stop()
    {
        $this->cli->print("{red}Stopping:{end} DNS...\n");

        $this->disable();
        $this->dnsMasq->stop();

        $address = Address::instance('127.0.0.1');
        $address->ping();
        $this->cli->print((string)$address);

        $address = Address::instance('google.com');
        $address->ping();
        $this->cli->print((string)$address);
    }

    public function restart(): void
    {
        $this->cli->print("{yel}Restarting:{end} DNS...\n");

        if($this->dnsMasq->isRunning()){
            $this->stop();
            $this->start();
        }else{
            $this->start();
        }
    }

    public function logs(?string $since=null): void
    {
        try{
            $this->dnsMasq->logs(false, $since);
        }catch(DockerContainerNotFoundException $e){
            $this->cli->failure("The DNS Container is not running");
        }
    }

    public function logsF(?string $since=null): void
    {
        try{
            $this->dnsMasq->logs(true, $since);
        }catch(DockerContainerNotFoundException $e){
            $this->cli->failure("The DNS Container is not running");
        }
    }

    public function addDomain(string $domain): void
    {
        $this->cli->sudo();

        $domain = Address::instance($domain);

        if($domain->hostname === null){
            throw new \Exception('The hostname was not interpreted correctly, if relevant; please use a hostname and not an ip address');
        }

        $ipAddress = $this->ipConfig->get();

        // TODO: support overriding the ip address through the command line argument --ip-address=x.x.x.x

        $this->cli->print("{blu}Adding domain:{end} '{yel}$domain->hostname{end}' with ip address '{yel}$ipAddress{end}' to Dns Resolver: ");
        
        try{
            if($this->dnsMasq->addDomain($domain->hostname, $ipAddress)){
                $this->dnsMasq->reload();

                $this->cli->silenceChannel('stdout', function(){
                    $this->refresh();
                });

                $this->dnsConfig->addDomain($domain->hostname, $ipAddress);

                $domain = Address::instance($domain->hostname);
                if($domain->ping() === true){
                    $this->cli->print("{grn}SUCCESS{end}\n");
                }else{
                    $this->cli->print("{red}FAILURE{end}\n");
                }
            }
        }catch(DockerContainerNotFoundException $e){
            $this->cli->failure("\nThe DNS Container is not running");
        }
    }

    public function removeDomain(string $domain): void
    {
        $this->cli->sudo();

        $domain = Address::instance($domain);

        if($domain->hostname === null){
            throw new \Exception('The hostname was not interpreted correctly, if relevant; please use a hostname and not an ip address');
        }

        $ipAddress = $this->ipConfig->get();

        // TODO: support overriding the ip address through the command line argument --ip-address=x.x.x.x

        $this->cli->print("{blu}Removing domain:{end} '{yel}$domain->hostname{end}' with ip address '{yel}$ipAddress{end}' from the Dns Resolver: ");
        
        try{
            if($this->dnsMasq->removeDomain($domain->hostname, $ipAddress)){
                $this->dnsMasq->reload();
    
                $this->cli->silenceChannel('stdout', function(){
                    $this->refresh();
                });
    
                $this->dnsConfig->removeDomain($domain->hostname, $ipAddress);
    
                $domain = Address::instance($domain->hostname);
                if($domain->ping() === false){
                    $this->cli->print("{grn}SUCCESS{end}\n");
                }else{
                    $this->cli->print("{red}FAILURE{end}\n");
                }
            }
        }catch(DockerContainerNotFoundException $e){
            $this->cli->failure("\nThe DNS Container is not running");
        }
    }

    public function ip(?string $address=null): string
    {
        // TODO: what to do when you set a new ip address, here, should reconfigure everything with that new ip address?
        // NOTE: this could be quite a lot of changes in various aspects of the system that might be storing that ip address and using it locally
        $this->cli->debug("new ip address = '$address'");

        $list = $this->dnsService->listIpAddress();

        return implode("\n", $list);
    }

    public function ping()
    {
        $list = $this->dnsService->listIpAddress();

        foreach($list as $ipAddress){
            $address = Address::instance($ipAddress);

            $address->ping();

            $this->cli->print((string)$address);
        }

        $address = Address::instance('127.0.0.1');
        $address->ping();
        $this->cli->print((string)$address);

        $address = Address::instance('google.com');
        $address->ping();
        $this->cli->print((string)$address);

        $domainGroup = $this->dnsConfig->listDomains();
        foreach($domainGroup as $ipAddress => $domainList){
            foreach($domainList as $domain){
                $address = Address::instance($domain);
                $address->ping();
                $this->cli->print((string)$address);
            }
        }
    }

    public function containerName(?string $name=null): string
    {
        if(empty($name)){
            return $this->dnsConfig->getContainerName();
        }else{
            return $this->dnsConfig->setContainerName($name);
        }
    }

    public function dockerImage(?string $name=null): string
    {
        if(empty($name)){
            return $this->dnsConfig->getDockerImage();
        }else{
            return $this->dnsConfig->setDockerImage($name);
        }
    }

    public function status(): void
    {
        $this->cli->print("{blu}Registered domains:{end}\n");

        $domainList = $this->dnsMasq->listDomains();

        $table = container(Table::class);
        $table->setRightPadding(10);
        $table->addRow(['{yel}Domain{end}', '{yel}IP Address{end}']);

        $reply = [];
        foreach($domainList as $item){
            if(!array_key_exists($item['ip_address'], array_keys($reply))){
                $reply[$item['ip_address']] = true;
            }

            $table->addRow(["{wht}{$item['domain']}{end}", "{wht}{$item['ip_address']}{end}"]);
        }
        
        $this->cli->print($table->render(true));
    }

    // public function listDevices() {
    //     $this->cli->print("{blu}List of all devices:{end}\n");
        
    //     foreach($this->dnsService->getHardwarePorts() as $index => $device){
    //         $this->cli->print($index+1 . " - ${device['name']} (dev: ${device['device']})\n");
    //     }
    // }

    // public function setDevice(string $device=null) {
    //     $this->cli->print("{blu}Set the device for DNS service:{end}\n");

    //     $list = $this->dnsService->getHardwarePorts();

    //     foreach($list as $index => $device){
    //         $this->cli->print($index+1 . " - ${device['name']} (dev: ${device['device']})\n");
    //     }

    //     $answer = (int)$this->cli->ask("Please enter the number of device to select: ", range(1,count($list)));

    //     if($answer < 1 || $answer > count($list)) {
    //         $this->cli->print("{red}Sorry but the device selected was not available (requested: $answer){end}\n");
    //     }else{
    //         $device = $list[$answer-1];
    //         $this->cli->print("You have requested device: {yel}{$device['name']} (dev: {$device['device']}){end}\n");
    //         $this->dnsConfig->setDevice($device['name'], $device['device']);
    //     }
    // }

    // public function removeDevice() {
    //     $this->dnsConfig->removeDevice();
    // }
}
