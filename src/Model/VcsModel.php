<?php declare(strict_types=1);

namespace DDT\Model;

class VcsModel
{
    public function __construct(string $url, ?string $branch=null, ?string $name=null)
    {
        $this->url = $url;
        $this->branch = $branch;
        $this->name = $name;
    }
}