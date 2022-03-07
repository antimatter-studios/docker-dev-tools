<?php declare(strict_types=1);

namespace DDT\Network\Ubuntu_16;

use DDT\CLI;
use DDT\Contract\DnsServiceInterface;

class DnsService implements DnsServiceInterface
{
    /** @var CLI */
    private $cli;

    public function __construct(CLI $cli)
    {
        $this->cli = $cli;
    }

	public function listIpAddress(): array
	{
		return [];
	}

	public function getIpAddressList(): array
	{
		throw new \Exception("TODO: write method " . __METHOD__);
	}

    public function enable(string $dnsIpAddress): bool
    {
        throw new \Exception("Implement method: " . __METHOD__);
    }

    public function disable(): bool
    {
        throw new \Exception("Implement method: " . __METHOD__);
    }

    public function flush(): void
    {
        throw new \Exception("Implement method: " . __METHOD__);
    }

    public function enableDNS(): bool
    {
		$ipAddress = "0.0.0.0";

		$this->cli->print("Updating DNS Resolver to use nameserver with ip address {yel}'$ipAddress'{end}\n");
		$this->cli->print("{blu}Note: If you are asked for your password, it means your sudo password{end}\n");

		# Add the nameserver to the '/etc/resolvconf/resolv.conf.d/head' file if it does not exist, then call resolvconf to update everything
		$file = "/etc/resolvconf/resolv.conf.d/head";
		if(file_exists($file)){
			$this->cli->exec("[ -z \"\$(cat $file | grep \"nameserver $ipAddress\")\" ] && echo \"nameserver $ipAddress\" | sudo tee -a $file");
		}
		$this->cli->exec("sudo resolvconf -u");

		$this->cli->print("Restarting system services after reconfiguration\n");

		$file = "/etc/NetworkManager/NetworkManager.conf";
		if(file_exists($file)){
			$this->cli->exec("sudo sed -i 's/^dns=dnsmasq/#dns=dnsmasq/i' $file");
		}

		$output = explode("\n", $this->cli->exec("sudo service --status-all | grep network-manager"));
		if(count($output) > 0){
			$this->cli->passthru("sudo service network-manager restart");
		}

		$this->cli->exec("sudo kill -9 $(ps aux | grep [N]etworkManager/dnsmasq | awk '{ print $2 }')");
		$this->cli->exec("sudo kill -9 $(ps aux | grep [d]nsmasq | awk '{ print $2 }')");

		return true;
    }

    public function disableDNS(): bool
    {
    	$ipAddress = "127.0.0.1";

    	$file = "/etc/NetworkManager/NetworkManager.conf";
    	if(file_exists($file)){
			$this->cli->exec("sudo sed -i 's/^#dns=dnsmasq/dns=dnsmasq/i' $file");
		}

    	$file = "/etc/resolvconf/resolv.conf.d/head";
    	if(file_exists($file)){
			// Remove the nameserver from the resolv.conf
			$this->cli->exec("sudo sed -i \"/^nameserver $ipAddress/d\" $file");
		}

		$this->cli->exec("sudo resolvconf -u");

		$this->cli->exec("sudo service network-manager restart", false, false);

		return true;
    }

    public function flushDNS(): void
    {
		// for linux we don't have anything to do
    }
}