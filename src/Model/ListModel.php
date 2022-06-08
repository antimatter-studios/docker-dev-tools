<?php declare(strict_types=1);

namespace DDT\Model;

use Countable;
use IteratorIterator;
use JsonSerializable;
use Traversable;

abstract class ListModel extends IteratorIterator implements ModelInterface, JsonSerializable, Countable
{
    use JsonSerializableTrait;
    
    public function __construct(Traversable $iterator)
    {
        parent::__construct($iterator);
    }

    public function count(): int
    {
        return iterator_count($this->getInnerIterator());
    }

    public function map(callable $operation): self
    {
        $inner = function() use ($operation) {
            foreach ($this->list as $k => $v) {
                yield $operation($k, $v);
            }
        };

        return call_user_func_array(get_class($this)."::fromArray", $inner());
    }

    public function filter(callable $filter): iterable 
    {
        foreach ($this->list as $k => $v) {
            if ($filter($v)) {
                yield $k => $v;
            }
        }
    }

    public function reduce(callable $reducer, $acc)
    {
        foreach ($this->list as $v) {
            $acc = $reducer($acc, $v);
        }

        return $acc;
    }
}