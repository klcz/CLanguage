<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\BlockContext;
use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;

class BreakStatement extends Statement
{
    public function __construct()
    {
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
        if ($ec->BreakLabel !== null) {
            $ec->emit(OpCode::Jump, $ec->BreakLabel);
        } else {
            $ec->Report->error(139, error: "No enclosing statement out of which to break");
        }
    }
}
