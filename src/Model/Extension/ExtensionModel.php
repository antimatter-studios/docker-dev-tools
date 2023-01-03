<?php declare(strict_types=1);

namespace DDT\Model\Extension;

use DDT\Config\Project\ExtensionProjectConfig;

/**
 * Extension Model
 * 
 * As part of the System configuration, there is a section for installed extensions. Each one of these
 * configuration blocks is represented by this ExtensionModel class. 
 * 
 * From each ExtensionModel, you can ask to get the ExtensionProjectConfig object, which can read
 * the specific .ddt-extension.json file in every extension and there are config section objects
 * which can read specific parts of that configuration into other models in order to do futher things
 */
class ExtensionModel
{
    /** @var string */
    private $name;

    /** @var string */
    private $url;

    /** @var string */
    private $path;

    /** @var string */
    private $test;

    public function __construct(string $name, string $url, string $path, ?string $test=null)
    {
        $this->name = $name;
        $this->url = $url;
        $this->path = $path;
        $this->test = $test;

        // check if the path exists
        if(!is_dir($this->path)){
            throw new \Exception("The path given '$path' was not found");
        }
    }

    /**
     * Obtain the ExtensionProjectConfig object which represents an Extensions
     * .ddt-extension.json configuration file and read other types of section
     * configuration objects in those files into other types of models that
     * can be used by other aspects of the system.
     *
     * @return ExtensionProjectConfig
     */
    public function getConfig(): ExtensionProjectConfig
    {
        return ExtensionProjectConfig::fromPath($this->path);
    }
}