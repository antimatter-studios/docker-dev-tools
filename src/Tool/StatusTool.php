<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI\CLI;
use DDT\Config\SystemConfig;
use DDT\Contract\ToolRegistryInterface;
use DDT\Text\Text;

class StatusTool extends Tool
{
    /** @var Text */
    private $text;

    /** @var SystemConfig  */
    private $config;

    /** @var ToolRegistryInterface */
    private $toolRegistry;

    public function __construct(CLI $cli, Text $text, SystemConfig $config, ToolRegistryInterface $toolRegistry)
    {
    	parent::__construct('status', $cli);

        $this->text = $text;
        $this->config = $config;
        $this->toolRegistry = $toolRegistry;

        $this->setToolCommand('main', null, true);
    }

    public function getToolMetadata(): array
    {
        $entrypoint = $this->cli->getScript(false) . " " . $this->getToolName();

        return [
            'title' => 'System Status',
            'short_description' => 'A tool to provide a quick status breakdown of resources',
            'description' => [
                "This tool will provide a series of status feedbacks from various tools like",
                "the proxy, the dns, and various installed projects, with the ability to call", 
                "specific groups of projects"
            ],
            'examples' => [
                "{yel}Usage Example:{end} $entrypoint - Will return status information the proxy, the dns, and for every project in every group (could be a large list)",
                "{yel}Usage Example:{end} $entrypoint <group> - Will return status information for the proxy, the dns, and a specific group of projects",
            ],
        ];
    }

    public function main()
    {
        /** @var ProxyTool */
        $proxy = $this->toolRegistry->getTool('proxy');
        $proxy->status();

        /** @var DnsTool */
        $dns = $this->toolRegistry->getTool('dns');
        $dns->status();
    }
}

// FIXME: This code is being moved into project configurations which means it's broken for now
// $projectConfig = $systemConfig->getProjectConfig('api-server');

// $config = $projectConfig; //new \DDT\Config\ProjectConfig();

// $this->cli->title("Healthchecks", "Hopefully nobody died.....");

// $debug = $cli->hasArg("debug");

// foreach($argv as $arg){
//     if($arg === "--list"){
// 		$list = $config->listHealthcheck();
// 		print("Listing Healthchecks...\n");
// 		foreach($list as $healthcheck){
// 		    print("\t$healthcheck\n");
//         }
// 		die();
//     }
// }

// $list = $config->listHealthcheck();
// $table = new Table(new Text);
// $table->setRightPadding(5);

// foreach($list as $name){
// 	$healthcheck = $config->getHealthcheck($name);

// 	$result = $healthcheck->run();

//     $url = $result['url'];

// 	$access = $result['connected']
//         ? $this->text->green($this->text->checkIcon() . " Connected")
//         : $this->text->red($this->text->crossIcon() . " Failed");

// 	if(array_key_exists('http_code', $result)){
// 		$http_status = $result['http_code'] === 200
// 			? $this->text->green($this->text->checkIcon() . " HTTP Success ({$result['http_code']})")
// 			: $this->text->red($this->text->crossIcon() . " HTTP Failed ({$result['http_code']})");
//     }else{
// 		$http_status = "";
//     }

// 	if(array_key_exists('success', $result)){
// 		if($result['success']){
// 			$data_ok = $this->text->green($this->text->checkIcon() . " Data OK");
// 		}else{
// 			$data_ok = $this->text->red($this->text->crossIcon() . " Data Failed");
// 		}
//     }else{
// 	    $data_ok = "";
//     }

// 	if(array_key_exists('db_status', $result) && is_array($result['db_status'])){
// 	    $db_ok = array_reduce(array_keys($result['db_status']), function($c, $i) use ($result){
// 	        $status = $result['db_status'][$i] === true ? $this->text->green($i) : $this->text->red($i);
//             return trim("$c, $status", " ,");
//         }, "");

// 		$data_ok = "$data_ok Databases ($db_ok)";
//     }

// 	$table->addRow([
// 		ucwords(str_replace(["-","_"], " ", $name)).":",
//         $access,
//         $http_status,
//         $data_ok,
//         "URL: $url",
//     ]);

// 	if($reasons = \DDT\Helper\Arr::get($result, 'reasons')){
// 		array_map(function($r) use ($table) {
// 			$table->addRow(["\t$r"], true);
// 		}, $reasons);
// 	}


// 	if(is_string($debug)){
// 		print("Server Replied: $debug\n");
// 	}
// }

// print($table->render(true));

// if(empty($list)){
//     print($this->text->yellow("There were no healthchecks found, nothing to test\n"));
// }
