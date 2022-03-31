<?php declare(strict_types=1);

namespace DDT\Contract\External;

interface ProjectConfigInterface
{
	public function getGroup(): ?string;
	public function getProject(): string;
	public function getDefaultFilename(): string;
	public function write(?string $filename=null): bool;
	public function getPath(): string;
	public function listScripts(): array;
	public function getScript(string $name);
	public function getDependencies(string $script): array;
}