<?php declare(strict_types=1);

namespace DDT\Contract\Project;

use DDT\Model\Project\ProjectGroupModel;

interface ProjectConfigInterface
{
	public function getGroup(): ProjectGroupModel;
	public function getProject(): string;
	static public function getDefaultFilename(): string;
	public function write(?string $filename=null): bool;
	public function getPath(): string;
	public function listScripts(): array;
	public function getScript(string $name);
	public function getDependencies(string $script): array;
}