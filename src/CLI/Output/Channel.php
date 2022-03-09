<?php declare(strict_types=1);

namespace DDT\CLI\Output;

use DDT\Contract\ChannelInterface;

abstract class Channel implements ChannelInterface
{
    private $name;
    private $last = '';
    private $tap;
    private $enabled;
    private $history = [];

    public function __construct(string $name, bool $enabled=true)
    {
        $this->name = $name;
        $this->tap(false);
        $this->enable($enabled);
    }

    public function setParent(ChannelInterface $parent)
    {
        $this->parent = $parent;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function enable(bool $state)
    {
        $this->enabled = $state;
    }

    public function status(): bool
    {
        return $this->enabled;
    }

    public function setLast(string $string)
    {
        $this->last = $string;
    }

    public function getLast(): string
    {
        return $this->last;
    }

    public function tap(bool $state)
    {
        $this->tap = $state;
        $this->history = [];
    }

    public function record(string $string)
    {
        $this->setLast($string);

        if($this->tap){
            $this->history[] = trim($string);
        }

        return $string;
    }

    public function history(): array
    {
        return $this->history;
    }

    protected function coerceToString($string): string
    {
        if(is_object($string)) $string = get_class($string);
        if(is_array($string)) $string = json_encode($string);
        if(!is_string($string)) $string = '';
        if(empty($string)) $string = '';

        return $string;
    }
}