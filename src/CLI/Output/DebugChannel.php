<?php declare(strict_types=1);

namespace DDT\CLI\Output;

use DDT\Contract\ChannelInterface;

class DebugChannel extends Channel
{
    public function __construct(ChannelInterface $parent)
    {
        $this->setName('debug');
        $this->attach($parent);
        $this->setPrefix('{blu}[DEBUG]:{end} ');
    }

    public function write($string='', ?array $params=[]): string
    {
        if($this->isEnabled()){
            // Make sure the string ends with a newline in all cases
            $string = trim($string) . "\n";

            // First, render the passed data to a string using various ways to convert objects, arrays, scalars, etc
            $string = $this->renderString($string, $params);

            // If any, render a prefix to start each string
            $string = $this->renderPrefix($string);

            // Send out the string to every listener attached
            $string = $this->sendToListeners($string);
        }

        return $string;
    }
}