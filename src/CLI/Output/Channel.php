<?php declare(strict_types=1);

namespace DDT\CLI\Output;

use DDT\Contract\ChannelInterface;

abstract class Channel implements ChannelInterface {
    private $name;
    private $enabled = true;
    private $parent = null;
    private $prefix = '';
    private $listeners = [];

    public function setName(string $name): void {
        $this->name = $name;
    }

    public function getName(): string {
        return $this->name;
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setPrefix(string $prefix): void 
    {
        $this->prefix = $prefix;
    }

    public function removePrefix(): void 
    {
        $this->prefix = '';
    }

    public function getParent(): ?ChannelInterface 
    {
        return $this->parent;
    }

    public function attach(ChannelInterface $channel): void 
    {
        $this->listeners[] = $channel;
    }

    public function unattach(ChannelInterface $channel): ChannelInterface 
    {
        $index = array_search($channel, $this->listeners);
        if($index !== false){
            unset($this->listeners[$index]);
            $this->listeners = array_values($this->listeners);
        }

        return $this;
    }

    abstract function write($string='', ?array $params=[]): string;

    protected function renderString($string, ?array $params = []): string
    {
        // coerce whatever string is, to an actual string
        if(is_object($string)) $string = "class(" . get_class($string) . ")";
        if(is_array($string)) $string = json_encode($string);
        if(is_scalar($string)) $string = "$string";
        if(!is_string($string)) $string = '';
        if(empty($string)) $string = '';

        // If there are parameters to render into the string
        $string = $this->renderParams($string, $params);

        return $string;
    }

    protected function renderParams(string $string, ?array $params = []): string
    {
        if(!empty($params)){
            $string = sprintf($string, ...$params);
        }

        return $string;
    }

    protected function renderPrefix(string $string): string
    {
        if(!empty($this->prefix)) {
            $append = str_ends_with($string, "\n") ? "\n" : "";
            
            $string = implode("\n", array_map(function($str) {
                return $this->prefix . $str;
            }, explode("\n", trim($string)) + []));
            
            $string = $string . $append;
        }

        return $string;
    }

    protected function sendToListeners(string $string): string
    {
        foreach($this->listeners as $listener) {
            $string = $listener->write($string);
        }

        return $string;
    }
}