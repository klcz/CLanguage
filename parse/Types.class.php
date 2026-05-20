<?php
namespace parse;

// ============================================================================
// Dependency stubs (minimal definitions for types used by the type system)
// ============================================================================

class Report
{
    public function error(int $code, string $message): void
    {
        // Stub — error reporting hook
    }
}

class MachineInfo
{
    public $charSize = 1;
    public $shortIntSize = 2;
    public $intSize = 4;
    public $longIntSize = 4;
    public $longLongIntSize = 8;
    public $floatSize = 4;
    public $doubleSize = 8;
    public $longDoubleSize = 8;
    public $pointerSize = 4;
}

class EmitContext
{
    public $report;
    public $machineInfo;

    public function __construct()
    {
        $this->report = new Report();
        $this->machineInfo = new MachineInfo();
    }
}

abstract class TypeQualifiers
{
    const NONE = 0;
    const CONST = 1;
    const VOLATILE = 2;
    const RESTRICT = 4;
}

// ============================================================================
// Value — 8-byte union analogue
// ============================================================================

class Value
{
    public $float64Value = 0.0;
    public $int64Value = 0;
    public $uint64Value = 0;
    public $float32Value = 0.0;
    public $int32Value = 0;
    public $uint32Value = 0;
    public $int16Value = 0;
    public $uint16Value = 0;
    public $int8Value = 0;
    public $uint8Value = 0;
    public $pointerValue = 0;
    public $charValue = "\0";

    public function __toString(): string
    {
        return (string)$this->int32Value;
    }

    public static function pointer(int $address): self
    {
        $v = new self();
        $v->pointerValue = $address;
        return $v;
    }
}

// ============================================================================
// Signedness
// ============================================================================

abstract class Signedness
{
    const UNSIGNED = 0;
    const SIGNED = 1;
}

// ============================================================================
// VTableEntry
// ============================================================================

class VTableEntry
{
    public $slotIndex = 0;
    public $methodName = '';
    public $signature;
    public $declaringType;

    public function __construct(int $slotIndex, string $methodName, CFunctionType $signature, CStructType $declaringType)
    {
        $this->slotIndex = $slotIndex;
        $this->methodName = $methodName;
        $this->signature = $signature;
        $this->declaringType = $declaringType;
    }

    public function __toString(): string
    {
        return "vtable[{$this->slotIndex}] {$this->methodName} (from {$this->declaringType})";
    }
}

// ============================================================================
// VTable
// ============================================================================

class VTable
{
    public $typeId = 0;
    public $entries = [];

    public function getCount(): int
    {
        return count($this->entries);
    }

    public function getRuntimeSlotCount(): int
    {
        return 1 + count($this->entries);
    }

    public function get(int $index): VTableEntry
    {
        return $this->entries[$index];
    }
}

// ============================================================================
// TypeHierarchyEntry
// ============================================================================

class TypeHierarchyEntry
{
    public $typeId = 0;
    public $baseTypeId = 0;
    public $typeName = '';

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

// ============================================================================
// Parameter (nested type of CFunctionType, defined here for ordering)
// ============================================================================

class Parameter
{
    public $name = '';
    public $parameterType;
    public $offset = 0;
    public $defaultValue = null;

    public function __construct(string $name, CType $parameterType, ?Value $defaultValue)
    {
        $this->name = $name;
        $this->parameterType = $parameterType;
        $this->defaultValue = $defaultValue;
    }

    public function __toString(): string
    {
        return (string)$this->parameterType . " " . $this->name;
    }
}

// ============================================================================
// CType (abstract base)
// ============================================================================

abstract class CType
{
    public $typeQualifiers = 0;

    abstract public function getByteSize(EmitContext $c): int;
    abstract public function getNumValues(): int;

    public static $void;
    public static $voidInitialized = false;

    /** @var bool */
    protected $isIntegral = false;

