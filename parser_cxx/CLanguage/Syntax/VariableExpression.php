<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\EmitContext;
use CLanguage\Compiler\ResolvedVariable;
use CLanguage\Compiler\VariableScope;
use CLanguage\Interpreter\OpCode;
use CLanguage\Types\CArrayType;
use CLanguage\Types\CBasicType;
use CLanguage\Types\CEnumType;
use CLanguage\Types\CPointerType;
use CLanguage\Types\CReferenceType;
use CLanguage\Types\CStructType;
use CLanguage\Types\CType;
use CLanguage\Value;
use Override;
use RuntimeException;

class VariableExpression extends Expression
{
    public string $VariableName;

    public function __construct(string $val, Location $loc, Location $endLoc)
    {
        $this->VariableName = $val;
        $this->Location = $loc;
        $this->EndLocation = $endLoc;
    }

    #[Override]
    public function getEvaluatedCType(EmitContext $ec): CType
    {
        $type = $ec->resolveVariable($this, null)->VariableType;
        if ($type instanceof CReferenceType)
            return $type->InnerType;
        return $type;
    }

    #[Override]
    public function CanEmitPointer(): bool
    {
        return true;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->VariableName;
    }

    #[Override]
    public function evalConstant(EmitContext $ec): Value
    {
        $res = $ec->resolveVariable($this, null);

        if ($res !== null) {
            if ($res->Scope === VariableScope::Constant) {
                return $res->Constant;
            }
        }

        return parent::evalConstant($ec);
    }

    #[Override]
    protected function doEmit(EmitContext $ec): void
    {
        $variable = $ec->resolveVariable($this, null);

        if ($variable !== null) {

            if ($variable->Scope === VariableScope::Function) {
                $ec->emit(OpCode::LoadConstant, Value::Pointer($variable->Address));
            } elseif ($variable->VariableType instanceof CReferenceType) {
                self::emitLoadReferenceSlot($ec, $variable);
                $ec->emit(OpCode::LoadPointer);
            } else {
                if ($variable->VariableType instanceof CBasicType ||
                    $variable->VariableType instanceof CPointerType ||
                    $variable->VariableType instanceof CEnumType) {

                    switch ($variable->Scope) {
                        case VariableScope::Arg:
                            $ec->emit(OpCode::LoadArg, Value::fromInt($variable->Address));
                            break;
                        case VariableScope::Global:
                            $ec->emit(OpCode::LoadGlobal, Value::fromInt($variable->Address));
                            break;
                        case VariableScope::Local:
                            $ec->emit(OpCode::LoadLocal, Value::fromInt($variable->Address));
                            break;
                        case VariableScope::Constant:
                            $ec->emit(OpCode::LoadConstant, $variable->Constant);
                            break;
                        default:
                            throw new RuntimeException("Cannot evaluate variable scope '{$variable->Scope}'");
                    }
                } elseif ($variable->VariableType instanceof CStructType) {
                    $numValues = $variable->VariableType->getNumValues();
                    for ($i = 0; $i < $numValues; $i++) {
                        switch ($variable->Scope) {
                            case VariableScope::Arg:
                                $ec->emit(OpCode::LoadArg, Value::fromInt($variable->Address + $i));
                                break;
                            case VariableScope::Global:
                                $ec->emit(OpCode::LoadGlobal, Value::fromInt($variable->Address + $i));
                                break;
                            case VariableScope::Local:
                                $ec->emit(OpCode::LoadLocal, Value::fromInt($variable->Address + $i));
                                break;
                            default:
                                throw new RuntimeException("Cannot evaluate struct variable scope '{$variable->Scope}'");
                        }
                    }
                } elseif ($variable->VariableType instanceof CArrayType) {
                    switch ($variable->Scope) {
                        case VariableScope::Arg:
                            $ec->emit(OpCode::LoadConstant, Value::Pointer($variable->Address));
                            $ec->emit(OpCode::LoadFramePointer);
                            $ec->emit(OpCode::OffsetPointer);
                            break;
                        case VariableScope::Global:
                            $ec->emit(OpCode::LoadConstant, Value::Pointer($variable->Address));
                            break;
                        case VariableScope::Local:
                            $ec->emit(OpCode::LoadConstant, Value::Pointer($variable->Address));
                            $ec->emit(OpCode::LoadFramePointer);
                            $ec->emit(OpCode::OffsetPointer);
                            break;
                        default:
                            throw new RuntimeException("Cannot evaluate array variable scope '{$variable->Scope}'");
                    }
                } else {
                    throw new RuntimeException("Cannot evaluate variable type '{$variable->VariableType}'");
                }
            }
        } else {
            $ec->emit(OpCode::LoadConstant, Value::fromInt(0));
        }
    }

    /**
     * Emit a load of the raw slot value for a reference variable (the stored pointer).
     * Used by doEmit, doEmitPointer, and AssignExpression for reference variable handling.
     */
    public static function emitLoadReferenceSlot(EmitContext $ec, ResolvedVariable $variable): void
    {
        switch ($variable->Scope) {
            case VariableScope::Arg:
                $ec->emit(OpCode::LoadArg, Value::fromInt($variable->Address));
                break;
            case VariableScope::Local:
                $ec->emit(OpCode::LoadLocal, Value::fromInt($variable->Address));
                break;
            case VariableScope::Global:
                $ec->emit(OpCode::LoadGlobal, Value::fromInt($variable->Address));
                break;
            default:
                throw new RuntimeException("Cannot access reference variable scope '{$variable->Scope}'");
        }
    }

    #[Override]
    protected function doEmitPointer(EmitContext $ec): void
    {
        $res = $ec->resolveVariable($this, null);

        if ($res !== null) {
            if ($res->VariableType instanceof CReferenceType) {
                self::emitLoadReferenceSlot($ec, $res);
            } else {
                $res->emitPointer($ec);
            }
        } else {
            $ec->emit(OpCode::LoadConstant, Value::fromInt(0));
        }
    }
}
