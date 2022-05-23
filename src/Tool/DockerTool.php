<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI;
use DDT\CLI\ArgumentList;
use DDT\Config\DockerConfig;
use DDT\Debug;
use DDT\Services\DockerService;
use DDT\Model\Docker\RunProfile;
use DDT\Model\Docker\SyncProfile;

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
            'use', 'sync'
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
                "\tadd-project <name> <host> <port> <tlscacert> <tlscert> <tlskey>: See 'Adding Profiles' for more details",
                "\tremove-project <name>: The name of the profile to remove",
                "\tlist-project: List all the registered profiles",

                "\n\t{cyn}Using Profiles{end}",
                "\tuse <profile-name>: To execute a command using this profile (all following arguments are sent directly to docker executable without modification",
                "\tsync <profile-name> <project-name>: Start watching for file modifications inside a project against a docker server, syncing all updates to the container"
            ],
            'notes' => [
                "The parameter {yel}--add-profile{end} depends on: {yel}name, host, port, tlscacert, tlscert, tlskey{end} options",
                "and unfortunately you can't create a profile without all of those paraameters at the moment\n",
                "If you don't pass a profile to execute under, it'll default to your local docker server. Which means you can use this",
                "tool as a wrapper and optionally pass commands to various dockers by just adjusting the command parameters and",
                "adding the {yel}--profile=staging{end} or not",
            ],
            'examples' => [
                "{yel}Usage Examples: {end}",
                "$entrypoint profile --name=staging exec -it phpfpm sh",
                "$entrypoint add-profile --name=staging --host=mycompany.com --port=2376 --tlscacert=cacert.pem --tlscert=cert.pem --tlskey=key.pem",
                "$entrypoint remove-profile --name=staging",
                "$entrypoint get-profile --name=staging",
                "$entrypoint list-profile",
                "$entrypoint use the-profile ps"
            ],
        ];
    }

    public function addProfile(string $name, string $host, int $port, string $tlscacert, string $tlscert, string $tlskey, bool $tlsverify)
    {
        $this->cli->print("{blu}Creating new Docker Run Profile:{end}\n\n");
        $this->cli->print(" - name: '$name'\n");
        $this->cli->print(" - host: '$host'\n");
        $this->cli->print(" - port: '$port'\n");
        $this->cli->print(" - tls cacert: '$tlscacert'\n");
        $this->cli->print(" - tls cert: '$tlscert'\n");
        $this->cli->print(" - tls key: '$tlskey'\n");
        $this->cli->print(" - tls verify: " . ($tlsverify ? "yes" : "no") . "\n");

        $profile = new RunProfile($name, $host, $port, $tlscacert, $tlscert, $tlskey, $tlsverify);
        
        if($this->config->writeRunProfile($profile)){
            $this->cli->success("\nDocker Run Profile '$name' written successfully\n");
        }else{
            $this->cli->failure("\nDocker Run Profile '$name' did not write successfully\n");
        }
    }

    public function removeProfile(string $name)
    {
        $this->cli->print("{blu}Removing Docker Run Profile:{end} '$name'\n\n");

        if($this->config->deleteRunProfile($name)){
            $this->cli->success("\nDocker Run Profile '$name' removed successfully\n");
        }else{
            $this->cli->failure("\nDocker Run Profile '$name' could not be removed successfully\n");
        }
    }

    public function listProfile()
    {
        $this->cli->print("{blu}Listing Docker Run Profiles{end}\n\n");

        $list = $this->config->listRunProfile();

        foreach($list as $profile){
            $data = $profile->get();

            $this->cli->print("{blu}Profile:{end} {$data['name']}\n");
            $this->cli->print(" - host: '{$data['host']}'\n");
            $this->cli->print(" - port: '{$data['port']}'\n");
            $this->cli->print(" - tls cacert: '{$data['tlscacert']}'\n");
            $this->cli->print(" - tls cert: '{$data['tlscert']}'\n");
            $this->cli->print(" - tls key: '{$data['tlskey']}'\n");
            $this->cli->print(" - tls verify: " . ($data['tlsverify'] ? "yes" : "no") . "\n");
        }

        if(empty($list)){
            $this->cli->print("There are no registered Docker Run Profiles\n");
        }
    }

    public function addProject(string $name, string $containerName, string $localDir, string $remoteDir)
    {
        $this->cli->print("{blu}Creating new Docker Sync Project:{end}\n\n");
        $this->cli->print(" - name: '$name'\n");
        $this->cli->print(" - container name: '$containerName'\n");
        $this->cli->print(" - local dir: '$localDir'\n");
        $this->cli->print(" - remote dir: '$remoteDir'\n");

        $profile = new SyncProfile($name, $containerName, $localDir, $remoteDir);
        
        if($this->config->writeSyncProfile($profile)){
            $this->cli->success("\nDocker Sync Project '$name' written successfully\n");
        }else{
            $this->cli->failure("\nDocker Sync Project '$name' did not write successfully\n");
        }
    }

    public function removeProject(string $name)
    {
        $this->cli->print("{blu}Removing Docker Sync Project:{end} '$name'\n\n");

        if($this->config->deleteSyncProfile($name)){
            $this->cli->success("\nDocker Sync Profile '$name' removed successfully\n");
        }else{
            $this->cli->failure("\nDocker Sync Profile '$name' could not be removed successfully\n");
        }
    }

    public function listProject()
    {
        $this->cli->print("{blu}Listing Docker Sync Project(s){end}\n\n");

        $list = $this->config->listSyncProfile();

        foreach($list as $profile){
            $data = $profile->get();

            $this->cli->print("{blu}Project:{end} {$data['name']}\n");
            $this->cli->print(" - name: '{$data['name']}'\n");
            $this->cli->print(" - container name: '{$data['container_name']}'\n");
            $this->cli->print(" - local dir: '{$data['local_dir']}'\n");
            $this->cli->print(" - remote dir: '{$data['remote_dir']}'\n");
        }

        if(empty($list)){
            $this->cli->print("There are no registered Docker Sync Projects\n");
        }
    }

    public function use(string $profileName): void
    {
        $profile = $this->config->readRunProfile($profileName);
        $arguments = new ArgumentList($this->cli->getArgList(), 2);

        $this->docker->setProfile($profile);
        $this->docker->passthru((string)$arguments . " >/dev/tty </dev/tty");
    }

    public function sync(string $profileName, string $projectName, ?string $localFilename=null): void
    {
        if(!$this->cli->isCommand('fswatch')){
            $this->cli->failure("The 'fswatch' command must be installed for this tool to function correctly");
        }

        $run = $this->config->readRunProfile($profileName);
        $sync = $this->config->readSyncProfile($projectName);

        if(!$run){
            $this->cli->failure("The docker run profile '$profileName' was not found");
        }

        if(!$sync){
            $this->cli->failure("The docker sync project '$projectName' was not found");
        }

        if(empty($localFilename)){
            $debug = Debug::is(true) ? '--debug' : '';
            $script = "{$this->getEntrypoint()} {$debug} {$this->getToolName()} sync {$run->getName()} {$sync->getName()} \"\$file\"";
            $command = "fswatch {$sync->getLocalDir()} | while read file; do file=$(echo \"\$file\" | sed '/\~$/d'); $script; done";

            $this->cli->passthru($command);
        }else{
            $this->docker->setProfile($run);

            $container = $sync->getContainerName();
            $remoteFilename = $sync->toRemoteFilename($localFilename);

            $temp = "/tmp/".implode('_', [bin2hex(random_bytes(8)), basename($remoteFilename)]);

            $this->cli->print("[{yel}" . date("d-m-Y h:i:s"). "{end}] Writing File: '$localFilename' to '$remoteFilename': ");

            $this->docker->exec("cp -a $localFilename $container:$temp");
            
            if($this->docker->getExitCode() === 0){
                $this->docker->exec("exec -i --user=0 $container mv -f $temp $remoteFilename");
            }

            if($this->docker->getExitCode() === 0){
                $this->cli->print("{grn}SUCCESS{end}");
            }else{
                $this->cli->print("{red}FAILURE{end}");
            }
        }
    }
}
