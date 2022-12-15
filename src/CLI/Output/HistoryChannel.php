<?php declare(strict_types=1);

namespace DDT\CLI\Output;

use DDT\Contract\HistoryChannelInterface;
use DDT\Model\CLI\Output\HistoryItem;

class HistoryChannel extends Channel implements HistoryChannelInterface {
    private $history = [];
    private $enabled = true;

    public function __construct() 
    {
        $this->setName('history');
    }

    public function enableHistory(): void 
    {
        $this->enabled = true;
    }

    public function disableHistory(): void
    {
        $this->enabled = false;
    }

    public function isHistoryEnabled(): bool
    {
        return $this->enabled;
    }

    public function write($string='', ?array $params=[]): string 
    {
        if($this->isEnabled()) {
            // Just record what you were given
            $this->history[] = new HistoryItem($string, $params);
        }

        return $string;
    }

    /**
     * Get the history as a plain array of records
     *
     * @param boolean $clear
     * @return array
     */
    public function get(bool $clear = false): array {
        return $this->history;
    }

    /**
     * Render each string before returning it using the parameters when it was added to the history
     *
     * @return void
     */
    public function getRendered(): array {
        return array_map(function($item) {
            return $this->renderString($item->string, $item->params);
        }, $this->get());
    }

    /**
     * Clear the history of previous information
     *
     * @return void
     */
    public function clear(): void {
        $this->history = [];
    }
}