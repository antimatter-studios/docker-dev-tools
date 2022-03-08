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
        if(is_object($string)) $string = get_class($string);
        if(is_array($string)) $string = json_encode($string);
        if(!is_string($string)) $string = '';
        if(empty($string)) $string = '';

        return $this->process($string, $params);
    }
}