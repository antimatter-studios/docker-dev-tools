<?php declare(strict_types=1);

namespace DDT\Tool;

use DDT\CLI;
use DDT\CLI\ArgumentList;
use DDT\Docker\DockerContainer;
use DDT\Docker\DockerImage;
use DDT\Exceptions\Docker\DockerImageBuildFailureException;
use DDT\Exceptions\Docker\DockerImageNotFoundException;

class AwsTool extends Tool
{
    private $containerName = "ddt-aws";
    
    private $imageName = 'awscli:ddt';
    
    private $dockerfile = [
        'FROM --platform=linux/amd64 debian:bookworm-slim',
        'RUN apt-get update && apt-get install -y groff jq curl zip',
        'RUN curl -s https://awscli.amazonaws.com/awscli-exe-linux-x86_64-2.0.30.zip -o awscliv2.zip',
        'RUN unzip awscliv2.zip && ./aws/install',
        'ENV PATH=\${PATH}:/app/bin',
    ];

    public function __construct(CLI $cli)
    {
    	parent::__construct('aws', $cli);
        $this->setToolCommand('run', null, true);
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

    public function getImage(): DockerImage
    {
        return DockerImage::get($this->imageName);
    }

    public function buildImage(): DockerImage
    {
        try{
            return $this->getImage();
        }catch(DockerImageNotFoundException $e){
            // do nothing I guess?             
        }

        $this->cli->print("AWSCli Docker Image: '$this->imageName' Not Found, building...");
        return DockerImage::build($this->imageName, implode("\n", $this->dockerfile));
    }

    public function startContainer(DockerImage $image): void
    {
        DockerContainer::background(
            $this->containerName, 
            "tail -f /dev/null", 
            $image->getName(), 
            ["\$HOME/.aws:/root/.aws", "\$PWD:/app"], 
            ['-w /app'],
        );
    }

    public function getContainer(): DockerContainer
    {
        return DockerContainer::get($this->containerName);
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
            $env = !empty($val) ? [...$env, "-e \"$key=$val\""] : $env;
        }

        return $env;
    }

    public function run(): void
    {
        try{
            $arguments = new ArgumentList($this->cli->getArgList());
            $image = $this->buildImage();
            $this->startContainer($image);
            $container = $this->getContainer();
            $env = $this->getEnv();
            $exitCode = $this->runCommand($container, (string)$arguments, $env);
            exit($exitCode);
        }catch(DockerImageBuildFailureException $e){
            $this->cli->failure("The '$this->imageName' has failed to build for unknown reasons\n");
        }
    }

    public function runCommand(DockerContainer $container, string $command, ?array $env=[]): int
    {
        return $container->passthru("aws $command", $env);
    }
}
