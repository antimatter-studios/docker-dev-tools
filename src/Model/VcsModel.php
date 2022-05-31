<?php declare(strict_types=1);

namespace DDT\Model;

class VcsModel extends Model
{
    public function __construct(string $url, ?string $branch=null, ?string $name=null)
    {
        $this->url = $url;
        $this->branch = $branch;
        $this->name = $name;
    }

    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'branch' => $this->branch,
            'name' => $this->name,
        ];
    }
}