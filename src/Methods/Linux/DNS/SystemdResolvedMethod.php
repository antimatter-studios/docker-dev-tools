<?php declare(strict_types=1);

namespace DDT\Methods\Linux\DNS;

use DDT\CLI;

class SystemdResolvedMethod
{
    /** @var CLI $cli */
    private $cli;

    public function __construct(CLI $cli)
    {
        $this->cli = $cli;
    }

    static public function supported(CLI $cli): bool
    {
        return $cli->isCommand('systemd-resolve');
    }

    /**
     * Get a list of dns servers this machine is configured to use
     *
     * @todo we should make this dynamic. I don't know how to query systemd to get this information yet
     * @return array
     */
    public function get(): array
    {
        return ['1.1.1.1'];
    }

    public function add(string $ipAddress): bool
    {
        $this->cli->print("{blu}DNS:{end} Writing new DNS Configuration\n");
        $this->cli->sudo('sed -i "s/^[#]\?DNS=.*\?/DNS=' . $ipAddress . '/i" /etc/systemd/resolved.conf');
        $this->cli->sudo('sed -i "s/^[#]\?FallbackDNS=.*\?/FallbackDNS=' . implode(',', $this->get()) . '/i" /etc/systemd/resolved.conf');
        //$this->cli->sudo('sed -i "s/^[#]\?DNSStubListener=.*\?/DNSStubListener=no/i" /etc/systemd/resolved.conf');
        //$this->cli->exec('sudo ln -sf /run/systemd/resolve/resolv.conf /etc/resolv.conf');
        
        $this->cli->print("{blu}DNS:{end} Restarting 'systemd-resolved' to set DNS to use local DNS server\n");
        $this->cli->sudo('systemctl restart systemd-resolved');

        if(!$this->cli->getExitCode()){
            return false;
        }

        return true;
    }

    public function remove(string $ipAddress): bool
    {
        $this->cli->print("{blu}DNS:{end} Resetting DNS Configuration back to sensible defaults\n");
        $this->cli->sudo('sed -i "s/^DNS=.*\?/#DNS=/i" /etc/systemd/resolved.conf');
        $this->cli->sudo('sed -i "s/^FallbackDNS=.*\?/#FallbackDNS=/i" /etc/systemd/resolved.conf');
        // $this->cli->sudo('sed -i "s/^DNSStubListener=.*\?/#DNSStubListener=yes/i" /etc/systemd/resolved.conf');

        $this->cli->print("{blu}DNS:{end} Restarting 'systemd-resolved' to set DNS to use default resolver\n");
        $this->cli->sudo("systemctl restart systemd-resolved");

        // TODO: decide what I wanna do here, I'm not sure yet
        if(!$this->cli->getExitCode()){
            return false;
        }

        return true;
    }
}