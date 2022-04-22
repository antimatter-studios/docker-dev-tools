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

class TerraformTool extends Tool
{
    private $image = null;
    private $container = null;

    private $containerName = "ddt-terraform";

    private $awsCredsTool;
    
    private $dockerfile = '/docker/terraform.dockerfile';

    public function __construct(CLI $cli, AwsCredsTool $awsCredsTool) 
    {
    	parent::__construct('terraform', $cli);
        $this->setToolCommand('run', null, true);
        
        $this->awsCredsTool = $awsCredsTool;
    }

    public function setArch(string $arch): void
    {
        $this->imageName = str_replace('__ARCH__', $arch, $this->imageName);
    }
    
    public function getToolMetadata(): array
    {
        return [
            'title' => 'Terraform Docker Wrapper',
            'short_description' => 'A tool to assist in using terraform with aws profiles together in a simpler way',
            'description' => [
                "",
            ],
            'examples' => [
                
            ],
            'notes' => [
                "This tool is not yet usable by other people",
            ],
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
            $dockerArch = $this->cli->getArch();
            $imageName = $this->containerName . ":" . $dockerArch;

            $awsArch = strpos($dockerArch,'arm64') !== false ? 'aarch64' : 'x86_64';
            $awsVersion = "2.0.30";

            $params = ['DOCKER_ARCH' => $dockerArch, 'AWS_ARCH' => $awsArch, 'AWS_VERSION' => $awsVersion];

            $this->dockerfile = file_get_contents(config('tools.path').$this->dockerfile);
            $this->dockerfile = str_replace('$', '\$', $this->dockerfile);
            $this->dockerfile = (string)new Template($this->dockerfile, $params);
            
            $this->image = DockerImage::get($imageName);
            return $this->image;
        }catch(DockerImageNotFoundException $e){
            // do nothing I guess?
        }

        $this->cli->print("Terraform Docker Image: '$imageName' Not Found, building...");
        $this->image = DockerImage::build($imageName, $this->dockerfile, false);
        return $this->image;
    }

    public function getContainer(string $path, string $workspaceDir, string $workspaceProfile, ArgumentList $arguments): DockerContainer
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

        // docker run -it --rm $this->getEnvLog() $this->getEnvWorkspace() $this->getEnv() \
        //     -v ${dir}:/app \
        //     -v ${HOME}/.ssh:/root/.ssh:ro \
        //     -w $this->getWorkDir() \
        //     ${image_name} $this->getChdirArg() $this->getRemainingArgs() && exit 0

        $this->container = DockerContainer::background(
            $this->containerName, 
            "tail -f /dev/null", 
            $this->getImage()->getName(), 
            [realpath($_SERVER['HOME'].'/.ssh').":/root/.ssh:ro", "$path:/app"], 
            ["-w " . $this->getWorkDir($path, $workspaceDir)],
            array_filter(array_merge([
                $this->getEnvLog(),
                $this->getEnvWorkspace($workspaceProfile, $arguments),
            ], ...$this->getEnv()))
        );

