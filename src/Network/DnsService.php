<?php declare(strict_types=1);

namespace DDT\Network;

use DDT\CLI;
use DDT\Contract\DnsServiceInterface;
use DDT\Methods\MacOs\DNS\NetworkSetupMethod;
use DDT\Methods\Linux\DNS\ResolvConfFileMethod;
use DDT\Methods\Linux\DNS\SystemdResolvedMethod;

// apt-get install network-manager

class DnsService implements DnsServiceInterface
{
    /** @var CLI */
    private $cli;

    public function __construct(CLI $cli)
    {
        $this->cli = $cli;
    }

    private function getSupportedMethod()
    {
        if(NetworkSetupMethod::supported($this->cli)){
            return container(MacOsDnsMethod::class);
        }

        if(SystemdResolvedMethod::supported($this->cli)){
            return container(SystemdResolvedMethod::class);
        }

        if(ResolvConfFileMethod::supported($this->cli)){
            return container(ResolvConfFileMethod::class);
        }

        throw new \Exception("No supported dns configuration method found");
    }

    public function listIpAddress(): array
    {   
        $method = $this->getSupportedMethod();
        return $method->get();
    }

    public function enable(string $ipAddress): bool
    {
        $method = $this->getSupportedMethod();
        return $method->add($ipAddress);
    }

    public function disable(string $ipAddress): bool
    {
        $method = $this->getSupportedMethod();
        return $method->remove($ipAddress);
    }

    public function flush(): void
    {
        throw new \Exception("Implement method: " . __METHOD__);
    }
}
