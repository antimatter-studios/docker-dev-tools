<?php declare(strict_types=1);

namespace DDT\CLI\Output;

class PrintChannel extends Channel
{
    public function __construct()
    {
        parent::__construct('print');
    }

    public function write(?string $string='', ?array $params=[]): string
    {
        if($this->status()){
            print(parent::write($string, $params));
        }

        return $string;
    }
}