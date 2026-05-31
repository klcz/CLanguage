<?php declare(strict_types=1);

namespace CLanguage\Types;

use CLanguage\Compiler\EmitContext;

class CReferenceType extends CType
{
    public readonly CType $InnerType;

    public function __construct(CType $innerType)
    {
        $this->InnerType = $innerType;
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
        if ($otherType instanceof CReferenceType && $this->InnerType->equals($otherType->InnerType)) {
            return 1000;
        }
        if ($this->InnerType->equals($otherType)) {
            return 900;
        }
        if ($otherType instanceof CPointerType && $this->InnerType->equals($otherType->InnerType)) {
            return 800;
        }
        return $this->InnerType->scoreCastTo($otherType);
    }

    public function equals(?object $obj): bool
    {
        return $obj instanceof CReferenceType && $this->InnerType->equals($obj->InnerType);
    }

    public function getHashCode(): int
    {
        return $this->InnerType->getHashCode() ^ 0x5A5A;
    }

    public function __toString(): string
    {
        return "{$this->InnerType}&";
    }
}
