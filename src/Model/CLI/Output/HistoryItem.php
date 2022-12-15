<?php declare(strict_types=1);

namespace DDT\Model\CLI\Output;

use JsonSerializable;

class HistoryItem implements JsonSerializable {
    public $string;
    public $params;

    public function __construct(string $string, array $params) {
        $this->string = $string;
        $this->params = $params;
    }

    public function __toString()
    {
        return json_encode(['string' => $this->string, 'params' => $this->params]);
    }

    public function jsonSerialize(): mixed
    {
        return $this->__toString();
    }
}