<?php declare(strict_types=1);

namespace DDT\Model;

use DDT\Config\External\ComposerProjectConfig;
use DDT\Config\External\NodeProjectConfig;
use DDT\Config\External\StandardProjectConfig;
use JsonSerializable;

class Project implements JsonSerializable
{
    private $name;
    private $path;
    private $group;

    public function __construct(string $path, ?string $name=null, ?array $group=[])
    {
        $this->setPath($path);
        $this->setName($name ?? basename($path));
        $this->setGroup($group);
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

    public function setGroup(array $groupList): self
    {
        $this->group = $groupList;
        return $this;
    }

    public function addGroup(string $groupName): self
    {
        $this->group[] = $groupName;
        return $this;
    }

    public function removeGroup(string $groupName): self
    {
        $this->group = array_filter($this->groupList, function($v) use ($groupName) {
            return $v !== $groupName;
        });

        return $this;
    }

    public function hasGroup(string $groupName): bool
    {
        return in_array($groupName, $this->group);
    }

    public function getGroups(): array
    {
        return $this->group;
    }

    static public function fromArray(array $data): Project
    {
        $path   = array_key_exists('path', $data) ? $data['path'] : null;
        $name   = array_key_exists('name', $data) ? $data['name'] : null;
        $group  = array_key_exists('group', $data) ? $data['group'] : null;

        return new Project($path, $name, $group);
    }

    public function toArray(): array
    {
        return [
            "name" => $this->getName(),
            "path" => $this->getPath(),
            "group" => $this->getGroups(),
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}