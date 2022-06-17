<?php declare(strict_types=1);

namespace DDT\Model\Traits;

trait JsonSerializableTrait
{
    public function __toString(): string
    {
        return json_encode($this->jsonSerialize(), JSON_PRETTY_PRINT);
    }

    public function jsonSerialize()
    {
        return $this->getData();
    }
}