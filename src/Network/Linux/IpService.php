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
        if($this->cli->isCommand('ip')){
            $manager = container(IpCommand::class);
            return $manager->set($ipAddress);
        }else if($this->cli->isCommand('ifconfig')){
            $manager = container(IfconfigCommand::class);
            return $manager->set($ipAddress);
        }

        throw new \Exception("No supported linux ip configuration tool found");
    }

    public function remove(string $ipAddress): bool
    {
        if($this->cli->isCommand('ip')){
            $manager = container(IpCommand::class);
            return $manager->remove($ipAddress);
        }else if($this->cli->isCommand('ifconfig')){
            $manager = container(IfconfigCommand::class);
            return $manager->remove($ipAddress);
        }

        throw new \Exception("No supported linux ip configuration tool found");
    }
}