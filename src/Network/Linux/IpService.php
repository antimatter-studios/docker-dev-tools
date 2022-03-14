<?php declare(strict_types=1);

namespace DDT\Network\Linux;

use DDT\CLI;
use DDT\Contract\IpServiceInterface;

class IpService implements IpServiceInterface
{
    /** @var CLI */
    private $cli;

    public function __construct(CLI $cli)
    {
        $this->cli = $cli;
    }

    public function set(string $ipAddress): bool
    {
        try{
			if(!empty($ipAddress)){
                $this->cli->sudo("ip addr add $ipAddress/24 dev lo label lo:40");
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
				$this->cli->sudo("ip addr del $ipAddress/24 dev lo");
				return true;
			}
		}catch(\Exception $e){
            $this->cli->debug("ip service", $e->getMessage());
        }

		return false;
    }
}