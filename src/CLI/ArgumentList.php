<?php declare(strict_types=1);

namespace DDT\CLI;

class ArgumentList
{
    /** @var array */
    private $argList;

    public function __construct(array $argList, ?int $start=0, ?int $length=null)
    {
        $this->argList = $argList;
        $this->slice($start, $length);
    }

    public function slice(int $start, ?int $length=null): array
    {
        return $this->argList = array_slice($this->argList, $start, $length);
    }

    public function __toString(): string
    {
        return implode(" ", array_map(function($a){
            if(!array_key_exists('value', $a)){
                $a['value'] = '';
            }
            return trim("{$a['name']}={$a['value']}", " =");
        }, $this->argList));
    }

    public function search(string $name, ?string $value=null): ?array
    {
        foreach($this->argList as $a){
            if(!preg_match("/$name/", $a['name'], $nameMatches)){
                continue;
            }

            if($value !== null && array_key_exists('value', $a)){
                if(!preg_match("/$value/", $a['value'], $valueMatches)){
                    continue;
                }
            }else{
                $valueMatches = [$a['value']];
            }

            return [
                'name' => array_pop($nameMatches), 
                'value' => array_pop($valueMatches)
            ];
        }

        return null;
    }

    public function all(): array
    {
        return $this->argList;
    }

    public function shift()
    {
        return array_shift($this->argList);
    }

    public function add(string $name, string $value)
    {
        $this->argList[] = ['name' => $name, 'value' => $value];
    }

    public function remove(?string $name, ?string $value=null)
    {
        $removed = null;

        $this->argList = array_filter($this->argList, function($a) use ($name, $value, &$removed){
            if($name === $a['name']){
                $removed = $a;
                return false;
            }
            if(array_key_exists('value', $a) && $value === $a['value']){
                $removed = $a;
                return false;
            }

            return true;
        });

        return $removed;
    }
}