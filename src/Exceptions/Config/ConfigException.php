<?php declare(strict_types=1);

namespace DDT\Exceptions\Config;

class ConfigException extends \Exception
{
    public function __construct(string $message = null, int $code = 0, \Throwable $previous=null)
    {
        parent::__construct($message ?? "A generic configuration exception occurred", $code, $previous);
    }
}