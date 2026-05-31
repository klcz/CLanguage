<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\BlockContext;
use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;

class ContinueStatement extends Statement
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
        if ($ec->ContinueLabel !== null) {
            $ec->emit(OpCode::Jump, $ec->ContinueLabel);
        } else {
            $ec->Report->error(139, error: "No enclosing statement out of which to continue");
        }
    }
}