        return $this->container;
    }

    private function getWorkDir(string $path, string $workspaceDir): string
    {
        //     [ ! -z "${WORKSPACE_DIR}" ] && wkdir=${WORKSPACE_DIR} || wkdir=${PWD}
        //     echo "/app/$(echo ${wkdir} | sed -e "s#^${dir}##g" | sed -e "s#^/##g")"
        $w = !empty($workspaceDir) ? $workspaceDir : getcwd();
        $w = preg_replace('@'.preg_quote($path).'@', '', $w);
        $a = "/app/" . ltrim($w, '/');

        return $a;
    }

    private function getChdirArg(string $path, ArgumentList $arguments): string
    {
        // find the -chdir parameter on the command line
        $chdir = $arguments->remove('-chdir');

        if(empty($chdir)){
            return '';
        }

        // recalculate the value relative to the /app directory inside the docker container
        if(!preg_match("@^/app@", $chdir['value'])){
            $chdir = preg_replace('@'.preg_quote($path).'@', '', $chdir['value']);
        }else{
            $chdir = $chdir['value'];
        }

        return "-chdir={$chdir}";
    }

    private function getRemainingArgs(): array
    {
        // function get_args()
        // {
        //     temp=()

        //     for arg in ${command_line}; do
        //         [ -z "${chdir}" ] && chdir=$(echo "${arg}" | grep "^-chdir") || temp+=("${arg}")
        //     done

        //     echo ${temp[@]}
        // }
        return [];
    }

    private function getCommand(ArgumentList $arguments): ?string
    {
        foreach($arguments->all() as $a){
            if(strpos($a['name'], '-') !== 0){
                return $a['name'];
            }
        }

        return null;
    }

    private function getEnvLog(): string
    {
        $tf_log = getenv('TF_LOG');

        if($tf_log !== false){
            return "--env TF_LOG=$tf_log";
        }
        
        return '';
    }

    private function useWorkspace(ArgumentList $arguments): bool
    {
        $command = $this->getCommand($arguments);
        
        return strpos($command, 'workspace') !== false;
    }

    private function getEnvWorkspace(string $workspaceProfile, ArgumentList $arguments): string
    {
        if($this->useWorkspace($arguments)){
            return "--env TF_DATA_DIR=/app/.terraform/" . $this->getWorkspace($workspaceProfile, $arguments);
        }

        return '';
    }

    // FIXME: I have to figure out a way to make this "configurable"
    // FIXME: because not everybody will want all of these parameters
    private function getEnv(): array
    {
        // AWS TOKENS
        // If these values are available, inject them, otherwise don't.
        // We can't inject empty strings, if the values even exist, terraform or
        // aws will use and complain about the incorrect data set
        if("AWS_ACCESS_KEY_ID" === false) $aws[] = "--env AWS_ACCESS_KEY_ID=@AWS_ACCESS_KEY_ID";
        if("AWS_SECRET_ACCESS_KEY" === false) $aws[] = "--env AWS_SECRET_ACCESS_KEY=@AWS_SECRET_ACCESS_KEY";
        if("AWS_SESSION_TOKEN" === false) $aws[] = "--env AWS_SESSION_TOKEN=@AWS_SESSION_TOKEN";
        if("AWS_SECURITY_TOKEN" === false) $aws[] = "--env AWS_SECURITY_TOKEN=@AWS_SECURITY_TOKEN";

        //     # GITLAB TERRAFORM REMOTE STATE
        //     # Only add these parameters if running against a real AWS account
        //     # We only need them when we are using gitlab terraform remote state
        //     # otherwise we just run locally on the developers computer
        //     if [ "${is_dev}" = "no" ]; then
        //         GITLAB_URL=https://gitlab.ptenv.net/api/v4
        //         GITLAB_PROJECT=$(php -r "echo urlencode('${GITLAB_PROJECT}');")
        //         GITLAB_STATE_URL=${GITLAB_URL}/projects/${GITLAB_PROJECT}/terraform/state/${GITLAB_STATE_FILE}

        //         # TERRAFORM HTTP CONFIG
        //         state_args=(
        //             "--env TF_HTTP_USERNAME=${GITLAB_TERRAFORM_USERNAME:-gitlab-ci-token}"
        //             "--env TF_HTTP_PASSWORD=${GITLAB_TERRAFORM_PASSWORD}"
        //             "--env TF_HTTP_ADDRESS=${GITLAB_STATE_URL}"
        //             "--env TF_HTTP_LOCK_ADDRESS=${GITLAB_STATE_URL}/lock"
        //             "--env TF_HTTP_UNLOCK_ADDRESS=${GITLAB_STATE_URL}/lock"
        //             "--env TF_HTTP_LOCK_METHOD=POST"
        //             "--env TF_HTTP_UNLOCK_METHOD=DELETE"
        //             "--env TF_HTTP_RETRY_WAIT_MIN=5"
        //         )
        //     fi

        // GENERAL TERRAFORM FLAGS FOR AUTOMATION
        $general = [
            "--env TF_IN_AUTOMATION=true",
            "--env TF_INPUT=false",
        ];

        return array_merge([
            $aws,
            $general,
        ]);
    }

    private function getWorkspace(string $workspaceProfile, ArgumentList $arguments): string
    {
        if($this->useWorkspace($arguments)){
            if(!empty($workspaceProfile)){
                return $workspaceProfile;
            }
        }

        return 'default';
    }

    public function run(string $awsProfile, string $path): void
    {
        try{
            // # The base path of this project
            // dir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && dirname $(pwd) )"
            // # The name of this project, is the directory name
            // project=$(basename ${dir})
            $project = basename($path);

            // # Extract all the arguments after the profile and terraform command
            // args=${@:3}
            $arguments = new ArgumentList($this->cli->getArgList(), 2);

            // # By default, this is not a dev env (needs to be exported because it's used in a subshell running terraform)
            // export -p is_dev=no
            $is_dev = false;

            switch($awsProfile){
                case 'plista-dev':
                    // # oh yes, it is a dev env ;)
                    // export -p is_dev=yes
                    $is_dev = true;
                    // # so therefore, use the local terraform configuration
                    // src_dir=${dir}/src/local
                    $src_dir = "$path/src/local";
                    // # default dev env terraform deployment parameters
                    // args+=("-var env=dev")
                    // args+=("-var aws_region=eu-west-1")
                    $arguments->add('-var', 'env=dev');
                    $arguments->add('-var', 'aws_region=eu-west-1');

                    break;

                case 'plista-taspli':
                    // # use the real aws configuration
                    // src_dir=${dir}/src/aws
                    $src_dir = "$path/src/aws";
                    // # default dev env terraform deployment parameters
                    // args+=("-var env=taspli")
                    // args+=("-var aws_region=eu-west-1")
                    $arguments->add('-var', 'env=taspli');
                    $arguments->add('-var', 'aws_region=eu-west-1');
                    break;

                default:
                    // src_dir=${dir}/src/aws
                    $src_dir = "$path/src/aws";
                    break;
            }

            // # Find if a terraform argument was passed to the command line,

            // export -f env_tf_custom;
            
            $aws_region = $arguments->search('-var', '^aws_region=(.*)$');
            $env = $arguments->search('-var', '^env=(.*)$');

            // # These two terraform parameter are required, even in dev environment for configuring the terraform setup and resource names
            // [ -z "${aws_region}" ] && echo "AWS Region is required, provide it by using the -var aws_region=xxx parameter" && exit 1
            if(empty($aws_region)){
                $this->cli->failure("AWS Region is required, provide it by using the -var aws_region=xxx parameter");
            }
            
            // [ -z "${env}" ] && echo "Runtime environment is required, provide it by using the -var env=xxx parameter" && exit 1
            if(empty($env)){
                $this->cli->failure("Runtime environment is required, provide it by using the -var env=xxx parameter");
            }

            // # If running against real AWS, we need to setup the gitlab terraform remote state and login information
            // if [ "${is_dev}" = "no" ]; then
            if($is_dev === false){
                //     export -p GITLAB_PROJECT=platforms/cloud/services/${project}
                //     export -p GITLAB_STATE_FILE=${env}-${project}
                $gitlabProject = "platforms/cloud/services/$project";
                $gitlabStateFile = "{$env}-{$project}";

                //     [ -z "${GITLAB_TERRAFORM_USERNAME}" ] && echo "Environment variable GITLAB_TERRAFORM_USERNAME was not found, it will default to gitlab-ci-token"
                //     [ -z "${GITLAB_TERRAFORM_PASSWORD}" ] && echo "Environment variable GITLAB_TERRAFORM_PASSWORD was not found, it's not possible to use terraform state without it, set it in the terminal" && exit 1
                $gitlabUsername = getenv("GITLAB_TERRAFORM_USERNAME");
                $gilabPassword  = getenv("GITLAB_TERRAFORM_PASSWORD");
            }
            // fi

            // # terraform validate:
            // # When this script is run, some default arguments are used and provided to terraform automatically
            // # This helps by reducing the amount of command line arguments which are always the same
            // # But when you run terraform validate, these extra parameters cause the command to crash out
            // # So here, we just remove them all, which provides a quick and simple fix but still allows the command to run normally
            // [ "${command}" = "validate" ] && args=()
            if(strpos((string)$arguments, 'validate') === 0){
                $arguments = new ArgumentList([]);
            }

            // # Extract aws credentials for this profile, so we can set them as environment variables
            $creds = $this->awsCredsTool->get($awsProfile, $project);

            // # If no AWS credentials were found, we fail here, cause it should never happen
            if(empty($creds)){
                $this->cli->failure("Credentials were not found, is aws profile '$project' correct?\n");
            }

            // # We setup the local terraform workspace setup and directory structure
            // # These are just abstracted parameters for where terraform will store files
            // # on your local disk whilst working in a consistent way
            // export -p WORKSPACE_PROFILE="${profile}-${project}"
            $workspaceProfile = "${awsProfile}-${project}";
            // export -p WORKSPACE_DIR=${src_dir}
            $workspaceDir = $src_dir;
            // # Export into the current shell, all the AWS credentials we found before
            // export -p $(echo $creds | tr '\r\n' ' ')
            // TODO: export the credentials into the environment

            // # Now we are finally ready to run Terraform itself, phew!!
            $this->cli->print("Using workspace configuration: " . $this->getWorkspace($workspaceProfile, $arguments) . "\n");
            //$env = $this->getEnv();
            
            //$arguments = new ArgumentList([/*, ...$this->getRemainingArgs()*/]);
            $container = $this->getContainer($path, $workspaceDir, $workspaceProfile, $arguments);
            $container->passthru($this->getChdirArg($path, $arguments) . (string)$arguments, []);
        }catch(DockerImageBuildFailureException $e){
            $this->cli->failure("The '{$e->getImageName()}' has failed to build for unknown reasons\n");
        }catch(DockerException $e){
            $this->cli->failure("The '$this->imageName' has failed with a runtime exception: " . $e->getMessage() . "\n");
        }
    }
}
