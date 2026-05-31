<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;
use CLanguage\Types\CBasicType;
use CLanguage\Types\CType;
use CLanguage\Value;
use Override;
use RuntimeException;

enum Binop: int
{
    case Add = 0;
    case Subtract = 1;
    case Multiply = 2;
    case Divide = 3;
    case Mod = 4;
    case ShiftLeft = 5;
    case ShiftRight = 6;
    case BinaryAnd = 7;
    case BinaryOr = 8;
    case BinaryXor = 9;
}

class BinaryExpression extends Expression
{
    public readonly Expression $Left;
    public readonly Binop $Op;
    public readonly Expression $Right;

    public function __construct(Expression $left, Binop $op, Expression $right)
    {
        $this->Left = $left;
        $this->Op = $op;
        $this->Right = $right;
    }

    #[Override]
    public function __toString(): string
    {
        return "({$this->Left} {$this->Op->name} {$this->Right})";
    }

    #[Override]
    public function evalConstant(EmitContext $ec): Value
    {
        $leftType = $this->Left->getEvaluatedCType($ec);
        $rightType = $this->Right->getEvaluatedCType($ec);

        if ($leftType->isIntegral() && $rightType->isIntegral()) {
            $left = (int)$this->Left->evalConstant($ec);
            $right = (int)$this->Right->evalConstant($ec);

            /** @noinspection PhpUnusedMatchConditionInspection */
            return match ($this->Op) {
                Binop::Add => Value::fromInt($left + $right),
                Binop::Subtract => Value::fromInt($left - $right),
                Binop::Multiply => Value::fromInt($left * $right),
                Binop::Divide => Value::fromInt((int)($left / $right)),
                Binop::Mod => Value::fromInt($left % $right),
                Binop::BinaryAnd => Value::fromInt($left & $right),
                Binop::BinaryOr => Value::fromInt($left | $right),
                Binop::BinaryXor => Value::fromInt($left ^ $right),
                Binop::ShiftLeft => Value::fromInt($left << $right),
                Binop::ShiftRight => Value::fromInt($left >> $right),
                default => throw new RuntimeException("Unsupported binary operator '" . $this->Op->name . "'"),
            };
        }

        return parent::evalConstant($ec);
    }

    #[Override]
    public function getEvaluatedCType(EmitContext $ec): CType
    {
        $leftType = $this->Left->getEvaluatedCType($ec);
        $rightType = $this->Right->getEvaluatedCType($ec);
        $ft = self::tryResolveBinaryOperatorType($ec, $leftType, $rightType, self::binopToOperatorName($this->Op));
        if ($ft !== null)
            return $ft->ReturnType;
        if ($this->Op === Binop::ShiftLeft || $this->Op === Binop::ShiftRight)
            return self::getShiftPromotedType($leftType, $ec);
        return self::getArithmeticType($this->Left, $this->Right, $this->Op->name, $ec);
    }

    /**
     * C11 §6.5.7: For shift operators, each operand is independently integer-promoted.
     * The type of the result is that of the promoted left operand.
     */
    private static function getShiftPromotedType(CType $type, EmitContext $ec): CType
    {
        if ($type instanceof CBasicType)
            return $type->integerPromote($ec);
        return $type;
    }

    #[Override]
    protected function doEmit(EmitContext $ec): void
    {
        $leftType = $this->Left->getEvaluatedCType($ec);
        $rightType = $this->Right->getEvaluatedCType($ec);

        if (self::tryEmitBinaryOperatorCall(
            $ec, $leftType, $rightType,
            $this->Left, $this->Right,
            self::binopToOperatorName($this->Op)
        )) {
            return;
        }

        if ($this->Op === Binop::ShiftLeft || $this->Op === Binop::ShiftRight) {
            // C11 §6.5.7: The integer promotions are performed on each of the operands.
            // The type of the result is that of the promoted left operand.
            $promotedLeft = self::getShiftPromotedType($leftType, $ec);

            $this->Left->emit($ec);
            $ec->emitCast($leftType, $promotedLeft);
            $this->Right->emit($ec);
            $ec->emitCast($rightType, $promotedLeft);

            $shiftOff = $ec->getInstructionOffset($promotedLeft);

            if ($this->Op === Binop::ShiftLeft) {
                $ec->emit(OpCode::from(OpCode::ShiftLeftInt8->value + $shiftOff));
            } else {
                $ec->emit(OpCode::from(OpCode::ShiftRightInt8->value + $shiftOff));
            }
            return;
        }

        $aType = self::getArithmeticType($this->Left, $this->Right, $this->Op->name, $ec);

        $this->Left->emit($ec);
        $ec->emitCast($leftType, $aType);
        $this->Right->emit($ec);
        $ec->emitCast($rightType, $aType);

        $ioff = $ec->getInstructionOffset($aType);

        match ($this->Op) {
            Binop::Add => $ec->emit(OpCode::from(OpCode::AddInt8->value + $ioff)),
            Binop::Subtract => $ec->emit(OpCode::from(OpCode::SubtractInt8->value + $ioff)),
            Binop::Multiply => $ec->emit(OpCode::from(OpCode::MultiplyInt8->value + $ioff)),
            Binop::Divide => $ec->emit(OpCode::from(OpCode::DivideInt8->value + $ioff)),
            Binop::Mod => $ec->emit(OpCode::from(OpCode::ModuloInt8->value + $ioff)),
            Binop::BinaryAnd => $ec->emit(OpCode::from(OpCode::BinaryAndInt8->value + $ioff)),
            Binop::BinaryOr => $ec->emit(OpCode::from(OpCode::BinaryOrInt8->value + $ioff)),
            Binop::BinaryXor => $ec->emit(OpCode::from(OpCode::BinaryXorInt8->value + $ioff)),
            default => throw new RuntimeException("Unsupported binary operator '" . $this->Op->name . "'"),
        };
    }
}
