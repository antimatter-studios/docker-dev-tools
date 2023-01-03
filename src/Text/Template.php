<?php declare(strict_types=1);

namespace DDT\Text;

class Template
{
    private $template;
    private $params;
    public function __construct(string $template, ?array $params=[])
    {
        $this->template = $template;
        $this->params = $params;
    }

    public function __toString()
    {
        do {
            $before = $this->template;
            foreach($this->params as $key => $value) {
                $this->template = str_replace('{{'.$key.'}}', $value, $this->template);
            }
        }while(strpos($this->template, '{{') !== false && $before !== $this->template);

        return $this->template;
    }
}