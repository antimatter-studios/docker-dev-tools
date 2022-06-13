<?php declare(strict_types=1);

namespace DDT\Config\Project;

use DDT\Config\JsonConfig;

class ExtensionProjectConfig extends JsonConfig
{
    const defaultFilename = 'ddt-extension.json';

    static public function getDefaultFilename(): string
    {
        return self::defaultFilename;
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