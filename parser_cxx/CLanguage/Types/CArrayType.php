<?php declare(strict_types=1);

namespace CLanguage\Types;

use CLanguage\Compiler\EmitContext;

class CArrayType extends CType
{
    public readonly CType $ElementType;
    public readonly ?int $Length;

    public function __construct(CType $elementType, ?int $length)
    {
        $this->ElementType = $elementType;
        $this->Length = $length;
    }

    public function getNumValues(): int
    {
        if ($this->Length === null) {
            return 1;
        }
        $innerSize = $this->ElementType->getNumValues();
        return $this->Length * $innerSize;
    }

    public function getByteSize(EmitContext $c): int
    {
        if ($this->Length === null) {
            return $c->MachineInfo->pointerSize;
        }
        $innerSize = $this->ElementType->getByteSize($c);
        return $this->Length * $innerSize;
    }

    public function scoreCastTo(CType $otherType): int
    {
        if ($this->equals($otherType)) {
            return 1000;
        }

        if ($otherType instanceof CPointerType) {
            if ($this->ElementType->equals($otherType->InnerType)) {
                return 900;
            } else {
                return intdiv($this->ElementType->scoreCastTo($otherType->InnerType), 2);
            }
        }

        return 0;
    }

    public function equals(?object $obj): bool
    {
        return $obj instanceof CArrayType
            && $this->Length === $obj->Length
            && $this->ElementType->equals($obj->ElementType);
    }

    public function getHashCode(): int
    {
        $hash = 17;
        $hash = $hash * 37 + $this->ElementType->getHashCode();
        return $hash * 37 + ($this->Length !== null ? $this->Length : 0);
    }

    public function __toString(): string
    {
        return sprintf('%s[%s]', $this->ElementType, $this->Length ?? '');
    }

    protected function createPointerType(): CPointerType
    {
        return $this->ElementType->pointer();
    }
}
