<?php declare(strict_types=1);

namespace DDT;

use DDT\CLI\CLI;
use DDT\CLI\Output\CustomChannel;
use DDT\Exceptions\Container\ContainerNotInstantiatedException;
use ReflectionClass;
use DDT\Exceptions\Container\NotClassReferenceException;

class Container {
    static public $instance = null;

    private $cli;
    private $instantiator = null;
    private $bind = [];
    private $singleton = [];
    private $singletonCache = [];
    private $debug;

    public function __construct(CLI $cli, ?callable $instantiator=null)
    {
        self::$instance = $this;

        $this->cli = $cli;
        $this->instantiator = $instantiator;
        $this->debug = new CustomChannel($this->cli->getChannel('debug'), 'container', false);
        $this->cli->setChannel($this->debug);
    }

    static public function instantiate(?string $ref = null, ?array $args = [])
    {
        if(Container::$instance === null){
			throw new ContainerNotInstantiatedException();
		}
	
		return is_string($ref)
			? Container::$instance->get($ref, $args)
			: Container::$instance;
    }

    public function bind(string $ref, $func){
        $this->bind[$ref] = $func;
    }

    public function singleton(string $ref, $func){
        $this->singleton[$ref] = $func;
        unset($this->singletonCache[$ref]);
    }

    private function createInstance(string $ref, array $args = []) {
        $this->debug->write("{red}[CONTAINER]:{end} '$ref' was bound as an instance");

        $thing = $this->bind[$ref];

        switch(true){
            case is_callable($thing):
                return call_user_func_array($thing, $args);

            case class_exists($thing):
                return $this->createClass($thing, $args);
        }
    }

    private function createSingleton(string $ref, array $args = []) {
        $this->debug->write("{red}[CONTAINER]:{end} '$ref' was bound as a singleton");

        if(!array_key_exists($ref, $this->singletonCache)){

            $thing = $this->singleton[$ref];

            // var_dump($thing);

            switch(true){
                case is_callable($thing):
                    $this->debug->write("{red}[CONTAINER]:{end} the singleton references a callable");
                    $this->singletonCache[$ref] = call_user_func_array($thing, $args);
                    break;

                case is_object($thing):
                    $this->debug->write("{red}[CONTAINER]:{end} the singleton references an object");
                    $this->singletonCache[$ref] = $thing;
                    break;

                case is_string($thing) && class_exists($thing):
                    $this->debug->write("{red}[CONTAINER]:{end} the singleton references a class name");
                    $this->singletonCache[$ref] = $this->createClass($thing, $args);
                    break;

                case is_array($thing) || is_scalar($thing):
                    $this->debug->write("{red}[CONTAINER]:{end} the singleton references a scalar value");
                    $this->singletonCache[$ref] = $thing;
                    break;    
            }
        }

        return $this->singletonCache[$ref];
    }

    private function createClass(string $ref, array $args = []) {
        $this->debug->write("{red}[CONTAINER]:{end} '$ref' was bound as class");

        if($this->instantiator){
            $resolver = [$this, "get"];
            return call_user_func_array($this->instantiator, [$resolver, $ref, $args]);
        }
        
        $rc = new ReflectionClass($ref);
        return $rc->newInstanceArgs($args);
    }

    public function isSingleton(string $ref): bool {
        return array_key_exists($ref, $this->singleton);
    }

    public function isInstance(string $ref): bool{
        return array_key_exists($ref, $this->bind);
    }

    public function isClass(string $ref): bool {
        return class_exists($ref);
    }

    public function has(string $ref): bool {
        if($this->isSingleton($ref)) return true;
        if($this->isInstance($ref)) return true;
        if($this->isClass($ref)) return true;
        
        return false;
    }

    public function get(?string $ref = null, ?array $args = [])
    {
        switch(true){
            case $this->isSingleton($ref):
                return $this->createSingleton($ref, $args);
            case $this->isInstance($ref):
                return $this->createInstance($ref, $args);
            case $this->isClass($ref):
                return $this->createClass($ref, $args);
            default:
                throw new NotClassReferenceException($ref);
        }
    }
}
