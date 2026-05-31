<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\BlockContext;
use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;

class GotoStatement extends Statement
{
    public readonly string $label;

    public function __construct(string $label, Location $location)
    {
        $this->label = $label;
        $this->Location = $location;
    }

    public function alwaysReturns(): bool
    {
        return false;
    }

    public function addDeclarationToBlock(BlockContext $context): void
    {
    }

    protected function doEmit(EmitContext $ec): void
    {
        $label = $ec->resolveGotoLabel($this->label);
        if ($label !== null) {
            $ec->emit(OpCode::Jump, $label);
        } else {
            $ec->Report->error(9999, error: "goto statement used outside of function body");
        }
    }
}
