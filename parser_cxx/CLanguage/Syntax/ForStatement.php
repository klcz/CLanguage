<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\BlockContext;
use CLanguage\Compiler\EmitContext;
use CLanguage\Compiler\VariableScope;
use CLanguage\Interpreter\OpCode;

class ForStatement extends Statement
{
    public readonly Block $initBlock;
    public readonly ?Expression $continueExpression;
    public readonly ?Expression $nextExpression;
    public readonly Block $loopBody;

    public function __construct(
        ?Statement  $initStatement = null,
        ?Expression $continueExpr = null,
        ?Block      $loopBody = null,
        ?Expression $nextExpression = null,
    )
    {
        $this->initBlock = new Block(VariableScope::Local);
        if ($initStatement !== null) {
            $this->initBlock->addStatement($initStatement);
        }
        $this->continueExpression = $continueExpr;
        $this->nextExpression = $nextExpression;
        $this->loopBody = $loopBody ?? new Block(VariableScope::Local);
    }

    public function __toString(): string
    {
        $init = (string)$this->initBlock;
        $cont = (string)$this->continueExpression;
        $next = (string)$this->nextExpression;
        return "for ({$init}; {$cont}; {$next}) {$this->loopBody}";
    }

    /** @noinspection PhpParameterNameChangedDuringInheritanceInspection */

    public function addDeclarationToBlock(BlockContext $context): void
    {
        $this->initBlock->addDeclarationToBlock($context);
        $this->loopBody->addDeclarationToBlock($context);
    }

    public function alwaysReturns(): bool
    {
        return false;
    }

    protected function doEmit(EmitContext $initialContext): void
    {
        $initialContext->beginBlock($this->initBlock);
        foreach ($this->initBlock->InitStatements as $s) {
            $s->emit($initialContext);
        }
        foreach ($this->initBlock->Statements as $s) {
            $s->emit($initialContext);
        }

        $nextLabel = $initialContext->defineLabel();
        $endLabel = $initialContext->defineLabel();

        $ec = $initialContext->pushLoop(breakLabel: $endLabel, continueLabel: $nextLabel);

        $conditionLabel = $ec->defineLabel();
        $ec->emitLabel($conditionLabel);
        if ($this->continueExpression !== null) {
            $this->continueExpression->emit($ec);
            $ec->emitCastToBoolean($this->continueExpression->getEvaluatedCType($ec));
            $ec->emit(OpCode::BranchIfFalse, $endLabel);
        }

        $this->loopBody->emit($ec);

        $ec->emitLabel($nextLabel);
        if ($this->nextExpression !== null) {
            $this->nextExpression->emit($ec);
            $ec->emit(OpCode::Pop);
        }
        $ec->emit(OpCode::Jump, $conditionLabel);

        $ec->emitLabel($endLabel);

        $ec->endBlock();
    }
}