    /** @var CPointerType|null */
    protected $pointer = null;

    public function __construct()
    {
        if (!self::$voidInitialized) {
            self::$void = new CVoidType();
            self::$voidInitialized = true;
        }
        $this->pointer = $this->createPointerType();
    }

    public function getIsIntegral(): bool
    {
        return $this->isIntegral;
    }

    public function getIsVoid(): bool
    {
        return false;
    }

    public function getPointer(): CPointerType
    {
        return $this->pointer;
    }

    public function getIsVoidPointer(): bool
    {
        if ($this instanceof CPointerType) {
            return $this->innerType->getIsVoid() || $this->innerType->getIsVoidPointer();
        }
        return false;
    }

    public function getIsPointer(): bool
    {
        return $this instanceof CPointerType;
    }

    protected function createPointerType(): CPointerType
    {
        return new CPointerType($this);
    }

    public function scoreCastTo(CType $otherType): int
    {
        return $this == $otherType ? 1000 : 0;
    }

    /**
     * @param Value[] $values
     * @return mixed
     */
    public function getClrValue(array $values, MachineInfo $machineInfo)
    {
        throw new \RuntimeException("Cannot get CLR type from " . get_class($this));
    }
}

// ============================================================================
// CBasicType (abstract)
// ============================================================================

abstract class CBasicType extends CType
{
    public $name = '';
    public $signedness;
    public $size = '';

    public function __construct(string $name, int $signedness, string $size)
    {
        $this->name = $name;
        $this->signedness = $signedness;
        $this->size = $size;
        parent::__construct();
    }

    public function equals($obj): bool
    {
        return ($obj instanceof CBasicType) && $this->name === $obj->name && $this->signedness === $obj->signedness && $this->size === $obj->size;
    }

    public function hashCode(): int
    {
        $hash = 17;
        $hash = $hash * 37 + crc32($this->name);
        $hash = $hash * 37 + crc32($this->size);
        $hash = $hash * 37 + $this->signedness;
        return $hash;
    }

    public static $constChar;
    public static $unsignedChar;
    public static $signedChar;
    public static $unsignedShortInt;
    public static $signedShortInt;
    public static $unsignedInt;
    public static $signedInt;
    public static $unsignedLongInt;
    public static $signedLongInt;
    public static $unsignedLongLongInt;
    public static $signedLongLongInt;
    public static $float;
    public static $double;
    public static $bool;
    public static $basicInitialized = false;

    public static function initStatics(): void
    {
        if (self::$basicInitialized) return;
        self::$constChar = new CIntType("char", Signedness::SIGNED, "");
        self::$constChar->typeQualifiers = TypeQualifiers::CONST;
        self::$unsignedChar = new CIntType("char", Signedness::UNSIGNED, "");
        self::$signedChar = new CIntType("char", Signedness::SIGNED, "");
        self::$unsignedShortInt = new CIntType("int", Signedness::UNSIGNED, "short");
        self::$signedShortInt = new CIntType("int", Signedness::SIGNED, "short");
        self::$unsignedInt = new CIntType("int", Signedness::UNSIGNED, "");
        self::$signedInt = new CIntType("int", Signedness::SIGNED, "");
        self::$unsignedLongInt = new CIntType("int", Signedness::UNSIGNED, "long");
        self::$signedLongInt = new CIntType("int", Signedness::SIGNED, "long");
        self::$unsignedLongLongInt = new CIntType("int", Signedness::UNSIGNED, "long long");
        self::$signedLongLongInt = new CIntType("int", Signedness::SIGNED, "long long");
        self::$float = new CFloatType("float", 32);
        self::$double = new CFloatType("double", 64);
        self::$bool = new CBoolType();
        self::$basicInitialized = true;
    }

