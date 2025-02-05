<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI;
use DDT\CLI\ArgumentList;
use DDT\Config\Sections\DockerConfig;
use DDT\Debug;
use DDT\Exceptions\Config\Docker\DockerRunProfileNotFoundException;
use DDT\Exceptions\Config\Docker\DockerSyncProfileNotFoundException;
use DDT\Services\DockerService;
use DDT\Model\Docker\RunProfileModel;
use DDT\Model\Docker\SyncProfileModel;
use DDT\Text\Table;

class DockerTool extends Tool
{
    /** @var DockerService $docker */
    private $docker;

    /** @var DockerConfig $config */
    private $config;
    
    public function __construct(CLI $cli, DockerService $docker, DockerConfig $config)
    {
        parent::__construct('docker', $cli);

        $this->docker = $docker;
        $this->config = $config;

        foreach([
            'add-profile', 'remove-profile', 'list-profile',
            'add-project', 'remove-project', 'list-project',
            'use', 'sync', 'write'
        ] as $command){
            $this->setToolCommand($command);
        }
    }

    public function getToolMetadata(): array
    {
        $entrypoint = $this->cli->getScript(false) . ' ' . $this->getToolName();

        return [
            'title' => 'Docker Helper',
            'short_description' => 'A tool to interact with docker enhanced by the dev tools to provide extra functionality',
            'description' => [
                "This tool will manage the configured docker execution profiles that you can use in other tools.",
                "Primarily the tool was created for the purpose of wrapping up and simplifying the ability to",
                "execute docker commands on other docker servers hosted elsewhere.\n",
                "These profiles contain connection information to those remote docker profiles and make it",
                "easy to integrate working with those remote servers into other tools without spreading",
                "the connection information into various places throughout your custom toolsets",
            ],
            'options' => [
                "{cyn}Managing Profiles{end}",
                "\tadd-profile <name> <host> <port> <tlscacert> <tlscert> <tlskey>: See 'Adding Profiles' for more details",
                "\tremove-profile <name>: The name of the profile to remove",
                "\tlist-profile(s): List all the registered profiles",
                "\n\t{cyn}Adding Profiles, the following options are available{end}",
                "\thost=xxx: The host of the docker server (or IP Address)",
                "\tport=xxx: The port, when using TLS, it must be 2376",
                "\ttlscacert=xxx: The filename of this tls cacert (cacert, not cert)",
                "\ttlscert=xxx: The filename of the tls cert",
                "\ttlskey=xxx: The filename of the tls key",
                
                "\n\t{cyn}Managing Projects (Sync Profiles){end}",
                "\tadd-project <name> <container-name> <local-dir> <remote-dir>: See 'Adding Profiles' for more details",
                "\tremove-project <name>: The name of the project to remove",
                "\tlist-project: List all the registered projects",

                "\n\t{cyn}Using Profiles{end}",
                "\tuse <profile-name>: To execute a command using this profile (all following arguments are sent directly to docker executable without modification",
                "\tsync <profile-name> <project-name>: Start watching for file modifications inside a project against a docker server, syncing all updates to the container"
            ],
            'notes' => [
                "The command {yel}add-profile{end} depends on: {yel}name, host, port, tlscacert, tlscert, tlskey{end} options",
                "and unfortunately you can't create a profile without all of those parameters at the moment\n",
            ],
            'examples' => [
                "{yel}Usage Examples: {end}",
                "$entrypoint use <profile-name> exec -it phpfpm sh",
                "$entrypoint add-profile --name=staging --host=mycompany.com --port=2376 --tlscacert=cacert.pem --tlscert=cert.pem --tlskey=key.pem",
                "$entrypoint remove-profile --name=staging",
                "$entrypoint get-profile --name=staging",
                "$entrypoint list-profile",
            ],
        ];
    }

