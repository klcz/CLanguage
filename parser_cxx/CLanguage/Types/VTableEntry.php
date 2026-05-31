<?php declare(strict_types=1);

namespace CLanguage\Types;

/**
 * Represents a single slot in a virtual method table. Each entry maps
 * a slot index to the method that currently fills that slot for a given type.
 */
class VTableEntry
{
    /**
     * Zero-based position of this method in the vtable array.
     */
    public int $SlotIndex;

    /**
     * Name of the virtual method. Used for override matching and debugging.
     */
    public string $MethodName = "";

    /**
     * The function signature expected at this vtable slot.
     */
    public CFunctionType $Signature;

    /**
     * The type that originally introduced this virtual method slot.
     * When a derived class overrides a method, DeclaringType
     * is updated to the overriding type, while SlotIndex
     * remains the same as in the base class vtable.
     */
    public CStructType $DeclaringType;

    public function __construct(
        int           $slotIndex,
        string        $methodName,
        CFunctionType $signature,
        CStructType   $declaringType
    )
    {
        $this->SlotIndex = $slotIndex;
        $this->MethodName = $methodName;
        $this->Signature = $signature;
        $this->DeclaringType = $declaringType;
    }

    public function __toString(): string
    {
        return "vtable[{$this->SlotIndex}] {$this->MethodName} (from {$this->DeclaringType})";
    }
}
