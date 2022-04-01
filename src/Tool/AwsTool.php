<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI;
use DDT\CLI\ArgumentList;
use DDT\Docker\DockerContainer;
use DDT\Docker\DockerImage;
use DDT\Exceptions\Docker\DockerContainerNotFoundException;
use DDT\Exceptions\Docker\DockerException;
use DDT\Exceptions\Docker\DockerImageBuildFailureException;
use DDT\Exceptions\Docker\DockerImageNotFoundException;
use DDT\Text\Template;

class AwsTool extends Tool
{
    private $image = null;
    private $container = null;

    private $containerName = "ddt-awscli";    
    private $imageName = 'ddt-awscli:{{DOCKER_ARCH}}';

    private $localAws = false;
    private $dockerfile = '/docker/awscli.dockerfile';

    public function __construct(CLI $cli)
    {
    	parent::__construct('aws', $cli);
        $this->setToolCommand('run', null, true);
        
        $this->setArch($this->getArch());
        $this->localAws = $this->cli->isCommand('aws');
    }

    public function getArch(): string
    {
        $arch = $this->cli->exec('uname -m');
        $rosetta = false;

        if($this->cli->exec('uname -s') === "Darwin"){
            $rosetta = (int)$this->cli->exec('sysctl -in sysctl.proc_translated');
            if($arch === 'x86_64' && $rosetta === 1){
                $arch = 'arm64';
            }
        }

        $this->cli->debug('cpu arch', $arch);
        $this->cli->debug('cpu rosetta', $rosetta ? 'yes' : 'no');

        return $arch;
    }

    public function setArch(string $dockerArch): void
    {
        $awsArch = strpos($dockerArch,'arm64') !== false ? 'aarch64' : 'x86_64';
        $awsVersion = "2.0.30";

        $params = ['DOCKER_ARCH' => $dockerArch, 'AWS_ARCH' => $awsArch, 'AWS_VERSION' => $awsVersion];

        $this->dockerfile = file_get_contents(config('tools.path').$this->dockerfile);
        $this->dockerfile = str_replace('$', '\$', $this->dockerfile);

        $this->imageName = (string)new Template($this->imageName, $params);
        $this->dockerfile = (string)new Template($this->dockerfile, $params);
    }
    
    public function getToolMetadata(): array
    {
        return [
            'title' => 'AWS CLI Docker Wrapper',
            'short_description' => 'A tool to output into the shells environment AWS credentials from a chosen profile',
            'description' => [
                'The purpose of this command is to output credentials into the shells environment so they can',
                'be used by commands that run inside that shell after they are set, such as aws cli or terraform, etc.'
            ],
            'examples' => [
                '- export $(ddt aws-creds mock) && aws s3api list-buckets',
                '- export $(ddt aws-creds mock) && aws --endpoint=http://s3.eu-west-1.aws.develop s3api list-buckets',
            ]
        ];
    }

    /**
     * @throws DockerImageBuildFailureException
     */
    public function getImage(): DockerImage
    {
        if($this->image instanceof DockerImage){
            return $this->image;
        }

        try{
            $this->image = DockerImage::get($this->imageName);
            return $this->image;
        }catch(DockerImageNotFoundException $e){
            // do nothing I guess?             
        }

        $this->cli->print("AWSCli Docker Image: '$this->imageName' Not Found, building...");
        $this->image = DockerImage::build($this->imageName, $this->dockerfile, false);
        return $this->image;
    }

    public function getContainer(): DockerContainer
    {
        if($this->container instanceof DockerContainer){
            return $this->container;
        }

        try{
            $this->container = DockerContainer::get($this->containerName);
            return $this->container;
        }catch(DockerContainerNotFoundException $e){
            // start container instead
        }

        $this->container = DockerContainer::background(
            $this->containerName, 
            "tail -f /dev/null", 
            $this->getImage()->getName(), 
            ["\$HOME/.aws:/root/.aws", "\$PWD:/app"], 
            ['-w /app'],
        );

        return $this->container;
    }

    public function getEnv(): array
    {
        $env = [];

        foreach([
            "AWS_ACCESS_KEY_ID",
            "AWS_SECRET_ACCESS_KEY",
            "AWS_SESSION_TOKEN",
        ] as $key){
            $val = getenv($key);
            $env = !empty($val) ? array_merge($env, ["-e \"$key=$val\""]) : $env;
        }

        return $env;
    }

    public function run(): void
    {
        try{
            $input = implode(' ', func_get_args());
            $arguments = empty($input) ? new ArgumentList($this->cli->getArgList()) : $input;

            if(false && $this->localAws){
                $this->cli->passthru("aws $arguments");
            }else{
                $env = $this->getEnv();
                $container = $this->getContainer();
                $container->passthru("aws $arguments", $env);
            }
        }catch(DockerImageBuildFailureException $e){
            $this->cli->failure("The '$this->imageName' has failed to build for unknown reasons\n");
        }
    }
}
