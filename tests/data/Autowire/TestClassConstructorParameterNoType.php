<?php declare(strict_types=1);

namespace DDT\Test\Data\Autowire;

class TestClassConstructorParameterNoType
{
    public $data = false;

    public function __construct($param)
    {
        $this->data = $param;
    }
}