    public function addProfile(string $name, string $host, int $port, string $tlscacert, string $tlscert, string $tlskey, bool $tlsverify)
    {
        $profile = new RunProfileModel($name, $host, $port, $tlscacert, $tlscert, $tlskey, $tlsverify);
        
        if($this->config->writeRunProfile($profile)){
            $this->cli->print("{grn}Docker Run Profile '$name' written successfully{grn}\n");
        }else{
            $this->cli->print("{red}Docker Run Profile '$name' did not write successfully{end}\n");
        }

        $this->listProfile();
    }

    public function removeProfile(string $name)
    {
        $this->cli->print("{blu}Removing Docker Run Profile:{end} '$name'\n\n");

        if($this->config->deleteRunProfile($name)){
            $this->cli->print("{grn}Docker Run Profile '$name' removed successfully{end}");
        }else{
            $this->cli->print("{red}Docker Run Profile '$name' could not be removed successfully{end}\n");
        }

        $this->listProfile();
    }

    public function listProfile()
    {
        $this->cli->print("{blu}Listing Docker Run Profiles{end}\n\n");

        $list = $this->config->listRunProfile();

        $table = Table::getInstance();
        $table->addRow([
            "{yel}Profile{end}", 
            "{yel}Host{end}", 
            "{yel}Port{end}", 
            "{yel}TLS{end}", 
            "{yel}TLS Verify{end}"
        ]);

        foreach($list as $profile){
            $data = $profile->getData();

            $tlsVerifyColour = $data['tlsverify'] ? "{grn}" : "{red}";
            $table->addRow([$data['name'], $data['host'], $data['port'], $data['tlscacert'], $tlsVerifyColour . ($data['tlsverify'] ? "yes" : "no") . "{end}"]);
            $table->addRow([null, null, null, $data['tlscert'], null]);
            $table->addRow([null, null, null, $data['tlskey'], null]); 
        }

        if(empty($list)){
            $this->cli->print("There are no registered Docker Run Profiles");
        }else{
            $this->cli->print($table->render());
        }
    }

    public function addProject(string $name, string $containerName, string $localDir, string $remoteDir)
    {
        $this->cli->print("{blu}Creating new Docker Sync Project:{end}\n\n");

        $profile = new SyncProfileModel($name, $containerName, $localDir, $remoteDir);
        
        if($this->config->writeSyncProfile($profile)){
            $this->cli->print("{grn}Docker Sync Project '$name' written successfully{end}\n");
        }else{
            $this->cli->print("{red}Docker Sync Project '$name' did not write successfully{end}\n");
        }

        $this->listProject();
    }

    public function removeProject(string $name)
    {
        $this->cli->print("{blu}Removing Docker Sync Project:{end} '$name'\n\n");

        if($this->config->deleteSyncProfile($name)){
            $this->cli->print("{grn}Docker Sync Profile '$name' removed successfully{end}\n");
        }else{
            $this->cli->print("{red}Docker Sync Profile '$name' could not be removed successfully{end}\n");
        }

        $this->listProject();
    }

    public function listProject()
    {
        $this->cli->print("{blu}Listing Docker Sync Project(s){end}\n\n");

        $list = $this->config->listSyncProfile();

        $table = Table::getInstance();
        
        $table->addRow([
            "{yel}Project{end}", 
            "{yel}Container Name{end}", 
            "{yel}Local Dir{end}", 
            "{yel}Remote Dir{end}"
        ]);

        foreach($list as $profile){
            $data = $profile->getData();

            $table->addRow([$data['name'], $data['container_name'], $data['local_dir'], $data['remote_dir']]);
        }

        if(empty($list)){
            $this->cli->print("There are no registered Docker Sync Projects\n");
        }else{
            $this->cli->print($table->render());
        }
    }

    public function use(string $profileName): void
    {
        $profile = $this->config->readRunProfile($profileName);
        $arguments = new ArgumentList($this->cli->getArgList(), 2);

        $this->docker->setProfile($profile);
        $this->docker->passthru((string)$arguments . " >/dev/tty </dev/tty");
    }

