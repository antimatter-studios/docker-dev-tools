<?php declare(strict_types=1);

namespace DDT\Network;

use DDT\CLI;
use DDT\Contract\IpServiceInterface;
use DDT\Methods\MacOs\IP\IfconfigMethod as MacIfconfigMethod;
use DDT\Methods\Linux\IP\IfconfigMethod as LinuxIfconfigMethod;
use DDT\Methods\Linux\IP\IpMethod;

class IpService implements IpServiceInterface
{
    /** @var CLI */
    private $cli;

    public function __construct(CLI $cli)
    {
        $this->cli = $cli;
    }

    private function getSupportedMethod()
    {
        if(MacIfconfigMethod::supported($this->cli)){
            return container(MacIfconfigMethod::class);
        }

        if(IpMethod::supported($this->cli)){
            return container(IpMethod::class);
        }

        if(LinuxIfconfigMethod::supported($this->cli)){
            return container(LinuxIfconfigMethod::class);
        }

        throw new \Exception("No supported ip configuration method found");
    }

    public function set(string $ipAddress): bool
    {
        $method = $this->getSupportedMethod();
        return $method->add($ipAddress);
    }

    public function remove(string $ipAddress): bool
    {
        $method = $this->getSupportedMethod();
        return $method->remove($ipAddress);
    }
}