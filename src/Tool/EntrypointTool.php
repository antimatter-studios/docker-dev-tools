<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI;
use DDT\Config\SystemConfig;
use DDT\Contract\ToolRegistryInterface;
use DDT\Exceptions\Autowire\CannotAutowireParameterException;
use DDT\Exceptions\Tool\ToolNotFoundException;

class EntrypointTool extends Tool implements ToolRegistryInterface
{
    private $tools = [];

    public function __construct(CLI $cli)
    {
        parent::__construct('entrypoint', $cli);

        $this->setDebug((bool)$cli->getArg('--debug', false, true));
        $this->setQuiet((bool)$cli->getArg('--quiet', false, true));

        $this->setToolCommand('--version', 'getVersion');
    }

    public function getVersion(SystemConfig $config): string
    {
        return $config->getVersion();
    }

    public function setDebug(bool $enable): void
    {
        $this->debug = $enable;
        $this->cli->enableErrors($enable);
        $this->cli->listenChannel('debug', $enable);
        
        if($enable){
            $this->cli->print("{yel}[SYSTEM]:{end} Errors enabled\n");
        }
    }

    public function getDebug(): bool
    {
        return $this->debug;
    }

    public function setQuiet(bool $enable): void
    {
        $this->quiet = $enable;
        $this->cli->listenChannel('quiet', $enable);

        if($enable){
            $this->cli->print("{yel}[SYSTEM]:{end} Quiet output enabled\n");
        }
    }

    public function getQuiet(): bool 
    {
        return $this->quiet;
    }

    public function handle()
    {        
        try{
            $requestedCommand = null;
            $methodName = null;

            // TODO: need to support ddt --version commands
            // right now the entrypoint is too linear in that it assumes everything running is
            // inside a tool, but what if the entrypoint has it's own functionality too?
            // so this is too much of a customised setup, I would like to build a more 
            // generic code which can run with flexible argument streams

            $toolArg = $this->cli->shiftArg();

            // There were no commands or arguments, show main help
            if(empty($toolArg)){
                return $this->cli->print($this->help());
            }

            // If the tool name, is the entrypoint, we stop this from happening 
            // by just treating it as if you called the help
            $toolName = strtolower($toolArg['name']);
            if($toolName === $this->name) {
                return $this->cli->print($this->help());
            }

            // Obtain the tool, throw exception if not found
            $tool = $this->getTool($toolName);
            if(!$tool instanceof Tool){
                throw new ToolNotFoundException($toolName);
            }

            $argList = $this->cli->getArgList();

            // If there are arguments, pick the first and resolve it to a command method
            if($this->cli->countArgs()){
                $requestedCommand = $argList[0]['name'];
                $methodName = $tool->getToolCommand($requestedCommand);
            }

            if($methodName === null){
                // If it does not resolve into a command method, fall back to the default command method
                $methodName = $tool->getToolDefaultCommand();
            }else{
                // If the argument contained a valid command method, we should slice this parameter off the list
                // This is so it doesn't get consumed twice
                $argList = array_slice($argList, 1);
            }

            if($methodName !== null){
                if($methodName === '__call'){
                    $response = $tool->$toolName($argList);
                }else{
                    $response = $tool->invoke($methodName, $argList);
                }

                $response = (is_string($response) ? $response : '') . "\n";
                
                $this->cli->print($response);
                return $response;
            }

            $this->cli->print($tool->help());

            if($requestedCommand){
                $this->cli->failure("The requested command '$requestedCommand' from tool '$toolName' does not exist, check your spelling against the help");
            }
        }catch(CannotAutowireParameterException $e){
            $this->cli->print($tool->help());
            $commandName = $tool->getToolCommandName($e->getMethodName());
            $commandText = $tool->isToolDefaultCommand($commandName) ? 'tool' : "command '$commandName' on the tool";

            $this->cli->failure("The {$commandText} '$toolName' requires a parameter '{$e->getParameterName()}' with format '{$e->getParameterType()}'\n");
        }
    }

    public function getToolMetadata(): array
    {
        $list = $this->tools;

        $options = [];

        foreach($this->tools as $group){
            $options[] = "{cyn}{$group['name']}{end}";
            foreach($group['tools'] as $tool){
                // Don't process 'itself' or 'entrypoint'
                if($tool['name'] === $this->name){
                    continue;
                }

                /** @var Tool */
                $instance = $this->getTool($tool['name']);

                $metadata = $instance->getToolMetadata();
                $shortDescription = array_key_exists('short_description', $metadata) ? $metadata['short_description'] : $metadata['description'];

                $options[] = "\t - {yel}{$instance->getToolName()}{end}: {$shortDescription}";
            }
            $options[] = "";
        }

        return [
            'title' => 'Main Help',
            'description' => trim(
                "The docker dev tools provides multiple tools which will assist you when building a reliable, \n". 
                "stable development environment. See the below options for subcommands that you can run for \n". 
                "specific functionality which also provide their own help when run without arguments"
            ),
            'options' => implode("\n", $options),
        ];
    }

    public function registerTools($name, $path, $namespace)
    {
        $namespace = rtrim($namespace, "\\");

        $tools = glob($path . "/Tool/?*Tool.php");
        $tools = array_map(function($file) use ($namespace) {
            return ['name' => str_replace(['tool', '.php'], '', strtolower(basename($file))), 'namespace' => $namespace, 'path' => realpath($file)];
        }, $tools);

        $this->tools[] = ['name' => $name, 'tools' => $tools];

        //var_dump($this->tools);

        return $this->tools;
    }

    public function listTools(): array
    {
        $list = array_map(function($t){ 
            return ['name' => str_replace(['tool', '.php'], '', strtolower(basename($t))), 'path' => $t];
        }, glob(__DIR__ . "/../Tool/?*Tool.php"));
        
        return [];
    }

    public function getTool(string $name): Tool
    {
        if(empty($name)) throw new \Exception('Tool name cannot be empty');

        $name = strtolower($name);
        $name = explode("-", $name);
        $name = implode(" ", $name);
        $name = ucwords($name);
        $name = str_replace(" ", "", $name);

        foreach($this->tools as $group){
            foreach($group['tools'] as $tool){
                if($tool['name'] === strtolower($name)){
                    return container("{$tool['namespace']}\\{$name}Tool");
                }
            }
        }

        throw new ToolNotFoundException($name);
    }
}