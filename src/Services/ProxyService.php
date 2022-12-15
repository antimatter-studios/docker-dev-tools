<?php declare(strict_types=1);

namespace DDT\Services;

use DDT\CLI\CLI;
use DDT\Config\Services\ProxyConfig;
use DDT\Docker\DockerContainer;
use DDT\Docker\DockerNetwork;
use DDT\Docker\DockerVolume;
use DDT\Exceptions\Docker\DockerContainerNotFoundException;
use DDT\Exceptions\Docker\DockerInspectException;
use DDT\Exceptions\Docker\DockerNetworkNotFoundException;

class ProxyService
{
	/** @var CLI */
	private $cli;

	/** @var ProxyConfig */
	private $config;

	/** @var DockerService */
	private $dockerService;

	public function __construct(CLI $cli, ProxyConfig $config, DockerService $dockerService)
	{
		$this->cli = $cli;
		$this->config = $config;
		$this->dockerService = $dockerService;
	}

	public function getContainer(): DockerContainer
	{
		return DockerContainer::get($this->config->getContainerName());
	}

	public function getContainerId(): ?string
	{
        $data = $this->dockerService->inspect("container", $this->config->getContainerName());

        return is_array($data) && array_key_exists("Id", $data) ? $data["Id"] : null;
	}

	public function isRunning(): bool
	{
		try{
			$this->getContainer();
			return true;
		}catch(\Exception $e){
			return false;
		}
	}

    /**
     * @return string
     */
	public function getConfig(): string
	{
		try{
			$container = $this->getContainer();
			return $container->exec("cat /etc/nginx/conf.d/default.conf");
		}catch(\Exception $e){
			$this->cli->debug("proxy", $e->getMessage());
			return "";
		}
	}

	public function getNetworks(?bool $inactive=false): array
	{
		$container = $this->getContainer();
		
		$list = array_merge(array_keys($container->listNetworks()), $this->config->listNetworks());
		$list = array_unique($list);
		$list = array_filter($list, function($a){ return $a !== 'bridge'; });
		
		return $list;
	}

	public function getContainersOnNetwork(string $network): array
	{
		$network = DockerNetwork::instance($network);

		$list = $network->listContainers();

		// remove itself from the list, cause thats redundant
		$list = array_filter($list, function($c){
			return $c !== $this->config->getContainerName();
		});

		$list = array_map(function($c) use ($network) {
			$container = DockerContainer::get($c);
			$ports = $container->getPorts();
			return [
				'name' => $c,
				'network' => $network->getName(),
				'ip_address' => $container->getIpAddress($network->getName()),
				'port' => array_column($ports, 'port'),
			];
		}, $list);

		$nginxConfig = $this->getConfig();
		$list = array_map(function($c) use ($nginxConfig) {
			return $this->attachNginxConfigInfo($c, $nginxConfig);
		}, $list);

		// Remove items from the list, which have no nginx configuration data attached to them
		// This means they are not found in the nginx proxy config
		$list = array_filter($list, function($c){
			return array_key_exists('nginx_status', $c);
		});

		return $list;
	}

	public function attachNginxConfigInfo(array $container, ?string $nginxConfig=null): array
	{
		if($nginxConfig === null){
			$nginxConfig = $this->getConfig();
		}

		$network = $container['network'];
		$name = $container['name'];
		$ip_address = preg_quote($container['ip_address']);
		$port = $container['port'];

		foreach($port as $portNumber){
			preg_match_all("/upstream\s(?P<upstream>".$name."-".$portNumber.")\s{(?P<config>[^}]*)}/m", $nginxConfig, $upstreams);

			// group all results by index
			$upstreams = array_map(null, $upstreams['upstream'], $upstreams['config']);
			// remap them into associative array
			$upstreams = array_reduce($upstreams, function($a, $c){
				$a[array_shift($c)] = array_shift($c);
				return $a;
			}, []);
	
			if(empty($upstreams)) return $container;
	
			$feedback = array_map(function($u) use ($name, $network, $ip_address, $portNumber) {
				preg_match(
					"/##\sCan be connected with \"(?P<network>".$network.")\" network\s*".
					"server\s(?P<ip_address>".$ip_address.")\:(?P<port>".$portNumber.")\;/m", $u, $feedback);
	
				return array_intersect_key($feedback, array_flip(['name', 'network', 'ip_address', 'port']));
			}, $upstreams);
	
			if(empty($feedback)) return $container;
	
			// TODO: What other feedback do I want to do here?
	
			$container['nginx_status'][$portNumber] = '{grn}passed{end}';
		}

		return $container;
	}

