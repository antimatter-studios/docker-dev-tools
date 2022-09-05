<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI;
use DDT\CLI\ArgumentList;
use DDT\Config\Sections\ProjectConfig;
use DDT\Exceptions\Config\ConfigMissingException;
use DDT\Exceptions\Project\ProjectNotFoundException;
use DDT\Model\Project\ProjectListModel;
use DDT\Model\Project\ProjectModel;
use DDT\Model\Script\RunConfigurationModel;
use DDT\Services\ProjectService;
use DDT\Services\RunService;
use DDT\Text\Table;

class RunTool extends Tool
{
    /** @var ProjectConfig */
    private $projectConfig;

    /** @var ProjectService */
    private $projectService;

    /** @var RunService */
    private $runService;

    public function __construct(CLI $cli, ProjectConfig $projectConfig, ProjectService $projectService, RunService $runService)
    {
    	parent::__construct('run', $cli);

        $this->projectConfig = $projectConfig;
        $this->projectService = $projectService;
        $this->runService = $runService;

        $this->setToolCommand('script', null, true);
        $this->setToolCommand('--list', 'list');
        $this->setToolCommand('--show', 'show');
    }

    public function getToolMetadata(): array
    {
        $entrypoint = $this->getEntrypoint();
        
        return [
            'title' => 'Script Runner',
            'short_description' => 'A tool to run scripts configured as part of projects',
            'description' => [
                "This tool allows projects to define scripts that will do actions, similar to 'yarn start'.",
                "However this tool allows projects to define dependencies and this allows projects to start",
                "and stop their dependencies as each project requires. Making developing with complex stacks",
                "of software easier because developers can develop orchestrated stacks of software to run on",
                "demand instead of requiring each developer to know each project and each dependency and how",
                "to start them",
            ],
            'examples' => [
                "{yel}$entrypoint run{end}: This help",
                "{yel}$entrypoint run start api-project my-company{end}: Run the 'start' script from the 'backendapi' project in the 'mycompany' group",
                "{yel}$entrypoint run start --group=my-company{end}: Run the 'start' script from the ALL the projects in the 'mycompany' group",
                "{yel}$entrypoint run start api-project my-company{end}: The same command as above, but using anonymous parameters",
                "{yel}$entrypoint run --list{end}: Will output all the possible scripts that it's possible to run",
                "{yel}$entrypoint run --list --group=mycompany{end}: Will limit the output from --list to only scripts within a particular group",
                "{yel}$entrypoint run --list --project=api-project{end}: Will limit the output from --list to only scripts within a particular project",
                "{yel}$entrypoint run --list --script=start{end}: Will limit the output from --list to only entries which match a particular script",
                "{yel}$entrypoint run --list --script=start{end}: Will limit the output from --list to only entries which match a particular script",
                "{yel}$entrypoint run --show start api-project{end}: Will show the entrire script dependency tree for this script 'start' on the project 'api-project'",
                "{yel}$entrypoint run s3api -- list-buckets{end}: An example from fakews project, it uses the -- escape sequence to pass parameters to awscli",
            ],
            'notes' => [
                "- Use -- in your command line to use the bash escape sequence that will take all the arguments to the right and pass",
                "\tthem to the script verbatim. This is useful when you want to not specific project or group, then you can use this to",
                "\tto stop the processor from finding the wrong arguments",
                "- To the --list command, you can combine --group --project --script together"
            ]
        ];
    }

    private function resolveProjectList(ArgumentList $arguments, string $script, ?string $project, ?string $group): ProjectListModel
    {
        // remove project if it was specified
        if($project !== null) $arguments->shift();
        // remove group if it was specified
        if($group !== null) $arguments->shift();
        // remove -- arg if found
        $arguments->remove('--');

        return $this->projectService
            ->listProjectsByScript($script)
            ->filter(function($config) use ($project) {
                return $project === null || $project === $config->getName();
            })
            ->filter(function($config) use ($group) {
                return $group === null || $config->hasGroup($group);
            });
    }

