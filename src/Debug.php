<?php declare(strict_types=1);

namespace DDT;

class Debug
{
    static public $enabled = false;
    
    static public function setState($enabled)
    {
        if($enabled === false) return;

        if($enabled === true) $enabled = "true";

        if(is_string($enabled)){
            self::$enabled = array_map('trim', explode(',', $enabled));
        }

        if(is_array(self::$enabled) && in_array('container', self::$enabled)){
            container(CLI::class)->getChannel('container')->enable(true);
        }
    }

    static public function dump($filter, $mixed)
    {
        if(is_array(self::$enabled)){
            if(in_array($filter, self::$enabled) || in_array('verbose', self::$enabled)){
                is_scalar($mixed) ? print("$mixed\n") : var_dump($mixed);
            }
        }
    }
}