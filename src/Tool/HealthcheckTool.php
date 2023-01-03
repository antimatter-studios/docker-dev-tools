<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI\CLI;
use DDT\Model\Metadata\ToolMetadataModel;
use DDT\Model\Project\ProjectModel;
use DDT\Services\ProjectService;

class HealthcheckTool extends Tool
{
    private $description =  "This tool will run various probes against the system to check things are working normally," .
                            "then report back in sections depending on the systems configuration to show each group." .
                            "Depending on the software installed, will depend on the health checks made. It's possible" .
                            "to customise what health checks to run by default and even to select groups by name from the" .
                            "project list to show particular results depending on use-case";

    private $shortDescription = "Display various health checks about the current working state";

    /** @var ProjectService */
    private $projectService;

    public function __construct(CLI $cli, ProjectService $projectService)
    {
        parent::__construct('healthcheck', $cli);
        $this->setToolCommand('run', null, true);

        $this->projectService = $projectService;
    }

    public function getToolMetadata(): array
    {
        return [
            "description" => $this->description,
            "short_description" => $this->shortDescription
        ];
    }

    public function help(): string
    {
        $entrypoint = $this->getToolEntrypoint();

        $t = new ToolMetadataModel($this->getToolName(), 'Health Check', $this->getToolEntrypoint());
        $t->setDescription($this->description, $this->shortDescription);

        $t->setExamples([
            "- $entrypoint <project name> {grn}- Run the healthchecks for a particular <project name>{end}",
        ]);

        return $t->renderHelp();
    }

    public function run(string $project, string $group=null)
    {
        /** @var ProjectModel */
        $project = $this->projectService->findProject($project, $group);
    }
}