<?php declare(strict_types=1);

namespace DDT\Network\Darwin;

use DDT\CLI;
use DDT\Contract\DnsServiceInterface;

/**
 * Other useful DNS commands I found online that might be useful
 * MacOS:
 * - arp -ad = flush the arp (address resolution protocol) cache
 * - arp eu-west-1.s3.aws.develop = show information about a specific hostname
 */

class DnsService implements DnsServiceInterface
{
    private $cli;

    public function __construct(CLI $cli)
    {
        $this->cli = $cli;
    }

    private function getHardwarePorts(): array
	{
		$interfaces = [];

        $hardwarePorts = explode("\n", $this->cli->exec("networksetup -listnetworkserviceorder | grep 'Hardware Port'"));

		foreach($hardwarePorts as $hwport){
			if(preg_match("/Hardware Port:\s+(?P<name>[^,]+),\s+Device:\s+(?P<device>[^)]+)/", $hwport, $matches)){
				try{
					$dev = $this->cli->exec("ifconfig {$matches['device']} 2>/dev/null");
					if(strpos($dev, "status: active") !== false){
						$interfaces[] = ['name' => $matches['name'], 'device' => $matches['device']];
					}
				}catch(\Exception $e) {
					// ignore this device
				}
			}
		}

		return $interfaces;
	}

    private function setDNS(string $message, string $ipAddress): bool
	{
		$this->cli->print("DNS Servers: '{yel}$message{end}' => '{yel}$ipAddress{end}'\n");

        $this->cli->sudo();

		$interfaces = $this->getHardwarePorts();
		foreach($interfaces as $i){
			$this->cli->print("Configuring interface '{yel}{$i['name']}{end}'\n");
            $this->cli->exec("networksetup -setdnsservers '{$i['name']}' $ipAddress");
		}

		$this->flush();

		return true;
	}

	public function listIpAddress(): array
	{
		return explode("\n",$this->cli->exec("scutil --dns | grep nameserver | awk '{print $3}' | sort | uniq"));
	}

    public function enable(string $dnsIpAddress): bool
    {
		$existing = $this->listIpAddress();
		$ipAddress = implode(' ', array_unique(array_merge([$dnsIpAddress], $existing)));

		return $this->setDNS('Docker Container', $ipAddress);
    }

    public function disable(string $dnsIpAddress): bool
    {
        return $this->setDNS('Reset back to router', 'empty');
    }

    public function flush(): void
    {
        $this->cli->print("Flushing DNS Cache: ");

        $this->cli->sudo();

        if($this->cli->isCommand('dscacheutil')){
            $this->cli->exec('dscacheutil -flushcache');
        }

        $this->cli->exec('killall -HUP mDNSResponder || true');

		$this->cli->print("{grn}FLUSHED{end}\n");
    }
}