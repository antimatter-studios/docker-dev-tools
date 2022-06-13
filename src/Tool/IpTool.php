<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI;
use DDT\Config\Sections\IpConfig;
use DDT\Contract\IpServiceInterface;
use DDT\Network\Address;

class IpTool extends Tool
{
    /** @var IpConfig  */
    private $config;

	/** @var IpServiceInterface */
	private $ipService;

    public function __construct(CLI $cli, IpConfig $config, IpServiceInterface $ipService)
    {
    	parent::__construct('ip', $cli);

        $this->cli = $cli;
        $this->config = $config;
		$this->ipService = $ipService;

		foreach(['set', 'get', 'add', 'remove', 'reset', 'ping'] as $command){
			$this->setToolCommand($command);
		}
    }

	public function getToolMetadata(): array
	{
		return [
			'title' => 'IP Address Tool',
			'short_description' => 'A tool to configure and control local ip addresses used to enable the dns server',
			'description' => [
				"This tool creates an alias for {yel}localhost{end}/{yel}127.0.0.1{end} on your machine which is",
				"addressable from your local machine and from inside docker containers. This is useful when wanting",
				"to connect xdebug from your software running inside a container, to your local machine where your",
				"IDE Is listening for incoming connections",
			],
			'options' => [
				"set <ip-address>: Set into the configuration the IP Address, this value will be remembered and used in the future",
				"get: Get the Currently configured IP Address.",
				"add: Add '{yel}<ip-address>{end}' as an ip alias for '{yel}127.0.0.1{end}'",
				"remove: Remove '{yel}<ip-address>{end}' from your computer",
				"reset: Remove and Add the configuration again, just in case it broke somehow",
				"ping: Ping the configured ip address",
			],
			'notes' => [
				"Please don't use '{yel}localhost{end}' or '{yel}127.0.0.1{end}'\n",
				"The problem is that inside a docker container, '{yel}localhost{end}' and '{yel}127.0.0.1{end}' resolves to itself.",
				"This means you have no ip address which is addressable from your local machine, or inside docker containers.",
				"** For this reason the tool will refuse to work with these addresses and fail **",
			],
		];
	}

	private function failOnInvalid(string $ipAddress): void
	{
		if(in_array($ipAddress, ['', 'localhost', '127.0.0.1', '127.001'])){
			$this->cli->failure("{red}The requested value of '$ipAddress' would lead to an invalid configuration, see notes in help{end}");
		}
	}

	public function set(string $ipAddress): void
	{
		$this->failOnInvalid($ipAddress);

		$this->cli->print("Writing IP Address '{yel}$ipAddress{end}': ");

		$this->config->set($ipAddress);

		if($this->config->set($ipAddress)){
			$this->cli->print("{grn}SUCCESS{end}\n");
		}else{
			$this->cli->failure("{red}FAILURE{end}\n");
		}
	}

	public function get(): string
	{
		return $this->config->get() . "\n";
	}

	public function add(): void
	{
		$ipAddress = $this->config->get();

		$this->failOnInvalid($ipAddress);

		$this->cli->sudo();
		$this->cli->print("Installing IP Address '{yel}$ipAddress{end}': ");
		
		if($this->ipService->set($ipAddress)){
			$this->cli->print("{grn}SUCCESS{end}\n");
		}else{
			$this->cli->print("{red}FAILURE{end}\n");
		}
	}

	public function remove(): void
	{
		$ipAddress = $this->config->get();

		$this->failOnInvalid($ipAddress);

		$this->cli->sudo();
		$this->cli->print("Uninstalling IP Address '{yel}$ipAddress{end}': ");

		if($this->ipService->remove($ipAddress)){
			$this->cli->print("{grn}SUCCESS{end}\n");
		}else{
			$this->cli->print("{red}FAILURE{end}\n");
		}
	}

	public function reset(): void
	{
		$this->cli->print("{blu}Resetting IP Address:{end}\n");
		$this->remove();
		$this->add();
	}

	public function ping(): string
	{
		$address = Address::instance($this->config->get());		
		$address->ping();
		
		return (string)$address;
	}
}
