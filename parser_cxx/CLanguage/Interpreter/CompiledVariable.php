<?php declare(strict_types=1);

namespace CLanguage\Interpreter;

use CLanguage\Types\CType;

class CompiledVariable
{
    public readonly string $Name;
    public readonly CType $VariableType;
    public int $StackOffset;
    public ?array $InitialValue = null;

    public function __construct(string $name, int $offset, CType $type)
    {
        $this->Name = $name;
        $this->StackOffset = $offset;
        $this->VariableType = $type;
    }

    public function __toString(): string
    {
        return "{$this->VariableType} {$this->Name}";
    }
}
