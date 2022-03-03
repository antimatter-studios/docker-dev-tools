<?php declare(strict_types=1);

use DDT\Autowire;
use DDT\CLI;
use DDT\Debug;
use DDT\Text\Text;
use DDT\Text\Table;
use DDT\Container;
use DDT\DistroDetect;
use DDT\Tool\EntrypointTool;
use DDT\Config\SystemConfig;
use DDT\Contract\IpServiceInterface;
use DDT\Contract\DnsServiceInterface;
use DDT\Exceptions\Config\ConfigInvalidException;
use DDT\Exceptions\Config\ConfigMissingException;
use DDT\Exceptions\Container\ContainerNotInstantiatedException;
use DDT\Exceptions\Tool\ToolCommandNotFoundException;
use DDT\Exceptions\Tool\ToolNotFoundException;
use DDT\Exceptions\Tool\ToolNotSpecifiedException;

try{
	if (version_compare(phpversion(), '7.2', '<')) {
		die("Sorry but the tools require at least PHP 7.2, you have ".phpversion()." installed\n");
	}

	spl_autoload_register(function ($fqcn) {
		$class = implode('/', array_slice(explode('\\', $fqcn), 1));

		$file = __DIR__ . '/' . $class . '.php';

		if (strlen($class) && file_exists($file)) {
			return require_once($file);
		}
		
		return false;
	});

	register_shutdown_function(function(){
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

	$text = new Text();
	$cli = new CLI($argv, $text);

	// We have to set this value really early so it's useful when the autowirer starts using it
	Debug::$enabled = (bool)$cli->getArg('--dev-debug', false, true);

	$container = new Container($cli, [Autowire::class, 'instantiator']);

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
	$container->singleton('config.tools.path', __DIR__ . '/..');
	// This is the default system configuration that is the basic template for any new installation
	$container->singleton('config.file.default', __DIR__ . '/../default.ddt-system.json');
	// This is the currently installed system configuration
	$container->singleton('config.file.system', $_SERVER['HOME'] . '/.ddt-system.json');

	$container->singleton(CLI::class, $cli);
	
	$container->singleton(SystemConfig::class, function() {
		static $c = null;
		
		if($c === null) {
			$installConfig = container('config.file.system');
			$defaultConfig = container('config.file.default');

			if(file_exists($installConfig)){
				$c = new SystemConfig($installConfig, false);
			}else{
				$c = new SystemConfig($defaultConfig, true);
			}
		}

		return $c;
	});

	$detect = $container->get(DistroDetect::class);

	if($detect->isDarwin()){
		$container->singleton(IpServiceInterface::class, \DDT\Network\Darwin\IpService::class);
		$container->singleton(DnsServiceInterface::class, \DDT\Network\Darwin\DnsService::class);
	}else if($detect->isLinux()){
		$container->singleton(IpServiceInterface::class, \DDT\Network\Linux\IpService::class);

		if($detect->isUbuntu('16.04') || $detect->isUbuntu('16.10')){
			$container->singleton(DnsServiceInterface::class, \DDT\Network\Ubuntu_16\DnsService::class);
		}else if($detect->isUbuntu('18.04') || $detect->isUbuntu('18.10')){
			$container->singleton(DnsServiceInterface::class, \DDT\Network\Ubuntu_18\DnsService::class);
		}else{
			$container->singleton(DnsServiceInterface::class, \DDT\Network\Linux\DnsService::class);
		}
	}

	$tool = container(EntrypointTool::class);
	
	// Before handling the request, check to see if the timeout passes and self update
	$tool->getTool('self-update')->run();

	// But in the end, handle the request made by the user
	$tool->handle();
}catch(ConfigMissingException $e){
	$cli->failure(get_class($e) . $text->box($e->getMessage(), "wht", "red"));
}catch(ConfigInvalidException $e){
	$cli->failure($text->box($e->getMessage(), "wht", "red"));
}catch(ToolNotFoundException $e){
	$cli->failure($text->box($e->getMessage(), "wht", "red"));
}catch(ToolNotSpecifiedException $e){
	$cli->failure($text->box($e->getMessage(), "wht", "red"));
}catch(ToolCommandNotFoundException $e){
	$cli->failure($text->box($e->getMessage(), "wht", "red"));
}catch(Exception $e){
	$cli->failure($text->box(get_class($e) . ":\nThe tool has a non-specified error: " . $e->getMessage(), "wht", "red"));
}