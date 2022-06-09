<?php declare(strict_types=1);

namespace DDT\Model\Project;

use DDT\Model\Model;

class ProjectGroupModel extends Model
{
    private $group;

    /**
     * @param $group
     * @throws \InvalidArgumentException
     */
    public function __construct($group)
    {
        if($group instanceof self){
            $this->group = $group->getData();
        }else if(is_string($group)) {
            $this->group = [$group];
        }else if(is_array($group)) {
            $this->group = array_map(function($v) {
                if(!is_string($v)) {
                    throw new \InvalidArgumentException("Elements of the Project Group can only be strings");
                }

                return $v;
            }, $group);
        }else {
            throw new \InvalidArgumentException("Group parameter must be a string of an array of strings");
        }
    }

    public function getData()
    {
        return $this->group;
    }

    public function add(string $name): ProjectGroupModel
    {
        $g = array_merge($this->group, [$name]);
        $g = array_unique($g);

        return new ProjectGroupModel($g);
    }

    public function remove(string $name): ProjectGroupModel
    {
        return new ProjectGroupModel(
            array_filter($this->group, function($v) use ($name) {
                return $v !== $name;
            })
        );
    }

    public function has(string $name)
    {
        return in_array($name, $this->group);
    }
}