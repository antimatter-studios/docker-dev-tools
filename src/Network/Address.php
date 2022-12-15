<?php declare(strict_types=1);

namespace DDT\Network;

use DDT\CLI\CLI;

class Address
{
    /** @var CLI */
    private $cli;

    /** @var string */
    private $address;

    private $status = false;
    private $hostname = null;
    private $ip_address = null;
    private $packet_loss = 0.0;
    private $can_resolve = true;

    public function __construct(CLI $cli, string $address)
    {
        $address = trim($address);

        if(empty($address)){
            throw new \Exception("The address given must not be an empty string");
        }

        $this->cli = $cli;
        $this->address = $address;

        if (filter_var($address, FILTER_VALIDATE_IP)) {
            $this->ip_address = $address;
        }else if (filter_var($address, FILTER_VALIDATE_DOMAIN)){
            $this->hostname = $address;
        }
    }

    static public function instance(string $address): Address
    {
        return container(Address::class, ['address' => $address]);
    }

    public function ping(): bool
    {
        try{
            $count = 10;
            $delay = 0.2;
			$result = $this->cli->exec("LC_NUMERIC=en_US.utf8 ping $this->address -c $count -i $delay 2>&1");
            $this->cli->debug('address raw', $result);
		}catch(\Exception $e){
			$result = $e->getMessage();
		}

        // FIXME: I have on linux seen this code return output with errors and yet say everything passed successfully

        if(preg_match("/^PING\s+([^\s]+)\s\(([^\)]+)\)/", $result, $matches)){
            $this->hostname = $matches[1];
            // We do this because pinging a hostname will return the ip address
            $this->ip_address = $matches[2];
        }

        if($this->hostname === $this->ip_address){
            $this->hostname = null;
        }

        if(preg_match("/((?:[0-9]{1,3})(?:\.[0-9]+)?)[\s]?% packet loss/", $result, $matches)){
            $this->packet_loss = (float)$matches[1];
        }

        if(preg_match("/cannot resolve ([^\s]+): unknown host/i", $result, $matches)){
            $this->ip_address = $matches[1];
        }

        if(preg_match("/(cannot resolve|Time to live exceeded|\s0 packets received)/", $result, $matches)){
            $this->can_resolve = false;
            $this->packet_loss = 100;
            $this->cli->debug("ping", "There was a problem resolving address");
        }

        if($this->packet_loss > 0){
            $this->cli->debug("ping", "There was non-zero packet loss: '{$this->packet_loss}%'");
        }

        if($this->ip_address !== null && $this->packet_loss === 0.0 && $this->can_resolve === true){
            return $this->status = true;
        }else{
            return $this->status = false;
        }
    }

    public function __get(string $name){
        if(in_array($name, ['status', 'address', 'hostname', 'ip_address', 'packet_loss', 'can_resolve'])){
            return $this->$name;
        }

        throw new \Exception("The property named '$name' is not available");
    }

    public function __toString(): string
    {
        $output = '';

        $temp = [];

        $this->cli->debug('address raw', json_encode([
            'status' => $this->status,
            'address' => $this->address,
            'hostname' => $this->hostname,
            'ip_address' => $this->ip_address,
            'packet_loss' => $this->packet_loss,
            'can_resolve' => $this->can_resolve,
        ]));

        if($this->status){
			$temp[] = "Ping: {grn}SUCCESS{end}";
		}else{
			$temp[] = "Ping: {red}FAILURE{end}";
		}

		if($this->ip_address !== null){
			$temp[] = "IP Address: '{yel}{$this->ip_address}{end}'";
		}

		if($this->hostname !== null){
			$temp[] = "Hostname: '{yel}{$this->hostname}{end}'";
		}

		if($this->packet_loss === 0.0 && $this->can_resolve === true){
		    $temp[] = "Status: {grn}SUCCESS{end}";
		}else{
			$temp[] = "Status: {red}FAILURE{end}";
		}

		if($this->can_resolve === true){
			$temp[] = "Can Resolve: {grn}YES{end}";
		}else{
		    $temp[] = "Can Resolve: {red}NO{end}";
		}

		return $output = implode(", ", $temp) . "\n";
    }
}