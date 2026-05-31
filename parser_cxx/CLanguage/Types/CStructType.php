<?php declare(strict_types=1);

namespace CLanguage\Types;

use CLanguage\Compiler\EmitContext;
use Exception;
use RuntimeException;

class CStructType extends CType
{
    public string $Name;
    /** @--var CStructMember[] */
    public array $Members = [];
    public ?CStructType $BaseType = null;
    public ?VTable $VTable = null;
    public ?int $VTableGlobalAddress = null;
    public int $NumValues {
        get => count($this->Members);
    }
    public bool $IsPolymorphic {
        get {
            if ($this->hasVTable()) {
                return true;
            }
            if ($this->BaseType !== null) {
                return $this->BaseType->IsPolymorphic();
            }
            return false;
        }
    }

    public function __construct(string $name)
    {
        $this->Name = $name;
    }

    public function equals(?object $obj): bool
    {
        if ($this === $obj) return true;
        if (!$obj instanceof CStructType) return false;
        $other = $obj;
        return ($this->Name !== '') && $this->Name === $other->Name;
    }

    public function getHashCode(): int
    {
        if ($this->Name === '') {
            return spl_object_id($this);
        }
        return crc32($this->Name);
    }

    public function isDerivedFrom(CStructType $other): bool
    {
        for ($t = $this->BaseType; $t !== null; $t = $t->BaseType) {
            if ($t === $other) return true;
        }
        return false;
    }

    public function findMember(string $name): ?CStructMember
    {
        for ($t = $this; $t !== null; $t = $t->BaseType) {
            foreach ($t->Members as $m) {
                if ($m->Name === $name) return $m;
            }
        }
        return null;
    }

    /**
     * @return CStructMethod[]
     */
    public function findMethods(string $name): array
    {
        $methods = [];
        for ($t = $this; $t !== null; $t = $t->BaseType) {
            foreach ($t->Members as $mem) {
                if ($mem instanceof CStructMethod && $mem->Name === $name) {
                    $methods[] = $mem;
                }
            }
            if (count($methods) > 0) break;
        }
        return $methods;
    }

    public function __toString(): string
    {
        return ($this->Name === '') ? 'struct' : $this->Name;
    }

    public function getOwnFieldsByteSize(EmitContext $c): int
    {
        $s = 0;
        foreach ($this->Members as $m) {
            if ($m instanceof CStructField) {
                $s += $m->MemberType->getByteSize($c);
            }
        }
        return $s;
    }

    public function getByteSize(EmitContext $c): int
    {
        if (!$this->isPolymorphic() && $this->BaseType === null) {
            return $this->getOwnFieldsByteSize($c);
        }
        $total = $this->isPolymorphic() ? $c->MachineInfo->PointerSize : 0;
        if ($this->BaseType !== null) {
            $total += $this->BaseType->getOwnFieldsByteSize($c);
        }
        $total += $this->getOwnFieldsByteSize($c);
        return $total;
    }

    public function isPolymorphic(): bool
    {
        return $this->hasVTable() || ($this->BaseType !== null && $this->BaseType->isPolymorphic());
    }

    public function hasVTable(): bool
    {
        return $this->VTable !== null && $this->VTable->count() > 0;
    }

    public function getFieldValueOffset(CStructMember $member, EmitContext $c): int
    {
        if (!$this->isPolymorphic() && $this->BaseType === null) {
            $offset = 0;
            foreach ($this->Members as $m) {
                if ($m instanceof CStructField) {
                    if ($m === $member) return $offset;
                    $offset += $m->MemberType->numValues();
                }
            }
            throw new Exception("Member '{$member->Name}' not found");
        }
        return $this->getFieldValueOffsetPolymorphic($member, $c);
    }

    public function numValues(): int
    {
        if (!$this->isPolymorphic() && $this->BaseType === null) {
            return $this->getOwnFieldsNumValues();
        }
        $total = $this->isPolymorphic() ? 1 : 0;
        if ($this->BaseType !== null) {
            $total += $this->BaseType->getOwnFieldsNumValues();
        }
        $total += $this->getOwnFieldsNumValues();
        return $total;
    }

