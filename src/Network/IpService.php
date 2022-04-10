<?php declare(strict_types=1);

namespace DDT\Network;

use DDT\CLI;
use DDT\Contract\IpServiceInterface;
use DDT\Methods\IP\MacOsIfConfigMethod;
use DDT\Methods\IP\IpMethod;
use DDT\Methods\IP\IfconfigMethod;

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
        if(MacOsIfConfigMethod::supported($this->cli)){
            return container(MacOsIfConfigMethod::class);
        }

        if(IpMethod::supported($this->cli)){
            return container(IpMethod::class);
        }

        if(IfconfigMethod::supported($this->cli)){
            return container(IfconfigMethod::class);
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