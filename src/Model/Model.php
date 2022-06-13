<?php declare(strict_types=1);

namespace DDT\Model;

use DDT\Contract\ModelInterface;
use DDT\Model\Traits\JsonSerializableTrait;
use JsonSerializable;

abstract class Model implements ModelInterface, JsonSerializable
{
    use JsonSerializableTrait;
}