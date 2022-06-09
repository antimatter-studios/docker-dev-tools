<?php declare(strict_types=1);

namespace DDT\Model;

use Countable;
use IteratorIterator;
use JsonSerializable;
use Traversable;

abstract class ListModel extends IteratorIterator implements ModelInterface, JsonSerializable, Countable
{
    use JsonSerializableTrait;
    
    public function __construct(array $data, string $type)
    {
        $data = array_filter($data, function($item) use ($type) {
            return $item instanceof $type;
        });

        parent::__construct(new \ArrayIterator($data));
    }

    public function count(): int
    {
        return iterator_count($this->getInnerIterator());
    }

    static public function fromArray(...$data)
    {
        return new static(...$data);
    }

    public function first(): ModelInterface
    {
        return $this->current();
    }

    public function map(callable $callback): self
    {
        $inner = function() use ($callback) {
            foreach ($this->list as $k => $v) {
                yield $callback($k, $v);
            }
        };

        return self::fromArray(...$inner());
    }

    public function filter(callable $callback): self
    {
        $inner = function() use ($callback) {
            foreach ($this->list as $k => $v) {
                if ($callback($v)) {
                    yield $k => $v;
                }
            }
        };

        return self::fromArray(...$inner());
    }

    public function reduce(callable $reducer, $acc)
    {
        foreach ($this->list as $v) {
            $acc = $reducer($acc, $v);
        }

        return $acc;
    }
}