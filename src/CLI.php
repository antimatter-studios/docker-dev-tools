<?php declare(strict_types=1);

namespace DDT;

use DDT\Text\Text;
use DDT\CLI\Output\CustomChannel;
use DDT\CLI\Output\DebugChannel;
use DDT\CLI\Output\StderrChannel;
use DDT\CLI\Output\TerminalChannel;
use DDT\CLI\Output\StdoutChannel;
use DDT\CLI\Output\StringChannel;
use DDT\Contract\ChannelInterface;
use DDT\Exceptions\CLI\AskResponseRejectedException;
use Exception;

class CLI
{
	private $text;
	private $args = [];
	private $script = null;
	private $channels = [];
	private $exitCode = 0;
	private $terminal = null;

	// TODO: why are these static? they're never used statically in this class?
	// NOTE: maybe in external code?
	public static $stdout = "";
	public static $stderr = "";

	public function __construct(array $argv, Text $text)
	{
		$this->text = $text;
		$this->setScript($argv[0]);
		$this->setArgs(array_slice($argv, 1));

		$this->terminal = new TerminalChannel($this->text);
		$this->channels['stdout'] = new StdoutChannel($this->terminal, $this->text);
		$this->channels['stderr'] = new StderrChannel($this->terminal, $this->text);

		// These custom channels should not be created here
		// if some part of the code wants them, it should create them itself
		$this->channels['debug'] = new DebugChannel($this->channels['stderr'], $this->text);

		$this->isRoot();

		// This will reset any colours bleeding over from commands by resetting the shell colour codes
		// Don't do this! This breaks piping output to commands like jq because it'll output a shell code directly into the input of the next command
		//$this->print("{end}");
	}

	public function isRoot(): bool
	{
		$isRoot = $this->exec('whoami') === 'root';
		
		if($isRoot){
			$this->debug("{yel}[SYSTEM]:{end} Root user detected\n");
		}

		return $isRoot;
	}

	public function getArch(): string
    {
        $arch = $this->exec('uname -m');
        $rosetta = false;

        if($this->isDarwin()){
            $rosetta = (int)$this->exec('sysctl -in sysctl.proc_translated');
            if($arch === 'x86_64' && $rosetta === 1){
                $arch = 'arm64';
            }
        }

        $this->debug('cpu arch', $arch);
        $this->debug('cpu rosetta', $rosetta ? 'yes' : 'no');

        return $arch;
    }

	public function isDarwin(): bool
	{
		return strtolower(PHP_OS) === 'darwin';
	}

	public function isLinux(): bool
	{
		return strtolower(PHP_OS) === 'linux';
	}

	public function enableErrors(bool $showErrors=false)
	{
		if($showErrors){
			error_reporting(-1);
			ini_set('display_errors', 'true');
			$this->stderr("{yel}[SYSTEM]:{end} Errors enabled\n");
		}else{
			error_reporting(0);
			ini_set('display_errors', 'false');
		}
	}

	public function setScript(string $script): void
	{
		$this->script = $script;
	}

	public function getScript(?bool $withPath=true): string
	{
		return $withPath ? $this->script : basename($this->script);
	}

	public function ask(string $question, ?array $accept=null): string
	{
		$responses = is_array($accept) ? "(Accepts: " . implode(", ", $accept) . "): " : "";
		$answer = readline($this->text->write("{yel}$question $responses{end}"));

		if(is_array($accept) && !in_array($answer, $accept)){
			throw new AskResponseRejectedException("The answer was not one of the accepted inputs", $answer, $accept);
		}

		return $answer;
	}

	public function setChannel(ChannelInterface $channel): ChannelInterface
	{
		$name = $channel->getName();

		if(!array_key_exists($name, $this->channels)){
			$this->channels[$name] = $channel;
		}

		return $channel;
	}

	public function getChannel(string $channel): ?ChannelInterface
	{
		if(array_key_exists($channel, $this->channels)){
			return $this->channels[$channel];
		}

		return null;
	}

	public function toggleChannel(string $channel, bool $state)
	{
		if(array_key_exists($channel, $this->channels)){
			$this->channels[$channel]->enable($state);
		}
	}

	public function statusChannel(string $channel): bool
	{
		if(array_key_exists($channel, $this->channels)){
			return $this->channels[$channel]->status();
		}

		return false;
	}

	public function silenceChannel(string $channel, callable $callback)
	{
		$this->toggleChannel($channel, false);
		$value = $callback();
		$this->toggleChannel($channel, true);

		return $value;
	}

	public function setArgs(array $argv): array
	{
		$this->args = [];

		foreach($argv as $v){
			$v = explode('=', $v);
			$a = [];
			
			if(count($v)) $a['name'] = array_shift($v);
			if(count($v)) $a['value'] = array_shift($v);

			$this->args[] = $a;
		}

		return $this->args;
	}

