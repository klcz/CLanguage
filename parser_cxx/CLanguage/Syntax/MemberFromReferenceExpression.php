<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;
use CLanguage\Types\CBasicType;
use CLanguage\Types\CFunctionType;
use CLanguage\Types\CStructMember;
use CLanguage\Types\CStructMethod;
use CLanguage\Types\CStructType;
use CLanguage\Types\CType;
use CLanguage\Value;
use Override;
use RuntimeException;

class MemberFromReferenceExpression extends Expression
{
    public Expression $Left;
    public string $MemberName;

    public function __construct(Expression $left, string $memberName)
    {
        $this->Left = $left;
        $this->MemberName = $memberName;
    }

    #[Override]
    public function __toString(): string
    {
        return "{$this->Left}.{$this->MemberName}";
    }

    #[Override]
    protected function doEmit(EmitContext $ec): void
    {
        $targetType = $this->Left->getEvaluatedCType($ec);

        if ($targetType instanceof CStructType) {
            $member = self::findMember($targetType, $this->MemberName);

            if ($member === null) {
                $ec->Report->error(1061, error: "'{$this->MemberName}' not found in '{$targetType->Name}'");
            } else {
                if ($member instanceof CStructMethod && $member->MemberType instanceof CFunctionType) {
                    if ($member->VTableSlotIndex !== null && $targetType->VTableGlobalAddress !== null) {
                        // Virtual dispatch path
                        $this->Left->emitPointer($ec);
                        $ec->emit(OpCode::Dup);
                        $ec->emit(OpCode::LoadPointer);
                        $ec->emit(OpCode::LoadConstant, Value::pointer($member->VTableSlotIndex));
                        $ec->emit(OpCode::OffsetPointer);
                        $ec->emit(OpCode::LoadPointer);
                    } else {
                        // Non-virtual direct path
                        $res = $ec->resolveMethodFunction($targetType, $member);
                        if ($res !== null) {
                            $this->Left->emitPointer($ec);
                            $ec->emit(OpCode::LoadConstant, Value::pointer($res->Address));
                        }
                    }
                } else {
                    if ($this->Left->canEmitPointer()) {
                        $this->Left->emitPointer($ec);
                    } else {
                        // Left is a rvalue (e.g., function call returning struct).
                        // Allocate a temp, store the struct there, then use its address.
                        $tempOffset = $ec->allocateTemp($targetType);
                        $this->Left->emit($ec);
                        $numValues = $targetType->getNumValues();
                        for ($i = $numValues - 1; $i >= 0; $i--) {
                            $ec->emit(OpCode::StoreLocal, Value::fromInt($tempOffset + $i));
                        }
                        $ec->emit(OpCode::LoadConstant, Value::pointer($tempOffset));
                        $ec->emit(OpCode::LoadFramePointer);
                        $ec->emit(OpCode::OffsetPointer);
                    }
                    $ec->emit(OpCode::LoadConstant, Value::pointer($targetType->getFieldValueOffset($member, $ec)));
                    $ec->emit(OpCode::OffsetPointer);
                    $ec->emit(OpCode::LoadPointer);
                }
            }
        } else {
            throw new RuntimeException("Cannot read '{$this->MemberName}' on " . get_class($targetType));
        }
    }

    #[Override]
    public function getEvaluatedCType(EmitContext $ec): CType
    {
        $targetType = $this->Left->getEvaluatedCType($ec);

        if ($targetType instanceof CStructType) {
            $member = self::findMember($targetType, $this->MemberName);
            if ($member === null) {
                $ec->Report->error(1061, error: "'{$this->MemberName}' not found in '{$targetType->Name}'");
                return CBasicType::signedInt();
            }
            return $member->MemberType;
        } else {
            throw new RuntimeException('Member type on ' . ($targetType !== null ? get_class($targetType) : 'null'));
        }
    }

    protected static function findMember(CStructType $structType, string $name): ?CStructMember
    {
        return $structType->findMember($name);
    }

    #[Override]
    public function canEmitPointer(): bool
    {
        return true;
    }

    #[Override]
    protected function doEmitPointer(EmitContext $ec): void
    {
        $targetType = $this->Left->getEvaluatedCType($ec);

        if ($targetType instanceof CStructType) {
            $member = self::findMember($targetType, $this->MemberName);

            if ($member === null) {
                $ec->Report->error(1061, error: "'{$this->MemberName}' not found in '{$targetType->Name}'");
            } else {
                if ($member instanceof CStructMethod && $member->MemberType instanceof CFunctionType) {
                    $ec->Report->error(1656, error: "Cannot assign to '{$this->MemberName}'");
                } else {
                    $this->Left->emitPointer($ec);
                    $ec->emit(OpCode::LoadConstant, Value::pointer($targetType->getFieldValueOffset($member, $ec)));
                    $ec->emit(OpCode::OffsetPointer);
                }
            }
        } else {
            throw new RuntimeException("Cannot write '{$this->MemberName}' on " . get_class($targetType));
        }
    }
}
