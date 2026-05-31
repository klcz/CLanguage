<?php declare(strict_types=1);

namespace CLanguage\Types;

use CLanguage\Compiler\EmitContext;
use CLanguage\MachineInfo;
use Override;
use RuntimeException;

class CIntType extends CBasicType
{
    public function __construct(string $name, Signedness $signedness, string $size)
    {
        parent::__construct($name, $signedness, $size);
    }

    #[Override]
    public function isIntegral(): bool
    {
        return true;
    }

    #[Override]
    public function getNumValues(): int
    {
        return 1;
    }

    #[Override]
    public function getByteSize(EmitContext $c): int
    {
        return $this->getByteSizeForMachine($c->MachineInfo);
    }

    private function getByteSizeForMachine(MachineInfo $c): int
    {
        if ($this->Name === "char") {
            return $c->CharSize;
        } elseif ($this->Name === "int") {
            return match ($this->Size) {
                "short" => $c->ShortIntSize,
                "long" => $c->LongIntSize,
                "long long" => $c->LongLongIntSize,
                default => $c->IntSize,
            };
        } else {
            throw new RuntimeException((string)$this);
        }
    }

    #[Override]
    public function scoreCastTo(CType $otherType): int
    {
        if ($this->equals($otherType)) return 1000;
        if ($otherType instanceof CIntType) {
            if ($this->Name === $otherType->Name && $this->Size === $otherType->Size) return 950;
            if ($this->Size === $otherType->Size) return 900;
            return 800;
        } elseif ($otherType instanceof CFloatType) {
            if ($otherType->Bits === 64) return 400;
            return 300;
        } elseif ($otherType instanceof CBoolType) {
            return 200;
        } else {
            return 0;
        }
    }

    #[Override]
    public function getClrValue(array $values, MachineInfo $machineInfo): mixed
    {
        $byteSize = $this->getByteSizeForMachine($machineInfo);
        if ($this->Signedness === Signedness::Signed) {
            return match ($byteSize) {
                1 => $values[0]->Int8Value,
                2 => $values[0]->Int16Value,
                4 => $values[0]->Int32Value,
                default => $values[0]->Int64Value,
            };
        } else {
            return match ($byteSize) {
                1 => $values[0]->UInt8Value,
                2 => $values[0]->UInt16Value,
                4 => $values[0]->UInt32Value,
                default => $values[0]->UInt64Value,
            };
        }
    }
}
