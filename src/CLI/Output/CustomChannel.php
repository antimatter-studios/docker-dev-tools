<?php declare(strict_types=1);

namespace DDT\CLI\Output;

use DDT\Contract\ChannelInterface;

class CustomChannel extends Channel
{
    private $parent;
    
    public function __construct(ChannelInterface $parent, string $name, ?bool $enabled=true)
    {
        parent::__construct($name, $enabled);

        $this->parent = $parent;
    }

    public function write($string='', ?array $params=[]): string
    {
        if($this->status()){
            return $this->parent->write($string);
        }else{
            return $this->record($string);
        }
    }
}