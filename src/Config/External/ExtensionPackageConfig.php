<?php declare(strict_types=1);

namespace DDT\Config\External;

use DDT\Config\JsonConfig;

class ExtensionPackageConfig extends JsonConfig
{
    const defautFilename = 'ddt-extension.json';

    public function getDefaultFilename(): string
    {
        return self::defautFilename;
    }

    static public function instance(string $filename, ?bool $readonly=false): self
    {
        return container(self::class, ['filename' => $filename, 'readonly' => $readonly]);
    }

    public function getTest(): string
    {
        return $this->getKey('.test') ?? 'echo no test specified';
    }
}