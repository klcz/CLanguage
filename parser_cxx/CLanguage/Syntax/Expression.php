<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\EmitContext;
use CLanguage\Compiler\ResolvedVariable;
use CLanguage\Interpreter\OpCode;
use CLanguage\Types\CArrayType;
use CLanguage\Types\CBasicType;
use CLanguage\Types\CFunctionType;
use CLanguage\Types\CPointerType;
use CLanguage\Types\CReferenceType;
use CLanguage\Types\CStructMethod;
use CLanguage\Types\CStructType;
use CLanguage\Types\CType;
use CLanguage\Value;
use ReflectionClass;
use RuntimeException;

abstract class Expression
{
    public Location $Location;
    public Location $EndLocation;
    public bool $hasError = false;

    protected static function getPromotedType(Expression $expr, string $op, EmitContext $ec): CType
    {
        $leftType = $expr->getEvaluatedCType($ec);

        if ($leftType instanceof CBasicType) {
            return $leftType->integerPromote($ec);
        } elseif ($leftType instanceof CArrayType) {
            return $leftType->ElementType->pointer();
        } elseif ($leftType instanceof CPointerType) {
            return $leftType;
        } else {
            $ec->Report->error(19, error: "'{$op}' cannot be applied to operand of type '{$leftType}'");
            return CBasicType::signedInt();
        }
    }

    abstract public function getEvaluatedCType(EmitContext $ec): CType;

    protected static function getArithmeticType(Expression $leftExpr, Expression $rightExpr, string $op, EmitContext $ec): CType
    {
        $leftType = $leftExpr->getEvaluatedCType($ec);
        $rightType = $rightExpr->getEvaluatedCType($ec);

        if ($leftType instanceof CBasicType && $rightType instanceof CBasicType) {
            return $leftType->arithmeticConvert($rightType, $ec);
        } elseif ($leftType instanceof CPointerType && $rightType instanceof CBasicType) {
            return $leftType;
        } elseif ($leftType instanceof CArrayType && $rightType instanceof CBasicType) {
            return $leftType->ElementType->pointer();
        } elseif ($rightType instanceof CPointerType && $leftType instanceof CBasicType) {
            return $rightType;
        } elseif ($rightType instanceof CArrayType && $leftType instanceof CBasicType) {
            return $rightType->ElementType->pointer();
        } else {
            $ec->Report->error(19, error: "'{$op}' cannot be applied to operands of type '{$leftType}' and '{$rightType}'");
            return CBasicType::signedInt();
        }
    }

    protected static function binopToOperatorName(Binop $op): ?string
    {
        /** @noinspection PhpUnusedMatchConditionInspection */
        return match ($op) {
            Binop::Add => 'operator+',
            Binop::Subtract => 'operator-',
            Binop::Multiply => 'operator*',
            Binop::Divide => 'operator/',
            Binop::Mod => 'operator%',
            Binop::ShiftLeft => 'operator<<',
            Binop::ShiftRight => 'operator>>',
            Binop::BinaryAnd => 'operator&',
            Binop::BinaryOr => 'operator|',
            Binop::BinaryXor => 'operator^',
            default => null,
        };
    }

    protected static function relOpToOperatorName(RelationalOp $op): ?string
    {
        /** @noinspection PhpUnusedMatchConditionInspection */
        return match ($op) {
            RelationalOp::Equals => 'operator==',
            RelationalOp::NotEquals => 'operator!=',
            RelationalOp::LessThan => 'operator<',
            RelationalOp::LessThanOrEqual => 'operator<=',
            RelationalOp::GreaterThan => 'operator>',
            RelationalOp::GreaterThanOrEqual => 'operator>=',
            default => null,
        };
    }

    protected static function unopToOperatorName(Unop $op): ?string
    {
        return match ($op) {
            Unop::Negate => 'operator-',
            Unop::Not => 'operator!',
            Unop::BinaryComplement => 'operator~',
            default => null,
        };
    }

