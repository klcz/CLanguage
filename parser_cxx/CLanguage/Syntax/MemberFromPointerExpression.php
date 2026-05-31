<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;
use CLanguage\Types\CBasicType;
use CLanguage\Types\CFunctionType;
use CLanguage\Types\CPointerType;
use CLanguage\Types\CStructMember;
use CLanguage\Types\CStructMethod;
use CLanguage\Types\CStructType;
use CLanguage\Types\CType;
use CLanguage\Value;
use Override;
use RuntimeException;

class MemberFromPointerExpression extends Expression
{
    public Expression $Left;
    public string $MemberName;

    public function __construct(Expression $left, string $memberName)
    {
        $this->Left = $left;
        $this->MemberName = $memberName;
    }

    #[Override]
    public function canEmitPointer(): bool
    {
        return true;
    }

    #[Override]
    public function __toString(): string
    {
        return "{$this->Left}->{$this->MemberName}";
    }

    #[Override]
    protected function doEmit(EmitContext $ec): void
    {
        $targetType = $this->Left->getEvaluatedCType($ec);

        if ($targetType instanceof CPointerType && $targetType->InnerType instanceof CStructType) {
            $structType = $targetType->InnerType;
            $member = self::findMember($structType, $this->MemberName);

            if ($member === null) {
                $ec->Report->error(1061, error: "'{$this->MemberName}' not found in '{$structType->Name}'");
            } else {
                if ($member instanceof CStructMethod && $member->MemberType instanceof CFunctionType) {
                    if ($member->VTableSlotIndex !== null && $structType->VTableGlobalAddress !== null) {
                        // Virtual dispatch path
                        $this->Left->emit($ec);
                        $ec->emit(OpCode::Dup);
                        $ec->emit(OpCode::LoadPointer);
                        $ec->emit(OpCode::LoadConstant, Value::pointer($member->VTableSlotIndex));
                        $ec->emit(OpCode::OffsetPointer);
                        $ec->emit(OpCode::LoadPointer);
                    } else {
                        // Non-virtual direct path
                        $res = $ec->resolveMethodFunction($structType, $member);
                        if ($res !== null) {
                            $this->Left->emit($ec);
                            $ec->emit(OpCode::LoadConstant, Value::pointer($res->Address));
                        }
                    }
                } else {
                    $this->Left->emit($ec);
                    $ec->emit(OpCode::LoadConstant, Value::pointer($structType->getFieldValueOffset($member, $ec)));
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

        if ($targetType instanceof CPointerType && $targetType->InnerType instanceof CStructType) {
            $member = self::findMember($targetType->InnerType, $this->MemberName);
            if ($member === null) {
                $ec->Report->error(1061, error: "'{$this->MemberName}' not found in '{$targetType->InnerType->Name}'");
                return CBasicType::signedInt();
            }
            return $member->MemberType;
        }

        if ($targetType instanceof CPointerType) {
            $ec->Report->error(1061, error: "'{$this->MemberName}' not found in '{$targetType}'");
            return CBasicType::signedInt();
        } else {
            $ec->Report->error(1061, error: "-> cannot be used with '{$targetType}'");
            return CBasicType::signedInt();
        }
    }

    protected static function findMember(CStructType $structType, string $name): ?CStructMember
    {
        return $structType->findMember($name);
    }

    #[Override]
    protected function doEmitPointer(EmitContext $ec): void
    {
        $targetType = $this->Left->getEvaluatedCType($ec);

        if ($targetType instanceof CPointerType && $targetType->InnerType instanceof CStructType) {
            $structType = $targetType->InnerType;
            $member = self::findMember($structType, $this->MemberName);

            if ($member === null) {
                $ec->Report->error(1061, error: "'{$this->MemberName}' not found in '{$structType->Name}'");
            } else {
                if ($member instanceof CStructMethod && $member->MemberType instanceof CFunctionType) {
                    $ec->Report->error(1656, error: "Cannot assign to '{$this->MemberName}'");
                } else {
                    $this->Left->emit($ec);
                    $ec->emit(OpCode::LoadConstant, Value::pointer($structType->getFieldValueOffset($member, $ec)));
                    $ec->emit(OpCode::OffsetPointer);
                }
            }
        } else {
            throw new RuntimeException("Cannot write '{$this->MemberName}' on " . get_class($targetType));
        }
    }
}
