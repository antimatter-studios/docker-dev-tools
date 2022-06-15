<?php
namespace DDT\Exceptions\Tool;

class ToolNotSpecifiedException extends ToolException
{
    public function __construct(int $code = 0, \Throwable $previous=null)
    {
        parent::__construct("There was no tool specified to run", $code, $previous);
    }
}
