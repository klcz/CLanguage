<?php declare(strict_types=1);

namespace CLanguage\Types;

/**
 * Represents an entry in the compile-time type hierarchy table.
 * Each polymorphic type gets one entry. This table enables future
 * dynamic_cast and typeid implementations by encoding
 * the inheritance tree as a searchable array.
 */
readonly class TypeHierarchyEntry
{
    /**
     * Unique compile-time identifier for this type.
     * Matches the VTable.TypeId stored at runtime vtable slot 0.
     */
    public int $typeId;

    /**
     * Type ID of the base class, or -1 if this type has no polymorphic base.
     */
    public int $baseTypeId;

    /**
     * The name of this type (e.g., "Base", "Derived").
     */
    public string $typeName;

    public function __construct(int $typeId, int $baseTypeId, string $typeName)
    {
        $this->typeId = $typeId;
        $this->baseTypeId = $baseTypeId;
        $this->typeName = $typeName;
    }

    public function __toString(): string
    {
        return "TypeId={$this->typeId} Base={$this->baseTypeId} Name={$this->typeName}";
    }
}
