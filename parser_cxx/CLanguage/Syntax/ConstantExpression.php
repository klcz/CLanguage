<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;
use CLanguage\Types\CBasicType;
use CLanguage\Types\CBoolType;
use CLanguage\Types\CFloatType;
use CLanguage\Types\CIntType;
use CLanguage\Types\CPointerType;
use CLanguage\Types\CType;
use CLanguage\Types\Signedness;
use CLanguage\Value;
use Override;
use RuntimeException;
use Throwable;

class ConstantExpression extends Expression
{
    private static ?ConstantExpression $_zero = null;
    private static ?ConstantExpression $_one = null;
    private static ?ConstantExpression $_negativeOne = null;
    private static ?ConstantExpression $_true = null;
    private static ?ConstantExpression $_false = null;
    public mixed $Value;
    public CType $ConstantType;

    public function __construct(mixed $val, ?CType $type = null)
    {
        $this->Value = $val;

        if ($type !== null) {
            $this->ConstantType = $type;
            return;
        }

        if (is_string($val)) {
            $this->ConstantType = CPointerType::$PointerToConstChar;
        } elseif (is_bool($val)) {
            $this->ConstantType = CBasicType::bool();
        } elseif (is_int($val)) {
            $this->ConstantType = CBasicType::signedInt();
        } elseif (is_float($val)) {
            $this->ConstantType = CBasicType::double();
        } else {
            $this->ConstantType = CBasicType::signedInt();
        }
    }

    public static function zero(): ConstantExpression
    {
        return self::$_zero ??= new ConstantExpression(0);
    }

    public static function one(): ConstantExpression
    {
        return self::$_one ??= new ConstantExpression(1);
    }

    public static function negativeOne(): ConstantExpression
    {
        return self::$_negativeOne ??= new ConstantExpression(-1);
    }

    public static function trueVal(): ConstantExpression
    {
        return self::$_true ??= new ConstantExpression(true);
    }

    public static function falseVal(): ConstantExpression
    {
        return self::$_false ??= new ConstantExpression(false);
    }

    #[Override]
    public function __toString(): string
    {
        return (string)($this->Value ?? '');
    }

    #[Override]
    protected function doEmit(EmitContext $ec): void
    {
        $cval = $this->evalConstant($ec);
        $ec->emit(OpCode::LoadConstant, $cval);
    }

    #[Override]
    public function evalConstant(EmitContext $ec): Value
    {
        $evalType = $this->getEvaluatedCType($ec);

        if ($evalType instanceof CIntType) {
            $size = $evalType->getByteSize($ec);
            $v = (int)$this->Value;

            if ($evalType->Signedness === Signedness::Signed) {
                return match ($size) {
                    1 => Value::fromSByte(($v & 0xFF) > 127 ? ($v & 0xFF) - 256 : ($v & 0xFF)),
                    2 => Value::fromShort(($v & 0xFFFF) > 32767 ? ($v & 0xFFFF) - 65536 : ($v & 0xFFFF)),
                    4 => Value::fromInt(($v & 0xFFFFFFFF) > 2147483647 ? ($v & 0xFFFFFFFF) - 4294967296 : ($v & 0xFFFFFFFF)),
                    8 => Value::fromLong($v),
                    default => throw new RuntimeException("Signed integral constants with type '" . $this->ConstantType . "'"),
                };
            } else {
                return match ($size) {
                    1 => Value::fromByte($v & 0xFF),
                    2 => Value::fromUShort($v & 0xFFFF),
                    4 => Value::fromUInt($v & 0xFFFFFFFF),
                    8 => Value::fromULong($v),
                    default => throw new RuntimeException("Unsigned integral constants with type '" . $this->ConstantType . "'"),
                };
            }
        } elseif ($this->ConstantType instanceof CBoolType) {
            return Value::fromByte($this->Value ? 1 : 0);
        } elseif ($this->ConstantType instanceof CFloatType) {
            return $this->ConstantType->Bits === 64
                ? Value::fromDouble((float)$this->Value)
                : Value::fromFloat((float)$this->Value);
        } elseif (is_string($this->Value)) {
            return $ec->getConstantMemory($this->Value);
        } else {
            throw new RuntimeException("Non-basic constants with type '" . $this->ConstantType . "'");
        }
    }

    #[Override]
    public function getEvaluatedCType(EmitContext $ec): CType
    {
        if ($this->ConstantType instanceof CIntType) {
            return $this->promoteIntConstant($this->ConstantType, $ec);
        }
        return $this->ConstantType;
    }

    /**
     * C11 §6.4.4.1: Integer constants get the first type in the
     * promotion sequence that can represent the value.
     * Signed: int -> long -> long, long
     * Unsigned: unsigned int -> unsigned long -> unsigned long, long
     */
    private function promoteIntConstant(CIntType $intType, EmitContext $ec): CIntType
    {
        $mi = $ec->MachineInfo;
        $curSize = $intType->getByteSize($ec);

        if ($intType->Signedness === Signedness::Signed) {
            try {
                $val = (int)$this->Value;
            } catch (Throwable) {
                return $intType;
            }

            if (self::fitsInSignedBytes($val, $curSize))
                return $intType;

            if ($mi->longIntSize > $curSize && self::fitsInSignedBytes($val, $mi->longIntSize))
                return CBasicType::signedLongInt();

            if ($mi->longLongIntSize > $curSize && self::fitsInSignedBytes($val, $mi->longLongIntSize))
                return CBasicType::signedLongLongInt();
        } else {
            try {
                $val = (int)$this->Value;
                if ($val < 0) return $intType;
            } catch (Throwable) {
                return $intType;
            }

            if (self::fitsInUnsignedBytes($val, $curSize))
                return $intType;

            if ($mi->longIntSize > $curSize && self::fitsInUnsignedBytes($val, $mi->longIntSize))
                return CBasicType::unsignedLongInt();

            if ($mi->longLongIntSize > $curSize && self::fitsInUnsignedBytes($val, $mi->longLongIntSize))
                return CBasicType::unsignedLongLongInt();
        }

        return $intType;
    }

    private static function fitsInSignedBytes(int $val, int $byteSize): bool
    {
        return match ($byteSize) {
            1 => $val >= -128 && $val <= 127,
            2 => $val >= -32768 && $val <= 32767,
            4 => $val >= -2147483648 && $val <= 2147483647,
            8 => true,
            default => false,
        };
    }

    private static function fitsInUnsignedBytes(int $val, int $byteSize): bool
    {
        if ($val < 0) return false;
        return match ($byteSize) {
            1 => $val <= 255,
            2 => $val <= 65535,
            4 => $val <= 4294967295,
            8 => true,
            default => false,
        };
    }
}
