<?php declare(strict_types=1);

namespace DDT\Exceptions\ArrayCollection;

class ArrayCollectionInvalidKeyException extends ArrayCollectionException
{
    const NON_SCALAR_KEY = "Key was not a scalar value";
    const EMPTY_KEY = "Key was empty";

    public function __construct($key = "", $code = 0, \Throwable $previous = null)
    {
        if(!is_scalar($key)) $message = self::NON_SCALAR_KEY;
        if(empty($key)) $message = self::EMPTY_KEY;

        parent::__construct($message, $code, $previous);
    }
}