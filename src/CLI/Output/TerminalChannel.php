<?php declare(strict_types=1);

namespace DDT\CLI\Output;

class TerminalChannel extends Channel
{
    public function __construct()
    {
        parent::__construct('terminal');
    }

    public function stdout(?string $string=''): string
    {
        if($this->status()){
            fwrite(STDOUT, $string);
        }

        return $string;
    }

    public function stderr(?string $string=''): string
    {
        if($this->status()){
            fwrite(STDERR, $string);
        }

        return $string;
    }

    public function write(?string $string=''): string
    {
        return $this->stdout($string);
    }
}