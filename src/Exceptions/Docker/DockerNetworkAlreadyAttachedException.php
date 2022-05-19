<?php declare(strict_types=1);

namespace DDT\Exceptions\Docker;

class DockerNetworkAlreadyAttachedException extends DockerException
{
    public function __construct(string $network, string $containerId, int $code = 0, \Throwable $previous = null)
    {
        parent::__construct("Docker network '$network' is already with container id '$containerId', cannot attach it again", $code, $previous);
    }
}