<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI;
use DDT\Config\External\ComposerProjectConfig;
use DDT\Config\External\NodeProjectConfig;
use DDT\Config\External\StandardProjectConfig;
use DDT\Config\ProjectConfig;
use DDT\Exceptions\Git\GitNotARepositoryException;
use DDT\Exceptions\Project\ProjectExistsException;
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

        foreach(['list', 'set-type', 'add-group', 'remove-group', 'add-project', 'remove-project'] as $command){
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
                "{cyn}General Functionality{end}:",
                "\tlist: Will list a table with all the registered projects with their groups, paths, vcs info etc",
                
                "\n\t{cyn}Managing Projects{end}:",
                "\tadd-project {yel}<path> [optional: <project-name> <type> <group> <vcs> <remote-name>]{end}: Will add a new project that already exists on the disk.",
                "\t\t{yel}<project-name>{end}: Can be autodetected from the folder name that is given in the path",
                "\t\t{yel}<type=node|composer|ddt>{end}: Can be autodetected from looking at the files contained in the project, if given must be one of the types defined below",
                "\t\t{yel}<group>{end}: Can be empty, but groups may become required when two projects have the same name, or you can use path in other functionality to reduce the ambiguity",
                "\t\t{yel}<vcs>{end}: Can be autodetected from the projects contents. But Git is only supported right now, but this is the clone url",
                "\t\t{yel}<remote-name>{end}: Defaults to 'origin', but this is very git-centric and may change if other vcs systems are supported in the future",
                "\tremove-project {yel}<project-name> [optional: <path>]{end}: Remove a project, path is optional because maybe there are multiple projects with the same name",

                "\n\t{cyn}Managing Groups{end}:",
                "\tadd-group {yel}<project-name> <group> [optional: <path>]{end}: Add a project to a group, path is optional because if you have two projects with the same name, you can add the path to reduce the ambiguity which project to manipulate",
                "\tremove-group {yel}<project-name> <group> [optional: <path>]{end}: Remove a project from a group, path is optional because of the same reasons as with add-project",

                "\n\t{cyn}Project Types{end}:",
                "\tThese just define where the configuration will be stored, it has one of the following values:\n",
                "\tnone: {yel}There is no project type detected or configuration that could be read' file",
                "\tnode: This project type will use the 'package.json' file.",
                "\tcomposer: This project type will use the 'composer.json' file.",
                "\tddt: {yel}(default if no type given){end} This project will use the 'ddt-project.json' file",
            ],
            'notes' => [
                "- If adding a new project which already exists, you have to be careful that the groups over",
                "\toverlap. As it would create a situation where you would have the same project existing in",
                "\tmultiple groups with the same name, if you tried to operate upon it, which one would",
                "\tyou target? Since they both have the same name, but different directories, so could have",
                "\tdifferent code too. Hence this situation is not possible to tolerate",
                "- If adding a new project which already exists, you cannot leave the groups empty, as again",
                "\tit makes it hard or impossible to know how to work with it.",
            ]
        ];
    }

    public function isProjectType(string $type=null): bool
    {
        return in_array($type, ['composer', 'node', 'ddt', 'none']);
    }

    public function list(): void
    {
        $this->cli->print("{blu}Project Group List:{end}\n");

        $projectList = $this->config->listProjects();

        $table = container(Table::class);
        $table->setColumnMapping(['project', 'group', 'path', 'type', 'vcs', 'remote']);
        $table->addRow(['{yel}Project{end}', '{yel}Group{end}', '{yel}Path{end}', '{yel}Type{end}', '{yel}Repository Url{end}', '{yel}Remote Name{end}']);

        if(empty($projectList)){
            $table->addRow(['There are no projects']);
        }

        foreach($projectList as $project) {
            // To allow projects with empty group lists to display using the same logic as below
            if(empty($project['group'])){
                $project['group'][] = '';
            }

            foreach($project['group'] as $group){
                // If a project has multiple projects, render each as a separate empty row 
                // (apart from group), with a little "+ next" to it
                if(empty($project['name'])){
                    $group = "+ $group";
                }
                $table->addRow([$project['name'], $group, $project['path'], $project['type'], $project['vcs'], $project['remote']]);
                // For all extra rows created by multiple groups, leave all the other columns empty except group
                $project = array_fill_keys(['name', 'path', 'type', 'vcs', 'remote'], null);
            }
            
        }

        $this->cli->print($table->render());
    }

    public function addGroup(string $project, string $group, ?string $path=null): void
    {
        $pathText = !empty($path) ? " with given path '$path'" : "";

        $this->cli->print("{blu}Adding group '$group' to project '$project' $pathText{end}\n");

        if($this->config->addGroup($project, $group, $path)){
            $this->cli->print("{grn}Group was added, listing projects{end}...\n");

            $this->list();
        }else{
            $this->cli->print("{red}Adding the group '$group' to project '$project' has failed{end}\n");
        }
    }

    public function removeGroup(string $project, string $group, ?string $path=null): void
    {
        $pathText = !empty($path) ? " with given path '$path'" : "";

        $this->cli->print("{blu}Removing group '$group' from project '$project' $pathText{end}\n");

        if($this->config->removeGroup($project, $group, $path)){
            $this->cli->print("{grn}Project was removed, listing projects{end}...\n");
            $this->list();
        }else{
            $this->cli->print("{red}Removing the group '$group' from project '$project' has failed{end}\n");
        }
    }

    public function setType(string $type, string $project, ?string $group=null, ?string $path=null): void
    {
        $pathText = !empty($path) ? " with given path '$path'" : "";

        $this->cli->print("{blu}Changing type of project '$project' to '$type'{end}\n");

        if(!$this->isProjectType($type)){
            $this->cli->failure("The requested type was not valid");
        }

        if($this->config->setType($project, $group, $path, $type)){
            $this->cli->success("Project '$project' type was changed to '$type'");
        }else{
            $this->cli->failure("Project '$project' failed to change type to '$type'");
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
            $type = 'composer';
        }else if($hasComposerJson){
            $type = 'composer';
        }else if($hasPackageJson){
            $type = 'node';
        }else{
            $type = 'none';
        }

        return $type;
    }

    public function addProject(?string $path=null, ?string $project=null, ?string $type=null, ?string $group=null, ?string $vcs=null, ?string $remote='origin'): bool
    {
        $this->cli->print("{blu}Adding project{end}\n");

        if(empty($path)){
            $path = getcwd();
            $this->cli->print("{yel}WARNING{end}: No path provided so defaulting to the current directory '$path'\n");
        }
        
        $updatedPath = realpath($path);
        
        // Path does not exist, but vcs parameter was not specified so cannot clone into this location
        if(!$updatedPath && $vcs === null){
            $this->cli->failure("{red}The path given '$path' does not exist, but no --vcs parameter with a repository to clone from was given, please check and try again{end}\n");
        }
        
        // Path does not exist, but vcs is not null, check it's valid and if so, clone project into that location
        if(!$updatedPath && $vcs !== null){
            if($this->repoService->exists($vcs) && $this->repoService->clone($vcs, $path)){
                $this->cli->print("{grn}Repository cloned, continuing...{end}\n");
                $updatedPath = realpath($path);
            }else{
                $this->cli->failure("{red}An attempt to clone the repository was made, but failed. See the terminal output to try to correct the problem manually\n");
            }
        }

        $path = $updatedPath;

        if($type === null){
            $type = $this->autoDetectProjectType($path);
            $this->cli->print("Auto-detecting project type: '$type'\n");
        }

        if($this->isProjectType($type) === false){
            $this->cli->failure("{red}The project type '$type' given or auto-detected, can not be recognised. See help for options{end}\n");
        }

        $path = rtrim($path, '/');

        if($project === null){
            $project = basename($path);
            $this->cli->print("No project name given, using directory name '$project'\n");
        }

        if($vcs === null){
            try{
                $vcs = $this->repoService->getRemote($path, $remote);
            }catch(GitNotARepositoryException $e){
                // do nothing
                $this->cli->print("No git repository was found, nor one was given through the command line\n");
            }
        }

        try{
            if($this->config->addProject($path, $project, $type, $group, $vcs, $remote)){
                $this->cli->print("{grn}The project '$project' with type '$type' was successfully added with the path '$path'{end}\n");
                return true;
            }
        }catch(ProjectExistsException $e){
            $this->cli->failure("The project already exists, please just edit it if you want to change settings\n");
        }

        $this->cli->failure("The project '$project' failed\n");
    }

    public function removeProject(string $project, ?string $path=null, ?bool $delete=false): bool
    {
        $this->cli->print("{blu}Removing Project{end}\n");
        $this->cli->debug("project", "Delete functionality is not written yet\n");

        if($this->config->removeProject($project, $path)){
            $this->cli->print("{grn}The project '$project' was successfully removed'{end}\n");
            return true;
        }else{
            $this->cli->print("{red}The project '$project' failed to be remove{end}\n");
            return false;
        }
    }
}
