<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\BlockContext;
use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;
use CLanguage\Types\CStructType;

class ExpressionStatement extends Statement
{
    public ?Expression $Expression = null;

    public function __construct(Expression $expression)
    {
        $this->Expression = $expression;
    }

    public function __toString(): string
    {
        return "{$this->Expression};";
    }

    public function addDeclarationToBlock(BlockContext $context): void
    {
    }

    public function alwaysReturns(): bool
    {
        return false;
    }

    protected function doEmit(EmitContext $ec): void
    {
        if ($this->Expression !== null) {
            $this->Expression->emit($ec);

            $exprType = $this->Expression->getEvaluatedCType($ec);
            if ($exprType instanceof CStructType) {
                $numValues = $exprType->NumValues;
                for ($i = 0; $i < $numValues; $i++) {
                    $ec->emit(OpCode::Pop);
                }
            } else {
                $ec->emit(OpCode::Pop);
            }
        }
    }
}
