<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;
use CLanguage\Types\CBasicType;
use CLanguage\Types\CType;
use CLanguage\Value;
use Override;

enum RelationalOp: int
{
    case Equals = 0;
    case NotEquals = 1;
    case LessThan = 2;
    case LessThanOrEqual = 3;
    case GreaterThan = 4;
    case GreaterThanOrEqual = 5;
}

class RelationalExpression extends Expression
{
    public Expression $left;
    public RelationalOp $op;
    public Expression $right;

    public function __construct(Expression $left, RelationalOp $op, Expression $right)
    {
        $this->left = $left;
        $this->op = $op;
        $this->right = $right;
    }

    #[Override]
    public function evalConstant(EmitContext $ec): Value
    {
        $leftType = $this->left->getEvaluatedCType($ec);
        $rightType = $this->right->getEvaluatedCType($ec);

        if ($leftType->isIntegral() && $rightType->isIntegral()) {
            $left = $this->left->evalConstant($ec)->Int32Value;
            $right = $this->right->evalConstant($ec)->Int32Value;

            return match ($this->op) {
                RelationalOp::Equals => Value::fromBool($left === $right),
                RelationalOp::NotEquals => Value::fromBool($left !== $right),
                RelationalOp::LessThan => Value::fromBool($left < $right),
                RelationalOp::LessThanOrEqual => Value::fromBool($left <= $right),
                RelationalOp::GreaterThan => Value::fromBool($left > $right),
                RelationalOp::GreaterThanOrEqual => Value::fromBool($left >= $right),
            };
        }

        return parent::evalConstant($ec);
    }

    #[Override]
    public function getEvaluatedCType(EmitContext $ec): CType
    {
        $leftType = $this->left->getEvaluatedCType($ec);
        $rightType = $this->right->getEvaluatedCType($ec);
        $ft = self::tryResolveBinaryOperatorType($ec, $leftType, $rightType, self::relOpToOperatorName($this->op));
        if ($ft !== null) {
            return $ft->ReturnType;
        }
        return CBasicType::bool();
    }

    #[Override]
    public function __toString(): string
    {
        return "({$this->left} {$this->op->name} {$this->right})";
    }

    #[Override]
    protected function doEmit(EmitContext $ec): void
    {
        $leftType = $this->left->getEvaluatedCType($ec);
        $rightType = $this->right->getEvaluatedCType($ec);

        if (self::tryEmitBinaryOperatorCall($ec, $leftType, $rightType, $this->left, $this->right, self::relOpToOperatorName($this->op))) {
            return;
        }

        $aType = self::getArithmeticType($this->left, $this->right, $this->op->name, $ec);

        $this->left->emit($ec);
        $ec->emitCast($leftType, $aType);
        $this->right->emit($ec);
        $ec->emitCast($rightType, $aType);

        $ioff = $ec->getInstructionOffset($aType);

        switch ($this->op) {
            case RelationalOp::Equals:
                $ec->emit(OpCode::from((int)OpCode::EqualToInt8 + $ioff), Value::fromInt(0));
                break;
            case RelationalOp::NotEquals:
                $ec->emit(OpCode::from((int)OpCode::EqualToInt8 + $ioff), Value::fromInt(0));
                $ec->emit(OpCode::from((int)OpCode::NotInt8 + $ioff), Value::fromInt(0));
                break;
            case RelationalOp::LessThan:
                $ec->emit(OpCode::from((int)OpCode::LessThanInt8 + $ioff), Value::fromInt(0));
                break;
            case RelationalOp::LessThanOrEqual:
                $ec->emit(OpCode::from((int)OpCode::GreaterThanInt8 + $ioff), Value::fromInt(0));
                $ec->emit(OpCode::from((int)OpCode::NotInt8 + $ioff), Value::fromInt(0));
                break;
            case RelationalOp::GreaterThan:
                $ec->emit(OpCode::from((int)OpCode::GreaterThanInt8 + $ioff), Value::fromInt(0));
                break;
            case RelationalOp::GreaterThanOrEqual:
                $ec->emit(OpCode::from((int)OpCode::LessThanInt8 + $ioff), Value::fromInt(0));
                $ec->emit(OpCode::from((int)OpCode::NotInt8 + $ioff), Value::fromInt(0));
                break;
        }
    }
}