	public function getContainerProxyEnv(string $container): array
	{
		$configurations = [];

		$container = DockerContainer::get($container);
		$env = $container->listEnvParams();
		$config = ['proto' => 'http', 'port' => '80'];
		if(array_key_exists('VIRTUAL_PROTO', $env)){
			$config['proto'] = $env['VIRTUAL_PROTO'];
		}
		if(array_key_exists('VIRTUAL_HOST', $env)){
			$config['host'] = $env['VIRTUAL_HOST'];
		}
		if(array_key_exists('VIRTUAL_PORT', $env)){
			$config['port'] = $env['VIRTUAL_PORT'];
		}
		if(array_key_exists('VIRTUAL_PATH', $env)){
			$config['path'] = $env['VIRTUAL_PATH'];
		}

		// We require the host at the very minimum
		if(array_key_exists('host', $config)){
			$configurations[] = $config;
		}

		$labels = $container->listLabels();
		$labels = array_filter($labels, function($key){ 
			return strpos($key, 'docker-proxy') !== false; 
		}, ARRAY_FILTER_USE_KEY);
		
		$labelGroups = array_reduce(array_keys($labels), function($group, $value) use ($labels) {
			[$project, $name, $field] = explode('.', $value);
			if(!array_key_exists($name, $group)) $group[$name] = [];
			$group[$name][$field] = $labels[$value];
			return $group;
		}, []);

		foreach($labelGroups as $tag => $config){
			$configurations[] = array_merge(['tag' => $tag, 'proto' => 'http', 'port' => '80'], $config);
		}

		return $configurations;
	}

	public function pull()
	{
		$this->dockerService->pull($this->config->getDockerImage());
	}

	public function start(?array $networkList=null): bool
	{
		$image = $this->config->getDockerImage();
		$name = $this->config->getContainerName();
		$path = config('tools.path');

		try{
			// Remove the container that was previously built
			// cause otherwise it'll crash with "The container name /xxx" is already in use by container "xxxx"
			$container = $this->getContainer();
			if($container->isRunning()){
				return true;
			}
			
			$this->cli->debug("proxy", "The proxy was found, but it's state is that it's not running, so lets kill it and start again\n");
			$this->cli->print("Deleting Container with name '$name'\n");
			$container->stop();
			$container->delete();
		}catch(DockerContainerNotFoundException $e){
			// It's already not started or not found, so we have nothing to do
		}

		//	TODO: allow this tool to support HTTPS and production modes by creating the acme container
		//	TODO: right now it just ignores it and leaves it up to the developer to manually deploy

		//	In order to support HTTPS for production servers, we will create three empty volumes for the 
		//	acme container to possible use, if it's enabled
		DockerVolume::instance('ddt_proxy_certs');
		DockerVolume::instance('ddt_proxy_vhost');
		DockerVolume::instance('ddt_proxy_html');

		try{
			$container = DockerContainer::background(
				$name, 
				'',
				$image, 
				[
					'ddt_proxy_certs:/etc/nginx/certs',
					'ddt_proxy_vhost:/etc/nginx/vhost.d',
					'ddt_proxy_html:/usr/share/nginx/html',
					'ddt_config_gen:/config',
				],
				[], // options
				['NGINX_CONF=/config/docker-proxy/nginx.conf'],
				['80:80', '443:443'],
				[
					'docker-config-gen.input=/docker-proxy/nginx.tmpl',
					'docker-config-gen.output=/docker-proxy/nginx.conf',
					'docker-config-gen.exec="/app/reload.sh"',
				]
			);

			$id = $container->getId();

			$container->copyFile($path . '/proxy-config/global.conf', '/etc/nginx/conf.d/global.conf');
			$container->copyFile($path . '/proxy-config/nginx-proxy.conf', '/etc/nginx/proxy.conf');

			if(empty($networkList)){
				// use the networks from the configuration
				$networkList = $this->getNetworks();
			}
	
			foreach($networkList as $network){
				$this->cli->print("Connecting container '$name' to network '$network'\n");
				$network = DockerNetwork::instance($network, true);
				$network->attach($id);
			}

			$this->cli->print("Running image '$image' as '$name' using container id '$id'\n");
			return true;
		}catch(DockerContainerNotFoundException $e){
			$this->cli->failure("The container '$name' did not start correctly\n");
			return false;
		}
	}

	public function stop(): bool
	{
		try{
			$container = $this->getContainer();
			$container->stop();
			$container->delete();
			return true;
		}catch(DockerInspectException $e){
			$this->cli->print("{red}".$e->getMessage."{end}\n");
			return false;
		}
	}

	public function reload(): bool
	{
		$container = $this->getContainer();
		return $container->sighup('docker-gen');
	}

	public function logs(bool $follow, ?string $since=null)
	{
		try{
            $container = $this->getContainer();
			$container->logs($follow, $since);
        }catch(\Exception $e){
            throw new \Exception('Could not find docker container view the logs from: ' . $e->getMessage());
        }
	}

	public function addNetwork(string $name): bool
	{
		try{
			$network = DockerNetwork::instance($name, true);

			$containerId = $this->getContainerId();
	
			$network->attach($containerId);
	
			$this->cli->print("{blu}Attaching:{end} '{yel}$name{end}' to proxy so it can listen for containers\n");
			
			return $this->config->addNetwork($name);
		}catch(DockerInspectException $e){
			throw new DockerNetworkNotFoundException($name, 0, $e);
		}
	}

	public function removeNetwork(string $name): bool
	{
		try{
			$network = DockerNetwork::instance($name, false);

			$containerId = $this->getcontainerId();

			$network->detach($containerId);

			$this->cli->print("{blu}Detaching:{end} '{yel}$name{end}' from the proxy so it will stop listening for containers\n");

			return $this->config->removeNetwork($name );
		}catch(DockerInspectException $e){
			throw new DockerNetworkNotFoundException($name, 0, $e);
		}
	}
}
