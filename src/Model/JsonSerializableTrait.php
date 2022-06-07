<?php declare(strict_types=1);

namespace DDT\Model;

trait JsonSerializableTrait
{
    public function __toString(): string
	{
		return json_encode($this->jsonSerialize(), JSON_PRETTY_PRINT);
	}

    abstract public function getData();

    public function jsonSerialize(): mixed
    {
        return $this->getData();
    }
}