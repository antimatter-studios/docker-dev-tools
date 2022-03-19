<?php declare(strict_types=1);

namespace DDT\Exceptions\Docker;

class DockerInspectException extends \Exception
{
    public function __construct(string $type, string $name, int $code = 0, \Throwable $previous = null)
    {
        $message = $previous ? trim($previous->getMessage()) : 'no message given';
        parent::__construct("Docker could not find the '$type' resource named '$name' to inspect ($message)", $code, $previous);
    }
}