    protected static function tryResolveBinaryOperatorType(EmitContext $ec, CType $leftType, CType $rightType, ?string $operatorName): ?CFunctionType
    {
        if ($operatorName === null) {
            return null;
        }
        if ($leftType instanceof CStructType) {
            $m = self::findBestOperatorMethod($leftType, $operatorName, [$rightType]);
            if ($m !== null && $m->MemberType instanceof CFunctionType) {
                return $m->MemberType;
            }
            $resolved = $ec->tryResolveOperatorFunction($leftType->Name, $operatorName, [$rightType]);
            if ($resolved !== null && $resolved->VariableType instanceof CFunctionType) {
                return $resolved->VariableType;
            }
        }
        if ($leftType instanceof CStructType || $rightType instanceof CStructType) {
            $res = $ec->tryResolveVariable($operatorName, [$leftType, $rightType]);
            if ($res !== null && $res->VariableType instanceof CFunctionType) {
                return $res->VariableType;
            }
        }
        return null;
    }

    protected static function findBestOperatorMethod(CStructType $structType, string $operatorName, array $argTypes): ?CStructMethod
    {
        $methods = $structType->findMethods($operatorName);
        $best = null;
        $bestScore = 0;
        foreach ($methods as $m) {
            if ($m->MemberType instanceof CFunctionType) {
                $score = $m->MemberType->scoreParameterTypeMatches($argTypes);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $m;
                }
            }
        }
        return $best;
    }

    protected static function tryResolveUnaryOperatorType(EmitContext $ec, CType $operandType, ?string $operatorName): ?CFunctionType
    {
        if ($operatorName === null) {
            return null;
        }
        if ($operandType instanceof CStructType) {
            $m = self::findBestOperatorMethod($operandType, $operatorName, []);
            if ($m !== null && $m->MemberType instanceof CFunctionType) {
                return $m->MemberType;
            }
            $resolved = $ec->tryResolveOperatorFunction($operandType->Name, $operatorName, []);
            if ($resolved !== null && $resolved->VariableType instanceof CFunctionType) {
                return $resolved->VariableType;
            }
            $res = $ec->tryResolveVariable($operatorName, [$operandType]);
            if ($res !== null && $res->VariableType instanceof CFunctionType) {
                return $res->VariableType;
            }
        }
        return null;
    }

    // ── Operator Overload Helpers ──────────────────────────────────────────

    protected static function tryEmitBinaryOperatorCall(EmitContext $ec, CType $leftType, CType $rightType, Expression $left, Expression $right, ?string $operatorName): bool
    {
        if ($operatorName === null) {
            return false;
        }

        $argTypes = [$rightType];
        $args = [$right];

        if ($leftType instanceof CStructType) {
            $method = self::findBestOperatorMethod($leftType, $operatorName, $argTypes);
            if ($method !== null) {
                $funcType = $method->MemberType;
                $resolved = $ec->resolveMethodFunction($leftType, $method);
                /** @noinspection PhpParamsInspection */
                self::emitMemberOperatorCall($ec, $leftType, $resolved, $funcType, $left, $args, $argTypes);
                return true;
            }
            $resolvedOp = $ec->tryResolveOperatorFunction($leftType->Name, $operatorName, $argTypes);
            if ($resolvedOp !== null && $resolvedOp->VariableType instanceof CFunctionType) {
                self::emitMemberOperatorCall($ec, $leftType, $resolvedOp, $resolvedOp->VariableType, $left, $args, $argTypes);
                return true;
            }
        }

        if ($leftType instanceof CStructType || $rightType instanceof CStructType) {
            $freeArgTypes = [$leftType, $rightType];
            $res = $ec->tryResolveVariable($operatorName, $freeArgTypes);
            if ($res !== null && $res->VariableType instanceof CFunctionType) {
                self::emitFreeStandingOperatorCall($ec, $res, [$left, $right], $freeArgTypes);
                return true;
            }
        }

        return false;
    }

    private static function emitMemberOperatorCall(EmitContext $ec, CStructType $structType, ResolvedVariable $resolved, CFunctionType $funcType, Expression $thisExpr, array $args, array $argTypes): void
    {
        $tempThisOffset = -1;

        if (!$thisExpr->canEmitPointer()) {
            $tempThisOffset = $ec->allocateTemp($structType);
            $thisExpr->emit($ec);
            $numValues = $structType->getNumValues();
            for ($i = $numValues - 1; $i >= 0; $i--) {
                $ec->emit(OpCode::StoreLocal, Value::fromInt($tempThisOffset + $i));
            }
        }

        $params = $funcType->Parameters;
        for ($i = 0; $i < count($args); $i++) {
            $paramType = $params[$i]->ParameterType;
            self::emitOperatorArgument($ec, $args[$i], $argTypes[$i], $paramType);
        }

        if ($tempThisOffset >= 0) {
            $ec->emit(OpCode::LoadConstant, Value::pointer($tempThisOffset));
            $ec->emit(OpCode::LoadFramePointer);
            $ec->emit(OpCode::OffsetPointer);
        } else {
            $thisExpr->emitPointer($ec);
        }

        $ec->emit(OpCode::LoadConstant, Value::pointer($resolved->Address));
        $ec->emit(OpCode::Call, Value::fromInt(count($params)));

        if ($funcType->ReturnType->isVoid()) {
            $ec->emit(OpCode::LoadConstant, Value::fromInt(0));
        }
    }

    public function canEmitPointer(): bool
    {
        return false;
    }

    public function emit(EmitContext $ec): void
    {
        $this->doEmit($ec);
    }

    abstract protected function doEmit(EmitContext $ec): void;

    private static function emitOperatorArgument(EmitContext $ec, Expression $arg, CType $argType, CType $paramType): void
    {
        if ($paramType instanceof CReferenceType) {
            if ($arg->canEmitPointer()) {
                $arg->emitPointer($ec);
            } else {
                $innerType = $paramType->InnerType;
                $tempOffset = $ec->allocateTemp($innerType);
                $arg->emit($ec);
                $ec->emitCast($argType, $innerType);
                $numValues = $innerType->getNumValues();
                for ($i = $numValues - 1; $i >= 0; $i--) {
                    $ec->emit(OpCode::StoreLocal, Value::fromInt($tempOffset + $i));
                }
                $ec->emit(OpCode::LoadConstant, Value::pointer($tempOffset));
                $ec->emit(OpCode::LoadFramePointer);
                $ec->emit(OpCode::OffsetPointer);
            }
        } else {
            $arg->emit($ec);
            $ec->emitCast($argType, $paramType);
        }
    }

    public function emitPointer(EmitContext $ec): void
    {
        $this->doEmitPointer($ec);
    }

    protected function doEmitPointer(EmitContext $ec): void
    {
        throw new RuntimeException(
            "Cannot get address of " . new ReflectionClass($this)->getShortName() . " `{$this}`"
        );
    }

    private static function emitFreeStandingOperatorCall(EmitContext $ec, ResolvedVariable $resolvedFunc, array $args, array $argTypes): void
    {
        $funcType = $resolvedFunc->VariableType;

        $params = $funcType->Parameters;
        for ($i = 0; $i < count($args); $i++) {
            $paramType = $params[$i]->ParameterType;
            self::emitOperatorArgument($ec, $args[$i], $argTypes[$i], $paramType);
        }

        $resolvedFunc->emit($ec);
        $ec->emit(OpCode::Call, Value::fromInt(count($params)));

        if ($funcType->ReturnType->isVoid()) {
            $ec->emit(OpCode::LoadConstant, Value::fromInt(0));
        }
    }

    protected static function tryEmitUnaryOperatorCall(EmitContext $ec, CType $operandType, Expression $operand, ?string $operatorName): bool
    {
        if ($operatorName === null) {
            return false;
        }

        if ($operandType instanceof CStructType) {
            $method = self::findBestOperatorMethod($operandType, $operatorName, []);
            if ($method !== null) {
                $funcType = $method->MemberType;
                $resolved = $ec->resolveMethodFunction($operandType, $method);
                /** @noinspection PhpParamsInspection */
                self::emitMemberOperatorCall($ec, $operandType, $resolved, $funcType, $operand, [], []);
                return true;
            }
            $resolvedOp = $ec->tryResolveOperatorFunction($operandType->Name, $operatorName, []);
            if ($resolvedOp !== null && $resolvedOp->VariableType instanceof CFunctionType) {
                self::emitMemberOperatorCall($ec, $operandType, $resolvedOp, $resolvedOp->VariableType, $operand, [], []);
                return true;
            }
            $argTypes = [$operandType];
            $res = $ec->tryResolveVariable($operatorName, $argTypes);
            if ($res !== null && $res->VariableType instanceof CFunctionType) {
                self::emitFreeStandingOperatorCall($ec, $res, [$operand], $argTypes);
                return true;
            }
        }

        return false;
    }

    public function evalConstant(EmitContext $ec): Value
    {
        $ec->Report->error(133, error: "'{$this}' not constant");
        return new Value(0);
    }
}
