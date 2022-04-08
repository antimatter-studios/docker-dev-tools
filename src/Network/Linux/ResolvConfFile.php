<?php declare(strict_types=1);

namespace DDT\Network\Linux;

class ResolvConfFile
{
    private $file = '/etc/resolv.conf';

    public function enable(string $ipAddress): bool
    {
        $contents = file_get_contents($this->file);

        foreach(explode("\n", $contents) as $line){
            if(strpos($line, $ipAddress) !== false) {
                return true;
            }
        }

        $contents = implode("\n", ["nameserver $ipAddress", $contents]) . "\n";

        return file_put_contents($this->file, $contents) !== false;
    }

    public function disable(string $ipAddress): bool
    {
        return false;
    }
}