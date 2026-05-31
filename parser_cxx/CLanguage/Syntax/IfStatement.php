<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\BlockContext;
use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;

class IfStatement extends Statement
{
    public readonly Expression $condition;
    public readonly Statement $trueStatement;
    public readonly ?Statement $falseStatement;

    public function __construct(
        Expression $condition,
        Statement  $trueStatement,
        ?Statement $falseStatement = null,
        ?Location  $loc = null,
    )
    {
        $this->condition = $condition;
        $this->trueStatement = $trueStatement;
        $this->falseStatement = $falseStatement;
        if ($loc !== null) {
            $this->Location = $loc;
        }
    }

    public function __toString(): string
    {
        return "if ({$this->condition}) {$this->trueStatement};";
    }

    public function addDeclarationToBlock(BlockContext $context): void
    {
        $this->trueStatement->addDeclarationToBlock($context);
        $this->falseStatement?->addDeclarationToBlock($context);
    }

    public function alwaysReturns(): bool
    {
        $tr = $this->trueStatement->alwaysReturns();
        $fr = $this->falseStatement !== null && $this->falseStatement->alwaysReturns();
        return $tr && $fr;
    }

    protected function doEmit(EmitContext $ec): void
    {
        $endLabel = $ec->defineLabel();

        $this->condition->emit($ec);
        $ec->emitCastToBoolean($this->condition->getEvaluatedCType($ec));

        if ($this->falseStatement === null) {
            $ec->emit(OpCode::BranchIfFalse, $endLabel);
            $this->trueStatement->emit($ec);
        } else {
            $falseLabel = $ec->defineLabel();
            $ec->emit(OpCode::BranchIfFalse, $falseLabel);
            $this->trueStatement->emit($ec);
            $ec->emit(OpCode::Jump, $endLabel);
            $ec->emitLabel($falseLabel);
            $this->falseStatement->emit($ec);
        }

        $ec->emitLabel($endLabel);
    }
}
