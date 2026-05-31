<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;
use CLanguage\Types\CBasicType;
use CLanguage\Types\CType;
use CLanguage\Value;
use Override;

enum LogicOp: int
{
    case And = 0;
    case Or = 1;
}

class LogicExpression extends Expression
{
    public Expression $left;
    public LogicOp $op;
    public Expression $right;

    public function __construct(Expression $left, LogicOp $op, Expression $right)
    {
        $this->left = $left;
        $this->op = $op;
        $this->right = $right;
    }

    #[Override]
    public function __toString(): string
    {
        return "({$this->left} {$this->op->name} {$this->right})";
    }

    #[Override]
    protected function doEmit(EmitContext $ec): void
    {
        $shortCircuitLabel = $ec->defineLabel();
        $endLabel = $ec->defineLabel();

        $this->left->emit($ec);
        $ec->emitCastToBoolean($this->left->getEvaluatedCType($ec));

        switch ($this->op) {
            case LogicOp::And:
                $ec->emit(OpCode::BranchIfFalse, $shortCircuitLabel);
                break;
            case LogicOp::Or:
                $ec->emit(OpCode::BranchIfTrue, $shortCircuitLabel);
                break;
        }

        $this->right->emit($ec);
        $ec->emitCastToBoolean($this->right->getEvaluatedCType($ec));

        switch ($this->op) {
            case LogicOp::And:
                $ec->emit(OpCode::BranchIfFalse, $shortCircuitLabel);
                break;
            case LogicOp::Or:
                $ec->emit(OpCode::BranchIfTrue, $shortCircuitLabel);
                break;
        }

        switch ($this->op) {
            case LogicOp::And:
                $ec->emit(OpCode::LoadConstant, Value::fromInt(1));
                break;
            case LogicOp::Or:
                $ec->emit(OpCode::LoadConstant, Value::fromInt(0));
                break;
        }
        $ec->emit(OpCode::Jump, $endLabel);

        $ec->emitLabel($shortCircuitLabel);
        switch ($this->op) {
            case LogicOp::And:
                $ec->emit(OpCode::LoadConstant, Value::fromInt(0));
                break;
            case LogicOp::Or:
                $ec->emit(OpCode::LoadConstant, Value::fromInt(1));
                break;
        }

        $ec->emitLabel($endLabel);
    }

    #[Override]
    public function getEvaluatedCType(EmitContext $ec): CType
    {
        return CBasicType::bool();
    }
}
