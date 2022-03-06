<?php declare(strict_types=1);

namespace DDT\Exceptions\CLI;

class AskResponseRejectedException extends \Exception 
{
    public function __construct($message, $answer, $accept, ?int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}