    public function integerPromote(EmitContext $context): CBasicType
    {
        if ($this->getIsIntegral()) {
            $size = $this->getByteSize($context);
            $intSize = $context->machineInfo->intSize;
            if ($size < $intSize) {
                return self::$signedInt;
            } elseif ($size === $intSize) {
                if ($this->signedness === Signedness::UNSIGNED) {
                    return self::$unsignedInt;
                } else {
                    return self::$signedInt;
                }
            } else {
                return $this;
            }
        } else {
            return $this;
        }
    }

    public function arithmeticConvert(CType $otherType, EmitContext $context): CBasicType
    {
        $otherBasicType = null;
        if ($otherType instanceof CBasicType) {
            $otherBasicType = $otherType;
        }
        if ($otherBasicType === null) {
            $context->report->error(19, "Cannot perform arithmetic with " . get_class($otherType));
            return self::$signedInt;
        }
        if ($this->name === "double" || $otherBasicType->name === "double") {
            return self::$double;
        } elseif ($this->name === "single" || $otherBasicType->name === "single") {
            return self::$float;
        } else {
            $p1 = $this->integerPromote($context);
            $size1 = $p1->getByteSize($context);
            $p2 = $otherBasicType->integerPromote($context);
            $size2 = $p2->getByteSize($context);
            if ($p1->signedness === $p2->signedness) {
                return $size1 >= $size2 ? $p1 : $p2;
            } else {
                if ($p1->signedness === Signedness::UNSIGNED) {
                    if ($size1 > $size2) {
                        return $p1;
                    } else {
                        if ($size2 > $size1) {
                            return $p2;
                        } else {
                            return new CIntType($p2->name, Signedness::UNSIGNED, $p2->size);
                        }
                    }
                } else {
                    if ($size2 > $size1) {
                        return $p2;
                    } else {
                        if ($size1 > $size2) {
                            return $p1;
                        } else {
                            return new CIntType($p1->name, Signedness::UNSIGNED, $p1->size);
                        }
                    }
                }
            }
        }
    }

    public function __toString(): string
    {
        if ($this->getIsIntegral()) {
            $sign = $this->signedness === Signedness::SIGNED ? "signed" : "unsigned";
            if ($this->size === "") {
                return $sign . " " . $this->name;
            } else {
                return $sign . " " . $this->size . " " . $this->name;
            }
        } else {
            if ($this->size === "") {
                return $this->name;
            } else {
                return $this->size . " " . $this->name;
            }
        }
    }
}

// ============================================================================
// CBoolType
// ============================================================================

class CBoolType extends CBasicType
{
    protected $isIntegral = true;

    public function __construct()
    {
        parent::__construct("bool", Signedness::UNSIGNED, "");
    }

    public function getNumValues(): int
    {
        return 1;
    }

    public function getByteSize(EmitContext $c): int
    {
        return $c->machineInfo->charSize;
    }

    public function __toString(): string
    {
        return "bool";
    }
}

// ============================================================================
// CIntType
// ============================================================================

class CIntType extends CBasicType
{
    protected $isIntegral = true;

    public function __construct(string $name, int $signedness, string $size)
    {
        parent::__construct($name, $signedness, $size);
    }

    public function getNumValues(): int
    {
        return 1;
    }

    public function getByteSizeFromMachine(MachineInfo $c): int
    {
        if ($this->name === "char") {
            return $c->charSize;
        } elseif ($this->name === "int") {
            if ($this->size === "short") {
                return $c->shortIntSize;
            } elseif ($this->size === "long") {
                return $c->longIntSize;
            } elseif ($this->size === "long long") {
                return $c->longLongIntSize;
            } else {
                return $c->intSize;
            }
        } else {
            throw new \RuntimeException((string)$this);
        }
    }

    public function getByteSize(EmitContext $c): int
    {
        return $this->getByteSizeFromMachine($c->machineInfo);
    }

