<?php declare(strict_types=1);

namespace DDT\Exceptions\Model;

class ListModelDataWrongTypeException extends \InvalidArgumentException 
{
    public function __construct($data, array $allowedTypes, \Exception $previous = null)
    {
        $type = is_object($data) ? get_class($data) : gettype($data);
        
        parent::__construct("Parameter passed to function was not instanceof '".implode(', ', $allowedTypes)."', was: " . $type, 0, $previous);
    }
}