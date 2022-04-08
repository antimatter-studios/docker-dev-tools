<?php declare(strict_types=1);

namespace DDT;

use DDT\Exceptions\Autowire\CannotAutowireParameterException;

class Autowire
{
    /** @var callable $resolver A callback to return a type that the autowire class wants resolved to an argument to pass */
    private $resolver;

    public function __construct(callable $resolver)
    {
        $this->resolver = $resolver;
    }

    static public function instantiator(callable $resolver, string $ref, array $args)
    {
        $autowire = new Autowire($resolver);

        // Special case for the autowire class
        if($ref === Autowire::class){
            return $autowire;
        }
        
        return $autowire->getInstance($ref, $args);
    }

    public function getInstance(string $ref, ?array $args=[])
    {
        try{
            $rc = new \ReflectionClass($ref);
            $rm = $rc->getConstructor();

            if($rm){
                $params = $this->getReflectionParameters($rm);
            
                $args = $this->reformatArgs($args);
                $args = $this->resolveArgs($params, $args);   
            }else{
                $args = [];
            }
    
            return $rc->newInstanceArgs($args);
        }catch(CannotAutowireParameterException $e){
            $e->setClassName($ref);
            $e->setMethodName($rm->getName());
            throw $e;
        }
    }

    public function callMethod(object $class, string $method, ?array $args=[])
    {
        try{
            $rc = new \ReflectionClass($class);

            if($rc->hasMethod($method) === true || $rc->hasMethod('__call') === false){
                $rm = $rc->getMethod($method);
                $params = $this->getReflectionParameters($rm);
    
                $args = $this->reformatArgs($args);
                $args = $this->resolveArgs($params, $args);
    
                return $rm->invoke($class, ...$args);
            }
    
            // TODO: how will this reformat/resolve argument code work 
            // against __call interfaces which typically have no arguments
            // and work like magic? Surely this will fail?
            $rm = $rc->getMethod('__call');
    
            return $rm->invoke($class, $method, $args);
        }catch(CannotAutowireParameterException $e){
            $e->setClassName(get_class($class));
            $e->setMethodName($method);
            throw $e;
        }
    }

    private function getReflectionParameters(\ReflectionMethod $method): array
    {
        $params = $method->getParameters();
            
        // Resolve parameter data to a simple array
        $params = array_map(function($p) {
            $temp = ['name' => $p->getName()];

            $type = $p->getType();
            if($type instanceof \ReflectionNamedType){
                $temp['type'] = $type->getName();
            }else if($type instanceof \ReflectionUnionType){
                $temp['type'] = implode(',', $p->getTypes());
            }else{
                // ?? This should never happen
                $temp['type'] = 'string';
            }
            
            if($p->isOptional()){
                $temp['default'] = $p->getDefaultValue();
            }
            return $temp;
        }, $params);

        return $params;
    }

    private function reformatArgs($inputParameters): array
    {
        // Don't do anything if the array if empty
        if(count($inputParameters) === 0) return $inputParameters;

        $output = [];

        Debug::dump("autowire", "REFORMAT ARG....................");
        Debug::dump("autowire", ["REFORMAT ARG INPUT: " => $inputParameters]);

        foreach($inputParameters as $name => $arg){
            if(is_string($name)){
                // Take care of string key-ed arrays
                $output[] = ['name' => $this->normaliseArgName($name), 'value' => $arg];
            }else if(is_int($name) && is_array($arg)){
                // Take care of numerically indexed arrays with array like data (such as command line arguments)
                if(array_key_exists('name', $arg) && array_key_exists('value', $arg)){
                    $arg['name'] = $this->normaliseArgName($arg['name']);
                    $output[] = $arg;
                }else{
                    // I'm not sure what other formats to take care of right now
                    $output[] = $arg;
                }
            }
        }
        Debug::dump("autowire", ["REFORMAT ARG OUTPUT" => $output]);

        return $output;
    }

    private function normaliseArgName(string $name): string
    {
        $name = trim($name, " -");
        $name = ucwords(str_replace(['-', '_'], ' ', $name));
        $name = lcfirst(str_replace(' ', '', $name));
        return $name;
    }

