<?php declare(strict_types=1);

namespace DDT\Model;

class RunConfiguration
{
    private $name;
    private $group;
    private $commandList;
    private $dependencies;

    public function __construct(string $name, ?string $group, array $commandList, array $dependencies)
    {
        $this->name = $name;
        $this->group = $group;
        
        $this->commandList = array_filter($commandList, function($v, $k){
            if(!is_string($k)) throw new \Exception('Run Configuration command list must have string keys, each is the name of the command to execute');
            if(empty($v)) throw new \Exception('Run Configuration commands cannot be an empty value, this would indicate an incorrect configuration');
            return true;
        }, ARRAY_FILTER_USE_BOTH);
        
        $this->dependencies = array_filter($dependencies, function($v){
            return $v instanceof self;
        });
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function getCommandList(): array
    {
        return $this->commandList;
    }

    public function hasDependencies(): bool
    {
        return count($this->dependencies) > 0;
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }
}