    public function scoreCastTo(CType $otherType): int
    {
        if ($this == $otherType) return 1000;
        if ($otherType instanceof CIntType) {
            if ($this->name === $otherType->name && $this->size === $otherType->size) return 950;
            if ($this->size === $otherType->size) return 900;
            return 800;
        } elseif ($otherType instanceof CFloatType) {
            if ($otherType->bits === 64) return 400;
            return 300;
        } elseif ($otherType instanceof CBoolType) {
            return 200;
        } else {
            return 0;
        }
    }

    /**
     * @param Value[] $values
     * @return mixed
     */
    public function getClrValue(array $values, MachineInfo $machineInfo)
    {
        $byteSize = $this->getByteSizeFromMachine($machineInfo);
        if ($this->signedness === Signedness::SIGNED) {
            switch ($byteSize) {
                case 1: return $values[0]->int8Value;
                case 2: return $values[0]->int16Value;
                case 4: return $values[0]->int32Value;
                default: return $values[0]->int64Value;
            }
        } else {
            switch ($byteSize) {
                case 1: return $values[0]->uint8Value;
                case 2: return $values[0]->uint16Value;
                case 4: return $values[0]->uint32Value;
                default: return $values[0]->uint64Value;
            }
        }
    }
}

// ============================================================================
// CFloatType
// ============================================================================

class CFloatType extends CBasicType
{
    public $bits = 0;

    public function __construct(string $name, int $bits)
    {
        parent::__construct($name, Signedness::SIGNED, "");
        $this->bits = $bits;
    }

    public function getNumValues(): int
    {
        return 1;
    }

    public function getByteSize(EmitContext $c): int
    {
        return intdiv($this->bits, 8);
    }

    public function scoreCastTo(CType $otherType): int
    {
        if ($this == $otherType) return 1000;
        if ($otherType instanceof CFloatType) {
            return 900;
        } else {
            return 0;
        }
    }
}

// ============================================================================
// CPointerType
// ============================================================================

class CPointerType extends CType
{
    public $innerType;

    public function __construct(CType $innerType)
    {
        $this->innerType = $innerType;
        parent::__construct();
    }

    protected function createPointerType(): CPointerType
    {
        return $this;
    }

    public static $pointerToConstChar;
    public static $pointerToVoid;
    public static $pointerInitialized = false;

    public static function initStatics(): void
    {
        if (self::$pointerInitialized) return;
        CBasicType::initStatics();
        CType::$voidInitialized = true;
        if (CType::$void === null) {
            CType::$void = new CVoidType();
        }
        self::$pointerToConstChar = new CPointerType(CBasicType::$constChar);
        self::$pointerToVoid = new CPointerType(CType::$void);
        self::$pointerInitialized = true;
    }

    public function getNumValues(): int
    {
        return 1;
    }

    public function scoreCastTo(CType $otherType): int
    {
        if ($this == $otherType) return 1000;
        if ($otherType instanceof CPointerType
            && $this->innerType instanceof CStructType
            && $otherType->innerType instanceof CStructType
            && $this->innerType->isDerivedFrom($otherType->innerType)) {
            return 900;
        }
        return 0;
    }

    public function getByteSize(EmitContext $c): int
    {
        return $c->machineInfo->pointerSize;
    }

    public function __toString(): string
    {
        return (string)$this->innerType . "*";
    }

    public function equals($obj): bool
    {
        return ($obj instanceof CPointerType) && $this->innerType == $obj->innerType;
    }

    public function hashCode(): int
    {
        $hash = 17;
        $hash = $hash * 37 + (is_object($this->innerType) ? spl_object_id($this->innerType) : crc32((string)$this->innerType));
        $hash = $hash * 37 + 1;
        return $hash;
    }
}

// ============================================================================
// CArrayType
// ============================================================================

class CArrayType extends CType
{
    public $elementType;
    public $length;

    public function __construct(CType $elementType, ?int $length)
    {
        $this->elementType = $elementType;
        $this->length = $length;
        parent::__construct();
    }

