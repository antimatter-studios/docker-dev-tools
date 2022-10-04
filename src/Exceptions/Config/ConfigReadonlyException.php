<?php declare(strict_types=1);

namespace DDT\Exceptions\Config;

class ConfigReadonlyException extends ConfigException
{
    public function __construct(string $message = null, int $code = 0, \Throwable $previous=null) {
        parent::__construct($message ?? "The Configuration is readonly", $code, $previous);
    }
}