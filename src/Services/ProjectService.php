<?php declare(strict_types=1);

namespace DDT\Services;

use DDT\Config\Sections\ProjectConfig;
use DDT\Contract\ModelInterface;
use DDT\Model\Project\ProjectListModel;
use DDT\Model\Project\ProjectPathListModel;

class ProjectService
{
    /** @var ProjectConfig */
    private $config;

    public function __construct(ProjectConfig $config)
    {
        $this->config = $config;
    }

    public function listProjects(int $mode=ProjectConfig::LIST_ALL): ProjectListModel
    {
        return $this->config->listProjects($mode);
    }

    public function listProjectsByFilter(array $filter): ProjectListModel
    {
        return $this->listProjects()->listProjectsByFilter($filter);
    }

    public function listProjectsByScript(string $script): ProjectListModel
    {
        return $this->listProjects()->listProjectsByScript($script);
    }

    public function findProject(string $name, ?string $path=null, ?string $group=null): ModelInterface
    {
        return $this->listProjects()->findProject($name, $path, $group);
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

    public function addPath(string $path, ?string $group=null): bool
    {
        return $this->config->addPath($path, $group);
    }

    public function removePath(string $path): bool
    {
        return $this->config->removePath($path);
    }

    public function listPaths(): ProjectPathListModel
    {
        return $this->config->listPaths();
    }
}