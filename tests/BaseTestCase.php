<?php declare(strict_types=1);

namespace DDT\Test;

use DDT\Autowire;
use DDT\CLI\CLI;
use DDT\Container;
use DDT\Text\Text;
use PHPUnit\Framework\TestCase;

class BaseTestCase extends TestCase
{
    public function setUp(): void
    {
        $text = new Text();
	    $cli = new CLI(["./phpunit"], $text);
        $container = new Container($cli, [Autowire::class, 'instantiator']);
    }
}