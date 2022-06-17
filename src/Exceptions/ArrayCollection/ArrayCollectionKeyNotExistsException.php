<?php declare(strict_types=1);

namespace DDT\Exceptions\ArrayCollection;

class ArrayCollectionKeyNotExistsException extends ArrayCollectionException
{
    public function __construct($key = "", $code = 0, \Throwable $previous = null)
    {
        parent::__construct("Key '$key' does not exist", $code, $previous);
    }
}