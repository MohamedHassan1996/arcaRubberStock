<?php
    
namespace Core;

class Middleware
{
    public string $name;
    public ?array $only;
    public ?array $except;

    public function __construct(string $name, array $only = null, array $except = null)
    {
        $this->name = $name;
        $this->only = $only;
        $this->except = $except;
    }

    public function appliesTo(string $method): bool
    {
        if ($this->only !== null && !in_array($method, $this->only)) {
            return false;
        }

        if ($this->except !== null && in_array($method, $this->except)) {
            return false;
        }

        return true;
    }
}
