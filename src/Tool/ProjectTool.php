<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI\CLI;
use DDT\Config\Project\ComposerProjectConfig;
use DDT\Config\Project\NodeProjectConfig;
use DDT\Config\Project\StandardProjectConfig;
use DDT\Config\Sections\ProjectConfig;
use DDT\Exceptions\Filesystem\DirectoryNotExistException;
use DDT\Exceptions\Git\GitRepositoryNotFoundException;
use DDT\Exceptions\Project\ProjectExistsException;
use DDT\Model\Project\ProjectModel;
use DDT\Model\Project\ProjectPathModel;
use DDT\Services\GitService;
use DDT\Services\ProjectService;
use DDT\Text\Table;

class ProjectTool extends Tool
{
    /** @var \DDT\Services\ProjectService */
    private $projectService;

    /** @var \DDT\Services\GitService */
    private $repoService;

    public function __construct(CLI $cli, ProjectService $projectService, GitService $repoService)
    {
    	parent::__construct('project', $cli);

        $this->repoService = $repoService;
        $this->projectService = $projectService;

        foreach([
            'list',
            'add-path', 'remove-path', 'list-paths', 'dedup-path',
            'add-group', 'remove-group', 'clone-project', 'list-groups',
            'add-project', 'remove-project',
            'pull', 'push',
        ] as $command){
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

                "\n\t{cyn}Managing Paths{end}:",
                "\tlist-paths: List all the registered paths",
                "\tadd-path {yel}<path> [optional: <group>]{end}: Will add a path and automatically include all projects one level deep",
                "\tremove-path {yel}<path>: Will remove a matching path from the list",

                "\n\t{cyn}Managing Projects{end}:",
                "\tadd-project {yel}<path> [optional: <group>]{end}: Will add a new project that already exists on the disk.",
                "\t\t{yel}<path>{end}: A path on the disk for the project itself",
                "\t\t{yel}<group>{end}: Can be empty, but groups may become required when two projects have the same name, or you can use path in other functionality to reduce the ambiguity",
                "\tremove-project {yel}<project-name> [optional: <path>]{end}: Remove a project, see notes regarding path parameter",
                "\tclone-project {yel}<vcs> [optional: <group> <remote-name>]{end}: Will clone a project",
                "\t\t{yel}<vcs>{end}: The url of the repository to clone",
                "\t\t{yel}<group>{end}: Can be empty, but groups may become required when two projects have the same name, or you can use path in other functionality to reduce the ambiguity",
                "\t\t{yel}<remote-name>{end}: Defaults to 'origin', but this is very git-centric and may change if other vcs systems are supported in the future",


                "\n\t{cyn}Managing Groups{end}:",
                "\tlist-groups: List all the groups from all the projects",
                "\tadd-group {yel}<project-name> <group> [optional: <path>]{end}: Add a project to a group, see notes regarding path parameter",
                "\tremove-group {yel}<project-name> <group> [optional: <path>]{end}: Remove a project from a group, see notes regarding path parameter",

                "\n\t{cyn}Project Types{end}:",
                "\tThese just define where the configuration will be stored, it has one of the following values:\n",
                "\tnone: There is no project type detected or configuration that could be read",
                "\tnode: This project type will use the 'package.json' file.",
                "\tcomposer: This project type will use the 'composer.json' file.",
                "\tddt: {yel}(default if no type given){end} This project will use the 'ddt-project.json' file",
            ],
            'notes' => [
                "- If a project has package.json and composer.json, it will default to using composer.json",
                "- If adding a new project which already exists, you have to be careful that the groups over",
                "\toverlap. As it would create a situation where you would have the same project existing in",
                "\tmultiple groups with the same name, if you tried to operate upon it, which one would",
                "\tyou target? Since they both have the same name, but different directories, so could have",
                "\tdifferent code too. Hence this situation is not possible to handle and will fail",
                "- If a project with the same name exists, you are forced to provide a group parameter",
                "- {yel}OPTIONAL PATH PARAMETER{end}: It might be that multiple projects exist on a developers system called 'rest-api'",
                "\tand each needs to be managed separately. Therefore sometimes we need to more accurately select which 'rest-api'",
                "\tto act upon and thats why sometimes {cyn}<path>{end} is needed on some commands to say WHICH 'rest-api'",
                "\tto choose when performing actions",
            ]
        ];
    }

    public function isProjectType(string $type=null): bool
    {
        return in_array($type, ['composer', 'node', 'ddt', 'none']);
    }

    public function list(?string $name=null, ?string $group=null): void
    {
        $this->cli->print("{blu}Complete Project List:{end}\n");

        $projectList = $this->projectService->listProjects();

        $table = container(Table::class);
        $table->setColumnMapping(['project', 'group', 'path', 'type', 'vcs', 'remote']);
        $table->addRow(['{yel}Project{end}', '{yel}Group{end}', '{yel}Path{end}', '{yel}Type{end}', '{yel}Repository Url{end}']);

        if(empty($projectList)){
            $table->addRow(['There are no projects']);
        }

        /** @var ProjectModel $project */
        foreach($projectList as $project) {
            if($name !== null && $project->getName() !== $name) {
                continue;
            }

            if($group !== null && !$project->hasGroup($group)) {
                continue;
            }

            $groupList = $project->getGroups()->getData();

            // If there are no groups, we just output an empty column
            $groupName = empty($groupList) ? '' : array_shift($groupList);
            
            // Obtain path and test whether it exists or not (show to the user invalid paths)
            $path = $project->getPath();
            if(!is_dir($path)){
                $path = "{red}error, path not found:{end} $path";
            }

            $type = $project->getType();
            if($type === "none"){
                $type = "{red}invalid{end}";
            }

            try{
                $repo = $project->getVcs();
                $url = $repo->remote();
                if(!is_dir($path)){
                    $url = "{red}error, path not found{end}";
                }
            }catch(GitRepositoryNotFoundException $e){
                $url = "{red}error, path not a git repository{end}";
            }

            $table->addRow([$project->getName(), $groupName, $path, $type, $url]);

            // For every EXTRA group, we render an empty row with just the "+ group" name to indicate it's an extra group
            foreach($groupList as $groupName){
                $table->addRow([null, "+ $groupName", null, null, null]);
            }
        }

        $this->cli->print($table->render());
    }

    public function listPaths(): void
    {
        $this->cli->print("{blu}Path List{end}\n");

        $table = Table::getInstance();
        $table->addRow(["Path", "Groups"]);

        /** @var ProjectPathModel $item */
        foreach($this->projectService->listPaths() as $item) {
            $table->addRow([$item->getPath(), $item->getGroups()->toCsv()]);
        }

        $this->cli->print($table->render());
    }

    public function listGroups(): void
    {
        $this->cli->print("{blu}Group List:{end}\n");

        $groups = [];

        /** @var ProjectModel $project */
        foreach($this->projectService->listProjects() as $project) {
            $groups = array_merge($groups, $project->getGroups()->getData());
        }

        $groups = array_unique($groups);
        array_map(function($g){
            $this->cli->print(" - " . $g . "\n");
        }, $groups);
    }

    public function addPath(string $path, ?string $group=null): void
    {
        try{
            if($this->projectService->addPath($path, $group)){
                $this->cli->success("Added the project path '$path' and group '$group'\n");
            }
        }catch(DirectoryNotExistException $e){
            $this->cli->failure($e->getMessage());
        }

        $this->cli->failure("Could not add the project path '$path' with the group '$group'\n");
    }

    public function removePath(string $path): void
    {
        try{
            if($this->projectService->removePath($path)){
                $this->cli->success("Removed the project path '$path'\n");
            }
        }catch(DirectoryNotExistException $e){
            $this->cli->failure($e->getMessage());
        }

        $this->cli->failure("Could not remove the project path '$path'\n");
    }

    public function dedupPath(string $path): void
    {
        $this->cli->print("{blu}De-duplicating projects{end}: $path\n");

        $listByPath = $this->projectService->listProjects(ProjectConfig::LIST_PATHS);
        $listByProject = $this->projectService->listProjects(ProjectConfig::LIST_PROJECTS);

        $table = Table::getInstance();
        $table->addRow(['{yel}Name{end}', '{yel}Path{end}']);

        /** @var ProjectModel $search */
        foreach($listByPath as $search){
            /** @var ProjectModel $project */
            foreach($listByProject as $project){
                if ($search->getPath() === $project->getPath()) {
                    $table->addRow([$project->getName(), $project->getPath()]);
                }
            }
        }

        $this->cli->print($table->render());
    }

    public function addGroup(string $project, string $group, ?string $path=null): void
    {
        $pathText = !empty($path) ? " with given path '$path'" : "";

        $this->cli->print("{blu}Adding group '$group' to project '$project' $pathText{end}\n");

        if($this->projectService->addGroup($project, $group, $path)){
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

        if($this->projectService->removeGroup($project, $group, $path)){
            $this->cli->print("{grn}Project was removed, listing projects{end}...\n");
            $this->list();
        }else{
            $this->cli->print("{red}Removing the group '$group' from project '$project' has failed{end}\n");
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

        $this->cli->print("Auto-detecting project type: '$type'\n");

        return $type;
    }

    public function addProject(?string $path=null, ?string $group=null): bool
    {
        $this->cli->print("{blu}Adding project{end}\n");

        if(empty($path)){
            $path = getcwd();
            $this->cli->print("{yel}WARNING{end}: No path provided so defaulting to the current directory '$path'\n");
        }
        
        $updatedPath = realpath($path);
        
        // Path does not exist
        if(!$updatedPath){
            $this->cli->failure("{red}The path given '$path' does not exist, please use the clone-project command instead{end}\n");
        }
        
        $path = $updatedPath;

        $type = $this->autoDetectProjectType($path);

        if($type === 'none'){
            $this->cli->failure("{red}It is not possible to manage projects with an unrecognised project type{end}\n");
        }

        // I think this is redundant now
        if($this->isProjectType($type) === false){
            $this->cli->failure("{red}The project type '$type' given or auto-detected, can not be recognised. See help for options{end}\n");
        }

        $path = rtrim($path, '/');

        $name = null;
        $project = basename($path);

        try{
            if($this->projectService->addProject($path, $name, $group)){
                $this->cli->print("{grn}The project '$project' with type '$type' in group '$group' was successfully added with the path '$path'{end}\n");
                return true;
            }
        }catch(ProjectExistsException $e){
            $this->cli->failure("The project already exists, please just edit it if you want to change settings\n");
        }

        $this->cli->failure("The project '$project' failed\n");
    }

    public function cloneProject(string $vcs, ?string $group=null, ?string $branch=null, ?string $remote='origin'): bool 
    {
        $path = explode('.', basename($vcs));
        $path = current($path);
        $path = getcwd() . "/" . $path;
        $updatedPath = realpath($path);

        if($updatedPath){
            $this->cli->failure("{red}An attempt to clone the repository into an existing directory '$updatedPath'. This is not allowed\n");
        }

        if(!$this->repoService->exists($vcs)){
            $this->cli->failure("{red}There was no repository with the url '$vcs'{end}\n");
        }

        try{
            $repo = $this->repoService->clone($vcs, $path);
            $this->cli->print("{grn}Repository cloned, continuing...{end}\n");
            if(!empty($branch)){
                $this->cli->print("{yel}Repository branch:{end} changed to '$branch'\n");
                $repo->checkout($branch);
            }
            return $this->addProject($path, $group);
        }catch(\Exception $e){
            $this->cli->failure("{red}An attempt to clone the repository was made, but failed. See the terminal output to try to correct the problem manually\n");
        }
    }

    public function removeProject(string $project, ?string $path=null, ?bool $delete=false): bool
    {
        $this->cli->print("{blu}Removing Project{end}\n");
        $this->cli->debug("project", "Delete functionality is not written yet\n");

        if($this->projectService->removeProject($project, $path)){
            $this->cli->print("{grn}The project '$project' was successfully removed'{end}\n");
            return true;
        }else{
            $this->cli->print("{red}The project '$project' failed to be remove{end}\n");
            return false;
        }
    }

    public function pull(?string $filter=null): void
    {
        // for the first version, ignore the filter
        // list every path
        // list every project one level deep inside every path
        // for each project found, test if it's a repository
        // if it's a repo, do a git pull on that repo
        // use the same output semantics as in the previous sync command
        $this->cli->failure("The pull method for '$filter' is not yet implemented");
    }

    public function push(?string $filter=null): void
    {
        // the same as pull, but git push instead
        $this->cli->failure("The push method for '$filter' is not yet implemented");
    }
}
