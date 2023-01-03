<?php declare(strict_types=1);

namespace DDT\Contract;

interface ExecInterface
{
    public function exec(string $command, ?ChannelInterface $stdout=null, ?ChannelInterface $stderr=null);
    public function passthru(string $command, bool $throw=true): int;
    public function getExitCode(): int;
}