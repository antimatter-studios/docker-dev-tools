<?php declare(strict_types=1);

namespace DDT\Contract;

interface ChannelInterface
{
    public function write(string $text): string;
}