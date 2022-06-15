<?php declare(strict_types=1);

namespace DDT\Exceptions\Autowire;

class CannotAutowireParameterException extends \Exception {
    private $className = null;
    private $methodName = null;
    private $parameterName;
    private $parameterType;
    private $template = "Could not autowire parameter '{{name}}' {{class}} {{method}} because type '{{type}}' was not autowirable or with a default value";

    private function updateMessage()
    {
        $params = [
            '{{name}}' => $this->parameterName,
            '{{class}}' => $this->className ? "on class '$this->className'" : '',
            '{{method}}' => $this->methodName ? "using method '$this->methodName'" : '',
            '{{type}}' => $this->parameterType,
        ];

        $this->message = str_replace(array_keys($params), array_values($params), $this->template);
        $this->message = preg_replace("/\s+/", " ", $this->message);
    }

    public function __construct(string $name, string $type, int $code = 0, \Throwable $previous = null){
        parent::__construct("", $code, $previous);
        $this->parameterName = $name;
        $this->parameterType = $type;

        $this->updateMessage();
    }

    public function setClassName(string $className): void
    {
        $this->className = $className;

        $this->updateMessage();
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function setMethodName(string $methodName): void
    {
        $this->methodName = $methodName;

        $this->updateMessage();
    }

    public function getMethodName(): string
    {
        return $this->methodName;
    }

    public function getParameterName(): string
    {
        return $this->parameterName;
    }

    public function getParameterType(): string
    {
        return $this->parameterType;
    }
}