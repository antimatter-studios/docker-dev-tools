<?php declare(strict_types=1);

namespace DDT\Model;

use DDT\Contract\ModelInterface;
use DDT\Helper\Traits\JsonSerializableTrait;
use JsonSerializable;

abstract class Model implements ModelInterface, JsonSerializable
{
    use JsonSerializableTrait;
}