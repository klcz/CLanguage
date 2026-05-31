<?php declare(strict_types=1);

namespace CLanguage;

class Value
{
    public float $Float64Value = 0.0;
    public int $Int64Value = 0;
    public int $Uint64Value = 0;
    public float $Float32Value = 0.0;
    public int $Int32Value = 0;
    public int $Uint32Value = 0;
    public int $Int16Value = 0;
    public int $Uint16Value = 0;
    public int $Int8Value = 0;
    public int $Uint8Value = 0;
    public int $PointerValue = 0;
    public string $charValue = "\0";

    public function __construct(int|float $i = 0)
    {
        if (gettype($i) === "double") {
            $this->Float64Value = $i;
        } else {
            $this->Int64Value = $i;
        }
    }

    public static function fromShort(int $v): Value
    {
        $val = new self(0);
        $val->Uint64Value = $v;
        return $val;
    }

    public static function fromLong(int $v): Value
    {
        $val = new self(0);
        $val->Uint64Value = $v;
        return $val;
    }

    public static function fromUShort(int $v): Value
    {
        $val = new self(0);
        $val->Uint64Value = $v;
        return $val;
    }

    public static function fromUInt(int $v): Value
    {
        $val = new self(0);
        $val->Uint64Value = $v;
        return $val;
    }

    public static function fromULong(int $v): Value
    {
        $val = new self(0);
        $val->Uint64Value = $v;
        return $val;
    }

    public static function fromBool(bool $v): self
    {
        $val = new self(0);
        $val->Int32Value = $v ? 1 : 0;
        return $val;
    }

    public static function fromString(string $v): self
    {
        return new self(intval($v));
    }

    public static function fromChar(string $v): self
    {
        $val = new self(0);
        $val->charValue = $v;
        return $val;
    }

    public static function fromFloat(float $v): self
    {
        $val = new self(0);
        $val->Float32Value = $v;
        return $val;
    }

    public static function fromDouble(float $v): self
    {
        $val = new self(0);
        $val->Float64Value = $v;
        return $val;
    }

    public static function fromUInt64(int $v): self
    {
        $val = new self(0);
        $val->Uint64Value = $v;
        return $val;
    }

    public static function fromInt(int $v): self
    {
        $val = new self(0);
        $val->Int64Value = $v;
        return $val;
    }

    public static function fromInt64(int $v): self
    {
        $val = new self(0);
        $val->Int64Value = $v;
        return $val;
    }

    public static function fromUInt32(int $v): self
    {
        $val = new self(0);
        $val->Uint32Value = $v;
        return $val;
    }

    public static function fromInt32(int $v): self
    {
        $val = new self(0);
        $val->Int32Value = $v;
        return $val;
    }

    public static function fromUInt16(int $v): self
    {
        $val = new self(0);
        $val->Uint16Value = $v;
        return $val;
    }

    public static function fromInt16(int $v): self
    {
        $val = new self(0);
        $val->Int16Value = $v;
        return $val;
    }

    public static function fromByte(int $v): self
    {
        $val = new self(0);
        $val->Uint8Value = $v;
        return $val;
    }

    public static function fromSByte(int $v): self
    {
        $val = new self(0);
        $val->Int8Value = $v;
        return $val;
    }

    public static function pointer(int $address): self
    {
        $val = new self(0);
        $val->PointerValue = $address;
        return $val;
    }

    public function __toString(): string
    {
        return (string)$this->Int32Value;
    }

    public function int32(): int
    {
        return $this->Int64Value;
    }

    public function int64(): int
    {
        return $this->Int64Value;
    }

    public function float32(): float
    {
        return $this->Float64Value;
    }

    public function float64(): float
    {
        return $this->Float64Value;
    }
}
