<?php declare(strict_types=1);

namespace DDT\CLI\Output;

use DDT\Contract\ChannelInterface;
use DDT\Text\Text;

class DebugChannel extends Channel
{
    private $parent;
    private $renderer;
    
    public function __construct(ChannelInterface $parent, Text $renderer)
    {
        parent::__construct('debug', false);

        $this->parent = $parent;
        $this->renderer = $renderer;
    }

    public function write(?string $string='', ?array $params=[]): string
    {
        $string = $this->coerceToString($string);

        $string = $this->renderer->write('{blu}[DEBUG]:{end} ' . $string);
        $string = !empty($params) ? sprintf($string, ...$params) : $string;
		$string = trim($string) . "\n";

        if($this->status()){
            return $this->parent->write($string);
        }else{
            return $this->record($string);
        }
    }
}