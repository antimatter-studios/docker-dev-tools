<?php declare(strict_types=1);

namespace DDT\Helper;

use DDT\Contract\CollectionInterface;
use DDT\Exceptions\ArrayCollection\ArrayCollectionInvalidKeyException;
use DDT\Exceptions\ArrayCollection\ArrayCollectionKeyNotExistsException;
use DDT\Model\Traits\JsonSerializableTrait;

class ArrayCollection implements CollectionInterface
{
    use JsonSerializableTrait;

	private $data;

	public function __construct(array $data=[])
	{
		$this->data = $data;
	}

    static public function fromArray(array $data)
    {
        return new static($data);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->data);
    }

    public function getData()
    {
        return $this->data;
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function first()
    {
        return reset($this->data);
    }

    public function last()
    {
        return end($this->data);
    }

    public function key()
    {
        return key($this->data);
    }

    public function current()
    {
        return current($this->data);
    }

    public function next()
    {
        return next($this->data);
    }

    public function reset()
    {
        return reset($this->data);
    }

    /**
     * @param $key
     * @param $value
     * @return CollectionInterface
     * @throws ArrayCollectionInvalidKeyException
     */
    public function set($key, $value): CollectionInterface
    {
        if(empty($key) || !is_scalar($key)){
            throw new ArrayCollectionInvalidKeyException($key);
        }

        if(is_string($key)){
            $key = ltrim($key ?? '', '.');
            $segmentList = explode(".", $key);
        }else{
            $segmentList = [$key];
        }

        $array = &$this->data;

        while (count($segmentList) > 1) {
            $part = array_shift($segmentList);

            if (!isset($array[$part]) or !is_array($array[$part])) {
                $array[$part] = [];
            }

            $array = &$array[$part];
        }

        $topLevelPart = array_shift($segmentList);

        // NOTE: we do this because we want to only store plain arrays
        // NOTE: this seems to be the easiest way to strip out models, or arrays of models
        // NOTE: encoding -> decoding, seems to be the most universal way to deal with various conditions
        // NOTE: without having to resort to detecting each type of condition and handling them individually
        if(!is_scalar($value)){
            $value = json_decode(json_encode($value), true);
        }

        if(empty($topLevelPart)) $array = $value;
        else $array[$topLevelPart] = $value;

        unset($array);

        return $this;
    }

    /**
     * @param $key
     * @return array|mixed|null
     * @throws ArrayCollectionKeyNotExistsException
     */
    public function get($key)
    {
        if(is_string($key)){
            $key = ltrim($key ?? '', '.');
            $segmentList = explode(".", $key);
        }else{
            $segmentList = [$key];
        }

        $data = $this->data;

        foreach ($segmentList as $part) {
            if (!is_array($data) || !array_key_exists($part, $data)){
                throw new ArrayCollectionKeyNotExistsException($key);
            }

            $data = $data[$part];
        }

        return $data;
    }

    public function find($value)
    {
        return array_search($value, $this->data, true);
    }

    public function pull($key)
    {
        // set the search array to the top level data array
        $array = &$this->data;

        // break key up into segments if there is a dot to explode them by
        $key = strpos($key, '.') !== false ? explode('.', $key) : [$key];

        // The final data needed to correctly pull the requested key from the array data
        $pullKey = null;
        $pullParent = null;
        $pullData = null;

        foreach($key as $segment){
            // Search for segment in search array, otherwise return null
            if(!array_key_exists($segment, $array)) {
                return null;
            }

            // Reset the parent to the current array data and segment
            unset($pullParent);
            $pullParent = &$array;
            $pullKey = $segment;

            // Reset the array value to the subtree, so it can scan into that for the next segment
            unset($array);
            $array = &$pullParent[$pullKey];
        }

        // After all segments were found, the resulting pull parent/key will be what the user expects to be returned
        $pullData = $pullParent[$pullKey];

        // Now we have to remove the key from the array data structure
        unset($pullParent[$pullKey]);

        // unset these references to break the connection to the array data
        unset($pullParent);
        unset($array);

        // then return the subtree the user pulled
        return $pullData;
    }

    public function has($key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function add($value): CollectionInterface
    {
        $this->data[] = $value;

        return $this;
    }

    public function remove($key)
    {
        if(array_key_exists($key, $this->data)) {
            $reindex = $this->isAssoc() === false;

            $removed = $this->data[$key];
            unset($this->data[$key]);

            // optionally reindex if the original was numeric keys
            if ($reindex) {
                $this->data = array_values($this->data);
            }

            return $removed;
        }

        return null;
    }

    public function shift()
    {
        return array_shift($this->data);
    }

    public function unshift($value): CollectionInterface
    {
        array_unshift($this->data, $value);

        return $this;
    }

    public function clear(): CollectionInterface
    {
        $this->data = [];
        return $this;
    }

    public function isEmpty(): bool
    {
        return count($this->data) > 0;
    }

    public function toArray(): array
    {
        return $this->getData();
    }

    function isAssoc(): bool
    {
        $keys = array_keys($this->data);
        return $keys !== array_keys($keys);
    }

    public function offsetExists($key): bool
    {
        return $this->has($key);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($key)
    {
        return $this->get($key);
    }

    /**
     * @param $key
     * @param $value
     * @return CollectionInterface|void
     * @throws ArrayCollectionInvalidKeyException
     */
    public function offsetSet($key, $value)
    {
        if (!isset($key)) {
            $this->add($value);
        }else{
            $this->set($key, $value);
        }
    }

    public function offsetUnset($key): void
    {
        $this->remove($key);
    }

    public function filter(callable $callback): CollectionInterface
    {
        return self::fromArray(array_filter($this->data, $callback));
    }

    public function map(callable $callback): CollectionInterface
    {
        return self::fromArray(array_map($callback, $this->data));
    }

    public function reduce(callable $reducer, $acc)
    {
        return array_reduce($this->data, $reducer, $acc);
    }
}
