<?php declare(strict_types=1);

namespace DDT\Exceptions\Model;

class ListModelMissingKeyException extends \InvalidArgumentException 
{
    public function __construct(string $key, \Exception $previous = null)
    {
        parent::__construct("Key '$key' passed to function was not found", 0, $previous);
    }
}