<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\EmitContext;
use CLanguage\Compiler\VariableScope;
use CLanguage\Interpreter\OpCode;
use CLanguage\Types\CArrayType;
use CLanguage\Types\CReferenceType;
use CLanguage\Types\CStructType;
use CLanguage\Types\CType;
use CLanguage\Value;
use Override;
use RuntimeException;

class AssignExpression extends Expression
{
    public Expression $left;
    public Expression $right;

    public function __construct(Expression $left, Expression $right)
    {
        $this->left = $left;
        $this->right = $right;
    }

    #[Override]
    public function __toString(): string
    {
        return "{$this->left} = {$this->right}";
    }

    #[Override]
    protected function doEmit(EmitContext $ec): void
    {
        if ($this->right instanceof StructureExpression) {
            $this->doEmitStructureAssignment($this->right, $ec);
            return;
        }

        $this->right->emit($ec);

        if ($this->left instanceof VariableExpression) {
            $variable = $this->left;
            $v = $ec->resolveVariable($variable, null);

            if ($v->VariableType instanceof CReferenceType) {
                $refType = $v->VariableType;
                $ec->emitCast($this->right->getEvaluatedCType($ec), $refType->InnerType);
                $ec->emit(OpCode::Dup);
                VariableExpression::emitLoadReferenceSlot($ec, $v);
                $ec->emit(OpCode::StorePointer);
            } elseif ($v->VariableType instanceof CStructType) {
                $structType = $v->VariableType;
                $ec->emitCast($this->right->getEvaluatedCType($ec), $this->left->getEvaluatedCType($ec));
                $numValues = $structType->numValues();
                for ($i = $numValues - 1; $i >= 0; $i--) {
                    if ($v->Scope === VariableScope::Global) {
                        $ec->emit(OpCode::StoreGlobal, Value::fromInt($v->Address + $i));
                    } elseif ($v->Scope === VariableScope::Local) {
                        $ec->emit(OpCode::StoreLocal, Value::fromInt($v->Address + $i));
                    } elseif ($v->Scope === VariableScope::Arg) {
                        $ec->emit(OpCode::StoreArg, Value::fromInt($v->Address + $i));
                    } else {
                        throw new RuntimeException("Assigning struct to scope '{$v->Scope->name}'");
                    }
                }
                for ($i = 0; $i < $numValues; $i++) {
                    if ($v->Scope === VariableScope::Global) {
                        $ec->emit(OpCode::LoadGlobal, Value::fromInt($v->Address + $i));
                    } elseif ($v->Scope === VariableScope::Local) {
                        $ec->emit(OpCode::LoadLocal, Value::fromInt($v->Address + $i));
                    } elseif ($v->Scope === VariableScope::Arg) {
                        $ec->emit(OpCode::LoadArg, Value::fromInt($v->Address + $i));
                    } else {
                        throw new RuntimeException("Loading struct from scope '{$v->Scope->name}'");
                    }
                }
            } else {
                $ec->emitCast($this->right->getEvaluatedCType($ec), $this->left->getEvaluatedCType($ec));
                $ec->emit(OpCode::Dup);

                if ($v->Scope === VariableScope::Global) {
                    $ec->emit(OpCode::StoreGlobal, Value::fromInt($v->Address));
                } elseif ($v->Scope === VariableScope::Local) {
                    $ec->emit(OpCode::StoreLocal, Value::fromInt($v->Address));
                } elseif ($v->Scope === VariableScope::Arg) {
                    $ec->emit(OpCode::StoreArg, Value::fromInt($v->Address));
                } elseif ($v->Scope === VariableScope::Function) {
                    $ec->emit(OpCode::Pop);
                    $ec->Report->error(1656, error: "Cannot assign to `{$variable->VariableName}` because it is a function");
                } else {
                    throw new RuntimeException("Assigning to scope '{$v->Scope->name}'");
                }
            }
        } elseif ($this->left->canEmitPointer()) {
            $ec->emitCast($this->right->getEvaluatedCType($ec), $this->left->getEvaluatedCType($ec));
            $ec->emit(OpCode::Dup);
            $this->left->emitPointer($ec);
            $ec->emit(OpCode::StorePointer);
        } else {
            $ec->Report->error(131, error: "The left-hand side of an assignment must be a variable or an addressable memory location");
        }
    }

    private function doEmitStructureAssignment(StructureExpression $sexpr, EmitContext $ec): void
    {
        $type = $this->getEvaluatedCType($ec);

        if ($type instanceof CArrayType) {
            $this->emitArrayStructuredInit($sexpr, $type, 0, $ec);
            $this->left->emitPointer($ec);
        } else {
            throw new RuntimeException("Structured assignment of '{$this->getEvaluatedCType($ec)}' not supported");
        }
    }

    #[Override]
    public function getEvaluatedCType(EmitContext $ec): CType
    {
        return $this->left->getEvaluatedCType($ec);
    }

    private function emitArrayStructuredInit(StructureExpression $sexpr, CArrayType $arrayType, int $baseOffset, EmitContext $ec): void
    {
        $elementType = $arrayType->ElementType;
        $numItemValues = $elementType->getNumValues();

        $count = count($sexpr->Items);
        for ($i = 0; $i < $count; $i++) {
            $item = $sexpr->Items[$i];
            $itemOffset = $baseOffset + $i * $numItemValues;

            if ($item->Expression instanceof StructureExpression && $elementType instanceof CArrayType) {
                $this->emitArrayStructuredInit($item->Expression, $elementType, $itemOffset, $ec);
            } else {
                $item->Expression->emit($ec);
                $ec->emitCast($item->Expression->getEvaluatedCType($ec), $elementType);
                $this->left->emitPointer($ec);
                $ec->emit(OpCode::LoadConstant, Value::fromInt($itemOffset));
                $ec->emit(OpCode::OffsetPointer);
                $ec->emit(OpCode::StorePointer);
            }
        }
    }
}
