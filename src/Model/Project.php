<?php declare(strict_types=1);

namespace DDT\Model;

class Project
{
    private $name;
    private $type;
    private $path;
    private $group;
    private $vcsUrl;
    private $vcsRemote;

    public function __construct(string $name, string $type, string $path, array $group, string $vcsUrl, string $vcsRemote)
    {
        $this->setName($name);
        $this->setType($type);
        $this->setPath($path);
        $this->setGroup($group);
        $this->setVcsUrl($vcsUrl);
        $this->setVcsRemote($vcsRemote);
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function setGroup(array $groupList): self
    {
        $this->groupList = $groupList;
        return $this;
    }

    public function addGroup(string $groupName): self
    {
        $this->groupList[] = $groupName;
        return $this;
    }

    public function removeGroup(string $groupName): self
    {
        $this->groupList = array_filter($this->groupList, function($v) use ($groupName) {
            return $v !== $groupName;
        });

        return $this;
    }

    public function hasGroup(string $groupName): bool
    {
        return in_array($groupName, $this->toArray());
    }

    public function setVcsUrl(string $vcsUrl): self
    {
        $this->vcsUrl = $vcsUrl;
        return $this;
    }

    public function setVcsRemote(string $vcsRemote): self
    {
        $this->vcsRemote = $vcsRemote;
        return $this;
    }

    public function toArray(): array
    {
        return [
            "name" => $this->name,
            "type" => $this->type,
            "path" => $this->path,
            "group" => $this->group,
            "vcsUrl" => $this->vcsUrl,
            "vcsRemote" => $this->vcsRemote,
        ];
    }
}