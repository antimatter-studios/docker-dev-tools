<?php declare(strict_types=1);

namespace DDT\CLI\Output;

class CustomChannel extends HistoryChannel {
    private $in = [];
    private $out = [];

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

            // Pass the string through the filters
            $string = $this->filterString($string);

            // If the string is empty now, it didn't pass the filters, 
            // or the string after rendering and filtering is empty
            // in this case, we have nothing to process further, so quit early
            if(empty($string)) return $string;

            // If any, render a prefix to start each string
            $string = $this->renderPrefix($string);

            // Write it to the history parent
            $string = parent::write($string, $params);

            // Send out the string to every listener attached
            $string = $this->sendToListeners($string);
        }

        return $string;
    }

    public function filterIn(string $string): void
    {
        $this->in[] = $string;
    }

    public function filterOut(string $string): void 
    {
        $this->out[] = $string;
    }

    private function filterString(string $string): string
    {
        $string = array_reduce($this->in, function($a, $i){
            return !empty($a) && strpos($a, $i) !== false ? $a : '';
        }, $string);

        $string = array_reduce($this->out, function($a, $i) {
            return !empty($a) && strpos($a, $i) === false ? $a : '';
        }, $string);

        return $string;
    }
}