    private function resolveArgs(array $signatureParameters, array $inputParameters): array
    {
        $output = [];

        Debug::dump("autowire", "STARTING AUTOWIRING....................");
        Debug::dump("autowire", ["AUTOWIRING INPUT: " => $inputParameters]);
        foreach($signatureParameters as $search){
            $name = $search['name'];
            // FIXME: I'm not sure how to handle union types, which are represented here as a csv of variable types
            $type = trim((string)$search['type'], '?');

            if(empty($type)){
                $type = 'string';
            }

            Debug::dump("autowire", "=======================\nSEARCH PARAMETER: name = '$name' with type '$type'");

            if(class_exists($type) || interface_exists($type)){
                // When the type is a class, 
                foreach($inputParameters as $index => $data){
                    if($data['name']  === $name && is_object($data['value']) && get_class($data['value']) === $type){
                        Debug::dump("autowire", "FOUND OBJECT ARG: name = '$name'");
                        $output[] = $data['value'];
                        unset($inputParameters[$index]);
                        continue 2;
                    }
                }

                Debug::dump("autowire", "FOUND CONTAINER ARG: type = '$type'");
                $output[] = call_user_func($this->resolver, $type);
                continue;
            }else if($type === 'array'){
                foreach($inputParameters as $index => $data){
                    if($data['name'] == $name){
                        if(is_array($data['value'])){
                            Debug::dump("autowire", ['FOUND NAMED ARRAY' => $data]);
                            $output[] = $data['value'];
                            unset($inputParameters[$index]);
                            continue 2;
                        }
                    }
                }
            }else{
                // When the type is a string, we look in the input array for matches

                // for every named parameter, we must look for an input parameter with the same name AND HAS A VALUE
                foreach($inputParameters as $index => $data){
                    if(!array_key_exists('name', $data)){
                        Debug::dump("autowire", "Parameter format invalid: ".json_encode($data));
                        continue;
                    }

                    if($data['name'] === $name && array_key_exists('value', $data)){
                        $test_numeric = (int)filter_var($data['value'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
                        Debug::dump("autowire", "NAMED TYPE CHECK($name), numeric = $test_numeric, value = '{$data['value']}'");
                        if($type === 'bool'){
                            $value = filter_var($data['value'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                            if(is_bool($value)){
                                Debug::dump("autowire", "FOUND NAMED BOOL: name = '$name', value = '$value}'");
                                $output[] = $value;
                                unset($inputParameters[$index]);
                                continue 2;
                            }
                        }
                        
                        if($type === 'int'){
                            $value = filter_var($data['name'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
                            if(is_int($value)){
                                Debug::dump("autowire", "FOUND NAMED INT: name = '$name', value = '$value'");
                                $output[] = $value;
                                unset($inputParameters[$index]);
                                continue 2;    
                            }
                        }
                        
                        if($type === 'float'){
                            $value = filter_var($data['name'], FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
                            if(is_float($value)){
                                Debug::dump("autowire", "FOUND NAMED FLOAT: name = '$name', value = '$value'");
                                $output[] = $value;
                                unset($inputParameters[$index]);
                                continue 2;    
                            }
                        }
                        
                        if($type === 'string' && array_key_exists('value', $data)){
                            Debug::dump("autowire", "FOUND NAMED STRING: name = '$name', value = '{$data['value']}'");
                            $output[] = $data['value'];
                            unset($inputParameters[$index]);
                            continue 2;
                        }
                    }
                }

                // We did not find a named parameter, therefore lets pick the first anonymous parameter
                foreach($inputParameters as $index => $data){
                    if(!array_key_exists('name', $data)){
                        Debug::dump("autowire", "Parameter format invalid: ".json_encode($data));
                        continue;
                    }

                    // If it has a value, then it's not an anonymous string
                    if(is_array($data) && array_key_exists('value', $data)){
                        continue;
                    }
                    
                    // double-dash is a special shell escape sequence
                    // it means all the text to the right should be sent to a sub-command
                    // the text on the left, is the command being run, so this command maybe
                    // will run a sub-command and pass the arguments on the right to it
                    if($data['name'] === '--'){
                        break;
                    }

                    $test_numeric = (int)filter_var($data['name'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
                    Debug::dump("autowire", "ANON TYPE CHECK($type / {$data['name']}), numeric = $test_numeric, value = '{$data['name']}'");
                    if($type === 'bool'){
                        $value = filter_var($data['name'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                        if(is_bool($value)) {
                            Debug::dump("autowire", "FOUND ANON BOOL: name = '$value'");
                            $output[] = $value;
                            unset($inputParameters[$index]);
                            continue 2;
                        }
                    }

                    if($type === 'int'){
                        $value = filter_var($data['name'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
                        if(is_int($value)){
                            Debug::dump("autowire", "FOUND ANON INT: name = '$value'");
                            $output[] = $value;
                            unset($inputParameters[$index]);
                            continue 2;
                        }
                    }

                    if($type === 'float'){
                        $value = filter_var($data['name'], FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
                        if(is_float($value)){
                            Debug::dump("autowire", "FOUND ANON FLOAT: name = '$value'");
                            $output[] = $value;
                            unset($inputParameters[$index]);
                            continue 2;
                        }
                    }

                    if($type === 'string' && !empty($data['name'])){
                        Debug::dump("autowire", "FOUND ANON STRING: name = '{$data['name']}'");
                        $output[] = $data['name'];
                        unset($inputParameters[$index]);
                        continue 2;
                    }
                }
            }

            if(array_key_exists('default', $search)){
                $sd = is_scalar($search['default']) ? $search['default'] : json_encode($search['default']);
                Debug::dump("autowire", "FOUND DEFAULT VALUE: name = '$name', default = '{$sd}'");
                $output[] = $search['default'];
                continue;
            }

            throw new CannotAutowireParameterException($name, $type);
        }

        Debug::dump("autowire", ["AUTOWIRING OUTPUT" => array_map(function($a){ 
            return is_object($a) ? get_class($a) : $a;
        },$output)]);

        return $output;
    }
}