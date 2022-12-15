<?php declare(strict_types=1);

namespace DDT\Contract;

interface HistoryChannelInterface {
    /**
     * Get the history as a plain array of records
     *
     * @param boolean $clear
     * @return array
     */
    public function get(bool $clear = false): array;

    /**
     * Render each string before returning it using the parameters when it was added to the history
     *
     * @return array
     */
    public function getRendered(): array;

    /**
     * Clear the history of previous information
     *
     * @return void
     */
    public function clear(): void;
}
