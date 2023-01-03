<?php declare(strict_types=1);

namespace DDT\Config;

use DDT\Contract\ConfigInterface;
use DDT\Exceptions\Config\ConfigMissingException;
use DDT\Exceptions\Config\ConfigInvalidException;
use DDT\Exceptions\Config\ConfigReadonlyException;
use DDT\Exceptions\Filesystem\DirectoryExistsException;

abstract class JsonConfig implements ConfigInterface
{
    private $data = [];
	private $filename = null;
    private $readonly = false;

    public function __construct(string $filename, bool $readonly=false)
	{
        $this->setReadonly($readonly);
        $this->read($filename);
    }
    
    public function setFilename(string $filename): string
	{
        $temp = realpath($filename);

        if(!$temp){
            throw new ConfigInvalidException("An unknown problem with the filename '$filename' was detected");
        }

        if(!is_file($temp)){
            throw new ConfigInvalidException("The filename given '$filename' was not a file");
		}

		return $this->filename = $temp;
	}

    static abstract public function getDefaultFilename(): string;

	public function getFilename(): string
	{
		return $this->filename;
    }

    public function getName(): string
    {
        return $this->getKey('name');
    }

    public function getType(): string
	{
        $type = $this->getKey('type');

        if($type === null){
			throw new ConfigInvalidException("Every config must have a type field. If this is a main configuration file, add type=system to the top of json file");
		}

		return $this->data['type'];
    }

    public function setVersion(string $version): void
    {
        $this->setKey('version', $version);
    }
    
    public function getVersion(): int
	{
        $version = (int)$this->getKey('version');

        if($version === null){
            throw new ConfigInvalidException("Every config must have a version field");
        }

        return $version;
	}

    public function setReadonly(bool $readonly): void
    {
        $this->readonly = $readonly;
    }

    public function isReadonly(): bool
    {
        return $this->readonly;
    }

	public function read(string $filename): void
	{
        $filename = $this->setFilename($filename);

		if(file_exists($filename) === false){
            throw new ConfigMissingException($filename);
		}

		$contents = file_get_contents($filename);

		$json = json_decode($contents, true);

        if(!is_array($json)){
            $json = [];
        }

		$this->data = $json;
	}

	public function write(?string $filename=null): bool
	{
        if($this->isReadonly()){
            throw new ConfigReadonlyException();
        }

        if(!empty($filename)){
            if(!is_string($filename)){
                throw new \Exception('Filename must be a string');
            }

            if(is_dir($filename)){
                throw new DirectoryExistsException($filename);
            }
        }else{
            $filename = $this->getFilename();
        }

		$data = json_encode($this->data, JSON_PRETTY_PRINT);

		$result = file_put_contents($filename, $data."\n") !== false;

        $this->setFilename($filename);

        return $result;
    }
    
    public function setKey(string $key, $value): void
	{
        $key = ltrim($key ?? '', '.');

        $parts = explode(".", $key);

        $array = &$this->data;
  
        while (count($parts) > 1) {
            $part = array_shift($parts);
        
            if (!isset($array[$part]) or !is_array($array[$part])) {
                $array[$part] = [];
            }
        
            $array = &$array[$part];
        }
        
        $topLevelPart = array_shift($parts);

        // NOTE: we do this because we want to only store plain arrays
        // NOTE: this seems to be the easiest way to strip out models, or arrays of models
        // NOTE: encoding -> decoding, seems to be the most universal way to deal with various conditions
        // NOTE: without having to resort to detecting each type of condition and handling them individually
        if(!is_scalar($value)){
            $value = json_decode(json_encode($value), true);
        }
        
        if(empty($topLevelPart)) $array = $value;
        else $array[$topLevelPart] = $value;
        
        unset($array);
	}
    
    public function getKey(?string $key = null)
	{
        $key = ltrim($key ?? '', '.');

        if(empty($key)) return $this->data;

        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        $array = $this->data;
        
        foreach (explode(".", $key) as $part) {
            if (!is_array($array) or !isset($array[$part])) {
                return null;
            }
        
            $array = $array[$part];
        }
        
        return $array;
    }
    
    public function getKeyAsJson(?string $key = null): string
	{
		$data = $this->getKey($key);

		return json_encode($data, JSON_PRETTY_PRINT);
    }

    public function toJson(): string
    {
        return $this->getKeyAsJson();
    }
    
    public function deleteKey(string $key): bool
    {
        $key = ltrim($key ?? '', '.');

        $keys = explode('.', $key);

        $array = &$this->data;
        
        while (count($keys) > 1)
        {
            $key = array_shift($keys);
        
            if (isset($array[$key]) && is_array($array[$key])) {
                $array = &$array[$key];
            }
        }
        
        unset($array[array_shift($keys)]);
        unset($array);
        
        return true;
    }
}