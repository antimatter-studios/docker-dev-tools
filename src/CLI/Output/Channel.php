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
        $this->tap = false;
        $this->enabled = $enabled;
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

    public function tap()
    {
        $this->tap = true;
        $this->history = [];
    }

    public function record(string $string)
    {
        if($this->enabled && $this->tap){
            $this->history[] = $string;
        }
    }

    public function history(): array
    {
        return $this->history;
    }

    public function write(?string $string='', ?array $params=[]): string
    {
        if(empty($string)) $string = '';

        $string = !empty($params) ? sprintf($string, ...$params) : $string;

        $this->setLast($string);
        $this->record($string);

        return $string;
    }
}