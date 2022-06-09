<?php declare(strict_types=1);

namespace DDT\Model\Vcs;

use DDT\Model\Model;

class VcsModel extends Model
{
    private $url;
    private $branch;
    private $name;

    public function __construct(string $url, ?string $branch=null, ?string $name=null)
    {
        $this->url = $url;
        $this->branch = $branch;
        $this->name = $name;
    }

    public function getData()
    {
        return [
            'url' => $this->url,
            'branch' => $this->branch,
            'name' => $this->name,
        ];
    }
}