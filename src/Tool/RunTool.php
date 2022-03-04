<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI;
use DDT\CLI\ArgumentList;
use DDT\Config\ProjectConfig;
use DDT\Exceptions\CLI\ArgumentException;
use DDT\Exceptions\Config\ConfigMissingException;
use DDT\Exceptions\Project\ProjectFoundMultipleException;
use DDT\Exceptions\Project\ProjectFoundWrongGroupException;
use DDT\Services\RunService;
use DDT\Text\Table;
use PDO;

class RunTool extends Tool
{
    public function __construct(CLI $cli)
    {
    	parent::__construct('run', $cli);

        $this->setToolCommand('script', null, true);
        $this->setToolCommand('--list', 'list');
    }

    public function getToolMetadata(): array
    {
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
                "{yel}{$this->getEntrypoint()} run{end}: This help",
                "{yel}{$this->getEntrypoint()} run start backendapi mycompany{end}: Run the 'start' script from the 'backendapi' project in the 'mycompany' group",
                "{yel}{$this->getEntrypoint()} run start -- mycompany{end}: Run the 'start' script from the ALL the projects in the 'mycompany' group",
                "{yel}{$this->getEntrypoint()} run start mycompany api-project{end}: The same command as above, but using anonymous parameters",
                "{yel}{$this->getEntrypoint()} run --list{end}: Will output all the possible scripts that it's possible to run",
            ],
            'notes' => [
                "- You can not use * as a wildcard, just in case you are wondering why, because",
                "\tof shell expansion, when you use *, your shell environment passes instead a",
                "\thuge list of files and directories that are in your current directory, so",
                "\tinstead we will use -- which is easy enough to type",
                "- You can use -- as a wildcard as a placeholder for the project name, but in",
                "\tthis case, you must provide the group name as it's meaning it 'every project'",
                "- You can not use -- as a wildcard as a placeholder for the group name",
            ]
        ];
    }

    public function list(ProjectConfig $config): void
    {
        /* @var Table $table */
        $table = container(Table::class);
        $table->addRow(["{yel}Project{end}", "{yel}Group{end}", "{yel}Script Name{end}", "{yel}Script Command{end}"]);

        foreach($config->listProjects() as $path => $project){
            $projectConfig = $config->getProjectConfig($project['name'], $path);
            foreach($projectConfig->listScripts() as $script => $scriptCommand){
                if(is_array($scriptCommand)) {
                    $scriptCommand = '{grn}* sequence({end}' . implode(', ', $scriptCommand) . '{grn}){end}';
                }

                $table->addRow([$project['name'], implode(', ', $project['group']), $script, $scriptCommand]);
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

    public function script(ProjectConfig $config, RunService $runService, string $script, string $project, ?string $group=null): void
    {
        try{
            // Ignore the first three arguments, they would be script, group, project
            // This will not work when you don't have project specified, cause it wouldn't know
            // how to differentiate between a project name and an extra argument to forward on
            $arguments = new ArgumentList($this->cli->getArgList(), 3);

            $runService->reset();

            $wildcard = '--';

            // No project, no script to run
            if(empty($project)){
                throw new ArgumentException("The project parameter cannot be empty under any circumstances");
            }

            // You can't use the wildcard, when the group is valid, it doesn't make any sense
            if($project === $wildcard && $group === null){
                throw new ArgumentException("The project parameter cannot be '$wildcard' when the group is not given or specified");
            }

            // Make the project list from every project inside a particular group
            if($project === $wildcard && $group !== null){
                $projectList = $config->listProjectsInGroup($group);
                $projectList = array_map(function($v){ return $v['name']; }, $projectList);
            }

            // Find a project that is only registered once in any group
            if($project !== $wildcard && $group === null){
                $projectList = $config->listProjectsByName($project);
                if(count($projectList) > 1){
                    throw new ProjectFoundMultipleException($project);
                }
                $data = array_shift($projectList);
                $group = array_shift($data['group']);
                $projectList = [$project];
            }

            // Find a project which is registered in a particular group
            if($project !== $wildcard && $group !== null){
                $projectList = $config->listProjectsByName($project);
                if(count($projectList) > 1){
                    throw new ProjectFoundMultipleException($project);
                }
                $data = array_shift($projectList);
                if(!in_array($group, $data['group'])){
                    throw new ProjectFoundWrongGroupException($project, $group);
                }
                $projectList = [$project];
            }

            // Couldn't find anything, time to die!
            if(empty($projectList)){
                $this->cli->failure("The project '$project' was not found");
            }

            foreach($projectList as $projectName){
                $runService->run($script, $projectName, $group, $arguments);
            }
        }catch(ConfigMissingException $exception){
            $this->cli->failure("The project directory for '$project' in group '$group' was not found");
        }
    }
}
