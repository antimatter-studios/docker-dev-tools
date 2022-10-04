<?php declare(strict_types=1);

namespace DDT\Exceptions\Config;

class ConfigInvalidException extends ConfigException
{
    public function __construct(string $message, int $code = 0, \Throwable $previous=null)
    {
        parent::__construct("The Configuration was invalid: $message", $code, $previous);
    }
}