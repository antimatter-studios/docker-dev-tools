<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI;
use DDT\Exceptions\Tool\ToolNotFoundException;
use DDT\Exceptions\Tool\ToolNotSpecifiedException;
use DDT\Exceptions\Tool\CommandNotFoundException;
use DDT\Exceptions\Config\ConfigMissingException;

class EntrypointTool extends Tool
{
    public function __construct(CLI $cli)
    {
        parent::__construct($cli->getScript(false), $cli);
    }

    public function isTool(): bool
    {
        return false;
    }

    public function handle()
    {
        try{
            return $this->cli->print(parent::handle());
        }catch(ConfigMissingException $e){
            $this->cli->failure(\Text::box($e->getMessage(), "white", "red"));
        }catch(ToolNotFoundException $e){
            $this->cli->failure($e->getMessage());
        }catch(ToolNotSpecifiedException $e){
            $this->cli->failure($e->getMessage());
        }catch(CommandNotFoundException $e){
            $this->cli->failure($e->getMessage());
        }
    }

    public function handleArg(array $arg): void
    {
        switch(true){
            case $arg['name'] === '--debug':
                $this->cli->print("{yel}** errors enabled{end}\n");
                $this->cli->enableErrors(true);
                \Text::setDebug($arg['value'] ?? 'true');
                \Shell::setDebug(true);
                break;
            
            case $arg['name'] === '--quiet':
                $this->cli->print("{yel}** quiet output enabled{end}\n");
                \Text::setQuiet(true);
                break;
        }
    }

    public function handleCommand(array $command)
    {
        $tool = $this->createTool($command['name']);
        
        if($tool->isTool()){
            return $tool->handle();
        }
        
        throw new \DDT\Exceptions\Tool\ToolNotFoundException($command['name']);
    }

    public function createTool(string $name)
    {
        try{
            return container('DDT\\Tool\\'.ucwords($name).'Tool');
        }catch(\Exception $e){
            \Text::print("{debug}{red}".$e->getMessage()."{end}\n{/debug}");
            throw new \DDT\Exceptions\Tool\ToolNotFoundException($name, 0, $e);
        }
    }

    public function getTitle(): string
    {
        return 'Main Help';
    }

    public function getDescription(): string
    {
        return trim("
The docker dev tools provides multiple tools which will assist you when building a reliable, stable development environment
See the below options for subcommands that you can run for specific functionality which also provide their own help when run without arguments");
    }

    public function getShortDescription(): string
    {
        return '';
    }

    public function getOptions(): string
    {
        $list = array_map(function($t){ 
            return ['name' => str_replace(['tool', '.php'], '', strtolower(basename($t))), 'path' => $t];
        }, glob(__DIR__ . "/../Tool/?*Tool.php"));

        $options = [];

        foreach($list as $tool){
            $instance = $this->createTool($tool['name']);

            if($instance->isTool()){
                $options[] = \Text::write("  {$instance->getName()}: {$instance->getShortDescription()}");
            }
        }

        return implode("\n", $options);
    }
}