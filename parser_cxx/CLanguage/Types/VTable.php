<?php declare(strict_types=1);

namespace CLanguage\Types;

/**
 * Represents the virtual method table (vtable) for a polymorphic type.
 * Each polymorphic CStructType has exactly one VTable containing the
 * ordered list of virtual method slots. At runtime, objects of that
 * type carry a vptr (1 Value slot at offset 0) that points to this table.
 *
 * Runtime vtable layout:
 *   [0]  = type_id (integer, for RTTI)
 *   [1]  = first virtual method pointer
 *   [2]  = second virtual method pointer
 *   ...
 */
class VTable
{
    /**
     * Unique type identifier assigned at compile time. Used as the RTTI
     * foundation for future dynamic_cast and typeid support.
     * Stored at slot 0 of the runtime vtable array.
     */
    public int $TypeId;

    /**
     * The ordered list of virtual method slots. Indices in this list correspond
     * to the VTableEntry.SlotIndex values.
     * At runtime, method pointers start at index 1 (after the type_id slot).
     *
     * @var VTableEntry[]
     */
    public array $Entries = [];
    public int $RuntimeSlotCount {
        get => $this->getRuntimeSlotCount();
    }

    public function __construct(int $typeId)
    {
        $this->TypeId = $typeId;
    }

    /**
     * Gets the number of method slots in this vtable.
     */
    public function Count(): int
    {
        return count($this->Entries);
    }

    /**
     * The total number of runtime slots including the type_id slot.
     */
    public function getRuntimeSlotCount(): int
    {
        return 1 + count($this->Entries);
    }

    /**
     * Gets the vtable entry at the specified slot index.
     */
    public function get(int $index): VTableEntry
    {
        return $this->Entries[$index];
    }
}
