<?php declare(strict_types=1);

namespace DDT\Network\Linux;

use DDT\CLI;

class IfconfigCommand
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
                $this->cli->sudo("ifconfig lo:0 $ipAddress up");
                return true;
            }
		}catch(\Exception $e){
            $this->cli->debug("ifconfig command", $e->getMessage());
        }

        return false;
    }

    public function remove(string $ipAddress): bool
    {
        try{
			if(!empty($ipAddress)){
				$this->cli->sudo("ifconfig lo:0 $ipAddress down");
				return true;
			}
		}catch(\Exception $e){
            $this->cli->debug("ifconfig command", $e->getMessage());
        }

		return false;
    }
}