<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI;
use DDT\Config\ExtensionConfig;
use DDT\Exceptions\Config\ConfigWrongTypeException;
use DDT\Exceptions\Filesystem\DirectoryExistsException;
use DDT\Exceptions\Filesystem\DirectoryNotExistException;
use DDT\Services\GitService;
use DDT\Text\Table;
use InvalidArgumentException;

class ExtensionTool extends Tool
{
    /** @var ExtensionConfig */
    private $config;

    /** @var \DDT\Services\GitService $gitService The service that can handle git repositories and manage them */
    private $gitService;

    public function __construct(CLI $cli, ExtensionConfig $config, GitService $gitService)
    {
    	parent::__construct('extension', $cli);

        $this->config = $config;
        $this->gitService = $gitService;

        foreach(['install', 'uninstall', 'update', 'list'] as $command){
            $this->setToolCommand($command);
        }
    }

    public function getToolMetadata(): array
    {
        $entrypoint = $this->cli->getScript(false) . " " . $this->getToolName();

        return [
            'title' => 'Extension Management Tool',
            'short_description' => 'A tool to manage tool extensions and update them',
            'description' => trim(
                "This tool will manage extensions installed within the tools. It can install, uninstall,\n".
                "or update them. At this time the tool only supports extensions from GIT repositories\n"
            ),
            'options' => [
                "install --name=<name> --url=<url>: Will install a new extension, requires two parameters, --name and --url, only git urls are supported",
                "uninstall --name<name>: Will uninstall an extension with the given name",
                "list: Will list the installed extensions",
                "update: Will update all extensions from their repository urls given during installation",
            ],
            'examples' => [
                "{yel}Usage Example:{end} $entrypoint {yel}install --name=example --url=https://github.com/something/extension_repo.git{end}",
                "{yel}Usage Example:{end} $entrypoint {yel}uninstall --name=example{end}",
            ],
        ];
    }

    public function install(string $name, string $url, string $test)
    {
        $this->cli->print("Installing new ExtensionManager '{yel}$name{end}' from url '{yel}$url{end}'\n");

        $path = $this->config->getToolsPath("/extensions/$name");

        try{
            if($this->gitService->clone($url, $path)){
                /** @var SetupTool */
                $setupTool = $this->getTool('setup');
                $this->cli->print("Removing extension '$name' with path '$path' from system files\n");
                $setupTool->add($path);
                
                if($this->cli->silenceChannel('stdout', function() use ($setupTool, $test) {
                    return $setupTool->test($test);
                }) === true){
                    $this->config->add($name, $url, $path, $test);
                    $this->cli->success("Extension '$name' was installed");
                }
            }

            $this->cli->failure("Extension '$name' failed to install");
        }catch(DirectoryExistsException $e){
            $this->cli->failure("Sorry, but the path '$path' already exists, we cannot install to this location\n");
        }catch(InvalidArgumentException $e){
            $this->cli->failure("There seems to be a problem with the Git Repository, are you sure the url is correct?\n");
        }
    }

    public function uninstall(string $name)
    {
        $extension = $this->config->get($name);

        if(!$extension){
            throw new \Exception("The extension '$name' was not found in the configuration");
        }

        $path = $extension['path'];
        $test = $extension['test'];

        /** @var SetupTool */
        $setupTool = $this->getTool('setup');
        $this->cli->print("Removing extension '$name' with path '$path' from system files\n");
        $setupTool->remove($path);
        
        if($this->cli->silenceChannel('stdout', function() use ($setupTool, $test) {
            return $setupTool->test($test);
        }) === false){
            if($path == "/" || strpos($path, ".") === 0 || strpos($path, "extensions") === false){
                throw new \Exception("Refusing to work with this path, it's dangerous");
            }

            if(!is_dir($path)){
                throw new DirectoryNotExistException($path);
            }

            if($this->gitService->exists($path)){
                $cmd = "rm -rf $path";
                $this->cli->print("{red}WARNING: BE CAREFUL THE PATH IS CORRECT{end}\n");
                $answer = $this->cli->ask("Should we remove the extension directory with the command '$cmd'?",["yes"]);
    
                if($answer === "yes") {
                    $this->cli->passthru($cmd);
                }

                $this->config->remove($name);

                $this->cli->success("Extension '$name' was uninstalled\n");
            }    
        }

        $this->cli->failure("Extension '$name' failed to uninstall\n");
    }

    public function update(string $name)
    {
        $this->cli->failure('TODO: tool command: '.__METHOD__." is not implemented");
        /*
        NEW CODE
        if($name = $cli->getArg("update")){
            try{
                $extension = new ExtensionManager($config);
        
                $list = [];
        
                if($name === 'true'){
                    $list = array_map(function($e){ 
                        return basename($e['path']);
                    }, $extension->list());
                }else{
                    $list = [$name];
                }
        
                foreach($list as $name){
                    $this->cli->print("Updating extension '$name'\n");
                    if($extension->update($name)){
                        $this->cli->success("Extension '$name' was updated");
                    }else{
                        $this->cli->failure("Extension '$name' has failed to update");
                    }
                }
            }catch(DirectoryNotExistException $e){
                $this->cli->failure("Can't update extension '$name' as the directory that it was expected to be in was missing");
            }
        }

        OLD CODE
        $path = $this->config->getPath('tools', '/extensions/$name');

		$repo = new Git();
		if($repo->exists($path)){
			$extensionConfig = new \DDT\Config\ExtensionConfig($path);

			$this->cli->print("Pulling branch '{yel}".$repo->branch($path)."{end}' from repository '{yel}".$repo->remote($path)."{end}'\n");
			$repo->pull($path);

			$hook = new Hook($extensionConfig);
			$hook->run(Hook::AFTER_PULL);
			
			$this->cli->print("Pushing branch '{yel}".$repo->branch($path)."{end}' to repository '{yel}".$repo->remote($path)."{end}'\n");
			$repo->push($path);

			return true;
		}

		return false;
        */
    }

    public function list()
    {
        // get list of configured extensions
        // get list of extensions from the filesystem
        // foreach configured extension, test whether things work
        // when an extension is found, remove it from the list of extensions in the filesystem
        // the remaining extensions from the filesystem, are they executable?
        // we should show a table of information about the state of each extension found
        // we do like a venn diagram of configured and installed extensions regarding their status
        try{
            $extensionList = $this->config->list();

            /** @var Table $table */
            $table = container(Table::class);
            // table headers
            $table->addRow(["{yel}Name{end}", "{yel}Url{end}", "{yel}Path{end}"]);
            // table body
            foreach($extensionList as $name => $e){
                $table->addRow([$name, $e['url'], $e['path']]);
            }

            $this->cli->print($table->render(true));
        }catch(ConfigWrongTypeException $e){
            $this->cli->failure($e->getMessage());
        }
    }
}