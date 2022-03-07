<?php declare(strict_types=1);

namespace DDT\CLI\Output;

use DDT\Contract\ChannelInterface;
use DDT\Text\Text;

class StdoutChannel extends Channel
{
    private $parent;
    private $renderer;
    
    public function __construct(ChannelInterface $parent, Text $renderer)
    {
        parent::__construct('stdout');

        $this->parent = $parent;
        $this->renderer = $renderer;
    }

    public function write($string='', ?array $params=[]): string
    {
        if(is_object($string)) $string = get_class($string);
        if(is_array($string)) $string = json_encode($string);
        if(!is_string($string)) $string = '';
        if(empty($string)) $string = '';

		$string = !empty($params) ? sprintf($string, ...$params) : $string;
        $string = $this->renderer->write($string);

        if($this->status()){
            return $this->parent->write($string);
        }else{
            return $string;
        }
    }
}