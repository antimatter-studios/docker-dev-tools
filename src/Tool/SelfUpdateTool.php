<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI;
use DDT\Config\Sections\ExtensionConfig;
use DDT\Config\Sections\SelfUpdateConfig;
use DDT\Helper\DateTimeHelper;
use DDT\Services\GitService;

// Interesting git command to show how far you are ahead/behind: 
// git rev-list --left-right master...origin/master --count

class SelfUpdateTool extends Tool
{
    /** @var SelfUpdateConfig */
    private $selfUpdateConfig;
    
    /** @var GitService */
    private $gitService;

    public function __construct(CLI $cli, SelfUpdateConfig $selfUpdateConfig, ExtensionConfig $extensionConfig, GitService $gitService)
    {
    	parent::__construct('self-update', $cli);

        $this->selfUpdateConfig = $selfUpdateConfig;
        $this->extensionConfig = $extensionConfig;
        $this->gitService = $gitService;

        $this->setToolCommand('now');
        $this->setToolCommand('reset');
        $this->setToolCommand('timeout');
        $this->setToolCommand('period');
        $this->setToolCommand('enable');
    }

    public function getToolMetadata(): array
    {
        $entrypoint = $this->cli->getScript(false) . " " . $this->getToolName();

        return [
            'title' => 'Self Update Tool',
            'short_description' => 'A tool that pull updates to the docker dev tools',
            'description' => 'A tool that pull updates to the docker dev tools',
            'options' => [
                "now: Will manually trigger an update",
                "timeout: Show you how long until the next automatic self update",
                "reset: Reset the countdown timer",
                "period '1 minute': Set the timeout period for each update",
                "enabled true|false: Set whether the automatic self update should run or not",
            ],
            'examples' => [
                "- $entrypoint now",
                "- $entrypoint timeout",
                "- $entrypoint reset",
                "- $entrypoint period \"7 days\"",
                "- $entrypoint enable true|false",
            ],
        ];
    }

    public function timeout(): int
    {
        $timeout = $this->selfUpdateConfig->getTimeout();
        $text = DateTimeHelper::nicetime($timeout);
        $this->cli->print("{yel}Self Updater Timeout in{end}: $text\n");

        return $timeout;
    }

    public function period(?string $period=null)
    {
        if($this->selfUpdateConfig->setPeriod($period)){
            $timeout = $this->reset();
            $relative = DateTimeHelper::nicetime($timeout);
            $this->cli->print("{yel}Next update{end}: $relative\n");
        }
    }

    public function enable(?bool $enable=true)
    {
        $this->selfUpdateConfig->setEnabled($enable);
        
        $statusText = $this->selfUpdateConfig->isEnabled() ? 'enabled' : 'disabled';
        $this->cli->print("{yel}Self Updater is{end}: $statusText{end}\n");
    }

    public function reset(): int
    {
        $timeout = strtotime($this->selfUpdateConfig->getPeriod(), time());
        $this->selfUpdateConfig->setTimeout($timeout);

        return $this->timeout();
    }

    public function auto(): void
    {
        $timeout = $this->selfUpdateConfig->getTimeout();

        if(time() < $timeout){
            $text = DateTimeHelper::nicetime($timeout);
            $this->cli->debug("update", "Did not trigger because the timeout has not run out, timeout in $text\n");
            return;
        }

        if($this->cli->hasArg('--skip-update')){
            $this->cli->debug('update', "Skipping auto-update\n");
            return;
        }

        $this->now();
    }

    public function now(): void
    {
        $this->cli->print("========================================\n");
        $this->cli->print("{blu}Docker Dev Tools{end}: Self Updater\n");
        $this->cli->print("{cyn}Add --skip-update to your command to skip this auto-update{end}\n");

        if($this->selfUpdateConfig->isReadonly()){
            $this->cli->print("{yel}System Configuration is readonly, can not update{end}\n");
            return;
        }

        if(!$this->selfUpdateConfig->isEnabled()){
            $this->cli->print("{yel}Self Updater is disabled{end}\n");
            return;
        }

        $this->cli->print("Updating Tools: ");
        $this->gitService->getRepository(config('tools.path'))->pull();

        $extensionList = $this->extensionConfig->list();
        foreach($extensionList as $name => $extension){
            $this->cli->print("Updating extension '$name': ");
            $this->gitService->getRepository($extension['path'])->pull();
        }

        $timeout = $this->cli->silenceChannel('stdout', function(){
            return $this->reset();
        });
        
        $relative = DateTimeHelper::nicetime($timeout);
        
        $this->cli->print("{yel}Self Update Complete, next update: $relative{end}\n");
        $this->cli->print("========================================\n");
    }
}