    public function sync(string $profileName, string $projectName): void
    {
        if(!$this->cli->isCommand('fswatch')){
            $this->cli->failure("The 'fswatch' command must be installed for this tool to function correctly");
        }

        try{
            $run = $this->config->readRunProfile($profileName);
            $sync = $this->config->readSyncProfile($projectName);    
        
            $this->cli->print("Watching '{$sync->getLocalDir()}' for changes\n");
            $debug = Debug::is(true) ? '--debug' : '';
            $script = "{$this->getEntrypoint()} {$debug} {$this->getToolName()} write {$run->getName()} {$sync->getName()} \"\$file\"";
            $command = "fswatch {$sync->getLocalDir()} | while read file; do file=$(echo \"\$file\" | sed '/\~$/d') && [ ! -z \"\$file\" ] && $script; done";

            $this->cli->passthru($command);
        }catch(DockerRunProfileNotFoundException $e){
            $this->cli->failure($e->getMessage());
        }catch(DockerSyncProfileNotFoundException $e){
            $this->cli->failure($e->getMessage());
        }
    }

    public function write(string $profileName, string $projectName, string $filename): void
    {
        $run = $this->config->readRunProfile($profileName);
        $sync = $this->config->readSyncProfile($projectName);

        if(!$run){
            $this->cli->failure("The docker run profile '$profileName' was not found");
        }

        if(!$sync){
            $this->cli->failure("The docker sync project '$projectName' was not found");
        }

        $this->docker->setProfile($run);

        $container = $sync->getContainerName();
        $remoteFilename = $sync->toRemoteFilename($filename);

        $temp = "/tmp/".implode('_', [bin2hex(random_bytes(8)), basename($remoteFilename)]);

        // FIXME: what happens when the $filename is a directory? we should sync it's entire contents and entire file tree?
        // FIXME: what happens when $filename does not exist anymore? Does this mean delete on the remote in order to not exist on the remove too?

        $this->cli->print("[{yel}" . date("d-m-Y h:i:s"). "{end}] Writing File: '$filename' to '$remoteFilename': ");

        $this->docker->exec("cp -a $filename $container:$temp");
        
        if($this->docker->getExitCode() === 0){
            $this->docker->exec("exec -i --user=0 $container mv -f $temp $remoteFilename");
        }

        if($this->docker->getExitCode() === 0){
            $this->cli->print("{grn}SUCCESS{end}");
        }else{
            $this->cli->print("{red}FAILURE{end}");
        }
    }

    /* 
     * This is all the code which was in the previous version that would allow you to filter uploaded files against an ignore list
     * meaning it wouldn't just upload every and any file
    public function listIgnoreRules(): array
	{
		return $this->config->getKey($this->ignoreRuleKey);
	}

	public function setIgnoreRules(array $rules): void
	{
		$this->config->setKey($this->ignoreRuleKey, $rules);
		$this->config->write();
	}

	public function addIgnoreRule(string $rule): void
	{
		$list = $this->listIgnoreRules();
		$list[] = $rule;
		$this->setIgnoreRules(array_unique($list));
	}

	public function removeIgnoreRule(string $rule): void
	{
		$list = $this->listIgnoreRules();
		foreach(array_keys($list) as $test){
			if($list[$test] === $rule) unset($list[$test]);
		}
		$this->setIgnoreRules($list);
	}

	public function shouldIgnore(DockerSyncProfile $syncProfile, string $filename): bool
	{
		$list = $this->listIgnoreRules();

		if(strpos($filename, $syncProfile->getLocalDir()) === 0){
			$filename = substr($filename, strlen($syncProfile->getLocalDir()));
		}

		foreach($list as $rule){
			$la		= substr_compare($rule, "^", 0, 1) === 0 ? "^" : "";
			$ra		= substr_compare($rule, "$", -1) === 0 ? "$": "";
			$rule 	= rtrim(ltrim($rule,"^"),"$");
			$rule 	= ltrim($rule, '/');
			$rule 	= preg_quote("/$rule",'/');
			$rule 	= "/".$la.$rule.$ra."/";

			if(preg_match($rule, $filename)){
				return true;
			}
		}

		return false;
	}
    */
}
