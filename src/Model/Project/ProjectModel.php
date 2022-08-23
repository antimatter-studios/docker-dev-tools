<?php declare(strict_types=1);

namespace DDT\Model\Project;

use DDT\Config\Project\ComposerProjectConfig;
use DDT\Config\Project\NodeProjectConfig;
use DDT\Config\Project\StandardProjectConfig;
use DDT\Contract\Project\ProjectConfigInterface;
use DDT\Exceptions\Filesystem\DirectoryNotExistException;
use DDT\Exceptions\Project\ProjectNotFoundException;
use DDT\Model\Git\GitRepositoryModel;
use DDT\Model\Model;

class ProjectModel extends Model
{
    private $name;
    private $path;
    private $group;

    /**
     * @param string $path
     * @param string|null $name
     * @param ProjectGroupModel|null $group
     * @throws \Exception
     */
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

    public function getConfig(): ProjectConfigInterface
    {
        $type = $this->getType();
        $typeMap = ['ddt' => StandardProjectConfig::class, 'node' => NodeProjectConfig::class, 'composer' => ComposerProjectConfig::class];

        if(array_key_exists($type, $typeMap) && is_subclass_of($typeMap[$type], ProjectConfigInterface::class)){
            return $typeMap[$type]::fromPath($this->getPath(), $this->getName(), $this->getGroups());
        }

        $reason = "Project type '$type' does not match any allowed type";

        throw new ProjectNotFoundException($this->getName(), $reason);
    }

    public function listScripts(): array
    {
        $config = $this->getConfig();

        return $config->listScripts();
    }

    public function setPath(string $path): self
    {
        if(!is_dir($path)){
            throw new DirectoryNotExistException($path);
        }

        $this->path = realpath($path);
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

    public function getVcs(): GitRepositoryModel
    {
        return container(GitRepositoryModel::class, ['path' => $this->getPath()]);
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
