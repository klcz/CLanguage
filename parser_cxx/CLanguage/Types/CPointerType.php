<?php declare(strict_types=1);

namespace CLanguage\Types;

use CLanguage\Compiler\EmitContext;

class CPointerType extends CType
{
    public static CPointerType $PointerToConstChar;
    public static CPointerType $PointerToVoid;
    public readonly CType $InnerType;

    public function __construct(CType $innerType)
    {
        $this->InnerType = $innerType;
    }

    public static function init(): void
    {
        self::$PointerToConstChar = new CPointerType(CBasicType::constChar());
        self::$PointerToVoid = new CPointerType(CType::voidType());
    }

    public function getNumValues(): int
    {
        return 1;
    }

    public function getByteSize(EmitContext $c): int
    {
        return $c->MachineInfo->pointerSize;
    }

    public function scoreCastTo(CType $otherType): int
    {
        if ($this->equals($otherType)) {
            return 1000;
        }
        if ($otherType instanceof CPointerType
            && $this->InnerType instanceof CStructType
            && $otherType->InnerType instanceof CStructType
            && $this->InnerType->isDerivedFrom($otherType->InnerType)) {
            return 900;
        }
        return 0;
    }

    public function equals(?object $obj): bool
    {
        return $obj instanceof CPointerType && $this->InnerType->equals($obj->InnerType);
    }

    public function __toString(): string
    {
        return $this->InnerType . '*';
    }

    public function getHashCode(): int
    {
        $hash = 17;
        $hash = $hash * 37 + $this->InnerType->getHashCode();
        return $hash * 37 + 1;
    }
}
