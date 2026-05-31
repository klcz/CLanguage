<?php declare(strict_types=1);

namespace CLanguage\Types;

use CLanguage\Compiler\EmitContext;
use CLanguage\Syntax\TypeQualifiers;

enum Signedness: int
{
    case Unsigned = 0;
    case Signed = 1;
}

abstract class CBasicType extends CType
{
    private static ?CIntType $_constChar = null;
    private static ?CIntType $_unsignedChar = null;
    private static ?CIntType $_signedChar = null;
    private static ?CIntType $_unsignedShortInt = null;
    private static ?CIntType $_signedShortInt = null;
    private static ?CIntType $_unsignedInt = null;

    // Static type instances (lazy-initialized via getters)
    private static ?CIntType $_signedInt = null;
    private static ?CIntType $_unsignedLongInt = null;
    private static ?CIntType $_signedLongInt = null;
    private static ?CIntType $_unsignedLongLongInt = null;
    private static ?CIntType $_signedLongLongInt = null;
    private static ?CFloatType $_float = null;
    private static ?CFloatType $_double = null;
    private static ?CBoolType $_bool = null;
    public string $Name;
    public Signedness $Signedness;
    public string $Size;
    public bool $IsIntegral {
        get => $this->isIntegral();
    }

    public function __construct(string $name, Signedness $signedness, string $size)
    {
        $this->Name = $name;
        $this->Signedness = $signedness;
        $this->Size = $size;
    }

    public static function constChar(): CIntType
    {
        if (self::$_constChar === null) {
            $t = new CIntType('char', Signedness::Signed, '');
            $t->TypeQualifiers = TypeQualifiers::Const;
            self::$_constChar = $t;
        }
        return self::$_constChar;
    }

    public static function unsignedChar(): CIntType
    {
        return self::$_unsignedChar ??= new CIntType('char', Signedness::Unsigned, '');
    }

    public static function signedChar(): CIntType
    {
        return self::$_signedChar ??= new CIntType('char', Signedness::Signed, '');
    }

    public static function unsignedShortInt(): CIntType
    {
        return self::$_unsignedShortInt ??= new CIntType('int', Signedness::Unsigned, 'short');
    }

    public static function signedShortInt(): CIntType
    {
        return self::$_signedShortInt ??= new CIntType('int', Signedness::Signed, 'short');
    }

    public static function unsignedLongInt(): CIntType
    {
        return self::$_unsignedLongInt ??= new CIntType('int', Signedness::Unsigned, 'long');
    }

    public static function signedLongInt(): CIntType
    {
        return self::$_signedLongInt ??= new CIntType('int', Signedness::Signed, 'long');
    }

    public static function unsignedLongLongInt(): CIntType
    {
        return self::$_unsignedLongLongInt ??= new CIntType('int', Signedness::Unsigned, 'long long');
    }

    public static function signedLongLongInt(): CIntType
    {
        return self::$_signedLongLongInt ??= new CIntType('int', Signedness::Signed, 'long long');
    }

    public static function bool(): CBoolType
    {
        return self::$_bool ??= new CBoolType();
    }

    public function equals(?object $obj): bool
    {
        return $obj instanceof CBasicType
            && $this->Name === $obj->Name
            && $this->Signedness === $obj->Signedness
            && $this->Size === $obj->Size;
    }

    public function getHashCode(): int
    {
        $hash = 17;
        $hash = $hash * 37 + crc32($this->Name);
        $hash = $hash * 37 + crc32($this->Size);
        return $hash * 37 + $this->Signedness->value;
    }

    /**
     * Section 6.3.1.8 (page 53) of N1570
     */
    public function arithmeticConvert(CType $otherType, EmitContext $context): CBasicType
    {
        $otherBasicType = ($otherType instanceof CBasicType) ? $otherType : null;
        if ($otherBasicType === null) {
            $context->Report->error(19, error: "Cannot perform arithmetic with " . $otherType);
            return self::signedInt();
        }
        if ($this->Name === "double" || $otherBasicType->Name === "double") {
            return self::double();
        } elseif ($this->Name === "single" || $otherBasicType->Name === "single") {
            return self::float();
        } else {
            $p1 = $this->integerPromote($context);
            $size1 = $p1->getByteSize($context);

            $p2 = $otherBasicType->integerPromote($context);
            $size2 = $p2->getByteSize($context);

            if ($p1->Signedness === $p2->Signedness) {
                return $size1 >= $size2 ? $p1 : $p2;
            } else {
                if ($p1->Signedness === Signedness::Unsigned) {
                    if ($size1 > $size2) {
                        return $p1;
                    } else {
                        if ($size2 > $size1) {
                            return $p2;
                        } else {
                            return new CIntType($p2->Name, Signedness::Unsigned, $p2->Size);
                        }
                    }
                } else {
                    if ($size2 > $size1) {
                        return $p2;
                    } else {
                        if ($size1 > $size2) {
                            return $p1;
                        } else {
                            return new CIntType($p1->Name, Signedness::Unsigned, $p1->Size);
                        }
                    }
                }
            }
        }
    }

    public static function signedInt(): CIntType
    {
        return self::$_signedInt ??= new CIntType('int', Signedness::Signed, '');
    }

    public static function double(): CFloatType
    {
        return self::$_double ??= new CFloatType('double', 64);
    }

    public static function float(): CFloatType
    {
        return self::$_float ??= new CFloatType('float', 32);
    }

    /**
     * Section 6.3.1.1 (page 51) of N1570
     */
    public function integerPromote(EmitContext $context): CBasicType
    {
        if ($this->isIntegral()) {
            $size = $this->getByteSize($context);
            $intSize = $context->MachineInfo->intSize;
            if ($size < $intSize) {
                return self::signedInt();
            } elseif ($size === $intSize) {
                if ($this->Signedness === Signedness::Unsigned) {
                    return self::unsignedInt();
                } else {
                    return self::signedInt();
                }
            } else {
                return $this;
            }
        } else {
            return $this;
        }
    }

    public static function unsignedInt(): CIntType
    {
        return self::$_unsignedInt ??= new CIntType('int', Signedness::Unsigned, '');
    }

    public function __toString(): string
    {
        if ($this->isIntegral()) {
            $sign = $this->Signedness === Signedness::Signed ? "signed" : "unsigned";
            if ($this->Size === "") {
                return $sign . " " . $this->Name;
            } else {
                return $sign . " " . $this->Size . " " . $this->Name;
            }
        } else {
            if ($this->Size === "") {
                return $this->Name;
            } else {
                return $this->Size . " " . $this->Name;
            }
        }
    }

    private function hasRankGreaterThan(CBasicType $otherBasicType, EmitContext $context): bool
    {
        return false;
    }
}
