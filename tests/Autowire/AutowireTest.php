<?php declare(strict_types=1);

namespace DDT\Test\Autowire;

use DDT\Autowire;
use DDT\Text\Text;
use DDT\CLI\CLI;
use DDT\Config\SystemConfig;
use DDT\Container;
use DDT\Debug;
use DDT\Services\RunService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AutowireTest extends TestCase
{
    private $cli;
    private $container;

    public function setUp(): void
    {
        $argv = explode(" ", "ddt --debug run start mycompany some-project --userAge=monkey --debug 23 helloboys 99.99 77");

        $text = new Text();
        $systemConfig = new SystemConfig(__DIR__ . '/../../default.ddt-system.json', true);

        $this->cli = new CLI($argv, $text);
        $this->container = new Container($this->cli, [Autowire::class, 'instantiator']);
        $this->container->singleton(CLI::class, $this->cli);
        $this->container->singleton(SystemConfig::class, $systemConfig);
        
        new Debug($this->cli, true);
    }

    private function callHiddenMethod($obj, $name, array $args) {
        $class = new ReflectionClass($obj);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method->invokeArgs($obj, $args);
    }

     public function testReformatArgs(): void
     {
         $args = explode(" ", "run start mycompany some-project --userAge=monkey --debug 23 helloboys 99.99 77");
         $autowire = new Autowire([$this->container, 'get']);
         $response = $this->callHiddenMethod($autowire, "reformatArgs", [$args]);

         $this->assertIsArray($response);

         // TODO: Assert that the arguments were reformatted into a way that is predictable
     }

    // ****************** THIS TEST IS BROKEN
    // public function testResolveArgs(): void
    // {
    //     $autowire = new Autowire([$this->container, 'get']);

    //     $response = $this->callHiddenMethod($autowire, "resolveArgs", [
    //         [
    //             ['name' => 'runService','type' => 'DDT\Services\RunService'],
    //             ['name' => 'name','type' => 'string'],
    //             ['name' => 'group','type' => 'string'],
    //             ['name' => 'testing','type' => 'bool'],
    //             ['name' => 'userAge','type' => 'int'],
    //             ['name' => 'project','type' => 'string'],
    //             ['name' => 'systemConfig', 'type' => 'DDT\Config\SystemConfig'],
    //             ['name' => 'a','type' => 'int', 'default' => 1],
    //             ['name' => 'b','type' => 'float', 'default' => 2.2],
    //             ['name' => 'c','type' => 'string', 'default' => null],
    //             ['name' => 'the_data', 'type' => 'array', 'default' => null],
    //         ],[
    //             ['name' => 'start'],
    //             ['name' => 'mycompany'],
    //             ['name' => 'some-project'],
    //             ['name' => 'userAge', 'value' => 'monkey'],
    //             ['name' => 'debug'],
    //             ['name' => '23'],
    //             ['name' => 'helloboys'],
    //             ['name' => '99.99'],
    //             ['name' => '77'],
    //             ['name' => 'testing', 'value' => 'true'],
    //             ['name' => 'the_data', 'value' => 'NULL'],
    //         ]
    //     ]);

    //     $this->assertIsArray($response);
    // }

    // ****************** THIS TEST IS BROKEN
    // public function testSimpleFunction(): void
    // {
    //     $args = explode(" ", "run start mycompany some-project --userAge=monkey --debug 23 helloboys 99.99 77");
    //     $args[] = ['name' => 'testing', 'value' => false];
        
    //     $autowire = new Autowire([$this->container, 'get']);
    //     $response = $autowire->callMethod($this, 'autowireFunction', $args);

    //     var_dump($response);
    //     $this->assertIsArray($response);
    // }

    // ****************** THIS TEST IS BROKEN
    // public function testNewAutowireResolver(): void
    // {
    //     // we should use the resolve2 function to try to build a better resolver, the default one is pretty wild and stupidly complex
    //     $response = $this->resolve2([], []);

    //     $this->assertIsArray($response);
    // }

    // ****************** THIS TEST IS BROKEN
    // public function resolve2(array $signatureParameters, array $inputParameters): array
    // {
    //     $output = [];

    //     $vd = function (){
    //         var_dump(func_get_args());
    //     };

    //     foreach($inputParameters as $index => $data){

    //     }

    //     $output = [
    //         call_user_func($this->resolver, '\DDT\Service\RunService'),
    //         'start',
    //         'mycompany',
    //         false,
    //         23,
    //         'some-project',
    //         77,
    //         99.99,
    //         'debug'
    //     ];

    //     return $output;
    // }

    public function autowireFunction(
        RunService $runService, 
        string $name,
        string $group, 
        bool $testing, 
        int $userAge, 
        string $project, 
        SystemConfig $systemConfig, 
        int $a=1, 
        float $b=2.2, 
        ?string $c=null,
        ?array $the_data=null): array 
    {
        return [
            'method' => __METHOD__, 
            'cli' => get_class($this->cli), 
            'name' => $name, 
            'group' => $group, 
            'testing' => $testing, 
            'userAge' => $userAge, 
            'project' => $project, 
            'a' => $a, 
            'b' => $b, 
            'c' => $c,
        ];
    }
}
