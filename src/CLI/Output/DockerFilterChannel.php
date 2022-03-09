<?php declare(strict_types=1);

namespace DDT\CLI\Output;

use DDT\Contract\ChannelInterface;

class DockerFilterChannel extends Channel
{
    public function __construct(ChannelInterface $stderr)
    {
        parent::__construct('docker_filter');
        $this->stderr = $stderr;
    }

    public function write($string = '', ?array $params = []): string
    {
        if(is_string($string) && strpos($string, "WARNING: The requested image") !== false){
            return $this->record($string);
        }

        return $this->stderr->write($string, $params);
    }
}
