<?php declare(strict_types=1);

namespace CLanguage\Interpreter;

use CLanguage\Types\CStructField;
use CLanguage\Types\CStructType;
use RuntimeException;

/**
 * Pre-computed layout of a C struct type, providing named field access
 * for use in InternalFunction callbacks.
 *
 * Create once per struct type (e.g. in your MachineInfo constructor),
 * cache the instance, then use field() to get
 * FieldAccessor values that can read/write struct members
 * at zero additional cost compared to hardcoded offsets.
 */
class StructLayout
{
    /**
     * The name of the struct type.
     */
    public readonly string $Name;

    /**
     * Total number of Value slots this struct occupies on the stack.
     */
    public readonly int $NumValues;

    /** @--var array<string, FieldAccessor> */
    private array $fields = [];

    /** @--var array<string, CType> */
    private array $fieldTypes = [];

    /**
     * Create a StructLayout from a CStructType.
     * Walks the type's members (including base type fields) exactly once
     * and pre-computes all field offsets.
     */
    public function __construct(CStructType $structType)
    {
        $this->Name = $structType->Name;
        $this->NumValues = $structType->NumValues;
        $this->buildFieldMap($structType);
    }

    private function buildFieldMap(CStructType $structType): void
    {
        if (!$structType->IsPolymorphic && $structType->BaseType === null) {
            $offset = 0;
        } else {
            $offset = $structType->IsPolymorphic ? 1 : 0;
            if ($structType->BaseType !== null) {
                $offset = $this->addBaseFields($structType->BaseType, $offset);
            }
        }
        foreach ($structType->Members as $m) {
            if ($m instanceof CStructField) {
                $this->fields[$m->Name] = new FieldAccessor($offset, $m->MemberType->NumValues);
                $this->fieldTypes[$m->Name] = $m->MemberType;
                $offset += $m->MemberType->NumValues;
            }
        }
    }

    private function addBaseFields(CStructType $type, int $offset): int
    {
        if ($type->BaseType !== null) {
            $offset = $this->addBaseFields($type->BaseType, $offset);
        }
        foreach ($type->Members as $m) {
            if ($m instanceof CStructField) {
                $this->fields[$m->Name] = new FieldAccessor($offset, $m->MemberType->NumValues);
                $this->fieldTypes[$m->Name] = $m->MemberType;
                $offset += $m->MemberType->NumValues;
            }
        }
        return $offset;
    }

    /**
     * Get a FieldAccessor for the named field.
     * Call once and cache the result for maximum performance.
     *
     * @throws RuntimeException when no field with the given name exists in this struct.
     */
    public function field(string $name): FieldAccessor
    {
        if (isset($this->fields[$name])) {
            return $this->fields[$name];
        }
        throw new RuntimeException("Field '$name' not found in struct '$this->Name'");
    }

    /**
     * Get a StructLayout for a nested struct-typed field.
     * This creates a new StructLayout each time — cache the result.
     *
     * @throws RuntimeException when no field with the given name exists.
     * @throws RuntimeException when the named field is not a struct type.
     */
    public function fieldLayout(string $name): StructLayout
    {
        $memberType = $this->fieldTypes[$name] ?? null;
        if ($memberType === null) {
            throw new RuntimeException("Field '$name' not found in struct '$this->Name'");
        }
        if ($memberType instanceof CStructType) {
            return new StructLayout($memberType);
        }
        throw new RuntimeException("Field '$name' in struct '$this->Name' is not a struct type (it is " . get_class($memberType) . ")");
    }
}