    public function getNumValues(): int
    {
        if ($this->length === null) return 1;
        $innerSize = $this->elementType->getNumValues();
        return $this->length * $innerSize;
    }

    protected function createPointerType(): CPointerType
    {
        return $this->elementType->getPointer();
    }

    public function getByteSize(EmitContext $c): int
    {
        if ($this->length === null) return $c->machineInfo->pointerSize;
        $innerSize = $this->elementType->getByteSize($c);
        return $this->length * $innerSize;
    }

    public function scoreCastTo(CType $otherType): int
    {
        if ($this == $otherType) return 1000;
        if ($otherType instanceof CPointerType) {
            if ($this->elementType == $otherType->innerType) {
                return 900;
            } else {
                return intdiv($this->elementType->scoreCastTo($otherType->innerType), 2);
            }
        }
        return 0;
    }

    public function equals($obj): bool
    {
        return ($obj instanceof CArrayType) && $this->length === $obj->length && $this->elementType == $obj->elementType;
    }

    public function hashCode(): int
    {
        $hash = 17;
        $hash = $hash * 37 + (is_object($this->elementType) ? spl_object_id($this->elementType) : crc32((string)$this->elementType));
        $hash = $hash * 37 + ($this->length !== null ? $this->length : 0);
        return $hash;
    }

    public function __toString(): string
    {
        return "{$this->elementType}[{$this->length}]";
    }
}

// ============================================================================
// CStructMember hierarchy
// ============================================================================

abstract class CStructMember
{
    public $name = '';
    public $memberType;

    public function __construct()
    {
        CBasicType::initStatics();
        $this->memberType = CBasicType::$signedInt;
    }

    public function __toString(): string
    {
        return "{$this->memberType} {$this->name}";
    }
}

class CStructField extends CStructMember
{
}

class CStructMethod extends CStructMember
{
    public $isVirtual = false;
    public $isOverride = false;
    public $isPureVirtual = false;
    public $vtableSlotIndex = null;
}

// ============================================================================
// CStructType
// ============================================================================

class CStructType extends CType
{
    public $name = '';
    public $members = [];
    public $baseType = null;
    public $vtable = null;
    public $vtableGlobalAddress = null;

    public function __construct(string $name)
    {
        $this->name = $name;
        parent::__construct();
    }

    public function getHasVTable(): bool
    {
        return $this->vtable !== null && count($this->vtable->entries) > 0;
    }

    public function getIsPolymorphic(): bool
    {
        return $this->getHasVTable() || ($this->baseType !== null && $this->baseType->getIsPolymorphic());
    }

    public function equals($obj): bool
    {
        if ($this === $obj) return true;
        return ($obj instanceof CStructType) && $this->name !== '' && $this->name === $obj->name;
    }

    public function hashCode(): int
    {
        if ($this->name === '') {
            return spl_object_id($this);
        }
        return crc32($this->name);
    }

    public function isDerivedFrom(CStructType $other): bool
    {
        $t = $this->baseType;
        while ($t !== null) {
            if ($t === $other) return true;
            $t = $t->baseType;
        }
        return false;
    }

    public function findMember(string $name): ?CStructMember
    {
        $t = $this;
        while ($t !== null) {
            foreach ($t->members as $m) {
                if ($m->name === $name) return $m;
            }
            $t = $t->baseType;
        }
        return null;
    }

    /**
     * @return CStructMethod[]
     */
    public function findMethods(string $name): array
    {
        $methods = [];
        $t = $this;
        while ($t !== null) {
            foreach ($t->members as $mem) {
                if ($mem instanceof CStructMethod && $mem->name === $name) {
                    $methods[] = $mem;
                }
            }
            if (count($methods) > 0) break;
            $t = $t->baseType;
        }
        return $methods;
    }

    public function __toString(): string
    {
        return $this->name === '' ? "struct" : $this->name;
    }

