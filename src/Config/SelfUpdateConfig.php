<?php declare(strict_types=1);

namespace DDT\Config;

class SelfUpdateConfig
{
    private $config;
    private $defaultPeriod = '+7 day';
    private $key = '.self_update';

    public function __construct(SystemConfig $config)
    {
        $this->config = $config;
    }

    public function isReadonly(): bool
    {
        return $this->config->isReadonly();
    }

    public function isEnabled(): bool
    {
        return !!$this->config->getKey("$this->key.enabled");
    }

    public function setEnabled(bool $status): bool
    {
        $this->config->setKey("$this->key.enabled", $status);
        return $this->config->write();
    }

    public function getPeriod(): string
    {
        $period = $this->config->getKey("$this->key.period");
        
        if(!$period) {
            $period = $this->defaultPeriod;
            $this->config->setKey("$this->key.period", $period);
            $this->config->write();
        }

        return $period;
    }

	public function setPeriod(string $period): bool
	{
        if(strtotime($period, time()) !== false){
            $this->config->setKey("$this->key.period", $period);
            return $this->config->write();    
        }

        return false;
	}

    public function getTimeout(): int
    {
        $timeout = (int)$this->config->getKey("$this->key.timeout");
        
        if(!$timeout){
            $timeout = time() - 1;
            $this->config->setKey("$this->key.timeout", $timeout);
            $this->config->write();
        }

        return $timeout;
    }

	public function setTimeout(int $timeout): bool
	{
		$this->config->setKey("$this->key.timeout", $timeout);
		return $this->config->write();
	}
}