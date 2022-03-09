<?php declare(strict_types=1);

namespace DDT\CLI\Output;

use DDT\Contract\ChannelInterface;
use DDT\Text\Text;

class StdoutChannel extends Channel
{
    private $parent;
    private $renderer;
    
    public function __construct(TerminalChannel $parent, Text $renderer, ?string $name=null, ?bool $enabled=true)
    {
        parent::__construct($name ?? 'stdout', $enabled);

        $this->parent = $parent;
        $this->renderer = $renderer;
    }

    public function write($string='', ?array $params=[]): string
    {
        $string = $this->coerceToString($string);

		$string = !empty($params) ? sprintf($string, ...$params) : $string;
        $string = $this->renderer->write($string);

        if($this->status()){
            return $this->parent->stdout($string);
        }else{
            return $this->record($string);
        }
    }
}