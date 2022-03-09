<?php declare(strict_types=1);

namespace DDT\CLI\Output;

class StringChannel extends Channel
{    
    public function __construct()
    {
        parent::__construct('string');
        
        $this->tap(true);
    }

    public function write($string='', ?array $params=[]): string
    {
        $string = $this->coerceToString($string);

        $string = !empty($params) ? sprintf($string, ...$params) : $string;

        return $this->record($string);
    }
}