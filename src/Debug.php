<?php declare(strict_types=1);

namespace DDT;

class Debug
{
    static public $enabled = [];
    static private $cli;

    public function __construct(CLI $cli, $enabled)
    {
        if($enabled === false) return;

        if($enabled === true) $enabled = "true";

        if(is_string($enabled)){
            self::$enabled = array_map('trim', explode(',', $enabled));
        }

        self::$cli = container(CLI::class);
        self::$cli->enableErrors(true);
        self::$cli->toggleChannel('debug', true);

        if(is_array(self::$enabled) && in_array('container', self::$enabled)){
            $cli->getChannel('container')->enable(true);
        }
    }
    
    static public function dump($filter, $mixed)
    {
        $cli = self::$cli ?: container(CLI::class);

        if(in_array($filter, self::$enabled) || in_array('verbose', self::$enabled)){
            is_scalar($mixed) ? $cli->print("$mixed\n") : $cli->varDump($mixed);
        }
    }
}