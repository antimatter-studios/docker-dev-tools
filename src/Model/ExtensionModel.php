<?php declare(strict_types=1);

namespace DDT\Model;

class ExtensionModel
{
    /** @var string */
    private $name;

    /** @var string */
    private $url;

    /** @var string */
    private $path;

    public function __construct(string $name, string $url, string $path)
    {
        $this->name = $name;
        $this->url = $url;
        $this->path = $path;

        // check if the path exists
        if(!is_dir($this->path)){
            throw new \Exception("The path given '$path' was not found");
        }

        // read the .ddt-extension.json file and check it's valid
    }
}