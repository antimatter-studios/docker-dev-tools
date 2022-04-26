<?php declare(strict_types=1);

use DDT\Autowire;
use DDT\CLI;
use DDT\Debug;
use DDT\Text\Text;
use DDT\Text\Table;
use DDT\Container;
use DDT\Tool\EntrypointTool;
use DDT\Config\SystemConfig;
use DDT\Contract\IpServiceInterface;
use DDT\Contract\DnsServiceInterface;
use DDT\Contract\ToolRegistryInterface;
use DDT\Exceptions\Config\ConfigMissingException;
use DDT\Exceptions\Container\ContainerNotInstantiatedException;
use DDT\Exceptions\Project\ProjectConfigUpgradeException;
use DDT\Exceptions\Tool\ToolCommandNotFoundException;
use DDT\Exceptions\Tool\ToolNotFoundException;
use DDT\Exceptions\Tool\ToolNotSpecifiedException;
use DDT\Services\ConfigGeneratorService;
use DDT\Services\DnsMasqService;
use DDT\Services\DockerService;
use DDT\Services\GitService;
use DDT\Services\ProxyService;
use DDT\Services\RunService;

try{
	if (version_compare(phpversion(), '7.2', '<')) {
		die("Sorry but the tools require at least PHP 7.2, you have ".phpversion()." installed\n");
	}
	
	spl_autoload_register(function ($fqcn) {
		if(in_array($fqcn, ['string'])) return false;
	
		// Chop off the DDT namespace to get the right filename
		$class = array_slice(explode('\\', $fqcn), 1);
		$class = implode('/', $class);
	
		$file = __DIR__ . '/' . $class . '.php';
	
		if (strlen($class) && file_exists($file)) {
			return require_once($file);
		}
	
		return false;
	});
	
	register_shutdown_function(function(){
		//print("HERE!!!");
		$error = error_get_last();
	
		//check if it's a core/fatal error, otherwise it's a normal shutdown
		if($error !== NULL && $error['type'] === E_ERROR) {
			print("There was a fatal error that could not be handled: " . $error['message'] . "\n");
		}
	});
	
	function container(?string $ref = null, ?array $args = [])
	{
		if(Container::$instance === null){
			throw new ContainerNotInstantiatedException();
		}
	
		return is_string($ref)
			? Container::$instance->get($ref, $args)
			: Container::$instance;
	}
	
	function config(string $key)
	{
		return container("config.$key");
	}
	
	$text = new Text();
	$cli = new CLI($argv, $text);
	
	$container = new Container($cli, [Autowire::class, 'instantiator']);
	$container->singleton(Container::class, $container);
	$container->singleton(CLI::class, $cli);

	// We have configure this really early so it's useful when the autowirer starts using it
	$debug = container(Debug::class, ['cli' => $cli, 'enabled' => $cli->getArg('--debug', false, true)]);
	
	// Add all the services which we only want to instantiate once since they are singular in nature
	$container->singleton(ConfigGeneratorService::class, ConfigGeneratorService::class);
	$container->singleton(DnsMasqService::class, DnsMasqService::class);
	$container->singleton(DockerService::class, DockerService::class);
	$container->singleton(GitService::class, GitService::class);
	$container->singleton(ProxyService::class, ProxyService::class);
	$container->singleton(RunService::class, RunService::class);
	
	$container->bind(Table::class, function() use ($text) {
		$table = new Table($text);
		$table->setRightPadding(5);
		$table->setBorder('|', '-');
		$table->setNumHeaderRows(1);    
	
		return $table;
	});
	
	// This should move into the entrypoint but for now I'll specify this here
	// and mark it up as a future TODO item
	if((bool)$cli->getArg('--version', false, true)){
		die("1.0\n");
	}
	
	// Set the container to have some default values which can be extracted on demand
	// This just centralises all the defaults in one place, there are other ways to do it
	// But this just seems to be a nice place since you're also setting up the rest of the di-container
	// TODO: This is already stored in the default.ddt-system.json file and should be used instead of duplicating this here
	$container->singleton('defaults.ip_address',			'10.254.254.254');
	$container->singleton('defaults.proxy.docker_image',	'antimatter-studios/docker-proxy:latest');
	$container->singleton('defaults.proxy.container_name',	'ddt-proxy');
	$container->singleton('defaults.proxy.network',			['ddt-proxy']);
	$container->singleton('defaults.dns.docker_image',		'christhomas/supervisord-dnsmasq');
	$container->singleton('defaults.dns.container_name',	'ddt-dnsmasq');
	
	// Set these important values for the system configuration
	$container->singleton('config.tools.path', realpath(__DIR__ . '/..'));
	// This is the default system configuration that is the basic template for any new installation
	$container->singleton('config.file.default', config('tools.path') . '/default.ddt-system.json');
	// This is the currently installed system configuration
	$container->singleton('config.file.system', $_SERVER['HOME'] . '/.ddt-system.json');
	
	$container->singleton(SystemConfig::class, function() {
		static $c = null;
	
		if($c === null) {
			$installConfig = config('file.system');
			$defaultConfig = config('file.default');
	
			if(file_exists($installConfig)){
				$c = new SystemConfig($installConfig, false);
			}else{
				$c = new SystemConfig($defaultConfig, true);
			}
		}
	
		return $c;
	});
	
	$container->singleton(IpServiceInterface::class, \DDT\Network\IpService::class);
	$container->singleton(DnsServiceInterface::class, \DDT\Network\DnsService::class);
	
	$entrypoint = container(EntrypointTool::class);
	$container->singleton(ToolRegistryInterface::class, $entrypoint);

	$entrypoint->registerTools("Built-in Tools", __DIR__, "\\DDT\\Tool\\");
	$entrypoint->registerExtensions();
	
	// Before handling the request, check to see if the timeout passes and self update
	$entrypoint->getTool('self-update')->run();
	
	// But in the end, handle the request made by the user
	$entrypoint->handle();
}catch(\Throwable $e){
	$cli->debug(get_class($e), $e->getTraceAsString());

	$message = $text->box(get_class($e) . ":\n" . $e->getMessage(), "wht", "red");

	switch(true){
		case $e instanceof Exception:
			$message = $text->box(get_class($e) . ":\nThe tool has a non-specified error: " . $e->getMessage(), "wht", "red");
			break;
	}

	$cli->failure($message);
}
