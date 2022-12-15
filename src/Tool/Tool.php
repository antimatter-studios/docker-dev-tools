<?php declare(strict_types=1);

namespace DDT\Tool;

use Exception;
use DDT\Autowire;
use DDT\CLI\CLI;
use DDT\Exceptions\Tool\ToolCommandInvalidException;
use DDT\Exceptions\Tool\ToolCommandNotFoundException;

abstract class Tool
{
	/** @var string */
	protected $name;

	/** @var CLI */
    protected $cli;

    /** @var string $entrypoint The name of the script which acts as the entrypoint */
    private $entrypoint;

    /** @var array The registered commands for this tool */
    private $command = [];

    /** @var array A list of functions which cannot be used as tool commands */
    private $protectedFunctions = [
        'setToolCommand',
        'getToolCommand',
        'getToolCommandName',
        'getTool',
        'getToolMetaData',
        'getToolName',
    ];

    private $autowire;

    public function __construct(string $name, CLI $cli)
	{
		$this->cli = $cli;
        $this->autowire = container(Autowire::class);

        $this->setEntrypoint($this->cli->getScript(false));
        $this->setToolName($name);
        $this->setToolCommand('help');
	}

    public function setToolName(string $name): void
    {
        $this->name = $name;
    }

    public function getToolName(): string
    {
        return $this->name;
    }

    public function setEntrypoint(string $script): void 
    {
        $this->entrypoint = $script;
    }
    
    public function getEntrypoint(): string 
    {
        return $this->entrypoint;
    }

    public function getToolEntrypoint(): string
    {
        return $this->getEntrypoint() . " " . $this->getToolName();
    }

    public function setToolCommand(string $name, ?string $method=null, bool $isDefault=false): void
    {
        if(empty($name)){
            throw new ToolCommandInvalidException();
        }

        $name = strtolower($name);

        if($name === '__call'){
            $method = $name;
        }

        if(empty($method)){
            $method = ucwords(str_replace(['-', '_'], ' ', $name));
            $method = lcfirst(str_replace(' ', '', $method));
        }

        if(!is_callable([$this, $method])){
            throw new ToolCommandNotFoundException("$this->entrypoint $this->name", $name);
        }

        if(in_array($method, $this->protectedFunctions)){
            throw new ToolCommandInvalidException("The command '$name' with method name '$method' is a protected method and can't be set");
        }

        $this->command[$name] = ['method' => $method, 'is_default' => $isDefault];
    }

    public function getToolDefaultCommand(): ?string
    {
        foreach($this->command as $c){
            if($c['is_default'] === true){
                return $c['method'];
            }
        }

        return null;
    }

    public function isToolDefaultCommand(string $commandName): bool
    {
        if(array_key_exists($commandName, $this->command)){
            return $this->command[$commandName]['is_default'];
        }

        return false;
    }

    public function getToolCommand(string $commandName): ?string
    {
        if(array_key_exists($commandName, $this->command)){
            return $this->command[$commandName]['method'];
        }

        return null;
    }

    public function getToolCommandName(string $methodName): ?string
    {
        foreach($this->command as $commandName => $command){
            if($methodName === $command['method']){
                return $commandName;
            }
        }

        return null;
    }

    public function getToolMetadata(): array
    {
        return [
            'title' => 'A sample Tool Title',
            'description' => 'Please add getToolMetadata to your tool',
            'commands' => [
                'set: this will set some data',
                'get: this will get some data',
                'add: this will add some data',
                'help: this command is available in all tools'
            ],
            'options' => [
                'TODO: I think I should phase this element out'
            ],
            'examples' => [
                'ddt mytool set some-data'
            ],
            'notes' => [
                'This should be an array of notes',
                'Which can help your users understand your tool',
                'Leave an empty array if you do not want notes to be shown',
                'They are formatted as bulletpoints'
            ],
        ];
    }

    /**
     * Automatically autowire a function for any method on this object 
     * called manually by the code rather than by the container or entrypoint
     * 
     * @param string $method The method to call on this object
     * @param array $args An optional set of arguments to pass this method
     * @return mixed Can be anything the method returns
     */
    public function invoke(string $method, ?array $args=[])
    {
        return $this->autowire->callMethod($this, $method, $args);
    }

    public function help(): string
    {
        $section = [];
        $metadata = $this->getToolMetadata();

        $title = array_key_exists('title', $metadata) ? $metadata['title'] : null;
        if(!empty($title)){
            $section[] = "{grn}Docker Dev Tools: $title{end}";
        }

        $description = array_key_exists('description', $metadata) ? $metadata['description'] : null;
        if(!empty($description)){
            $description = is_array($description) ? ("\t" . implode("\n\t", $description)) : $description;
            $section[] = "{blu}Description:{end}\n$description";
        }
        
        $options = array_key_exists('options', $metadata) ? $metadata['options'] : null;
        if(!empty($options)){
            $options = is_array($options) ? ("\t" . implode("\n\t", $options)) : $options;
            $section[] = "{blu}Options:{end}\n$options";
        }

        $examples = array_key_exists('examples', $metadata) ? $metadata['examples'] : null;
        if(!empty($examples)){
            $examples = is_array($examples) ? ("\t" . implode("\n\t", $examples)) : $examples;
            $section[] = "{blu}Examples:{end}\n$examples";
        }

        $notes = array_key_exists('notes', $metadata) ? $metadata['notes'] : null;
        if(!empty($notes)) {
            $notes = is_array($notes) ? ("\t" . implode("\n\t", $notes)) : $notes;
            $section[] = "{blu}Notes:{end}\n$notes";
        }

        return implode("\n\n", $section) . "\n\n";
    }
}
