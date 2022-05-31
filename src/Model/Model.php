<?php declare(strict_types=1);

namespace DDT\Model;

use JsonSerializable;

abstract class Model implements JsonSerializable
{
    public function __toString(): string
	{
		return json_encode($this->toArray(), JSON_PRETTY_PRINT);
	}

    abstract public function toArray(): array;

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}