    public function getOwnFieldsNumValues(): int
    {
        $s = 0;
        foreach ($this->members as $m) {
            if ($m instanceof CStructField) {
                $s += $m->memberType->getNumValues();
            }
        }
        return $s;
    }

    public function getOwnFieldsByteSize(EmitContext $c): int
    {
        $s = 0;
        foreach ($this->members as $m) {
            if ($m instanceof CStructField) {
                $s += $m->memberType->getByteSize($c);
            }
        }
        return $s;
    }

    public function getNumValues(): int
    {
        if (!$this->getIsPolymorphic() && $this->baseType === null) {
            return $this->getOwnFieldsNumValues();
        }
        $total = $this->getIsPolymorphic() ? 1 : 0;
        if ($this->baseType !== null) {
            $total += $this->baseType->getOwnFieldsNumValues();
        }
        $total += $this->getOwnFieldsNumValues();
        return $total;
    }

    public function getByteSize(EmitContext $c): int
    {
        if (!$this->getIsPolymorphic() && $this->baseType === null) {
            return $this->getOwnFieldsByteSize($c);
        }
        $total = $this->getIsPolymorphic() ? $c->machineInfo->pointerSize : 0;
        if ($this->baseType !== null) {
            $total += $this->baseType->getOwnFieldsByteSize($c);
        }
        $total += $this->getOwnFieldsByteSize($c);
        return $total;
    }

    public function getFieldValueOffset(CStructMember $member, EmitContext $c): int
    {
        if (!$this->getIsPolymorphic() && $this->baseType === null) {
            $offset = 0;
            foreach ($this->members as $m) {
                if ($m instanceof CStructField) {
                    if ($m === $member) return $offset;
                    $offset += $m->memberType->getNumValues();
                }
            }
            throw new \RuntimeException("Member '{$member->name}' not found");
        }
        return $this->getFieldValueOffsetPolymorphic($member, $c);
    }

    protected function getFieldValueOffsetPolymorphic(CStructMember $member, EmitContext $c): int
    {
        $offset = $this->getIsPolymorphic() ? 1 : 0;
        if ($this->baseType !== null) {
            $baseOffset = $this->baseType->findFieldValueOffset($member, $c);
            if ($baseOffset >= 0) return $offset + $baseOffset;
            $offset += $this->baseType->getOwnFieldsNumValues();
        }
        foreach ($this->members as $m) {
            if ($m instanceof CStructField) {
                if ($m === $member) return $offset;
                $offset += $m->memberType->getNumValues();
            }
        }
        throw new \RuntimeException("Member '{$member->name}' not found");
    }

    protected function findFieldValueOffset(CStructMember $member, EmitContext $c): int
    {
        $offset = 0;
        if ($this->baseType !== null) {
            $baseOffset = $this->baseType->findFieldValueOffset($member, $c);
            if ($baseOffset >= 0) return $offset + $baseOffset;
            $offset += $this->baseType->getOwnFieldsNumValues();
        }
        foreach ($this->members as $m) {
            if ($m instanceof CStructField) {
                if ($m === $member) return $offset;
                $offset += $m->memberType->getNumValues();
            }
        }
        return -1;
    }

