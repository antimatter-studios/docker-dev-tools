<?php declare(strict_types=1);

namespace DDT\Contract;

use DDT\Tool\Tool;

interface ToolRegistryInterface
{
    public function getTool(string $name): Tool;
}