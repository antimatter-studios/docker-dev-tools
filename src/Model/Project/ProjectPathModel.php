<?php declare(strict_types=1);

namespace DDT\Model\Project;

use DDT\Exceptions\Filesystem\DirectoryNotExistException;
use DDT\Model\Model;

class ProjectPathModel extends Model
{
    private $path;
    private $group = null;

    public function __construct(string $path, ?string $group=null)
    {
        if(!is_dir($path)) {
            throw new DirectoryNotExistException($path);
        }

        $this->path = $path;
        $this->group = $group;
    }

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'group' => $this->group,
        ];
    }

    static public function fromPath(string $path, ?string $group=null): self
    {
        return new self($path, $group);
    }

    static public function fromArray(array $data): self
    {
        $path = array_key_exists('path', $data) ? $data['path'] : null;
        $group = array_key_exists('group', $data) ? $data['group'] : null;

        return new self($path, $group);
    }
}