<?php declare(strict_types=1);

namespace DDT\Model\Project;

use DDT\Config\Project\ComposerProjectConfig;
use DDT\Config\Project\NodeProjectConfig;
use DDT\Config\Project\StandardProjectConfig;
use DDT\Exceptions\Filesystem\DirectoryNotExistException;
use DDT\Model\Model;

class ProjectPathModel extends Model
{
    private $path;
    private $group;

    public function __construct(string $path, ?ProjectGroupModel $group=null)
    {
        if(!is_dir($path)) {
            throw new DirectoryNotExistException($path);
        }

        $this->path = realpath($path);
        $this->group = $group ?? new ProjectGroupModel([]);
    }

    public function getData()
    {
        return [
            'path' => $this->path,
            'group' => $this->group,
        ];
    }

    static public function fromPath(string $path, ?string $group=null): self
    {
        return new self($path, new ProjectGroupModel($group));
    }

    /**
     * @param array $data
     * @return static
     * @throws DirectoryNotExistException When the path requested does not exist
     * @throws \InvalidArgumentException When the group information is not in an acceptable format
     */
    static public function fromArray(array $data): self
    {
        $path = array_key_exists('path', $data) ? $data['path'] : null;
        $group = array_key_exists('group', $data) ? $data['group'] : null;

        if($group !== null){
            $group = new ProjectGroupModel($group);
        }

        return new self($path, $group);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getGroups(): ProjectGroupModel
    {
        return $this->group;
    }

    public function hasGroup(string $group): bool
    {
        return $this->group->has($group);
    }

    public function listProjects(): ProjectListModel
    {
        $paths = [
            "$this->path/*/" . ComposerProjectConfig::defaultFilename,
            "$this->path/*/" . NodeProjectConfig::defaultFilename,
            "$this->path/*/" . StandardProjectConfig::defaultFilename,
        ];

        $projects = [];
        foreach($paths as $type){
            $list = glob($type);
            foreach($list as $dir){
                $projectPath = dirname($dir);
                $projectName = basename($projectPath);
                $projects[] = ProjectModel::fromArray([
                    'path' => $projectPath,
                    'name' => $projectName,
                    'group' => $this->group,
                ]);
            }
        }

        return ProjectListModel::fromArray($projects);
    }
}