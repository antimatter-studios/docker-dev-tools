<?php declare(strict_types=1);

namespace DDT\CLI\Output;

use DDT\Text\Text;

class StdoutChannel extends Channel {
    private $textRenderer;
    
    public function __construct(Text $textRenderer) 
    {
        $this->setName('stdout');

        $this->textRenderer = $textRenderer;
    }

    public function write($string='', ?array $params=[]): string
    {
        if($this->isEnabled()) {
            // First, render the passed data to a string using various ways to convert objects, arrays, scalars, etc
            $string = $this->renderString($string, $params);

            // If any, render a prefix to start each string
            $string = $this->renderPrefix($string);

            // Terminals can resolve colours, etc
            $string = $this->textRenderer->write($string);

            // write to the correct IO channel
            fwrite(STDOUT, $string);

            // Send out the string to every listener attached
            $string = $this->sendToListeners($string);
        }

        return $string;
    }
}