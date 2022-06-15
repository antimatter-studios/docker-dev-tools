<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI;
use DDT\Config\SystemConfig;
use DDT\Contract\ToolRegistryInterface;
use DDT\Exceptions\Autowire\CannotAutowireParameterException;
use DDT\Exceptions\Tool\ToolException;
use DDT\Exceptions\Tool\ToolNotFoundException;

class EntrypointTool extends Tool implements ToolRegistryInterface
{
    private $tools = [];

    public function __construct(CLI $cli)
    {
        parent::__construct('entrypoint', $cli);
    }

    public function getVersion(SystemConfig $config): string
    {
        return (string)$config->getVersion();
    }

    public function handle()
    {
        $requestedCommand = null;
        $methodName = null;

        // If there are no arguments, output the default help
        if ($this->cli->countArgs() === 0) {
            return $this->cli->print($this->help());
        }

        if ($this->cli->getArg('--version', false, true)) {
            return $this->cli->print($this->invoke('getVersion'));
        }

        $toolArg = $this->cli->shiftArg();

        // There were no commands or arguments, show main help
        if (empty($toolArg)) {
            return $this->cli->print($this->help());
        }

        // If the tool name, is the entrypoint, we stop this from happening
        // by just treating it as if you called the help
        $toolName = strtolower($toolArg['name']);
        if ($toolName === $this->name) {
            return $this->cli->print($this->help());
        }

        // Obtain the tool, throw exception if not found
        $tool = $this->getTool($toolName);

        $argList = $this->cli->getArgList();

        // If there are arguments, pick the first and resolve it to a command method
        if ($this->cli->countArgs()) {
            $requestedCommand = $argList[0]['name'];
            $methodName = $tool->getToolCommand($requestedCommand);
        }

        if ($methodName === null) {
            // If it does not resolve into a command method, fall back to the default command method
            $methodName = $tool->getToolDefaultCommand();
        } else {
            // If the argument contained a valid command method, we should slice this parameter off the list
            // This is so it doesn't get consumed twice
            $argList = array_slice($argList, 1);
        }

        // Special case for the auto-updater
        // Before handling the request, check to see if the timeout passes and self update
        if ($toolName !== 'self-update') {
            $this->getTool('self-update')->run();
        }

        if ($methodName !== null) {
            if ($methodName === '__call') {
                $response = $tool->$toolName($argList);
            } else {
                $response = $tool->invoke($methodName, $argList);
            }

            $response = (is_string($response) ? $response : '') . "\n";

            $this->cli->print($response);
            return $response;
        }

        $this->cli->print($tool->help());

        if ($requestedCommand) {
            $this->cli->failure("The requested command '$requestedCommand' from tool '$toolName' does not exist, check your spelling against the help");
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
                $instance = $this->getToolByClass($tool['class']);
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

    public function registerTools($name, $path, $namespace): array
    {
        $namespace = rtrim($namespace, "\\");

        $tools = glob($path . "/Tool/?*Tool.php");
        $tools = array_map(function($path) use ($namespace) {
            $file = basename($path);
            $class = str_replace('.php', '', $file);
            $name = str_replace('tool', '', strtolower($class));
            return ['name' => $name, 'namespace' => $namespace, 'class' => $class, 'path' => realpath($path)];
        }, $tools);

        $this->tools[] = ['name' => $name, 'tools' => $tools];

        return $this->tools;
    }

    public function registerExtensions(): void
    {
        $extensionBootstraps = glob(config('tools.path') . '/extensions/**/src/bootstrap.php');
	
        $entrypoint = $this;
        foreach($extensionBootstraps as $bootstrap){
            require_once($bootstrap);
        }
    }

    public function getTool(string $name): Tool
    {
        $name = strtolower($name);
        $name = explode("-", $name);
        $name = implode(" ", $name);
        $name = ucwords($name);
        $name = str_replace(" ", "", $name);

        return $this->getToolByClass($name . "Tool");
    }

    public function getToolByClass(string $name): Tool
    {
        if(empty($name)) throw new ToolException('Tool name cannot be empty');

        foreach($this->tools as $group){
            foreach($group['tools'] as $tool){
                if($tool['class'] === $name){
                    return container("{$tool['namespace']}\\{$tool['class']}");
                }
            }
        }

        throw new ToolNotFoundException($name);
    }
}