<?php declare(strict_types=1);

namespace DDT\Model;

use DDT\Contract\CollectionInterface;
use DDT\Helper\ArrayCollection;

abstract class ListModel2 extends ArrayCollection
{
    protected $type;
    
    public function __construct(array $data, string $type)
    {
        $data = array_filter($data, function($item) use ($type) {
            return $item instanceof $type;
        });

        $this->type = $type;

        parent::__construct($data);
    }

    public function set($key, $item): CollectionInterface
    {
        if($item instanceof $this->type) {
            return parent::set($key, $item);
        }

        $type = is_object($item) ? get_class($item) : gettype($item);
        throw new \InvalidArgumentException("Parameter passed to function was not instanceof $this->type, was: " . $type);
    }

    public function get($key)
    {
        $item = parent::get($key);

        if($item !== null) {
            return $item;
        }

        throw new \InvalidArgumentException("Key '$key' passed to function was not found");
    }

    public function add($item): CollectionInterface
    {
        if($item instanceof $this->type) {
            return parent::add($item);
        }

        $type = is_object($item) ? get_class($item) : gettype($item);
        throw new \InvalidArgumentException("Parameter passed to function was not instanceof $this->type, was: " . $type);
    }

    public function remove($key)
    {
        $item = parent::remove($key);

        if($item !== null){
            return $item;
        }

        throw new \InvalidArgumentException("Key '$key' passed to function was not found");
    }

    public function unshift($item): CollectionInterface
    {
        if($item instanceof $this->type) {
            return parent::unshift($item);
        }

        $type = is_object($item) ? get_class($item) : gettype($item);
        throw new \InvalidArgumentException("Parameter passed to function was not instanceof $this->type, was: " . $type);
    }
}