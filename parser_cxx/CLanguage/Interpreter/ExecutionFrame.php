<?php declare(strict_types=1);

namespace CLanguage\Interpreter;

class ExecutionFrame
{
    public int $FP = 0;
    public int $IP = 0;
    public BaseFunction $Function;

    public function __construct(BaseFunction $function)
    {
        $this->Function = $function;
    }

    public function __toString(): string
    {
        return "{$this->FP}: {$this->Function->Name}";
    }
}
