<?php declare(strict_types=1);

namespace DDT\Methods\MacOs\DNS;

use DDT\CLI\CLI;

/**
 * Other useful DNS commands I found online that might be useful
 * MacOS:
 * - arp -ad = flush the arp (address resolution protocol) cache
 * - arp eu-west-1.s3.aws.develop = show information about a specific hostname
 */

class NetworkSetupMethod
{
    /** @var CLI $cli */
    private $cli;

    public function __construct(CLI $cli)
    {
        $this->cli = $cli;
    }

    static public function supported(CLI $cli): bool
    {
		if(!$cli->isDarwin()){
			return false;
		}

		if(!$cli->isCommand('networksetup')){
			return false;
		}

		if(!$cli->isCommand('scutil')){
			return false;
		}

		if(!$cli->isCommand('dscacheutil')){
			return false;
		}

		return true;
    }

	private function getHardwarePorts(): array
	{
		$interfaces = [];

        $hardwarePorts = explode("\n", $this->cli->sudo("networksetup -listnetworkserviceorder | grep 'Hardware Port'"));

		foreach($hardwarePorts as $hwport){
			if(preg_match("/Hardware Port:\s+(?P<name>[^,]+),\s+Device:\s+(?P<device>[^)]+)/", $hwport, $matches)){
				try{
					$dev = $this->cli->sudo("ifconfig {$matches['device']} 2>/dev/null");
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

    private function set(string $message, string $ipAddress): bool
	{
		$this->cli->print("DNS Servers: '{yel}$message{end}' => '{yel}$ipAddress{end}'\n");

		$interfaces = $this->getHardwarePorts();
		foreach($interfaces as $i){
			$this->cli->print("Configuring interface '{yel}{$i['name']}{end}'\n");
            $this->cli->sudo("networksetup -setdnsservers '{$i['name']}' $ipAddress");
		}

		$this->flush();

		return true;
	}

	public function get(): array
	{
		return explode("\n",$this->cli->exec("scutil --dns | grep nameserver | awk '{print $3}' | sort | uniq"));
	}

    public function add(string $dnsIpAddress): bool
    {
		$existing = $this->get();
		$ipAddress = implode(' ', array_unique(array_merge([$dnsIpAddress], $existing)));

		return $this->set('Docker Container', $ipAddress);
    }

    public function remove(string $dnsIpAddress): bool
    {
        return $this->set('Reset back to router', 'empty');
    }

    public function flush(): void
    {
        $this->cli->print("Flushing DNS Cache: ");

        if($this->cli->isCommand('dscacheutil')){
            $this->cli->sudo('dscacheutil -flushcache');
        }

        $this->cli->sudo('killall -HUP mDNSResponder || true');

		$this->cli->print("{grn}FLUSHED{end}\n");
    }
}