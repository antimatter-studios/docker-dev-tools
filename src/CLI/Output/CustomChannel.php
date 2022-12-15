<?php declare(strict_types=1);

namespace DDT\CLI\Output;

class CustomChannel extends HistoryChannel {
    public function __construct(?string $name = 'custom', bool $record=true)
    {
        $this->setName($name);

        if($record === false) {
            $this->disableHistory();
        }
    }

    public function write($string='', ?array $params=[]): string
    {
        if($this->isEnabled()) {
            // First, render the passed data to a string using various ways to convert objects, arrays, scalars, etc
            $string = $this->renderString($string, $params);

            // If any, render a prefix to start each string
            $string = $this->renderPrefix($string);

            // Write it to the history parent
            $string = parent::write($string, $params);

            // Send out the string to every listener attached
            $string = $this->sendToListeners($string);
        }

        return $string;
    }
}