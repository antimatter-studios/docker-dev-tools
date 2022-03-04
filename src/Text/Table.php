<?php declare(strict_types=1);

namespace DDT\Text;

class Table
{
    private $text;
	private $data;
	private $tabWidth = 2;
	private $numColumns = 0;
	private $numHeaderRows = 0;
	private $border = null;
	private $space = " ";
	private $rightPadding = 0;
	private $debug = false;
	private $callback = [];

    public function __construct(Text $text, ?array $data=[], int $tabWidth=2)
    {
        $this->text = $text;
		$this->data = $data;
		$this->tabWidth = $tabWidth;
		$this->numColumns = 0;
		
		$this->setCallback('array', function($renderer, $array) { 
			return $renderer->write(implode(', ', $array)); 
		});

		$this->setCallback('object', function($renderer, $object) { 
			return $renderer->write(get_class($object));
		});
	}

	public function setDebug(bool $state)
	{
		$this->debug = $state;
		$this->space = $state ? '_' : ' ';
	}

	public function debug($data)
	{
		if($this->debug){
			print("$data\n");
		}
	}

    public function var_dump($data)
    {
        if($this->debug){
			var_dump($data);
		}
    }

	public function setCallback(string $name, callable $callback): void
	{
		$this->callback[$name] = $callback;
	}

	public function addRow($data) {
		if(count($data) > $this->numColumns){
			$this->numColumns = count($data);
		}

		$this->data[] = $data;
	}

	public function setRightPadding(int $padding = 0)
	{
		$this->rightPadding = $padding;
	}

	public function setNumHeaderRows(int $numHeaderRows): void
	{
		$this->numHeaderRows = $numHeaderRows;
	}

	public function setBorder(string $v, string $h): void
	{
		if(!empty($v) || !empty($h)){
			$this->border = ['v' => " $v ", 'h' => $h];
		}else{
			$this->border = null;
		}
	}

	private function calcPrintingWidth(string $text): int
	{
		$text_width = strlen($text);
		$non_printing = 0;

		// Find in the text given, all the special terminal codes
		$codes = $this->text->findCodes($text);
		
		// For every code, it might be printing or non-printing
		foreach($codes as $c){
			$text_width = $text_width + $c['printing'];
			$non_printing = $non_printing + $c['length'];
		}
		
		// Subtract the non-printing character widths summed up from the text width
		// giving the final number of charactes that'll be written to the terminal
		return (int)($text_width - $non_printing);
	}

	private function fixColumnWidths($columns): array
	{
		// Replace tabs with spaces
		$columns = array_map(function($text) {
			$replace = str_pad("", $this->tabWidth, $this->space);
			return str_replace("\t", $replace, $text ?? '');
		}, $columns);

		// Replace all special codes with shell script codes
		$columns = array_map(function($text) {
			return $this->renderText($text);
		}, $columns);

		// Find the printing character widths for every column
		$pw = array_reduce($columns, function($accum, $text) {
			$accum[] = $this->calcPrintingWidth($text);
			return $accum;
		}, []);

		// The Maximum printing width, is the minimum display width for this column
		$min = max($pw);

		// Determine the padding PER COLUMN, because each column contains a unique number of non-printing characters
		$padding = array_reduce($columns, function($accum, $text) use ($pw, $min) {
			$ci = count($accum);
			$tw = strlen($text);
			$np = $tw - $pw[$ci];
		
			$accum[] = $min + $np;
			return $accum;
		}, []);

		// Pad each column according to it's own requirements
		$columns = array_map(function($ci, $ct) use ($padding) {
			return str_pad($ct, $padding[$ci] + $this->rightPadding, $this->space);
		}, array_keys($columns), $columns);

		return $columns;
	}

	public function renderText($text): string
	{
		if(is_array($text)) {
			return $this->callback['array']($this->text, $text);
		}
		
		if(is_object($text)) {
			return $this->callback['object']($this->text, $text);
		}
		
		if(is_string($text)) {
			return $this->text->write($text);
		}

		throw new \Exception("Cannot render text as it's an unrecognised format");
	}

	public function render(): ?string
	{
		if(empty($this->data)) return null;

		$data = $this->data;

		// fix(1): avoid problems with only having one row of data
		array_unshift($data, []);

		// array_map(null, ...$data) is a really cool feature of php
		// it'll loop through all the arrays passed into it, then extract
		// all the array values with the same index and present it as an array
		// so all array indexes with value '0', get turned into an array
		// so this is like a way to 'flip' an array so that rows become columns
		// and we want to process data in a 'column' orientated way, so we can figure
		// out easily the width of each column in order to pad them out so they display
		// on the terminal correctly

		// Flip the array to so that process columns easily
		$data = array_map(null, ...$data);

		// Fix up all the column widths, including all hidden non-printing characters
		$data = array_map(function($columns){
			return $this->fixColumnWidths($columns);
		}, $data);

		// Flip the array back to normal
		$data = array_map(null, ...$data);

		// fix(1): removes the extra column created by fix(1) above where we add an empty row just for no reason
		$data = array_slice($data, 1);

		// Render the header and body
		$header	= $this->renderHeader(array_slice($data, 0, $this->numHeaderRows));
		$body	= $this->renderBody(array_slice($data, $this->numHeaderRows));
		// TODO: future idea, support a table footer
		$footer = $this->renderFooter([]);

		// Combine both the header and body together, trimming off what we don't want
		return trim(implode("\n", array_merge($header, $body, $footer))) . "\n";
	}

	private function renderRows(array $rows): array
	{
		if(count($rows) === 0) return [];

		// add the optionally configured vertical border character 
		// to surround every column in the table, such as '|'
		foreach($rows as $index => $row){
			$b = $this->border ? $this->border['v'] : '';
			$rows[$index] = trim($b . implode($b, $row) . $b);
		}

		// calculate the width of the table and use it to add the 
		// optionally configured horizontal border character to the 
		// table, which normally is '-'
		$pw = $this->calcPrintingWidth($rows[0]);
		$line = $this->border ? str_pad('', $pw, $this->border['h']) : '';
		$line = [$line];

		// every row set is given a 'top-border' made of the optionally
		// configured horizontal border character
		// NOTE: if borders are disabled, this means an empty string 
		// NOTE: which trim() removes afterwards leaving no spaces behind
		return array_merge($line, $rows);
	}

	private function renderHeader(array $rows): array
	{
		return $this->renderRows($rows);
	}

	private function renderBody(array $rows): array
	{
		$rows = $this->renderRows($rows);

		// special case: the body requires a bottom border, use the first row
		return array_merge($rows, [current($rows)]);
	}

	private function renderFooter(array $rows): array
	{
		$rows = $this->renderRows($rows);

		// special case: the footer requires a bottom border, use the first row
		return array_merge($rows, [current($rows)]);
	}
}
