<?php declare(strict_types=1);

namespace DDT\Model\Project;

use DDT\Config\External\ComposerProjectConfig;
use DDT\Config\External\NodeProjectConfig;
use DDT\Config\External\StandardProjectConfig;
use DDT\Model\Model;

class ProjectModel extends Model
{
    private $name;
    private $path;
    private $group;

    public function __construct(string $path, ?string $name=null, ?ProjectGroupModel $group=null)
    {
        $this->setPath($path);
        $this->setName($name ?? basename($path));
        $this->setGroup($group ?? new ProjectGroupModel([]));
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        $path = $this->getPath();

        $hasComposerJson = file_exists("$path/" . ComposerProjectConfig::defaultFilename);
        $hasPackageJson = file_exists("$path/" . NodeProjectConfig::defaultFilename);
        $hasDefault = file_exists("$path/" . StandardProjectConfig::defaultFilename);

        if($hasDefault) {
            $type = 'ddt';
        }else if($hasComposerJson && $hasPackageJson){
            $type = 'composer';
        }else if($hasComposerJson){
            $type = 'composer';
        }else if($hasPackageJson){
            $type = 'node';
        }else{
            $type = 'none';
        }

        return $type;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setGroup(ProjectGroupModel $group): self
    {
        $this->group = $group;
        return $this;
    }

    public function addGroup(string $name): self
    {
        $this->group = $this->group->add($name);
        return $this;
    }

    public function removeGroup(string $name): self
    {
        $this->group = $this->group->remove($name);
        return $this;
    }

    public function hasGroup(string $name): bool
    {
        return $this->group->has($name);
    }

    public function getGroups(): ProjectGroupModel
    {
        return $this->group;
    }

    static public function fromArray(array $data): self
    {
        $path   = array_key_exists('path', $data) ? $data['path'] : null;
        $name   = array_key_exists('name', $data) ? $data['name'] : null;
        $group  = array_key_exists('group', $data) ? $data['group'] : null;

        if($group !== null){
            $group = new ProjectGroupModel($group);
        }

        return new self($path, $name, $group);
    }

    public function getData()
    {
        return [
            'name' => $this->getName(),
            'path' => $this->getPath(),
            'group' => $this->getGroups()->getData(),
            //'type' => $this->getType(),
            //'repo_url' => ... something to get repo url
        ];
    }
}
