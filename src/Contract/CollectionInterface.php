<?php declare(strict_types=1);

namespace DDT\Contract;

interface CollectionInterface extends \Countable, \Iterator, \ArrayAccess, \JsonSerializable
{
    static public function fromArray(iterable $array): CollectionInterface;
    public function getIterator(): \Traversable;
    public function count(): int;

    public function first();
    public function last();
    
    #[\ReturnTypeWillChange]
    public function key();
    
    #[\ReturnTypeWillChange]
    public function current();
    
    public function next(): void;
    public function reset();
    public function rewind(): void;
    public function valid(): bool;

    public function set($key, $item): CollectionInterface;
    public function get($key);
    public function has($key): bool;
    public function add($item): CollectionInterface;
    public function remove($key);

    public function shift();
    public function unshift($item): CollectionInterface;
    public function clear();

    public function isEmpty(): bool;
    public function toArray(): array;
    public function isAssoc(): bool;

    public function offsetExists($key): bool;
    
    #[\ReturnTypeWillChange]
    public function offsetGet($key);
    public function offsetSet($key, $value): void;
    public function offsetUnset($key): void;

    public function filter(callable $callback): CollectionInterface;
    public function map(callable $callback): CollectionInterface;
    public function reduce(callable $reducer, $acc);
}