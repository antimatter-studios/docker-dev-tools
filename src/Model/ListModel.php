<?php declare(strict_types=1);

namespace DDT\Model;

use Countable;
use IteratorIterator;
use JsonSerializable;
use Traversable;

abstract class ListModel extends IteratorIterator implements ModelInterface, JsonSerializable, Countable
{
    use JsonSerializableTrait;

    protected $list;
    
    public function __construct(array $data, string $type)
    {
        $this->list = array_filter($data, function($item) use ($type) {
            return $item instanceof $type;
        });

        parent::__construct(new \ArrayIterator($this->list));
    }

    public function getData()
    {
        return $this->list;
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
        $list = [];
        foreach ($this->list as $k => $v) {
            $list[$k] = $callback($k, $v);
        }

        return self::fromArray(...$list);
    }

    public function filter(callable $callback): self
    {
        $list = [];
        foreach ($this->list as $k => $v) {
            if ($callback($v)) {
                $list[$k] = $v;
            }
        }

        return self::fromArray(...$list);
    }

    public function reduce(callable $reducer, $acc)
    {
        foreach ($this->list as $v) {
            $acc = $reducer($acc, $v);
        }

        return $acc;
    }
}