<?php declare(strict_types=1);

namespace DDT\Methods\MacOs\IP;

use DDT\CLI;

class IfconfigMethod
{
    /** @var CLI */
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

        if(!$cli->isCommand('iconfig')){
			return false;
		}

		return true;
    }

    public function add(string $ipAddress): bool
	{
		try{
			if(!empty($ipAddress)){
				$this->cli->sudo("ifconfig lo0 alias $ipAddress");
				return true;
			}
		}catch(\Exception $e){
            $this->cli->debug("ip service", $e->getMessage());
        }

		return false;
	}

	public function remove(string $ipAddress): bool
	{
		try{
			if(in_array($ipAddress, ['127.001', '127.0.0.1'])){
				return false;
			}

			if(!empty($ipAddress)){
				$this->cli->sudo("ifconfig lo0 $ipAddress delete &>/dev/null");
				return true;
			}
		}catch(\Exception $e){
            $this->cli->debug("ip service", $e->getMessage());
        }

		return false;
	}	
}