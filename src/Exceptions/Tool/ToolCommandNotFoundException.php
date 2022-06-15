<?php declare(strict_types=1);

namespace DDT\Exceptions\Tool;

class ToolCommandNotFoundException extends ToolException
{
    public function __construct($tool, $command, $code = 0, \Throwable $previous = null)
    {
        parent::__construct("The tool '$tool' does not support this command '$command'", $code, $previous);
    }
}