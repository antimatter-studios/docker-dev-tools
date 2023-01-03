<?php declare(strict_types=1);

namespace DDT\Model\Metadata;

use DDT\Model\Model;

class ToolMetadataModel
{
    private $systemName = 'Docker Dev Tools';
    private $toolName;
    private $title;
    private $shortDescription = null;
    private $entrypoint;
    private $description;
    private $commands = [];
    private $examples = [];
    private $notes = [];

    public function __construct(string $name, string $title, string $entrypoint)
    {
        if (empty($name)) {
            throw new \InvalidArgumentException("The name cannot be empty");
        }

        if (empty($title)) {
            throw new \InvalidArgumentException("The title cannot be empty");
        }

        if (empty($entrypoint)) {
            throw new \InvalidArgumentException("The entrypoint cannot be empty");
        }

        $this->toolName = $name;
        $this->title = $title;
        $this->entrypoint = $entrypoint;
    }

    public function setDescription(string $description, ?string $shortDescription=null)
    {
        $this->description = $description;
        $this->shortDescription = $shortDescription;
    }

    public function addCommands(?string $title, array $commands)
    {
        $this->commands[] = ['title' => $title, 'items' => $commands];
    }

    public function setExamples(array $examples)
    {
        $this->examples = $examples;
    }

    public function getName(): string
    {
        return $this->toolName;
    }

    public function getShortDescription(): string
    {
        return $this->shortDescription;
    }

    public function renderItem(): array
    {
        return [];
    }

    public function renderHelp(): string
    {
        $section = [];

        $section[] = $this->renderTitle($this->title);

        $description = $this->renderText($this->description, 1, 80);
        $section[] = $this->renderSection("Description", $description);

        $commands = $this->renderBlock($this->commands);
        $section[] = $this->renderSection("Commands", $commands);

        if(!empty($this->examples)){
            $examples = $this->renderBlock($this->examples);
            $section[] = $this->renderSection("Examples", $examples);
        }

        if(!empty($this->notes)){
            $notes = $this->renderBlock($this->notes);
            $section[] = $this->renderSection("Notes", $notes);
        }

        return implode("\n\n", $section) . "\n\n";
    }

    private function renderTitle(string $title): string
    {
        return "{grn}$this->systemName: $title{end}";
    }

    private function renderSection(string $title, string $text): string
    {
        return implode("\n", ["{blu}$title:{end}", $text]);
    }

    private function renderBlock(array $data, int $indent=1): string
    {
        $output = [];

        foreach($data as $item) {
            if(is_array($item) && array_key_exists('title', $item) && array_key_exists('items', $item)) {
                $output[] = $this->renderText("{cyn}{$item['title']}:{end}", $indent);
                $output[] = $this->renderBlock($item['items'], $indent+1) . "\n";
            }else if(is_string($item)){
                $output[] = $this->renderText($item, $indent, 80);
            }
        }

        return implode("\n", $output);
    }

    private function renderText(string $text, int $indent=0, int $width=0): string
    {
        $strings = $width !== 0 ? str_split($text, $width) : [$text];
        $prefix = str_repeat("\t", $indent);

        return implode("\n", array_map(function($s) use ($prefix) {
            return $prefix . ltrim($s);
        }, $strings));
    }
}