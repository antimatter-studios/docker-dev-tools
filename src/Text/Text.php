<?php declare(strict_types=1);

namespace DDT\Text;

class Text
{
	private $tab = "  ";
	
	private $codes = [
		// foreground colours
		'blk' => "\033[30m",
		'red' => "\033[31m",
		'grn' => "\033[32m",
		'yel' => "\033[33m",
		'blu' => "\033[34m",
		'mag' => "\033[35m",
		'cyn' => "\033[36m",
		'wht' => "\033[37m",
	
		// background colours
		'blk_b' => "\033[40m",
		'red_b' => "\033[41m",
		'grn_b' => "\033[42m",
		'yel_b' => "\033[43m",
		'blu_b' => "\033[44m",
		'mag_b' => "\033[45m",
		'cyn_b' => "\033[46m",
		'gry_b' => "\033[47m",
	
		// reset whatever terminal stuff you've done
		'end' => "\033[0m",
	
		// Some icons
		'i_chk' => "\xE2\x9C\x85",
		'i_mss' => "\xE2\x9D\x8C",
		'i_wrn' => "\xF0\x9F\x98\xB1",
	];

	public function __construct(?array $theme=[])
	{
		foreach(array_merge($this->codes, $theme) as $key => $val){
			if(strpos($val, '\033') === 0){
				$val = "\e".str_replace('\033', '', $val);
			}

			if(strpos($val, "\x") === 0){
				$val = array_filter(explode("\x", $val));
				$val = hex2bin(implode("", $val));
			}

			$this->addCode($key, $val);
		}
	}

	public function addCode($key, $value): void
	{
		$key = strtolower($key);

		$printing = strpos($key, "i_") !== false ? 2 : 0;

		$this->codes[$key] = ['key' => $key, 'printing' => $printing, 'value' => $value, 'length' => strlen($value)];
	}

	public function findCodes($string): array
	{
		$codes = [];

		foreach($this->codes as $code){
			$count = substr_count($string, $code['value']);
			$codes = $codes + array_fill(count($codes), $count, $code);
		}

		return $codes;
	}

	/** @deprecated */
	public function stripColours(string $input): string
	{
		return $this->stripCodes($input);
	}

	public function stripCodes(string $input): string
	{
		foreach($this->codes as $key){
			$input = str_replace($key['value'], '', $input);
		}
		return $input;
	}

	public function dump(): string
	{
		$args = func_get_args();

		if(count($args) === 0){
			print($this->write(
				"{cyn}\n".
				"dump(): called with no arguments?\n".
				"Why are you like this? You don't have to be this way.....\n{end}",
			));
		}

		if(count($args) == 1) $args = array_shift($args);

		ob_start();
		var_dump($args);
		return ob_get_clean();
	}

	public function write(string $string): string
	{
		foreach($this->codes as $key => $code){
			$string = str_replace('{'.$key.'}', $code['value'], $string);
		}

		// Replace all tabs with the configured replacement
		return str_replace("\t", $this->tab, $string);
	}

	public function white(string $string): string
	{
		return $this->write("{wht}$string{end}");
	}

	public function green(string $string): string
	{
		return $this->write("{grn}$string{end}");
	}

	public function red(string $string): string
	{
		return $this->write("{red}$string{end}");
	}

	public function blue(string $string): string
	{
		return $this->write("{blu}$string{end}");
	}

	public function yellow(string $string): string
	{
		return $this->write("{yel}$string{end}");
	}

	public function box(string $string, string $foreground, string $background): string
	{
		$string = explode("\n", $string);
		$string = implode("\n\t", $string);

		return $this->write("{" . $foreground . "}{" . $background . "_b}\n\t\n\t" . trim($string) . "\n{end}\n");
	}
}
