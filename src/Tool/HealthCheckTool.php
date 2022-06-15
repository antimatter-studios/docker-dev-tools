<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI;
use DDT\Model\Metadata\ToolMetadataModel;

class HealthCheckTool extends Tool
{
    private $description =  "This tool will run various probes against the system to check things are working normally," .
                            "then report back in sections depending on the systems configuration to show each group." .
                            "Depending on the software installed, will depend on the health checks made. It's possible" .
                            "to customise what health checks to run by default and even to select groups by name from the" .
                            "project list to show particular results depending on use-case";

    private $shortDescription = "Display various health checks about the current working state";

    public function __construct(CLI $cli)
    {
        parent::__construct('health-check', $cli);
        //$this->setToolAlias('hc');
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
        $t = new ToolMetadataModel($this->getToolName(), 'Health Check', $this->getToolEntrypoint());
        $t->setDescription($this->description, $this->shortDescription);
        $t->addCommands("Running of the NGINX Front End Proxy Container",
            [
                "start: Run the Nginx proxy, with an optional assignment for the network name to use",
                "stop: Stop the Nginx proxy",
                "restart: Restart the proxy",
                "reload: Reload the NGINX Configuration",
            ]
        );

        $t->addCommands("Logging",
            [
                "logs: View the logs from the Nginx proxy container",
                "logs-f: View and follow the logs from the Nginx proxy container",
            ]
        );

        $t->addCommands("Network Configuration",
            [
                "add-network <network-name>: Add a new network to a running proxy without needing to restart it",
                "remove-network <network-name>: Remove an existing network from the proxy container so it stops monitoring it",
            ]
        );

        $t->addCommands("General",
            [
                "nginx-config: Output the raw /etc/nginx/conf.d/default.conf which is generated when containers start and stop",
                "status: Show the domains that the Nginx proxy will respond to",
                "container-name: Get/Set the name to give to this container. Pass a second parameter for the container name if you wish to set it",
                "docker-image: Get/Set the docker image name to run. Pass a second parameter for the docker image if you with to set it",
            ]
        );

        $t->setExamples([
            "- ddt proxy logs-f {grn}- follow the log output for the proxy{end}",
            "- ddt proxy start {grn}- start the proxy{end}",
        ]);

        return $t->renderHelp();
    }
}