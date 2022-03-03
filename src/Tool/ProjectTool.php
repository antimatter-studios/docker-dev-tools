<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI;
use DDT\Config\External\ComposerProjectConfig;
use DDT\Config\External\NodeProjectConfig;
use DDT\Config\External\StandardProjectConfig;
use DDT\Config\ProjectConfig;
use DDT\Services\GitService;
use DDT\Text\Table;

class ProjectTool extends Tool
{
    /** @var \DDT\Config\ProjectConfig  */
    private $config;

    /** @var \DDT\Services\GitService */
    private $repoService;

    public function __construct(CLI $cli, ProjectConfig $config, GitService $repoService)
    {
    	parent::__construct('project', $cli);

        $this->config = $config;
        $this->repoService = $repoService;

        foreach(['list', 'add-group', 'remove-group', 'add-project', 'remove-project'] as $command){
            $this->setToolCommand($command);
        }
    }

    public function getToolMetadata(): array
    {
        return [
            'tool' => 'Project Management Tool',
            'short_description' => 'A tool to manage projects installed and control them using scripts and hooks',
            'description' => [
                "This tool allows projects to be installed and managed by the tooling system.",
                "It can run scripts, and perform actions upon the projects, using functionality",
                "or scripts installed within the projects themselves",
            ],
            'options' => [
                "{cyn}Managing Groups{end}:",
                "\tlist: List the project groups",
                "\tadd-group --name=group-name: Create a new project group.",
                "\tremove-group --name=group-name: Remove a project group.\n",

                "{cyn}Adding Projects{end}:",
                "\tadd-project --name=<project-name>: Will add a new project that already exists on the disk.",
                "\t--group=<group>: (REQUIRED) The group to which this project will be added",
                "\t--path=<path>: (REQUIRED) The location on the filesystem for this project",
                "\t--type=node|composer|ddt>: (OPTIONAL: default=ddt) One of the supported project types. {yel}(See Project Type list below){end}\n",

                "{cyn}Removing Projects{end}:",
                "\tremove-project --name=project-name: Remove the project from the group given.",
                "\t--group: (REQUIRED) The group from which this project will be removed",
                "\t--delete: (OPTIONAL) {red}**DANGEROUS**{end} This option will not only remove the project from a group, but delete the files from disk\n",

                "{cyn}Project Types{end}:",
                "\tThese just define where the configuration will be stored, it has one of the following values:\n",
                "\tnode: This project type will use the 'package.json' file.",
                "\tcomposer: This project type will use the 'composer.json' file.",
                "\tddt: {yel}(default if no type given){end} This project will use the 'ddt-project.json' file",
            ],
        ];
    }

    public function isProjectType(string $type=null): bool
    {
        return in_array($type, ['composer', 'node', 'ddt']);
    }

    public function list(): void
    {
        $this->cli->print("{blu}Project Group List:{end}\n");

        $groupList = $this->config->listGroup();

        $table = container(Table::class);
        $table->addRow(['{yel}Group{end}', '{yel}Project{end}', '{yel}Path{end}', '{yel}Type{end}', '{yel}Repository Url{end}', '{yel}Remote Name{end}']);

        if(empty($groupList)){
            $table->addRow(['There are no groups']);
        }
        
        foreach($groupList as $group => $projectList) {
            if(empty($projectList)){
                $table->addRow([$group, 'There are no projects']);
            }

            foreach($projectList as $project => $config) {
                $table->addRow([$group, $project, $config['path'], $config['type'], $config['repo']['url'], $config['repo']['remote']]);
            }
        }

        $this->cli->print($table->render());
    }

    public function addGroup(string $name): void
    {
        $this->cli->print("{blu}Adding group '$name'{end}\n");

        if($this->config->addGroup($name)){
            $this->cli->print("{grn}Project was added, listing projects{end}...\n");

            $this->list();
        }else{
            $this->cli->print("{red}Adding the group '$name' has failed (maybe it already exists?){end}\n");
        }
    }

    public function removeGroup(string $name): void
    {
        $this->cli->print("{blu}Removing group '$name'{end}\n");

        if($this->config->removeGroup($name)){
            $this->cli->print("{grn}Project was removed, listing projects{end}...\n");
            $this->list();
        }else{
            $this->cli->print("{red}Removing the group '$name' has failed (maybe it doesn't exist?){end}\n");
        }
    }

    private function autoDetectProjectType(string $path): ?string
    {
        $hasComposerJson = file_exists("$path/" . ComposerProjectConfig::defaultFilename);
        $hasPackageJson = file_exists("$path/" . NodeProjectConfig::defaultFilename);
        $hasDefault = file_exists("$path/" . StandardProjectConfig::defaultFilename);

        if($hasDefault) {
            $type = 'ddt';
        }else if($hasComposerJson && $hasPackageJson){
            $type = null;
        }else if($hasComposerJson){
            $type = 'composer';
        }else if($hasPackageJson){
            $type = 'node';
        }else{
            $type = null;
        }

        return $type;
    }

    public function addProjectNew(string $path, string $group){}

    public function addProject(string $group, string $path, ?string $name=null, ?string $type=null, ?string $git=null, ?string $remote='origin'): void
    {
        $this->cli->print("{blu}Adding project{end}\n");

        if($type === null){
            $type = $this->autoDetectProjectType($path);
        }

        if($this->isProjectType($type) === false){
            $this->cli->print("{red}The project type '$type' given or auto-detected, can not be recognised. See help for options{end}\n");
            return;
        }

        if($name === null){
            $name = basename($path);
        }

        if(is_dir($path)){
            $this->cli->print("Project '$name' exists?: {grn}Yes{end}\n");

            // Resolve any relative paths into absolute ones
            $path = realpath($path);

            try{
                $remoteUrl = $this->repoService->getRemote($path, $remote);
                $this->cli->print("Git Remote Url ($remote): {grn}$remoteUrl{end}\n");    
            }catch(\Exception $e){
                $this->cli->print("Git Remote Url ($remote): {red}Error occurred, is this a git directory? Will skip this...{end}\n");
                // Prevent the git configuration from being saved
                $remoteUrl = null;
            }
            
            if($this->config->addProject($group, $name, $path, $type, $remoteUrl, $remote)){
                $this->cli->success("The project '$name' with type '$type' was successfully added to the group '$group'\n");
            }else{
                $this->cli->failure("The project '$name' failed to be added to the group '$group'\n");
            }
        }else if($git !== null){
            $this->cli->print("Project '$name' exists?: {red}No{end}\n");
        }else{
            $this->cli->print("{red}Project '$name' in directory '$path' does not exist, but no --git option given in order to clone it{end}\n");
        }
    }

    public function removeProject(string $group, string $name, ?bool $delete=false): void
    {
        $this->cli->print("{blu}Removing Project{end}\n");

        if($this->config->removeProject($group, $name)){
            $this->cli->success("The project '$name' was successfully removed from the group '$group'\n");
        }else{
            $this->cli->failure("The project '$name' failed to be removed from the group '$group'\n");
        }
    }
}
