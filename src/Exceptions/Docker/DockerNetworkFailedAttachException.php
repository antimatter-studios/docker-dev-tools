<?php declare(strict_types=1);

namespace DDT\Exceptions\Docker;

class DockerNetworkFailedAttachException extends DockerException
{
    public function __construct(string $network, string $containerId, int $code = 0, \Throwable $previous = null)
    {
        parent::__construct("Docker network '$network' failed to attach to container id '$containerId'", $code, $previous);
    }
}