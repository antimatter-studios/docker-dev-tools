<?php declare(strict_types=1);

namespace DDT\Model\Docker;

use DDT\Model\Model;

class RunProfileModel extends Model
{
    private $name;
    
    private $hasHost = false;
    private $host = null;
    private $port = null;
    
    private $hasTLS = false;
    private $tlscacert = null;
    private $tlscert = null;
    private $tlskey = null;
    private $tlsverify = true;

    public function __construct(string $name, ?string $host=null, ?int $port=null, ?string $tlscacert=null, ?string $tlscert=null, ?string $tlskey=null, ?bool $tlsverify=true)
    {
        $this->setName($name);
        $this->setHost($host, $port);
        $this->setTLS($tlscacert, $tlscert, $tlskey, $tlsverify);
    }

    public function setName(string $name): void
    {
        if(empty($name)){
            throw new \Exception('name param cannot be empty');
        }

        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setHost(?string $host=null, ?int $port=null): void
    {
        if($host !== null && empty($host)){
            throw new \Exception('host param cannot be an empty string');
        }

        if($port !== null && $port < 0){
            throw new \Exception('port param must be a positive integer');
        }

        $params = [$host, $port];
        $count = count(array_filter($params));

        if($count === 0 || $count == count($params)){
            $this->hasHost = $count == count($params);
            $this->host = $host;
            $this->port = $port;
        }
    }

    public function setTLS(?string $tlscacert=null, ?string $tlscert=null, ?string $tlskey=null, ?bool $tlsverify=true): void
    {
        if($tlscacert !== null && !file_exists($tlscacert)){
            throw new \Exception('tlscacert must be null (disabled) or a file');
        }

        if($tlscert !== null && !file_exists($tlscert)){
            throw new \Exception('tlscert must be null (disabled) or a file');
        }

        if($tlskey !== null && !file_exists($tlskey)){
            throw new \Exception('tlskey must be null (disabled) or a file');
        }

        $params = [$tlscacert, $tlscert, $tlskey];
        $count = count(array_filter($params));

        if($count === 0 || $count === count($params)){
            $this->hasTLS = $count === count($params);
            $this->tlscacert = $tlscacert;
            $this->tlscert = $tlscert;
            $this->tlskey = $tlskey;
            $this->tlsverify = $tlsverify;
        }else{
            throw new \Exception('tlscacert, tlscert, tlskey parameters must all be valid, or all be null');
        }
    }

    static public function fromArray(array $data): self
    {
        return new self(
            $data['name'], 
            $data['host'], 
            $data['port'], 
            $data['tlscacert'], 
            $data['tlscert'], 
            $data['tlskey'],
            $data['tlsverify']
        );
    }

	public function getData()
	{
		return [
			"name"		=> $this->name,
			"host"		=> $this->host,
			"port"		=> $this->port,
			"tlscacert"	=> $this->tlscacert,
			"tlscert"	=> $this->tlscert,
			"tlskey"	=> $this->tlskey,
            "tlsverify" => $this->tlsverify,
            "tls"       => $this->hasTLS,
		];
	}

    public function toCommandLine(): string
    {
        $command = [];

        if($this->hasHost){
            $command[] = "-H=".$this->host.":".$this->port;
        }

        if($this->hasTLS){
            $command[] = "--tls";

            if($this->tlsverify){
                $command[] = '--tlsverify';
            }
            
			$command[] = "--tlscacert=" . $this->tlscacert;
			$command[] = "--tlscert=" . $this->tlscert;
			$command[] = "--tlskey=" . $this->tlskey;
        }

        return implode(' ', $command);
    }
}