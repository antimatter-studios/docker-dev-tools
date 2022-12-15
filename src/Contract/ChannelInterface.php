<?php declare(strict_types=1);

namespace DDT\Contract;

interface ChannelInterface {
    public function setName(string $name): void;
    public function getName(): string;
    public function enable(): void;
    public function disable(): void;
    public function isEnabled(): bool;
    public function attach(ChannelInterface $channel): void;
    public function unattach(ChannelInterface $channel): ChannelInterface;
    public function write($string='', ?array $params=[]): string;
}
