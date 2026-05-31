<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\BlockContext;
use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;

class WhileStatement extends Statement
{
    public readonly bool $isDo;
    public readonly Expression $condition;
    public readonly Block $loop;

    public function __construct(bool $isDo, Expression $condition, Block $loop)
    {
        $this->isDo = $isDo;
        $this->condition = $condition;
        $this->loop = $loop;
    }

    /** @noinspection PhpParameterNameChangedDuringInheritanceInspection */

    public function alwaysReturns(): bool
    {
        return false;
    }

    public function __toString(): string
    {
        if ($this->isDo) {
            return "do {$this->loop} while({$this->condition});";
        }
        return "while ({$this->condition}) {$this->loop};";
    }

    public function addDeclarationToBlock(BlockContext $context): void
    {
        $this->loop->addDeclarationToBlock($context);
    }

    protected function doEmit(EmitContext $parentContext): void
    {
        $condLabel = $parentContext->defineLabel();
        $loopLabel = $parentContext->defineLabel();
        $endLabel = $parentContext->defineLabel();

        $ec = $parentContext->pushLoop(breakLabel: $endLabel, continueLabel: $condLabel);

        if ($this->isDo) {
            $ec->emitLabel($loopLabel);
            $this->loop->emit($ec);
            $ec->emitLabel($condLabel);
            $this->condition->emit($ec);
            $ec->emitCastToBoolean($this->condition->getEvaluatedCType($ec));
            $ec->emit(OpCode::BranchIfFalse, $endLabel);
            $ec->emit(OpCode::Jump, $condLabel);
        } else {
            $ec->emitLabel($condLabel);
            $this->condition->emit($ec);
            $ec->emitCastToBoolean($this->condition->getEvaluatedCType($ec));
            $ec->emit(OpCode::BranchIfFalse, $endLabel);
            $ec->emitLabel($loopLabel);
            $parentContext->beginBlock($this->loop);
            $this->loop->emit($ec);
            $ec->emit(OpCode::Jump, $condLabel);
        }
        $ec->emitLabel($endLabel);
    }
}
