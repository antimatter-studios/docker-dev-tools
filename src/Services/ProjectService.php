<?php declare(strict_types=1);

namespace DDT\Services;

use DDT\CLI;
use DDT\Config\ProjectConfig;

class ProjectService
{
    /** @var CLI */
    private $cli;

    /** @var ProjectConfig */
    private $config;

    public function __construct(CLI $cli, ProjectConfig $config)
    {
        $this->cli = $cli;
        $this->config = $config;
    }

    public function list(): array
    {
        return $this->config->listProjects();   
    }

    public function addGroup(string $project, string $group, ?string $path=null): bool
    {
        return $this->config->addGroup($project, $group, $path);
    }

    public function removeGroup(string $project, string $group, ?string $path=null): bool
    {
        return $this->config->removeGroup($project, $group, $path);
    }

    public function addProject(string $path, ?string $name=null, ?string $group=null): bool
    {
        return $this->config->addProject($path, $name, $group);
    }

    public function removeProject(string $project, ?string $path=null): bool
    {
        return $this->config->removeProject($project, $path);
    }
}