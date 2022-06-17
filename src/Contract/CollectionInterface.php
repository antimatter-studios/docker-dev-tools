<?php declare(strict_types=1);

namespace DDT\Contract;

interface CollectionInterface extends \Countable, \IteratorAggregate, \ArrayAccess, \JsonSerializable
{
    public function getIterator(): \Traversable;
    public function count(): int;

    public function first();
    public function last();
    public function key();
    public function current();
    public function next();
    public function reset();

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

    public function filter(callable $callback): CollectionInterface;
    public function map(callable $callback): CollectionInterface;
    public function reduce(callable $reducer, $acc);
}