    public function list(?string $name=null, ?string $script=null, ?string $group=null): void
    {
        /* @var Table $table */
        $table = container(Table::class);
        $table->addRow(["{yel}Project{end}", "{yel}Group{end}", "{yel}Script Name{end}", "{yel}Script Command{end}"]);

        /** @var ProjectModel $project */
        foreach($this->projectService->listProjects() as $project){
            try{
                $config = $project->getConfig();

                foreach($config->listScripts() as $scriptName => $scriptCommand){
                    if(is_array($scriptCommand)) {
                        $scriptCommand = '{grn}* sequence({end}' . implode(', ', $scriptCommand) . '{grn}){end}';
                    }
    
                    if($group !== null && !$project->hasGroup($group)){
                        continue;
                    }
    
                    if($script !== null && $script !== $scriptName){
                        continue;
                    }
    
                    if($name !==null && $name !== $project->getName()){
                        continue;
                    }
    
                    $table->addRow([$project->getName(), $project->getGroups()->toCsv(), $scriptName, $scriptCommand]);
                }
            }catch(ProjectNotFoundException $e){
                // This exception is thrown when a registered project has no project configuration
                // So if this is the case, we just need to skip over this project instead of erroring out
            }
        }
        
        $this->cli->print($table->render());
        $this->cli->print(implode("\n", [
            "{yel}Notes{end}:",
            "- {grn}* A sequence is a set of command names which are run in sequence{end}",
            "- If the script takes extra parameters, it's required to pass the group parameter",
            "\tas the script engine can't tell whether one string is an argument or a group name\n",
        ]));
    }

    public function show(string $script, ?string $project=null, ?string $group=null): void
    {
        try{
            $arguments = new ArgumentList($this->cli->getArgList(), 2);
            $projectList = $this->resolveProjectList($arguments, $script, $project, $group);

            $this->runService->reset();

            // Couldn't find anything, time to die!
            if(empty($projectList)){
                $this->cli->failure("\nThe script '$script' requested was not found, please look at the above table to see what options are available");
            }

            // Resolve the project list and dependency tree into a final run list
            [$runlist] = $this->runService->resolve($script, $projectList);

            $print = function(RunConfigurationModel $r, int $indentLevel) use (&$print): void {
                $this->cli->print(str_repeat("\t", $indentLevel) . "- {blu}" . $r->getName()."{end}\n");
                foreach($r->getCommandList() as $script => $commandLine){
                    $this->cli->print(str_repeat("\t", $indentLevel + 1) . "{yel}script{end}: '$script' => '$commandLine'\n");
                }

                if($r->hasDependencies()){
                    $this->cli->print(str_repeat("\t", $indentLevel + 1) . "{mag}dependencies{end}:\n");
                    foreach($r->getDependencies() as $d){
                        $print($d, $indentLevel+2);
                    }
                }
            };

            foreach($runlist as $r){
                $print($r, 0);
            }
            // break reference
            $print = null;
        }catch(ConfigMissingException $e){
            $this->cli->failure("The project directory for '$project' in group '$group' was not found");
        }catch(ProjectNotFoundException $e){
            $this->cli->failure($e->getMessage());
        }
    }

    public function script(string $script, ?string $project=null, ?string $group=null): void
    {
        $scriptList = explode(',', $script);

        foreach($scriptList as $script){
            try{
                $arguments = new ArgumentList($this->cli->getArgList(), 2);
                $projectList = $this->resolveProjectList($arguments, $script, $project, $group);

                $this->runService->reset();

                // Couldn't find anything, time to die!
                if(empty($projectList)){
                    $this->list($project, null, $group);
                    $this->cli->failure("\nThe script '$script' requested was not found, please look at the above table to see what options are available");
                }

                // Resolve the project list and dependency tree into a final run list
                [$runlist] = $this->runService->resolve($script, $projectList);

                // Iterate the run list and execute each project in step
                foreach($runlist as $config){
                    $this->runService->run($config, $arguments);
                }

                if(empty($runlist)){
                    $this->cli->failure("There was nothing to run\n");
                }
            }catch(ConfigMissingException $e){
                $this->cli->failure("The project directory for '$project' in group '$group' was not found");
            }catch(ProjectNotFoundException $e){
                $this->cli->failure("The project was not found '" . $e->getProject() . "'");
            }
        }
    }
}