    public function getOwnFieldsNumValues(): int
    {
        $s = 0;
        foreach ($this->Members as $m) {
            if ($m instanceof CStructField) {
                $s += $m->MemberType->numValues();
            }
        }
        return $s;
    }

    private function getFieldValueOffsetPolymorphic(CStructMember $member, EmitContext $c): int
    {
        $offset = $this->isPolymorphic() ? 1 : 0;

        if ($this->BaseType !== null) {
            $baseOffset = $this->BaseType->findFieldValueOffset($member, $c);
            if ($baseOffset >= 0) return $offset + $baseOffset;
            $offset += $this->BaseType->getOwnFieldsNumValues();
        }

        foreach ($this->Members as $m) {
            if ($m instanceof CStructField) {
                if ($m === $member) return $offset;
                $offset += $m->MemberType->numValues();
            }
        }
        throw new Exception("Member '{$member->Name}' not found");
    }

    private function findFieldValueOffset(CStructMember $member, EmitContext $c): int
    {
        $offset = 0;

        if ($this->BaseType !== null) {
            $baseOffset = $this->BaseType->findFieldValueOffset($member, $c);
            if ($baseOffset >= 0) return $offset + $baseOffset;
            $offset += $this->BaseType->getOwnFieldsNumValues();
        }

        foreach ($this->Members as $m) {
            if ($m instanceof CStructField) {
                if ($m === $member) return $offset;
                $offset += $m->MemberType->numValues();
            }
        }
        return -1;
    }

    public function buildVTable(): void
    {
        $entries = [];

        if ($this->BaseType !== null && $this->BaseType->VTable !== null) {
            foreach ($this->BaseType->VTable->Entries as $baseEntry) {
                $entries[] = new VTableEntry(
                    $baseEntry->SlotIndex,
                    $baseEntry->MethodName,
                    $baseEntry->Signature,
                    $baseEntry->DeclaringType
                );
            }
        }

        foreach ($this->Members as $m) {
            if ($m instanceof CStructMethod && $m->MemberType instanceof CFunctionType) {
                $method = $m;
                $sig = $method->MemberType;

                if ($method->IsOverride) {
                    $slot = -1;
                    foreach ($entries as $idx => $e) {
                        /** @noinspection PhpParamsInspection */
                        if ($e->MethodName === $method->Name && $e->Signature->parameterTypesEqual($sig)) {
                            $slot = $idx;
                            break;
                        }
                    }
                    if ($slot < 0) {
                        throw new RuntimeException("Override method '{$method->Name}' does not match any base virtual method in '{$this->Name}'");
                    }
                    /** @noinspection PhpParamsInspection */
                    $entries[$slot] = new VTableEntry($slot, $method->Name, $sig, $this);
                    $method->VTableSlotIndex = $slot;
                } elseif ($method->IsVirtual) {
                    $slot = -1;
                    foreach ($entries as $idx => $e) {
                        /** @noinspection PhpParamsInspection */
                        if ($e->MethodName === $method->Name && $e->Signature->parameterTypesEqual($sig)) {
                            $slot = $idx;
                            break;
                        }
                    }
                    if ($slot >= 0) {
                        /** @noinspection PhpParamsInspection */
                        $entries[$slot] = new VTableEntry($slot, $method->Name, $sig, $this);
                        $method->VTableSlotIndex = $slot;
                    } else {
                        $newSlot = count($entries);
                        /** @noinspection PhpParamsInspection */
                        $entries[] = new VTableEntry($newSlot, $method->Name, $sig, $this);
                        $method->VTableSlotIndex = $newSlot;
                    }
                }
            }
        }

        if (count($entries) > 0) {
            $vtable = new VTable(0);
            foreach ($entries as $e) {
                $vtable->Entries[] = $e;
            }
            $this->VTable = $vtable;
        } else {
            $this->VTable = null;
        }
    }

    public function getNumValues(): int
    {
        return 1;
    }
}
