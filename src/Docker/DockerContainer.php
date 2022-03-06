<?php declare(strict_types=1);

namespace DDT\Docker;

use DDT\Exceptions\Docker\DockerContainerNotFoundException;

class DockerContainer
{
    private $docker;

    private $name;

    private $id = null;
    private $exitCode = 0;

    public function __construct(
        Docker $docker, 
        string $name, 
        ?string $command = '',
        ?string $image = null, 
        ?array $volumes = [], 
        ?array $options = [], 
        ?array $env = [], 
        ?array $ports = [],
        ?array $labels = [],
        ?bool $background = false
    ){
        $this->docker = $docker;

        $this->name = $name;

        try{
            $this->id = $this->getId();
        }catch(DockerContainerNotFoundException $e){
            if(empty($image)){
                throw $e;
            }

            $this->run($image, $name, $command, $volumes, $options, $env, $ports, $labels, $background);

            if($background){
                $this->id = $this->getId();
            }else{
                // We can not set the id of a foreground container, cause it ran, completed, then destroyed itself
            }
        }
    }

    static public function get(string $name): DockerContainer
    {
        return container(DockerContainer::class, ['name' => $name]);
    }

    static public function foreground(
        string $name, 
        ?string $command = '', 
        ?string $image = null, 
        ?array $volumes = [], 
        ?array $options = [], 
        ?array $env = []
    ): ?int {
        try{
            $container = container(DockerContainer::class, [
                'name' => $name,
                'command' => $command,
                'image' => $image,
                'volumes' => $volumes,
                'options' => $options,
                'env' => $env,
            ]);

            return $container->getExitCode();
        }catch(DockerContainerNotFoundException $e){
            // A return of null means there was an exception instead of a normal execution
            // A Normal execution could be a failure too, it doesn't just mean success
            // A failure still means the command ran and gave a result, a null value means
            // the command couldn't run at all
            return null;
        }
    }

    static public function background(
        string $name, 
        ?string $command = '', 
        ?string $image = null, 
        ?array $volumes = [], 
        ?array $options = [], 
        ?array $env = [], 
        ?array $ports = [], 
        ?array $labels = []
    ): DockerContainer {
        return container(DockerContainer::class, [
            'name' => $name,
            'command' => $command,
            'image' => $image,
            'volumes' => $volumes,
            'options' => $options,
            'env' => $env,
            'ports' => $ports,
            'labels' => $labels,
            'background' => true,
        ]);
    }

    public function logs(bool $follow, ?string $since=null)
    {   
        $this->docker->logsFollow($this->id, $follow, $since);
    }

    public function getId(): string
    {
        try{
            if($this->id) return $this->id;

            $id = $this->docker->inspect('container', $this->name, '{{json .Id}}');
            $id = current($id);

            if(empty($id)){
                throw new \Exception("There was no container found");
            }

            return $id;
        }catch(\Exception $e){
            throw new DockerContainerNotFoundException($this->name);
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isRunning(): bool
    {
        $status = $this->docker->inspect('container', $this->name, '{{json .State.Status }}');
        $status = $status[0];

        return $status === 'running';
    }

    public function listNetworks(): array
    {
        return $this->docker->inspect('container', $this->name, '{{json .NetworkSettings.Networks }}');
    }

    public function listEnvParams(): array
    {
        $list = $this->docker->inspect('container', $this->name, '{{json .Config.Env }}');
        
        return array_reduce($list, function($a, $e) {
            [$name, $value] = explode("=", $e) + [null, null];
            
            // If any name/value is null, 
            if($name === null || $value === null){
                return $a;
            }

            $a[$name] = $value;
            return $a;
        }, []);
    }

    public function listLabels(): array
    {
        return $this->docker->inspect('container', $this->name, '{{json .Config.Labels }}');
    }

    public function getIpAddress(string $network): string
    {
        $ipAddress = $this->docker->inspect('container', $this->name, "{{json .NetworkSettings.Networks.{$network}.IPAddress }}");

        return current($ipAddress);
    }

    public function getPorts(): array
    {
        $ports = $this->docker->inspect('container', $this->name, "{{json .NetworkSettings.Ports }}");

        return array_reduce(array_keys($ports), function($result, $key) use ($ports) {
            [$port, $protocol] = explode('/', $key);
            $result[] = ['port' => $port, 'protocol' => $protocol, 'data' => $ports[$key]];
            return $result;
        }, []);
    }

    public function run(string $image, string $name, string $command = '', array $volumes = [], array $options = [], array $env = [], array $ports = [], array $labels = [], bool $background=false): int
	{
		$exec = ["run"];

		$exec[] = "--name $name";

		if($background){
			$exec[] = "-d --restart always";
		}
		
		foreach($volumes as $v){
			$exec[] = "-v $v";
		}

		foreach($env as $e){
			$exec[] = "-e \"$e\"";
		}

		foreach($ports as $p){
			$exec[] = "-p $p";
		}

        foreach($labels as $l){
            $exec[] = "-l \"$l\"";
        }

		$exec = array_merge($exec, $options);

		$exec[] = $image;

		$exec[] = $command;

        return $this->docker->passthru(implode(" ", $exec));
	}

    public function exec(string $command)
    {
        $output = $this->docker->exec("exec -it $this->id $command");
        
        $this->exitCode = $this->docker->getExitCode();

        return $output;
    }

    public function passthru(string $command): int
    {
        return $this->exitCode = $this->docker->passthru("exec -it $this->id $command");
    }
    
    public function stop(): bool
    {
        return $this->docker->stop($this->getId());
    }

    public function delete(): bool
    {
        return $this->docker->delete($this->getId());
    }

    public function sighup(string $process): bool
    {
        $psid = $this->exec("ps | grep '".$process."' | awk '{print \$1}'");
        $psid = (int)current($psid);

        if(is_int($psid) && $psid > 0){
            $this->exec("kill -s SIGHUP $psid");
            return $this->getExitCode() === 0;
        }

        return false;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }
}