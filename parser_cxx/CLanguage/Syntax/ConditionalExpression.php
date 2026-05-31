<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;
use CLanguage\Types\CType;
use Override;

class ConditionalExpression extends Expression
{
    public Expression $condition;
    public Expression $trueValue;
    public Expression $falseValue;

    public function __construct(Expression $condition, Expression $trueValue, Expression $falseValue)
    {
        $this->condition = $condition;
        $this->trueValue = $trueValue;
        $this->falseValue = $falseValue;
    }

    #[Override]
    protected function doEmit(EmitContext $ec): void
    {
        $falseLabel = $ec->defineLabel();
        $endLabel = $ec->defineLabel();

        $this->condition->emit($ec);
        $ec->emitCastToBoolean($this->condition->getEvaluatedCType($ec));
        $ec->emit(OpCode::BranchIfFalse, $falseLabel);

        $this->trueValue->emit($ec);
        $ec->emit(OpCode::Jump, $endLabel);

        $ec->emitLabel($falseLabel);
        $this->falseValue->emit($ec);

        $ec->emitLabel($endLabel);
    }

    #[Override]
    public function getEvaluatedCType(EmitContext $ec): CType
    {
        return $this->trueValue->getEvaluatedCType($ec);
    }
}
