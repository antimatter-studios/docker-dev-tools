<?php declare(strict_types=1);

namespace DDT\Model;

use DDT\Exceptions\Model\ListModelDataWrongTypeException;
use DDT\Exceptions\Model\ListModelMissingKeyException;
use DDT\Helper\ArrayCollection;

abstract class ListModel extends ArrayCollection
{
    protected $type = [];
    
    public function __construct(iterable $data, array $type)
    {
        $this->type = array_filter($type, function($t) {
            return is_string($t);
        });

        $data = array_filter((array)$data, function($item) {
            return $this->isAllowedType($item);
        });

        parent::__construct($data);
    }

    public function isAllowedType($data): bool
    {
        return array_reduce($this->type, function($result, $type) use ($data) {
            return $data instanceof $type === false ? false : $result;
        }, true);
    }

    public function set($key, $item): static
    {
        if ($this->isAllowedType($item)) {
            return parent::set($key, $item);
        }

        throw new ListModelDataWrongTypeException($item, $this->type);
    }

    public function get($key)
    {
        $item = parent::get($key);

        if($item !== null) {
            return $item;
        }

        throw new ListModelMissingKeyException($key);
    }

    public function add($item): static
    {
        if ($this->isAllowedType($item)) {
            return parent::add($item);
        }

        throw new ListModelDataWrongTypeException($item, $this->type);
    }

    public function remove($key)
    {
        $item = parent::remove($key);

        if($item !== null){
            return $item;
        }

        throw new ListModelMissingKeyException($key);
    }

    public function unshift($item): static
    {
        if ($this->isAllowedType($item)) {
            return parent::unshift($item);
        }

        throw new ListModelDataWrongTypeException($item, $this->type);
    }
}