	public function shiftArg(): ?array
	{
		return array_shift($this->args);
	}

	// USELESS FUNCTIONALITY
	public function removeArg(string $name): ?array
	{
		foreach($this->args as $k => $v){
			if($name === $v['name']){
				unset($this->args[$k]);
				return $v;
			}
		}

		return null;
	}

	public function countArgs(): int
	{
		return count($this->args);
	}

	public function getArgList(): array
	{
		return $this->args;
	}

	/**
	 * Obtain the value of a named argument
	 * 
	 * @param $name the argument to find
	 * @param $default the value to return when not found
	 * @param $remove whether to remove the argument from the list afterwards
	 * @return null when argument is not found but no alternative default is set
	 * @return true when argument is set without value
	 * @return string when argument is set with value
	 */
	public function getArg(string $name, $default=null, bool $remove=false)
	{
		$value = $default;

		foreach($this->args as $index => $arg){
			if($arg['name'] === $name){
				$value = empty($arg['value']) ? true : $arg['value'];

				if($remove){
					unset($this->args[$index]);
				}
			}
		}

		return $value;
	}

	public function hasArg($name): ?bool
	{
		if(is_string($name)) $name = [$name];
		if(!is_array($name)) throw new Exception("name parameter must be string or array");

		foreach($name as $test){
			if($this->getArg($test) === null){
				return false;
			}
		}

		return true;
	}

	public function isCommand(string $command): bool
	{
		$this->exec("command -v $command");
		return $this->getExitCode() === 0;
	}

	public function sudo(?string $command='echo'): string
	{
		if($this->isRoot() === false){
			$command = "sudo $command";
		}

		return $this->exec($command);
	}

	public function getExitCode(): int
	{
		return $this->exitCode;
	}

	public function runProcess(string $command, ChannelInterface $stdout, ChannelInterface $stderr): string
	{
		unset($pipes);
		$pipes = [];

		$proc = proc_open($command,[
			0 => ['pipe','r'],
			1 => ['pipe','w'],
			2 => ['pipe','w'],
		],$pipes);

		$read = $pipes;
		$write = null;
		$except = null;

		$output = [
			1 => $stdout,
			2 => $stderr,
		];

		$feof = false;
		while ($feof === false && stream_select($read, $write, $except, 10) !== 0)
		{
			foreach($read as $index => $stream){
				if(feof($stream)){
					$feof = true;
				}
				if($index !== 0){
					$output[$index]->write(fgets($stream));
				}
			}

			// stream_select modifies the contents of $read in a loop we should replace it with the original
			$read = $pipes;
		}

		array_map('fclose', $pipes);
		$code = proc_close($proc);

		self::$stdout = trim(implode("\n", $output[1]->history()));
		self::$stderr = trim(implode("\n", $output[2]->history()));

		$this->exitCode = $code;

		return trim(self::$stdout);
	}

	public function exec(string $command, ?ChannelInterface $stdout=null, ?ChannelInterface $stderr=null): string
	{
		static $ctr = 0;

		$stdout = $stdout ?? new StringChannel();
		$stderr = $stderr ?? new StringChannel();

		$this->channels['debug']->write("{red}[EXEC(>>ctr:$ctr)]:{end} $command\n");

		$output = $this->runProcess($command, $stdout, $stderr);

		$code = $this->getExitCode();

		$this->channels['debug']->write("{red}[EXEC(<<ctr:$ctr)]:{end} {blu}Return Code:{end} $code {blu}Error Output:{end} '".self::$stderr."'");

		$ctr++;

		return $output;
	}

	public function passthru(string $command, bool $throw=true): int
	{
		$stdout = $this->getChannel('stdout');
		$stderr = $this->getChannel('stderr');
		
		$this->exec($command, $stdout, $stderr);

		return $this->getExitCode();
	}

	public function print(?string $string=''): string
	{
		return $this->channels['stdout']->write($string);
	}

	public function stderr(?string $string=''): string
	{
		return $this->channels['stderr']->write($this->text->write($string));
	}

	public function debug(string $type, ?string $string='', ?array $params=[])
	{
		return $this->channels['debug']->write("{red}[".strtoupper($type)."]{end}: " . $string, $params);
	}

	public function success(?string $string=null)
	{
		$this->die($string, 0);
	}

	public function failure(?string $string=null)
	{
		// FIXME: I think failure should write into stderr not stdout
		$this->die($string, 1);
	}

	public function box(string $string, string $foreground, string $background): string
	{
		return $this->print($this->text->box($string, $foreground, $background));
	}

	public function die(?string $string=null, int $exitCode=0)
	{
		$colour	= $exitCode === 0 ? "{grn}" : "{red}";
		$where	= $exitCode === 0 ? $this->getChannel('stdout') : $this->getChannel('stderr');

		if($string !== null){
			$where->write($colour.rtrim($string, "\n")."{end}\n");
		}

		exit($exitCode);
	}
}
