<?php declare(strict_types=1);

namespace DDT\Contract;

interface ChannelInterface
{
    public function getName(): string;
    public function enable(bool $state);
    public function status(): bool;
    public function setLast(string $string);
    public function getLast(): string;
    public function tap(bool $state);
    public function record(string $string);
    public function history(): array;
    public function write(string $text): string;
}