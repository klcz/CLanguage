<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;
use CLanguage\Types\CBasicType;
use CLanguage\Types\CType;
use CLanguage\Value;
use Override;
use RuntimeException;

enum Unop: int
{
    case None = 0;
    case Not = 1;
    case Negate = 2;
    case BinaryComplement = 3;
    case PreIncrement = 4;
    case PreDecrement = 5;
    case PostIncrement = 6;
    case PostDecrement = 7;
}

class UnaryExpression extends Expression
{
    public readonly Unop $Op;
    public readonly Expression $Right;

    public function __construct(Unop $op, Expression $right)
    {
        $this->Op = $op;
        $this->Right = $right;
    }

    #[Override]
    public function __toString(): string
    {
        return "({$this->Op->name} {$this->Right})";
    }

    #[Override]
    public function evalConstant(EmitContext $ec): Value
    {
        $rightType = $this->Right->getEvaluatedCType($ec);

        if ($rightType->isIntegral()) {
            $right = (int)$this->Right->evalConstant($ec);

            /** @noinspection PhpUnusedMatchConditionInspection */
            return match ($this->Op) {
                Unop::None => Value::fromInt($right),
                Unop::Not => Value::fromInt(($right === 0) ? 1 : 0),
                Unop::Negate => Value::fromInt(-$right),
                Unop::BinaryComplement => Value::fromInt(~$right),
                Unop::PreIncrement => Value::fromInt($right + 1),
                Unop::PreDecrement => Value::fromInt($right - 1),
                Unop::PostIncrement => Value::fromInt($right),
                Unop::PostDecrement => Value::fromInt($right),
                default => throw new RuntimeException("Unsupported unary operator '" . $this->Op->name . "'"),
            };
        }

        return parent::evalConstant($ec);
    }

    #[Override]
    public function getEvaluatedCType(EmitContext $ec): CType
    {
        $rightType = $this->Right->getEvaluatedCType($ec);
        $ft = self::tryResolveUnaryOperatorType($ec, $rightType, self::unopToOperatorName($this->Op));
        if ($ft !== null)
            return $ft->ReturnType;
        return ($this->Op === Unop::Not) ? CBasicType::signedInt() : self::getPromotedType($this->Right, $this->Op->name, $ec);
    }

    #[Override]
    protected function doEmit(EmitContext $ec): void
    {
        $rightType = $this->Right->getEvaluatedCType($ec);
        $opName = self::unopToOperatorName($this->Op);
        if ($opName !== null && self::tryEmitUnaryOperatorCall($ec, $rightType, $this->Right, $opName))
            return;

        switch ($this->Op) {
            case Unop::PreIncrement:
                {
                    $ie = new AssignExpression($this->Right, new BinaryExpression($this->Right, Binop::Add, ConstantExpression::one()));
                    $ie->emit($ec);
                }
                break;
            case Unop::PreDecrement:
                {
                    $ie = new AssignExpression($this->Right, new BinaryExpression($this->Right, Binop::Add, ConstantExpression::negativeOne()));
                    $ie->emit($ec);
                }
                break;
            case Unop::PostIncrement:
                {
                    $this->Right->emit($ec);
                    $ie = new AssignExpression($this->Right, new BinaryExpression($this->Right, Binop::Add, ConstantExpression::one()));
                    $ie->emit($ec);
                    $ec->emit(OpCode::Pop);
                }
                break;
            case Unop::PostDecrement:
                {
                    $this->Right->emit($ec);
                    $ie = new AssignExpression($this->Right, new BinaryExpression($this->Right, Binop::Add, ConstantExpression::negativeOne()));
                    $ie->emit($ec);
                    $ec->emit(OpCode::Pop);
                }
                break;
            default:
                {
                    $aType = $this->getEvaluatedCType($ec);
                    if (!($aType instanceof CBasicType)) {
                        throw new RuntimeException("Unary operator '{$this->Op->name}' requires arithmetic type");
                    }

                    $this->Right->emit($ec);
                    $ec->emitCast($this->Right->getEvaluatedCType($ec), $aType);

                    $ioff = $ec->getInstructionOffset($aType);

                    match ($this->Op) {
                        Unop::None => null,
                        Unop::Negate => $ec->emit(OpCode::from(OpCode::NegateInt8->value + $ioff)),
                        Unop::Not => $ec->emit(OpCode::from(OpCode::NotInt8->value + $ioff)),
                        Unop::BinaryComplement => $ec->emit(OpCode::from(OpCode::BinaryNotInt8->value + $ioff)),
                        default => throw new RuntimeException("Unsupported unary operator '" . $this->Op->name . "'"),
                    };
                }
                break;
        }
    }
}