    public function buildVTable(): void
    {
        $entries = [];
        if ($this->baseType !== null && $this->baseType->vtable !== null) {
            foreach ($this->baseType->vtable->entries as $baseEntry) {
                $entries[] = new VTableEntry(
                    $baseEntry->slotIndex,
                    $baseEntry->methodName,
                    $baseEntry->signature,
                    $baseEntry->declaringType
                );
            }
        }
        foreach ($this->members as $m) {
            if ($m instanceof CStructMethod && $m->memberType instanceof CFunctionType) {
                $sig = $m->memberType;
                if ($m->isOverride) {
                    $slot = -1;
                    foreach ($entries as $idx => $e) {
                        if ($e->methodName === $m->name && $e->signature->parameterTypesEqual($sig)) {
                            $slot = $idx;
                            break;
                        }
                    }
                    if ($slot < 0) {
                        throw new \RuntimeException("Override method '{$m->name}' does not match any base virtual method in '{$this->name}'");
                    }
                    $entries[$slot] = new VTableEntry($slot, $m->name, $sig, $this);
                    $m->vtableSlotIndex = $slot;
                } elseif ($m->isVirtual) {
                    $slot = -1;
                    foreach ($entries as $idx => $e) {
                        if ($e->methodName === $m->name && $e->signature->parameterTypesEqual($sig)) {
                            $slot = $idx;
                            break;
                        }
                    }
                    if ($slot >= 0) {
                        $entries[$slot] = new VTableEntry($slot, $m->name, $sig, $this);
                        $m->vtableSlotIndex = $slot;
                    } else {
                        $newSlot = count($entries);
                        $entries[] = new VTableEntry($newSlot, $m->name, $sig, $this);
                        $m->vtableSlotIndex = $newSlot;
                    }
                }
            }
        }
        if (count($entries) > 0) {
            $vtable = new VTable();
            $vtable->entries = $entries;
            $this->vtable = $vtable;
        } else {
            $this->vtable = null;
        }
    }
}

// ============================================================================
// CEnumMember
// ============================================================================

class CEnumMember
{
    public $name = '';
    public $value = 0;

    public function __construct(string $name, int $value)
    {
        if ($name === null) {
            throw new \InvalidArgumentException("name cannot be null");
        }
        $this->name = $name;
        $this->value = $value;
    }

    public function __toString(): string
    {
        return "{$this->name} = {$this->value}";
    }
}

// ============================================================================
// CEnumType
// ============================================================================

class CEnumType extends CType
{
    public $name = '';
    public $members = [];
    protected $isIntegral = true;

    public function __construct(string $name)
    {
        $this->name = $name;
        parent::__construct();
    }

    public function getNextValue(): int
    {
        if (count($this->members) > 0) {
            return $this->members[count($this->members) - 1]->value + 1;
        }
        return 0;
    }

    public function getNumValues(): int
    {
        return 1;
    }

    public function getIsIntegral(): bool
    {
        return true;
    }

    public function getByteSize(EmitContext $c): int
    {
        return CBasicType::$signedInt->getByteSize($c);
    }
}

// ============================================================================
// CReferenceType
// ============================================================================

class CReferenceType extends CType
{
    public $innerType;

    public function __construct(CType $innerType)
    {
        $this->innerType = $innerType;
        parent::__construct();
    }

    public function getNumValues(): int
    {
        return 1;
    }

    public function getByteSize(EmitContext $ec): int
    {
        return $ec->machineInfo->pointerSize;
    }

    public function scoreCastTo(CType $otherType): int
    {
        if ($otherType instanceof CReferenceType && $this->innerType == $otherType->innerType) {
            return 1000;
        }
        if ($this->innerType == $otherType) {
            return 900;
        }
        if ($otherType instanceof CPointerType && $this->innerType == $otherType->innerType) {
            return 800;
        }
        return $this->innerType->scoreCastTo($otherType);
    }

    public function equals($obj): bool
    {
        return ($obj instanceof CReferenceType) && $this->innerType == $obj->innerType;
    }

    public function hashCode(): int
    {
        return (is_object($this->innerType) ? spl_object_id($this->innerType) : 0) ^ 0x5A5A;
    }

    public function __toString(): string
    {
        return "{$this->innerType}&";
    }
}

// ============================================================================
// CVoidType
// ============================================================================

class CVoidType extends CType
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getIsVoid(): bool
    {
        return true;
    }

    public function getNumValues(): int
    {
        return 0;
    }

    public function getByteSize(EmitContext $c): int
    {
        $c->report->error(2070, "'void': illegal sizeof operand");
        return 0;
    }

    public function __toString(): string
    {
        return "void";
    }

