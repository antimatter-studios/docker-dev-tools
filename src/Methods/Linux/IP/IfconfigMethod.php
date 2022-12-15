<?php declare(strict_types=1);

namespace DDT\Methods\Linux\IP;

use DDT\CLI\CLI;

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
        return $cli->isCommand('ifconfig');
    }

    public function add(string $ipAddress): bool
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