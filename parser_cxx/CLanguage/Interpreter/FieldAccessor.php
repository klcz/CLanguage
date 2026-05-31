<?php declare(strict_types=1);

namespace CLanguage\Interpreter;

use CLanguage\Value;

/**
 * Pre-computed accessor for a single field within a C struct.
 * Created by StructLayout::field() and intended
 * to be cached for the lifetime of the struct type.
 * At runtime, get/set compile down to a single array access with an
 * integer add — identical cost to hardcoded $stack[$ptr + N].
 */
readonly class FieldAccessor
{
    /**
     * Pre-computed offset in Value slots from the struct base pointer.
     */
    public int $offset;

    /**
     * Number of Value slots this field occupies.
     * 1 for scalar types (int, float, pointer, etc.),
     * N for struct or array fields.
     */
    public int $numValues;

    public function __construct(int $offset, int $numValues)
    {
        $this->offset = $offset;
        $this->numValues = $numValues;
    }

    /**
     * Read this field's value from the stack at the given struct base pointer.
     * Only valid for scalar fields (numValues == 1).
     */
    public function get(array $stack, int $basePtr): Value
    {
        return $stack[$basePtr + $this->offset];
    }

    /**
     * Write a value to this field on the stack at the given struct base pointer.
     * Only valid for scalar fields (numValues == 1).
     */
    public function set(array &$stack, int $basePtr, Value $value): void
    {
        $stack[$basePtr + $this->offset] = $value;
    }

    /**
     * Returns the absolute stack address of this field.
     * Useful for struct or array fields where you need to pass
     * a pointer to the field's data.
     */
    public function getAddress(int $basePtr): int
    {
        return $basePtr + $this->offset;
    }
}
