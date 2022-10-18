<?php declare(strict_types=1);

namespace DDT\Test\Autowire;

use DDT\Autowire;
use DDT\CLI;
use DDT\Container;
use DDT\Test\Data\Autowire\TestClassConstructorParameterNoType;
use DDT\Text\Text;
use PHPUnit\Framework\TestCase;

class ParameterWithNoTypeTest extends TestCase
{
    public function setUp(): void
    {
        $text = new Text();

        $this->cli = new CLI(['phpunit'], $text);
        $this->container = new Container($this->cli, [Autowire::class, 'instantiator']);
        $this->autowire = new Autowire([$this->container, 'get']);
    }

    public function testAutowireClassConstructorParameterWithNoType()
    {
        $param = [1,2,3];
        $object = $this->container->get(TestClassConstructorParameterNoType::class, ['param' => $param]);

        $this->assertEquals($param, $object->data);
    }
}