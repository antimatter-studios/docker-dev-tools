<?php declare(strict_types=1);

namespace DDT\Methods\IP;

use DDT\CLI;

class IpMethod
{
    /** @var CLI */
    private $cli;

    public function __construct(CLI $cli)
    {
        $this->cli = $cli;
    }

    static public function supported(CLI $cli): bool 
    {
        return $cli->isCommand('ip');
    }
    
    public function add(string $ipAddress): bool
    {
        try{
			if(!empty($ipAddress)){
                $this->cli->sudo("ip addr add $ipAddress/24 dev lo label lo:40");
                return true;
            }
		}catch(\Exception $e){
            $this->cli->debug("ip command", $e->getMessage());
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
				$this->cli->sudo("ip addr del $ipAddress/24 dev lo");
				return true;
			}
		}catch(\Exception $e){
            $this->cli->debug("ip command", $e->getMessage());
        }

		return false;
    }
}