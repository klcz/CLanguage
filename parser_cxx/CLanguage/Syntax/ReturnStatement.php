<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\BlockContext;
use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;

class ReturnStatement extends Statement
{
    public readonly ?Expression $returnExpression;

    public function __construct(?Expression $returnExpression = null)
    {
        $this->returnExpression = $returnExpression;
    }

    public function addDeclarationToBlock(BlockContext $context): void
    {
    }

    public function alwaysReturns(): bool
    {
        return true;
    }

    protected function doEmit(EmitContext $ec): void
    {
        $f = $ec->FunctionDecl;
        if ($f === null) {
            $ec->Report->error(1519, error: "Invalid return outside of function");
            return;
        }

        if ($this->returnExpression !== null) {
            if ($f->FunctionType->ReturnType->isVoid()) {
                $ec->Report->error(127, error: "A return keyword must not be followed by any expression when the function returns void");
            } else {
                $this->returnExpression->emit($ec);
                $ec->emitCast($this->returnExpression->getEvaluatedCType($ec), $f->FunctionType->ReturnType);
                $ec->emit(OpCode::Return);
            }
        } else {
            if ($f->FunctionType->ReturnType->isVoid()) {
                $ec->emit(OpCode::Return);
            } else {
                $ec->Report->error(126, error: "A value is required for the return statement");
            }
        }
    }
}