    public function equals($obj): bool
    {
        return $obj instanceof CVoidType;
    }

    public function hashCode(): int
    {
        return 17;
    }
}

// ============================================================================
// CFunctionType
// ============================================================================

class CFunctionType extends CType
{
    public static $voidProcedure;

    public $returnType;
    protected $parameters = [];
    public $isInstance = false;
    public $declaringType = null;

    public static function initStatics(): void
    {
        if (self::$voidProcedure === null) {
            CType::$voidInitialized = true;
            if (CType::$void === null) {
                CType::$void = new CVoidType();
            }
            self::$voidProcedure = new CFunctionType(CType::$void, false, null);
        }
    }

    public function __construct(CType $returnType, bool $isInstance, ?CType $declaringType)
    {
        $this->returnType = $returnType;
        $this->isInstance = $isInstance;
        $this->declaringType = $declaringType;
        if ($isInstance && $declaringType === null) {
            throw new \InvalidArgumentException("declaringType cannot be null");
        }
        parent::__construct();
    }

    public function getNumValues(): int
    {
        return 1;
    }

    /**
     * @return Parameter[]
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function equals($obj): bool
    {
        return ($obj instanceof CFunctionType) && $this->returnType == $obj->returnType && $this->parameterTypesEqual($obj);
    }

    public function hashCode(): int
    {
        $pcode = 17;
        foreach ($this->parameters as $p) {
            $pcode += is_object($p->parameterType) ? spl_object_id($p->parameterType) : 0;
        }
        return spl_object_id($this) * 13 + (is_object($this->returnType) ? spl_object_id($this->returnType) : 0) * 11 + $pcode * 7;
    }

    public function addParameter(string $name, CType $type, ?Value $defaultValue): void
    {
        $this->parameters[] = new Parameter($name, $type, $defaultValue);
        $this->calculateParameterOffsets();
    }

    protected function calculateParameterOffsets(): void
    {
        $offset = $this->isInstance ? -1 : 0;
        for ($i = count($this->parameters) - 1; $i >= 0; --$i) {
            $p = $this->parameters[$i];
            $n = $p->parameterType->getNumValues();
            $offset -= $n;
            $p->offset = $offset;
        }
    }

    public function getByteSize(EmitContext $c): int
    {
        return $c->machineInfo->pointerSize;
    }

    public function __toString(): string
    {
        $s = "(Function " . $this->returnType . " (";
        $head = "";
        foreach ($this->parameters as $p) {
            $s .= $head;
            $s .= (string)$p;
            $head = " ";
        }
        $s .= "))";
        return $s;
    }

    /**
     * @param CType[]|null $argTypes
     */
    public function scoreParameterTypeMatches(?array $argTypes): int
    {
        if ($argTypes === null) return 1;
        $pc = count($this->parameters);
        $requiredParamCount = 0;
        for ($i = 0; $i < $pc; $i++) {
            if ($this->parameters[$i]->defaultValue !== null) break;
            $requiredParamCount++;
        }
        if (count($argTypes) < $requiredParamCount || count($argTypes) > $pc) return 0;
        $score = count($argTypes) === $pc ? 3 : 2;
        for ($i = 0; $i < count($argTypes); $i++) {
            $ft = $argTypes[$i];
            $tt = $this->parameters[$i]->parameterType;
            if ($tt instanceof CReferenceType) {
                $score += $ft->scoreCastTo($tt->innerType);
            } else {
                $score += $ft->scoreCastTo($tt);
            }
        }
        return $score;
    }

    public function parameterTypesEqual(CFunctionType $otherType): bool
    {
        if (count($this->parameters) !== count($otherType->parameters)) return false;
        for ($i = 0; $i < count($this->parameters); $i++) {
            $ft = $otherType->parameters[$i]->parameterType;
            $tt = $this->parameters[$i]->parameterType;
            if ($ft != $tt) return false;
        }
        return true;
    }
}
