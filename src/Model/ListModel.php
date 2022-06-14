<?php declare(strict_types=1);

namespace DDT\Model;

use Countable;
use DDT\Contract\ModelInterface;
use DDT\Model\Traits\JsonSerializableTrait;
use IteratorIterator;
use JsonSerializable;

abstract class ListModel extends IteratorIterator implements ModelInterface, JsonSerializable, Countable
{
    use JsonSerializableTrait;

    protected $list = [];
    protected $type;
    
    public function __construct(array $data, string $type)
    {
        $this->list = array_filter($data, function($item) use ($type) {
            return $item instanceof $type;
        });

        $this->type = $type;

        parent::__construct(new \ArrayIterator($this->list));
    }

    static public function fromArray(...$data)
    {
        return new static(...$data);
    }

    public function getData()
    {
        return $this->list;
    }

    public function count(): int
    {
        return count($this->list);
    }

    public function first(): ModelInterface
    {
        $this->reset();
        return current($this->list);
    }

    public function reset(): self
    {
        reset($this->list);
        return $this;
    }

    public function unshift(ModelInterface $model): self
    {
        if($model instanceof $this->type){
            $this->list = array_merge([$model], $this->list);

            return $this;
        }else{
            throw new \InvalidArgumentException("Parameter passed to function was not instanceof $this->type, was: ".get_class($model));
        }
    }

    public function append(ModelInterface $model): self
    {
        if($model instanceof $this->type){
            $this->list[] = $model;

            return $this;
        }else{
            throw new \InvalidArgumentException("Parameter passed to function was not instanceof $this->type, was: ".get_class($model));
        }
    }

    public function remove(int $index): self
    {
        if(array_key_exists($index, $this->list)){
            unset($this->list[$index]);
            // reindex the array to compact the indexes
            $this->list = array_values($this->list);

            return $this;
        }else{
            throw new \InvalidArgumentException("Index '$index' passed to function was not found");
        }
    }

    public function map(callable $callback): self
    {
        $list = [];
        foreach ($this->list as $k => $v) {
            $list[$k] = $callback($k, $v);
        }

        return self::fromArray($list);
    }

    public function filter(callable $callback): self
    {
        $list = [];
        foreach ($this->list as $k => $v) {
            if ($callback($v)) {
                $list[$k] = $v;
            }
        }

        return self::fromArray($list);
    }

    public function reduce(callable $reducer, $acc)
    {
        foreach ($this->list as $v) {
            $acc = $reducer($acc, $v);
        }

        return $acc;
    }
}