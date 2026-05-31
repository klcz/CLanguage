<?php declare(strict_types=1);

namespace CLanguage\Types;

use CLanguage\Compiler\EmitContext;
use CLanguage\MachineInfo;
use CLanguage\Syntax\TypeQualifiers;
use RuntimeException;

abstract class CType
{
    private static ?CVoidType $void = null;
    public int $TypeQualifiers = TypeQualifiers::None;

    public bool $IsVoidPointer {
        get => $this->isVoidPointer();
    }
    public bool $IsPointer {
        get => $this->isPointer();
    }

    public bool $IsVoid {
        get => $this->isVoid();
    }
    private ?CPointerType $pointer = null;

    public static function voidType(): CVoidType
    {
        if (self::$void === null) {
            self::$void = new CVoidType();
        }
        return self::$void;
    }

    public function getHashCode(): int
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return random_int(1, 9999);
    }

    abstract public function getByteSize(EmitContext $c): int;

    abstract public function getNumValues(): int;

    public function pointer(): CPointerType
    {
        if ($this->pointer === null) {
            $this->pointer = $this->createPointerType();
        }
        return $this->pointer;
    }

    protected function createPointerType(): CPointerType
    {
        return new CPointerType($this);
    }

    public function isVoidPointer(): bool
    {
        if ($this instanceof CPointerType) {
            return $this->InnerType->isVoid() || $this->InnerType->isVoidPointer();
        }
        return false;
    }

    public function isVoid(): bool
    {
        return false;
    }

    public function isPointer(): bool
    {
        return $this instanceof CPointerType;
    }

    public function isIntegral(): bool
    {
        return false;
    }

    public function scoreCastTo(CType $otherType): int
    {
        return $this === $otherType ? 1000 : 0;
    }

    public function getClrValue(array $values, MachineInfo $machineInfo): mixed
    {
        throw new RuntimeException("Cannot get CLR type from " . $this);
    }
}
