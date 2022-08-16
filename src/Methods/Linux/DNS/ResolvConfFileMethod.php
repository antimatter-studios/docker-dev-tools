<?php declare(strict_types=1);

namespace DDT\Methods\Linux\DNS;

use DDT\CLI;

class ResolvConfFileMethod
{
    static private $file = '/etc/resolv.conf';

    static public function supported(CLI $cli): bool
    {
        return file_exists(self::$file);
    }

    private function getFileContents(): array
    {
        $contents = file_get_contents(self::$file);

        return explode("\n", $contents);
    }

    /**
     * Get a list of dns servers this machine is configured to use
     *
     * @todo we should make this dynamic. I don't know how to query systemd to get this information yet
     * @return array
     */
    public function get(): array
    {
        $upstreamList = array_filter($this->getFileContents(), function($line) {
            return strpos($line, 'nameserver') !== false;
        });

        $upstreamList = array_map(function($line) {
            return trim(str_replace('nameserver', '', $line));
        }, $upstreamList);

        return array_values($upstreamList);
    }

    public function add(string $ipAddress): bool
    {
        $contents = $this->getFileContents();

        foreach($contents as $line){
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