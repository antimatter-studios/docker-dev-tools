<?php declare(strict_types=1);

namespace DDT\CLI\Output;

class TerminalChannel extends Channel
{
    public function __construct()
    {
        parent::__construct('terminal');
    }

    public function stdout(?string $string='', ?array $params=[]): string
    {
        if($this->status()){
            fwrite(STDOUT, $this->process($string, $params));
        }

        return $string;
    }

    public function stderr(?string $string='', ?array $params=[]): string
    {
        if($this->status()){
            fwrite(STDERR, $this->process($string, $params));
        }

        return $string;
    }

    public function write(?string $string='', ?array $params=[]): string
    {
        return $this->stdout($string, $params);
    }
}