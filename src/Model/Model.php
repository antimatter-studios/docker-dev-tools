<?php declare(strict_types=1);

namespace DDT\Model;

use JsonSerializable;

abstract class Model implements ModelInterface, JsonSerializable
{
    use JsonSerializableTrait;
}