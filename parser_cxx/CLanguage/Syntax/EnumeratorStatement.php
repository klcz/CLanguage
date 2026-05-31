<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\BlockContext;
use CLanguage\Compiler\EmitContext;

class EnumeratorStatement extends Statement
{
    public readonly string $Name;
    public readonly ?Expression $LiteralValue;

    public function __construct(string $name, ?Expression $literalValue = null)
    {
        $this->Name = $name;
        $this->LiteralValue = $literalValue;
    }

    public function AlwaysReturns(): bool
    {
        return false;
    }

    public function __toString(): string
    {
        if ($this->LiteralValue !== null) {
            return $this->Name . ' = ' . $this->LiteralValue;
        }
        return $this->Name;
    }

    public function AddDeclarationToBlock(BlockContext $context): void
    {
    }

    protected function DoEmit(EmitContext $ec): void
    {
    }
}
