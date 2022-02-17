<?php declare(strict_types=1);

namespace DDT\Tool;

use Exception;
use DDT\Autowire;
use DDT\CLI;
use DDT\Exceptions\Tool\CommandInvalidException;
use DDT\Exceptions\Tool\CommandNotFoundException;
use DDT\Exceptions\Tool\ToolCommandInvalidException;
use DDT\Exceptions\Tool\ToolCommandNotFoundException;
use DDT\Exceptions\Tool\ToolNotFoundException;

abstract class Tool
{
	/** @var string */
	protected $name;

	/** @var CLI */
    protected $cli;

    /** @var string $entrypoint The name of the script which acts as the entrypoint */
    private $entrypoint;

    /** @var array The registered commands for this tool */
    private $commands = [];

    /** @var array A list of functions which cannot be used as tool commands */
    private $protectedFunctions = [
        'setToolCommand',
        'getToolCommand',
        'getTool',
        'getToolMetaData',
        'getToolName',
    ];

    /** @var bool $debug Whether to write or process any extra debugging info */
    private $debug = false;

    /** @var bool $quiet Whether or not to silence any output that shouldn't hit the terminal cause it's not wanted */
    private $quiet = false;

    public function __construct(string $name, CLI $cli)
	{
		$this->name = $name;
		$this->cli = $cli;
        
        $this->setEntrypoint($this->cli->getScript(false));
        $this->setToolCommand('help');
	}

    public function setEntrypoint(string $script): void 
    {
        $this->entrypoint = $script;
    }
    
    public function getEntrypoint(): string 
    {
        return $this->entrypoint;
    }

    public function setDebug(bool $enable): void
    {
        $this->debug = $enable;
    }

    public function setQuiet(bool $enable): void
    {
        $this->quiet = $enable;
    }

    public function setToolCommand(string $name, ?string $method=null, bool $isDefault=false): void
    {
        if(empty($name)){
            throw new ToolCommandInvalidException();
        }

        $name = strtolower($name);

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

    public function getToolCommand(string $name): ?string
    {
        if(array_key_exists($name, $this->command)){
            return $this->command[$name]['method'];
        }

        throw new Exception("The requested command '$name' was not registered");
    }

    public function getTool(string $name): Tool
    {
        try{
            if(empty($name)) throw new Exception('Tool name cannot be empty');
            
            return container('DDT\\Tool\\'.ucwords($name).'Tool');
        }catch(Exception $e){
            $this->cli->debug("{red}".$e->getMessage()."{end}");
            throw new ToolNotFoundException($name, 0, $e);
        }
    }

    public function getToolName(): string
    {
        return $this->name;
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
        $autowire = container(Autowire::class);
        return $autowire->callMethod($this, $method, $args);
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
            $section[] = "{blu}Description:{end}\n$description";
        }
        
        $options = array_key_exists('options', $metadata) ? $metadata['options'] : null;
        if(!empty($options)){
            $options = is_array($options) ? implode("\n", $options) : $options;
            $section[] = "{blu}Options:{end}\n$options";
        }

        $examples = array_key_exists('examples', $metadata) ? $metadata['examples'] : null;
        if(!empty($examples)){
            $examples = is_array($examples) ? implode("\n", $examples) : $examples;
            $section[] = "{blu}Examples:{end}\n$examples";
        }

        $notes = array_key_exists('notes', $metadata) ? $metadata['notes'] : null;
        if(!empty($notes)) {
            $notes = is_array($notes) ? implode("\n", $notes) : $notes;
            $section[] = "{blu}Notes:{end}\n$notes";
        }

        return implode("\n\n", $section) . "\n";
    }
}
