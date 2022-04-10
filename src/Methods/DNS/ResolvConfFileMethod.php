<?php declare(strict_types=1);

namespace DDT\Methods\DNS;

class ResolvConfFileMethod
{
    static private $file = '/etc/resolv.conf';

    static public function supported(CLI $cli): bool
    {
        return file_exists(self::$file);
    }

    public function add(string $ipAddress): bool
    {
        $contents = file_get_contents($this->file);

        foreach(explode("\n", $contents) as $line){
            if(strpos($line, $ipAddress) !== false) {
                return true;
            }
        }

        $contents = implode("\n", ["nameserver $ipAddress", $contents]) . "\n";

        // FIXME: I am not sure I can do this if I'm not root, 
        // FIXME: and I'm not sure if sudo allows php scripts to write to root owned files
        return file_put_contents(self::$file, $contents) !== false;
    }

    public function remove(string $ipAddress): bool
    {
        return false;
    }
}