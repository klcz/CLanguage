<?php declare(strict_types=1);

namespace CLanguage\Interpreter;

use CLanguage\Types\CFunctionType;

abstract class BaseFunction
{
    public string $Name = '';
    public string $NameContext = '';
    public CFunctionType $FunctionType;

    public function __construct()
    {
        $this->FunctionType = CFunctionType::$VoidProcedure;
    }

    public function init(CInterpreter $state): void
    {
    }

    abstract public function step(CInterpreter $state, ExecutionFrame $frame): void;

    public function __toString(): string
    {
        return $this->NameContext === '' ? $this->Name : $this->NameContext . '::' . $this->Name;
    }
}
