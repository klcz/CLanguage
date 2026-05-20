<?php

namespace parse;


include_once __DIR__ . "/Types.class.php";

//============================================================================
// Location
//============================================================================
class Location
{
    public $Document;
    public $Index;
    public $Line;
    public $Column;

    public function __construct(?Document $document = null, int $index = 0, int $line = 0, int $column = 0)
    {
        $this->Document = $document;
        $this->Index = $index;
        $this->Line = $line;
        $this->Column = $column;
    }

    public function isNull(): bool
    {
        return $this->Document === null;
    }

    public function getIsNull(): bool
    {
        return $this->Document === null;
    }

    public function __toString(): string
    {
        if ($this->isNull()) return "?(?,?)";
        return $this->Document . "(" . $this->Line . "," . $this->Column . ")";
    }

    public static function add(Location $location, int $columnOffset): Location
    {
        return new Location($location->Document, $location->Index + $columnOffset, $location->Line, $location->Column + $columnOffset);
    }

    public function addOffset(int $columnOffset): Location
    {
        return new Location($this->Document, $this->Index + $columnOffset, $this->Line, $this->Column + $columnOffset);
    }

    public static function getNull(): Location
    {
        return Location::$Null;
    }

    public static $Null;

    public function equals(?Location $other): bool
    {
        if ($other === null) return false;
        return $this->Line === $other->Line && $this->Column === $other->Column
            && ($this->Document ? $this->Document->Path : null) === ($other->Document ? $other->Document->Path : null);
    }

    public static function opEqual(?Location $x, ?Location $y): bool
    {
        if ($x === null && $y === null) return true;
        if ($x === null || $y === null) return false;
        return $x->Line === $y->Line && $x->Column === $y->Column
            && ($x->Document ? $x->Document->Path : null) === ($y->Document ? $y->Document->Path : null);
    }

    public static function opNotEqual(?Location $x, ?Location $y): bool
    {
        return !self::opEqual($x, $y);
    }
}

Location::$Null = new Location();

//============================================================================
// Document
//============================================================================
class Document
{
    public $Path;
    public $Content;
    public $Encoding;

    public function __construct(string $path, string $content, $encoding = null)
    {
        if (trim($path) === '') throw new \InvalidArgumentException("Document path must be specified");
        $this->Path = $path;
        $this->Content = $content;
        $this->Encoding = $encoding ?? 'UTF-8';
    }

    public function getIsCompilable(): bool
    {
        $ext = strtolower(pathinfo($this->Path, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'c':
            case 'cpp':
            case 'cxx':
            case 'm':
            case 'mpp':
            case 'ino':
                return true;
            default:
                return false;
        }
    }

    public function __toString(): string
    {
        return $this->Path;
    }
}

//============================================================================
// ColorSpan & SyntaxColor
//============================================================================
class ColorSpan
{
    public $Index = 0;
    public $Length = 0;
    public $Color = SyntaxColor::Comment;

    public function __toString(): string
    {
        return (string)$this->Color;
    }
}

abstract class SyntaxColor
{
    const Comment = 0;
    const Identifier = 1;
    const Number = 2;
    const String = 3;
    const Keyword = 4;
    const Operator = 5;
    const Function = 6;
    const Type = 7;
}

//============================================================================
// Token
//============================================================================
class Token
{
    public $Kind;
    public $Location;
    public $EndLocation;
    public $Value;

    public function __construct($kind, $value = null, ?Location $location = null, ?Location $endLocation = null)
    {
        $this->Kind = $kind;
        $this->Value = $value ?? "";
        $this->Location = $location ?? Location::$Null;
        $this->EndLocation = $endLocation ?? Location::$Null;
    }

    public static function fromChar($kind): Token
    {
        $t = new Token($kind);
        $t->Value = null;
        return $t;
    }

    public function getStringValue(): string
    {
        if (is_string($this->Value)) return $this->Value;
        if ($this->Value !== null) return (string)$this->Value;
        return "";
    }

    public function getText(): string
    {
        if ($this->Location->isNull() || $this->EndLocation->isNull()) return "";
        if ($this->Location->Document->Path !== $this->EndLocation->Document->Path) return "";
        return substr($this->Location->Document->Content, $this->Location->Index, $this->EndLocation->Index - $this->Location->Index);
    }

    public function __toString(): string
    {
        $text = $this->Location->isNull()
            ? ($this->Value !== null ? (string)$this->Value : ($this->Kind < 127 ? chr($this->Kind) : ""))
            : $this->getText();
        if ($this->Kind < 127) return "\"$text\"";
        return "\"$text\": " . CParser::yyname($this->Kind);
    }

    public function asKind(int $kind): Token
    {
        return new Token($kind, $this->Value, $this->Location, $this->EndLocation);
    }

    public function equals($other): bool
    {
        if (!($other instanceof Token)) return false;
        return $this->Kind === $other->Kind && $this->Location->equals($other->Location)
            && (($this->Value === null && $other->Value === null) || ($this->Value !== null && $this->Value === $other->Value));
    }
}

//============================================================================
// VariableScope
//============================================================================
abstract class VariableScope
{
    const Local = 0;
    const Global = 1;
    const Arg = 2;
    const Function_ = 3;
    const Constant = 4;
}

//============================================================================
// Statement (abstract base)
//============================================================================
abstract class Statement
{
    public $Location;

    public function emit($ec): void
    {
        $this->doEmit($ec);
    }

    protected abstract function doEmit($ec): void;

    public abstract function getAlwaysReturns(): bool;

    public function toBlock(): Block
    {
        if ($this instanceof Block) return $this;
        $b = new Block(VariableScope::Local);
        $b->addStatement($this);
        return $b;
    }

    public abstract function addDeclarationToBlock($context): void;
}

//============================================================================
// Enums for operators
//============================================================================
abstract class Binop
{
    const Add = 0;
    const Subtract = 1;
    const Multiply = 2;
    const Divide = 3;
    const Mod = 4;
    const ShiftLeft = 5;
    const ShiftRight = 6;
    const BinaryAnd = 7;
    const BinaryOr = 8;
    const BinaryXor = 9;
}

abstract class Unop
{
    const None = 0;
    const Not = 1;
    const Negate = 2;
    const BinaryComplement = 3;
    const PreIncrement = 4;
    const PreDecrement = 5;
    const PostIncrement = 6;
    const PostDecrement = 7;
}

abstract class LogicOp
{
    const And = 0;
    const Or = 1;
}

abstract class RelationalOp
{
    const Equals = 0;
    const NotEquals = 1;
    const LessThan = 2;
    const LessThanOrEqual = 3;
    const GreaterThan = 4;
    const GreaterThanOrEqual = 5;
}

abstract class TypeQualifiers
{
    const None = 0;
    const Const_ = 1;
    const Restrict = 2;
    const Volatile = 4;
}

abstract class FunctionSpecifier
{
    const None = 0;
    const Inline = 1;
}

abstract class TypeSpecifierKind
{
    const Builtin = 0;
    const Typename = 1;
    const Struct = 2;
    const Class_ = 3;
    const Union = 4;
    const Enum = 5;
}

abstract class StorageClassSpecifier
{
    const None = 0;
    const Typedef = 1;
    const Extern = 2;
    const Static_ = 4;
    const Auto = 8;
    const Register = 16;
}

abstract class DeclarationsVisibility
{
    const Public = 0;
    const Private = 1;
    const Protected = 2;
}

//============================================================================
// Expression (abstract base)
//============================================================================

abstract class Expression
{
    public $Location;
    public $EndLocation;
    public $HasError = false;

    public function emit($ec): void
    {
        $this->doEmit($ec);
    }

    public function getCanEmitPointer(): bool
    {
        return false;
    }

    public function emitPointer($ec): void
    {
        $this->doEmitPointer($ec);
    }

    public abstract function getEvaluatedCType($ec): CType;

    protected abstract function doEmit($ec): void;

    protected function doEmitPointer($ec): void
    {
        throw new \RuntimeException("Cannot get address of " . get_class($this) . " `" . $this . "`");
    }

    protected static function getPromotedType($expr, string $op, $ec): CType
    {
        $leftType = $expr->getEvaluatedCType($ec);
        $leftBasicType = null;
        if ($leftType instanceof CBasicType) $leftBasicType = $leftType;
        if ($leftBasicType !== null) return $leftBasicType->integerPromote($ec);
        elseif ($leftType instanceof CArrayType) return $leftType->ElementType->getPointer();
        elseif ($leftType instanceof CPointerType) return $leftType;
        else {
            $ec->Report->error(19, "'" . $op . "' cannot be applied to operand of type '" . $leftType . "'");
            return CBasicType::$SignedInt;
        }
    }

    protected static function getArithmeticType($leftExpr, $rightExpr, string $op, $ec): CType
    {
        $leftType = $leftExpr->getEvaluatedCType($ec);
        $rightType = $rightExpr->getEvaluatedCType($ec);
        $leftBasicType = null;
        $rightBasicType = null;
        if ($leftType instanceof CBasicType) $leftBasicType = $leftType;
        if ($rightType instanceof CBasicType) $rightBasicType = $rightType;
        if ($leftBasicType !== null && $rightBasicType !== null) return $leftBasicType->arithmeticConvert($rightBasicType, $ec);
        elseif ($leftType instanceof CPointerType && $rightBasicType !== null) return $leftType;
        elseif ($leftType instanceof CArrayType && $rightBasicType !== null) return $leftType->ElementType->getPointer();
        elseif ($rightType instanceof CPointerType && $leftBasicType !== null) return $rightType;
        elseif ($rightType instanceof CArrayType && $leftBasicType !== null) return $rightType->ElementType->getPointer();
        else {
            $ec->Report->error(19, "'" . $op . "' cannot be applied to operands of type '" . $leftType . "' and '" . $rightType . "'");
            return CBasicType::$SignedInt;
        }
    }

    public function evalConstant($ec)
    {
        $ec->Report->error(133, "'" . $this . "' not constant");
        return 0;
    }

    protected static function binopToOperatorName(int $op): ?string
    {
        switch ($op) {
            case Binop::Add:
                return "operator+";
            case Binop::Subtract:
                return "operator-";
            case Binop::Multiply:
                return "operator*";
            case Binop::Divide:
                return "operator/";
            case Binop::Mod:
                return "operator%";
            case Binop::ShiftLeft:
                return "operator<<";
            case Binop::ShiftRight:
                return "operator>>";
            case Binop::BinaryAnd:
                return "operator&";
            case Binop::BinaryOr:
                return "operator|";
            case Binop::BinaryXor:
                return "operator^";
            default:
                return null;
        }
    }

    protected static function relOpToOperatorName(int $op): ?string
    {
        switch ($op) {
            case RelationalOp::Equals:
                return "operator==";
            case RelationalOp::NotEquals:
                return "operator!=";
            case RelationalOp::LessThan:
                return "operator<";
            case RelationalOp::LessThanOrEqual:
                return "operator<=";
            case RelationalOp::GreaterThan:
                return "operator>";
            case RelationalOp::GreaterThanOrEqual:
                return "operator>=";
            default:
                return null;
        }
    }

    protected static function unopToOperatorName(int $op): ?string
    {
        switch ($op) {
            case Unop::Negate:
                return "operator-";
            case Unop::Not:
                return "operator!";
            case Unop::BinaryComplement:
                return "operator~";
            default:
                return null;
        }
    }

    protected static function findBestOperatorMethod(CStructType $structType, string $operatorName, array $argTypes): ?CStructMethod
    {
        $methods = $structType->findMethods($operatorName);
        $best = null;
        $bestScore = 0;
        foreach ($methods as $m) {
            $mt = $m->MemberType;
            if ($mt instanceof CFunctionType) {
                $score = $mt->scoreParameterTypeMatches($argTypes);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $m;
                }
            }
        }
        return $best;
    }

    protected static function tryResolveBinaryOperatorType($ec, CType $leftType, CType $rightType, ?string $operatorName): ?CFunctionType
    {
        if ($operatorName === null) return null;
        if ($leftType instanceof CStructType) {
            $m = self::findBestOperatorMethod($leftType, $operatorName, [$rightType]);
            if ($m !== null && $m->MemberType instanceof CFunctionType) return $m->MemberType;
            $resolved = $ec->tryResolveOperatorFunction($leftType->Name, $operatorName, [$rightType]);
            if ($resolved !== null && $resolved->VariableType instanceof CFunctionType) return $resolved->VariableType;
        }
        if ($leftType instanceof CStructType || $rightType instanceof CStructType) {
            $res = $ec->tryResolveVariable($operatorName, [$leftType, $rightType]);
            if ($res !== null && $res->VariableType instanceof CFunctionType) return $res->VariableType;
        }
        return null;
    }

    protected static function tryResolveUnaryOperatorType($ec, CType $operandType, ?string $operatorName): ?CFunctionType
    {
        if ($operatorName === null) return null;
        if ($operandType instanceof CStructType) {
            $m = self::findBestOperatorMethod($operandType, $operatorName, []);
            if ($m !== null && $m->MemberType instanceof CFunctionType) return $m->MemberType;
            $resolved = $ec->tryResolveOperatorFunction($operandType->Name, $operatorName, []);
            if ($resolved !== null && $resolved->VariableType instanceof CFunctionType) return $resolved->VariableType;
            $res = $ec->tryResolveVariable($operatorName, [$operandType]);
            if ($res !== null && $res->VariableType instanceof CFunctionType) return $res->VariableType;
        }
        return null;
    }

    protected static function tryEmitBinaryOperatorCall($ec, CType $leftType, CType $rightType, $left, $right, ?string $operatorName): bool
    {
        if ($operatorName === null) return false;
        $argTypes = [$rightType];
        $args = [$right];
        if ($leftType instanceof CStructType) {
            $method = self::findBestOperatorMethod($leftType, $operatorName, $argTypes);
            if ($method !== null) {
                $funcType = $method->MemberType;
                $resolved = $ec->resolveMethodFunction($leftType, $method);
                self::emitMemberOperatorCall($ec, $leftType, $resolved, $funcType, $left, $args, $argTypes);
                return true;
            }
            $resolvedOp = $ec->tryResolveOperatorFunction($leftType->Name, $operatorName, $argTypes);
            if ($resolvedOp !== null && $resolvedOp->VariableType instanceof CFunctionType) {
                self::emitMemberOperatorCall($ec, $leftType, $resolvedOp, $resolvedOp->VariableType, $left, $args, $argTypes);
                return true;
            }
        }
        if ($leftType instanceof CStructType || $rightType instanceof CStructType) {
            $freeArgTypes = [$leftType, $rightType];
            $res = $ec->tryResolveVariable($operatorName, $freeArgTypes);
            if ($res !== null && $res->VariableType instanceof CFunctionType) {
                self::emitFreeStandingOperatorCall($ec, $res, [$left, $right], $freeArgTypes);
                return true;
            }
        }
        return false;
    }

    protected static function tryEmitUnaryOperatorCall($ec, CType $operandType, $operand, ?string $operatorName): bool
    {
        if ($operatorName === null) return false;
        if ($operandType instanceof CStructType) {
            $emptyTypes = [];
            $emptyArgs = [];
            $method = self::findBestOperatorMethod($operandType, $operatorName, $emptyTypes);
            if ($method !== null) {
                $funcType = $method->MemberType;
                $resolved = $ec->resolveMethodFunction($operandType, $method);
                self::emitMemberOperatorCall($ec, $operandType, $resolved, $funcType, $operand, $emptyArgs, $emptyTypes);
                return true;
            }
            $resolvedOp = $ec->tryResolveOperatorFunction($operandType->Name, $operatorName, $emptyTypes);
            if ($resolvedOp !== null && $resolvedOp->VariableType instanceof CFunctionType) {
                self::emitMemberOperatorCall($ec, $operandType, $resolvedOp, $resolvedOp->VariableType, $operand, $emptyArgs, $emptyTypes);
                return true;
            }
            $argTypes = [$operandType];
            $res = $ec->tryResolveVariable($operatorName, $argTypes);
            if ($res !== null && $res->VariableType instanceof CFunctionType) {
                self::emitFreeStandingOperatorCall($ec, $res, [$operand], $argTypes);
                return true;
            }
        }
        return false;
    }

    private static function emitMemberOperatorCall($ec, CStructType $structType, $resolved, CFunctionType $funcType, $thisExpr, array $args, array $argTypes): void
    {
        $tempThisOffset = -1;
        if (!$thisExpr->getCanEmitPointer()) {
            $tempThisOffset = $ec->allocateTemp($structType);
            $thisExpr->emit($ec);
            $numValues = $structType->getNumValues();
            for ($i = $numValues - 1; $i >= 0; $i--) $ec->emit(OpCode::StoreLocal, $tempThisOffset + $i);
        }
        for ($i = 0; $i < count($args); $i++) {
            $paramType = $funcType->Parameters[$i]->ParameterType;
            self::emitOperatorArgument($ec, $args[$i], $argTypes[$i], $paramType);
        }
        if ($tempThisOffset >= 0) {
            $ec->emit(OpCode::LoadConstant, Value::pointer($tempThisOffset));
            $ec->emit(OpCode::LoadFramePointer);
            $ec->emit(OpCode::OffsetPointer);
        } else {
            $thisExpr->emitPointer($ec);
        }
        $ec->emit(OpCode::LoadConstant, Value::pointer($resolved->Address));
        $ec->emit(OpCode::Call, $funcType->getParametersCount());
        if ($funcType->ReturnType->isVoid()) $ec->emit(OpCode::LoadConstant, 0);
    }

    private static function emitFreeStandingOperatorCall($ec, $resolvedFunc, array $args, array $argTypes): void
    {
        $funcType = $resolvedFunc->VariableType;
        for ($i = 0; $i < count($args); $i++) {
            $paramType = $funcType->Parameters[$i]->ParameterType;
            self::emitOperatorArgument($ec, $args[$i], $argTypes[$i], $paramType);
        }
        $resolvedFunc->emit($ec);
        $ec->emit(OpCode::Call, $funcType->getParametersCount());
        if ($funcType->ReturnType->isVoid()) $ec->emit(OpCode::LoadConstant, 0);
    }

    private static function emitOperatorArgument($ec, $arg, CType $argType, CType $paramType): void
    {
        if ($paramType instanceof CReferenceType) {
            if ($arg->getCanEmitPointer()) {
                $arg->emitPointer($ec);
            } else {
                $innerType = $paramType->InnerType;
                $tempOffset = $ec->allocateTemp($innerType);
                $arg->emit($ec);
                $ec->emitCast($argType, $innerType);
                $numValues = $innerType->getNumValues();
                for ($i = $numValues - 1; $i >= 0; $i--) $ec->emit(OpCode::StoreLocal, $tempOffset + $i);
                $ec->emit(OpCode::LoadConstant, Value::pointer($tempOffset));
                $ec->emit(OpCode::LoadFramePointer);
                $ec->emit(OpCode::OffsetPointer);
            }
        } else {
            $arg->emit($ec);
            $ec->emitCast($argType, $paramType);
        }
    }
}

//============================================================================
// Forward declarations (needed by some expressions)
//============================================================================
class ConstantExpression extends Expression
{
    public $Value;
    public $ConstantType;
    public static $Zero;
    public static $One;
    public static $NegativeOne;
    public static $True;
    public static $False;

    public function __construct($val, ?CType $type = null)
    {
        $this->Value = $val;
        if ($type !== null) {
            $this->ConstantType = $type;
        } else {
            if (is_string($val)) $this->ConstantType = CPointerType::pointerToConstChar();
            elseif (is_bool($val)) $this->ConstantType = CBasicType::$Bool;
            elseif (is_int($val)) $this->ConstantType = CBasicType::$SignedInt;
            elseif (is_float($val)) $this->ConstantType = CBasicType::$Double;
            else $this->ConstantType = CBasicType::$SignedInt;
        }
    }

    public function getEvaluatedCType($ec): CType
    {
        if ($this->ConstantType instanceof CIntType) return $this->promoteIntConstant($this->ConstantType, $ec);
        return $this->ConstantType;
    }

    private function promoteIntConstant($intType, $ec)
    {
        $mi = $ec->MachineInfo;
        $curSize = $intType->getByteSize($mi);
        if ($intType->Signedness === 0) {
            $val = (int)$this->Value;
            if (self::fitsInSignedBytes($val, $curSize)) return $intType;
            if ($mi->LongIntSize > $curSize && self::fitsInSignedBytes($val, $mi->LongIntSize)) return CBasicType::$SignedLongInt;
            if ($mi->LongLongIntSize > $curSize && self::fitsInSignedBytes($val, $mi->LongLongIntSize)) return CBasicType::$SignedLongLongInt;
        } else {
            $val = (int)$this->Value;
            if (self::fitsInUnsignedBytes($val, $curSize)) return $intType;
            if ($mi->LongIntSize > $curSize && self::fitsInUnsignedBytes($val, $mi->LongIntSize)) return CBasicType::$UnsignedLongInt;
            if ($mi->LongLongIntSize > $curSize && self::fitsInUnsignedBytes($val, $mi->LongLongIntSize)) return CBasicType::$UnsignedLongLongInt;
        }
        return $intType;
    }

    private static function fitsInSignedBytes(int $val, int $byteSize): bool
    {
        switch ($byteSize) {
            case 1:
                return $val >= -128 && $val <= 127;
            case 2:
                return $val >= -32768 && $val <= 32767;
            case 4:
                return $val >= -2147483648 && $val <= 2147483647;
            case 8:
                return true;
            default:
                return false;
        }
    }

    private static function fitsInUnsignedBytes(int $val, int $byteSize): bool
    {
        switch ($byteSize) {
            case 1:
                return $val <= 255;
            case 2:
                return $val <= 65535;
            case 4:
                return $val <= 4294967295;
            case 8:
                return true;
            default:
                return false;
        }
    }

    protected function doEmit($ec): void
    {
        $cval = $this->evalConstant($ec);
        $ec->emit(OpCode::LoadConstant, $cval);
    }

    public function __toString(): string
    {
        return (string)$this->Value;
    }

    public function evalConstant($ec)
    {
        $evalType = $this->getEvaluatedCType($ec);
        if ($evalType instanceof CIntType) {
            $size = $evalType->getByteSize($ec);
            $val = (int)$this->Value;
            return $val;
        } elseif ($this->ConstantType instanceof CBoolType) return $this->Value ? 1 : 0;
        elseif ($this->ConstantType instanceof CFloatType) return (float)$this->Value;
        elseif (is_string($this->Value)) return $ec->getConstantMemory($this->Value);
        else throw new \RuntimeException("Non-basic constants with type '" . $this->ConstantType . "'");
    }
}

ConstantExpression::$Zero = new ConstantExpression(0);
ConstantExpression::$One = new ConstantExpression(1);
ConstantExpression::$NegativeOne = new ConstantExpression(-1);
ConstantExpression::$True = new ConstantExpression(true);
ConstantExpression::$False = new ConstantExpression(false);

class VariableExpression extends Expression
{
    public $VariableName;

    public function __construct(string $val, ?Location $loc = null, ?Location $endLoc = null)
    {
        $this->VariableName = $val;
        $this->Location = $loc ?? Location::$Null;
        $this->EndLocation = $endLoc ?? Location::$Null;
    }

    public static function emitLoadReferenceSlot($ec, $variable): void
    {
        if ($variable->Scope === VariableScope::Arg) $ec->emit(OpCode::LoadArg, $variable->Address);
        elseif ($variable->Scope === VariableScope::Local) $ec->emit(OpCode::LoadLocal, $variable->Address);
        elseif ($variable->Scope === VariableScope::Global) $ec->emit(OpCode::LoadGlobal, $variable->Address);
        else throw new \RuntimeException("Cannot access reference variable scope '" . $variable->Scope . "'");
    }

    public function getEvaluatedCType($ec): CType
    {
        $type = $ec->resolveVariable($this, null)->VariableType;
        if ($type instanceof CReferenceType) return $type->InnerType;
        return $type;
    }

    protected function doEmit($ec): void
    {
        $variable = $ec->resolveVariable($this, null);
        if ($variable !== null) {
            if ($variable->Scope === VariableScope::Function_) {
                $ec->emit(OpCode::LoadConstant, Value::pointer($variable->Address));
            } elseif ($variable->VariableType instanceof CReferenceType) {
                self::emitLoadReferenceSlot($ec, $variable);
                $ec->emit(OpCode::LoadPointer);
            } else {
                $vt = $variable->VariableType;
                if ($vt instanceof CBasicType || $vt instanceof CPointerType || $vt instanceof CEnumType) {
                    if ($variable->Scope === VariableScope::Arg) $ec->emit(OpCode::LoadArg, $variable->Address);
                    elseif ($variable->Scope === VariableScope::Global) $ec->emit(OpCode::LoadGlobal, $variable->Address);
                    elseif ($variable->Scope === VariableScope::Local) $ec->emit(OpCode::LoadLocal, $variable->Address);
                    elseif ($variable->Scope === VariableScope::Constant) $ec->emit(OpCode::LoadConstant, $variable->Constant);
                    else throw new \RuntimeException("Cannot evaluate variable scope '" . $variable->Scope . "'");
                } elseif ($vt instanceof CStructType) {
                    $numValues = $vt->getNumValues();
                    for ($i = 0; $i < $numValues; $i++) {
                        if ($variable->Scope === VariableScope::Arg) $ec->emit(OpCode::LoadArg, $variable->Address + $i);
                        elseif ($variable->Scope === VariableScope::Global) $ec->emit(OpCode::LoadGlobal, $variable->Address + $i);
                        elseif ($variable->Scope === VariableScope::Local) $ec->emit(OpCode::LoadLocal, $variable->Address + $i);
                        else throw new \RuntimeException("Cannot evaluate struct variable scope '" . $variable->Scope . "'");
                    }
                } elseif ($vt instanceof CArrayType) {
                    if ($variable->Scope === VariableScope::Arg) {
                        $ec->emit(OpCode::LoadConstant, Value::pointer($variable->Address));
                        $ec->emit(OpCode::LoadFramePointer);
                        $ec->emit(OpCode::OffsetPointer);
                    } elseif ($variable->Scope === VariableScope::Global) {
                        $ec->emit(OpCode::LoadConstant, Value::pointer($variable->Address));
                    } elseif ($variable->Scope === VariableScope::Local) {
                        $ec->emit(OpCode::LoadConstant, Value::pointer($variable->Address));
                        $ec->emit(OpCode::LoadFramePointer);
                        $ec->emit(OpCode::OffsetPointer);
                    } else throw new \RuntimeException("Cannot evaluate array variable scope '" . $variable->Scope . "'");
                } else throw new \RuntimeException("Cannot evaluate variable type '" . $vt . "'");
            }
        } else $ec->emit(OpCode::LoadConstant, 0);
    }

    public function getCanEmitPointer(): bool
    {
        return true;
    }

    protected function doEmitPointer($ec): void
    {
        $res = $ec->resolveVariable($this, null);
        if ($res !== null) {
            if ($res->VariableType instanceof CReferenceType) self::emitLoadReferenceSlot($ec, $res);
            else $res->emitPointer($ec);
        } else $ec->emit(OpCode::LoadConstant, 0);
    }

    public function __toString(): string
    {
        return $this->VariableName;
    }

    public function evalConstant($ec)
    {
        $res = $ec->resolveVariable($this, null);
        if ($res !== null && $res->Scope === VariableScope::Constant) return $res->Constant;
        return parent::evalConstant($ec);
    }
}

//============================================================================
// Block (compound statement)
//============================================================================

class Block extends Statement
{
    public $VariableScope;
    public $Statements = [];
    public $Parent;
    public $Variables = [];
    public $Functions = [];
    public $Typedefs = [];
    public $InitStatements = [];
    public $Structures = [];
    public $Enums = [];

    public function __construct(int $variableScope, ?array $statements = null)
    {
        $this->VariableScope = $variableScope;
        if ($statements !== null) $this->addStatements($statements);
    }

    public function getAlwaysReturns(): bool
    {
        foreach ($this->Statements as $s) {
            if ($s->getAlwaysReturns()) return true;
        }
        return false;
    }

    public function __toString(): string
    {
        $stmtParts = [];
        foreach ($this->Statements as $s) $stmtParts[] = (string)$s;
        if (count($this->InitStatements) > 0) {
            $initParts = [];
            foreach ($this->InitStatements as $s) $initParts[] = (string)$s;
            return "{[" . implode("; ", $initParts) . "] " . implode("; ", $stmtParts) . "}";
        }
        return "{" . implode("; ", $stmtParts) . "}";
    }

    public function addStatement(?Statement $stmt): void
    {
        if ($stmt !== null) $this->Statements[] = $stmt;
        if ($stmt instanceof Block) $stmt->Parent = $this;
    }

    public function addStatements(array $stmts): void
    {
        foreach ($stmts as $s) $this->addStatement($s);
    }

    protected function doEmit($ec): void
    {
        $ec->beginBlock($this);
        foreach ($this->Variables as $v) {
            if ($v->VariableType instanceof CStructType && $v->VariableType->IsPolymorphic && $v->VariableType->VTableGlobalAddress !== null) {
                $ec->emit(OpCode::LoadConstant, Value::pointer($v->VariableType->VTableGlobalAddress));
                $ec->emit(OpCode::LoadFramePointer);
                $ec->emit(OpCode::LoadConstant, Value::pointer($v->StackOffset));
                $ec->emit(OpCode::OffsetPointer);
                $ec->emit(OpCode::StorePointer);
            }
        }
        foreach ($this->Statements as $s) $s->emit($ec);
        $ec->endBlock();
    }

    public function addVariable(string $name, CType $ctype): void
    {
        $this->Variables[] = new CompiledVariable($name, 0, $ctype);
    }

    public function addDeclarationToBlock($context): void
    {
        $subContext = new BlockContext($this, $context);
        foreach ($this->Statements as $s) $s->addDeclarationToBlock($subContext);
    }
}

//============================================================================
// TranslationUnit
//============================================================================
class TranslationUnit extends Block
{
    public $Name;

    public function __construct(string $name)
    {
        parent::__construct(VariableScope::Global);
        if (trim($name) === '') throw new \InvalidArgumentException("Translation unit name must be specified");
        $this->Name = $name;
    }

    public function __toString(): string
    {
        return $this->Name;
    }
}

//============================================================================
// BinaryExpression
//============================================================================
class BinaryExpression extends Expression
{
    public $Left;
    public $Op;
    public $Right;

    public function __construct($left, int $op, $right)
    {
        if ($left === null) throw new \InvalidArgumentException("left");
        if ($right === null) throw new \InvalidArgumentException("right");
        $this->Left = $left;
        $this->Op = $op;
        $this->Right = $right;
    }

    protected function doEmit($ec): void
    {
        $leftType = $this->Left->getEvaluatedCType($ec);
        $rightType = $this->Right->getEvaluatedCType($ec);
        if (self::tryEmitBinaryOperatorCall($ec, $leftType, $rightType, $this->Left, $this->Right, self::binopToOperatorName($this->Op))) return;

        if ($this->Op === Binop::ShiftLeft || $this->Op === Binop::ShiftRight) {
            $promotedLeft = self::getShiftPromotedType($leftType, $ec);
            $this->Left->emit($ec);
            $ec->emitCast($leftType, $promotedLeft);
            $this->Right->emit($ec);
            $ec->emitCast($rightType, $promotedLeft);
            $shiftOff = $ec->getInstructionOffset($promotedLeft);
            if ($this->Op === Binop::ShiftLeft) $ec->emit(OpCode::shiftLeftInt8() + $shiftOff);
            else $ec->emit(OpCode::shiftRightInt8() + $shiftOff);
            return;
        }

        $aType = self::getArithmeticType($this->Left, $this->Right, $this->getOpString(), $ec);
        $this->Left->emit($ec);
        $ec->emitCast($leftType, $aType);
        $this->Right->emit($ec);
        $ec->emitCast($rightType, $aType);
        $ioff = $ec->getInstructionOffset($aType);
        switch ($this->Op) {
            case Binop::Add:
                $ec->emit(OpCode::addInt8() + $ioff);
                break;
            case Binop::Subtract:
                $ec->emit(OpCode::subtractInt8() + $ioff);
                break;
            case Binop::Multiply:
                $ec->emit(OpCode::multiplyInt8() + $ioff);
                break;
            case Binop::Divide:
                $ec->emit(OpCode::divideInt8() + $ioff);
                break;
            case Binop::Mod:
                $ec->emit(OpCode::moduloInt8() + $ioff);
                break;
            case Binop::BinaryAnd:
                $ec->emit(OpCode::binaryAndInt8() + $ioff);
                break;
            case Binop::BinaryOr:
                $ec->emit(OpCode::binaryOrInt8() + $ioff);
                break;
            case Binop::BinaryXor:
                $ec->emit(OpCode::binaryXorInt8() + $ioff);
                break;
            default:
                throw new \RuntimeException("Unsupported binary operator '" . $this->Op . "'");
        }
    }

    private function getOpString(): string
    {
        switch ($this->Op) {
            case Binop::Add:
                return "Add";
            case Binop::Subtract:
                return "Subtract";
            case Binop::Multiply:
                return "Multiply";
            case Binop::Divide:
                return "Divide";
            case Binop::Mod:
                return "Mod";
            case Binop::ShiftLeft:
                return "ShiftLeft";
            case Binop::ShiftRight:
                return "ShiftRight";
            case Binop::BinaryAnd:
                return "BinaryAnd";
            case Binop::BinaryOr:
                return "BinaryOr";
            case Binop::BinaryXor:
                return "BinaryXor";
            default:
                return "Unknown";
        }
    }

    public function getEvaluatedCType($ec): CType
    {
        $leftType = $this->Left->getEvaluatedCType($ec);
        $rightType = $this->Right->getEvaluatedCType($ec);
        $ft = self::tryResolveBinaryOperatorType($ec, $leftType, $rightType, self::binopToOperatorName($this->Op));
        if ($ft !== null) return $ft->ReturnType;
        if ($this->Op === Binop::ShiftLeft || $this->Op === Binop::ShiftRight) return self::getShiftPromotedType($leftType, $ec);
        return self::getArithmeticType($this->Left, $this->Right, $this->getOpString(), $ec);
    }

    private static function getShiftPromotedType(CType $type, $ec): CType
    {
        if ($type instanceof CBasicType) return $type->integerPromote($ec);
        return $type;
    }

    public function __toString(): string
    {
        return "(" . $this->Left . " " . $this->Op . " " . $this->Right . ")";
    }

    public function evalConstant($ec)
    {
        $leftType = $this->Left->getEvaluatedCType($ec);
        $rightType = $this->Right->getEvaluatedCType($ec);
        if ($leftType->getIsIntegral() && $rightType->getIsIntegral()) {
            $left = (int)$this->Left->evalConstant($ec);
            $right = (int)$this->Right->evalConstant($ec);
            switch ($this->Op) {
                case Binop::Add:
                    return $left + $right;
                case Binop::Subtract:
                    return $left - $right;
                case Binop::Multiply:
                    return $left * $right;
                case Binop::Divide:
                    return intdiv($left, $right);
                case Binop::Mod:
                    return $left % $right;
                case Binop::BinaryAnd:
                    return $left & $right;
                case Binop::BinaryOr:
                    return $left | $right;
                case Binop::BinaryXor:
                    return $left ^ $right;
                case Binop::ShiftLeft:
                    return $left << $right;
                case Binop::ShiftRight:
                    return $left >> $right;
                default:
                    throw new \RuntimeException("Unsupported binary operator '" . $this->Op . "'");
            }
        }
        return parent::evalConstant($ec);
    }
}

//============================================================================
// UnaryExpression
//============================================================================
class UnaryExpression extends Expression
{
    public $Op;
    public $Right;

    public function __construct(int $op, $right)
    {
        $this->Op = $op;
        $this->Right = $right;
    }

    public function getEvaluatedCType($ec): CType
    {
        $rightType = $this->Right->getEvaluatedCType($ec);
        $ft = self::tryResolveUnaryOperatorType($ec, $rightType, self::unopToOperatorName($this->Op));
        if ($ft !== null) return $ft->ReturnType;
        return ($this->Op === Unop::Not) ? CBasicType::$SignedInt : self::getPromotedType($this->Right, $this->getOpString(), $ec);
    }

    private function getOpString(): string
    {
        switch ($this->Op) {
            case Unop::None:
                return "None";
            case Unop::Not:
                return "Not";
            case Unop::Negate:
                return "Negate";
            case Unop::BinaryComplement:
                return "BinaryComplement";
            case Unop::PreIncrement:
                return "PreIncrement";
            case Unop::PreDecrement:
                return "PreDecrement";
            case Unop::PostIncrement:
                return "PostIncrement";
            case Unop::PostDecrement:
                return "PostDecrement";
            default:
                return "Unknown";
        }
    }

    protected function doEmit($ec): void
    {
        $rightType = $this->Right->getEvaluatedCType($ec);
        $opName = self::unopToOperatorName($this->Op);
        if ($opName !== null && self::tryEmitUnaryOperatorCall($ec, $rightType, $this->Right, $opName)) return;
        switch ($this->Op) {
            case Unop::PreIncrement:
                (new AssignExpression($this->Right, new BinaryExpression($this->Right, Binop::Add, ConstantExpression::$One)))->emit($ec);
                break;
            case Unop::PreDecrement:
                (new AssignExpression($this->Right, new BinaryExpression($this->Right, Binop::Add, ConstantExpression::$NegativeOne)))->emit($ec);
                break;
            case Unop::PostIncrement:
                $this->Right->emit($ec);
                (new AssignExpression($this->Right, new BinaryExpression($this->Right, Binop::Add, ConstantExpression::$One)))->emit($ec);
                $ec->emit(OpCode::Pop);
                break;
            case Unop::PostDecrement:
                $this->Right->emit($ec);
                (new AssignExpression($this->Right, new BinaryExpression($this->Right, Binop::Add, ConstantExpression::$NegativeOne)))->emit($ec);
                $ec->emit(OpCode::Pop);
                break;
            default:
                $aType = $this->getEvaluatedCType($ec);
                $this->Right->emit($ec);
                $ec->emitCast($this->Right->getEvaluatedCType($ec), $aType);
                $ioff = $ec->getInstructionOffset($aType);
                switch ($this->Op) {
                    case Unop::None:
                        break;
                    case Unop::Negate:
                        $ec->emit(OpCode::negateInt8() + $ioff);
                        break;
                    case Unop::Not:
                        $ec->emit(OpCode::notInt8() + $ioff);
                        break;
                    case Unop::BinaryComplement:
                        $ec->emit(OpCode::binaryNotInt8() + $ioff);
                        break;
                    default:
                        throw new \RuntimeException("Unsupported unary operator '" . $this->Op . "'");
                }
                break;
        }
    }

    public function __toString(): string
    {
        return "(" . $this->Op . " " . $this->Right . ")";
    }

    public function evalConstant($ec)
    {
        $rightType = $this->Right->getEvaluatedCType($ec);
        if ($rightType->getIsIntegral()) {
            $right = (int)$this->Right->evalConstant($ec);
            switch ($this->Op) {
                case Unop::None:
                    return $right;
                case Unop::Not:
                    return ($right === 0) ? 1 : 0;
                case Unop::Negate:
                    return -$right;
                case Unop::BinaryComplement:
                    return ~$right;
                case Unop::PreIncrement:
                    return $right + 1;
                case Unop::PreDecrement:
                    return $right - 1;
                case Unop::PostIncrement:
                    return $right;
                case Unop::PostDecrement:
                    return $right;
                default:
                    throw new \RuntimeException("Unsupported unary operator '" . $this->Op . "'");
            }
        }
        return parent::evalConstant($ec);
    }
}

//============================================================================
// CastExpression
//============================================================================
class CastExpression extends Expression
{
    public $TypeName;
    public $InnerExpression;

    public function __construct(TypeName $typeName, $innerExpression)
    {
        $this->TypeName = $typeName;
        $this->InnerExpression = $innerExpression;
    }

    public function getEvaluatedCType($ec): CType
    {
        return $ec->resolveTypeName($this->TypeName) ?? CBasicType::$SignedInt;
    }

    protected function doEmit($ec): void
    {
        $rtype = $this->getEvaluatedCType($ec);
        $itype = $this->InnerExpression->getEvaluatedCType($ec);
        $this->InnerExpression->emit($ec);
        $ec->emitCast($itype, $rtype);
    }
}

//============================================================================
// AddressOfExpression
//============================================================================
class AddressOfExpression extends Expression
{
    public $InnerExpression;

    public function __construct($innerExpression)
    {
        $this->InnerExpression = $innerExpression;
    }

    public function getEvaluatedCType($ec): CType
    {
        return $this->InnerExpression->getEvaluatedCType($ec)->getPointer();
    }

    protected function doEmit($ec): void
    {
        $this->InnerExpression->emitPointer($ec);
    }
}

//============================================================================
// DereferenceExpression
//============================================================================
class DereferenceExpression extends Expression
{
    public $InnerExpression;

    public function __construct($innerExpression)
    {
        $this->InnerExpression = $innerExpression;
    }

    public function getEvaluatedCType($ec): CType
    {
        $it = $this->InnerExpression->getEvaluatedCType($ec);
        if ($it instanceof CPointerType) return $it->InnerType;
        $ec->Report->error(0, "Cannot dereference values of type `" . $it . "`.");
        return CBasicType::$SignedInt;
    }

    protected function doEmit($ec): void
    {
        $this->InnerExpression->emit($ec);
        $ec->emit(OpCode::LoadPointer);
    }

    public function getCanEmitPointer(): bool
    {
        return true;
    }

    protected function doEmitPointer($ec): void
    {
        $this->InnerExpression->emit($ec);
    }
}

//============================================================================
// ArrayElementExpression
//============================================================================
class ArrayElementExpression extends Expression
{
    public $Array;
    public $ElementIndex;

    public function __construct($array, $elementIndex)
    {
        $this->Array = $array;
        $this->ElementIndex = $elementIndex;
    }

    public function getEvaluatedCType($ec): CType
    {
        $t = $this->Array->getEvaluatedCType($ec);
        if ($t instanceof CArrayType) return $t->ElementType;
        elseif ($t instanceof CPointerType) return $t->InnerType;
        elseif ($t instanceof CStructType) {
            $indexType = $this->ElementIndex->getEvaluatedCType($ec);
            $ft = self::tryResolveBinaryOperatorType($ec, $t, $indexType, "operator[]");
            if ($ft !== null) return $ft->ReturnType;
            $ec->Report->error(601, "Left hand side of [ must be an array or pointer");
            return CType::void();
        } else {
            $ec->Report->error(601, "Left hand side of [ must be an array or pointer");
            return CType::void();
        }
    }

    protected function doEmit($ec): void
    {
        $t = $this->Array->getEvaluatedCType($ec);
        if ($t instanceof CStructType) {
            $indexType = $this->ElementIndex->getEvaluatedCType($ec);
            if (self::tryEmitBinaryOperatorCall($ec, $t, $indexType, $this->Array, $this->ElementIndex, "operator[]")) return;
            $ec->Report->error(601, "Left hand side of [ must be an array or pointer");
            return;
        }
        $this->doEmitPointer($ec);
        $evalType = $this->getEvaluatedCType($ec);
        if (!($evalType instanceof CArrayType)) $ec->emit(OpCode::LoadPointer);
    }

    public function __toString(): string
    {
        return $this->Array . "[" . $this->ElementIndex . "]";
    }

    public function getCanEmitPointer(): bool
    {
        return $this->Array->getCanEmitPointer();
    }

    protected function doEmitPointer($ec): void
    {
        $this->Array->emit($ec);
        $this->ElementIndex->emit($ec);
        $ec->emit(OpCode::LoadConstant, $this->getEvaluatedCType($ec)->getNumValues());
        $ec->emit(OpCode::MultiplyInt32);
        $ec->emit(OpCode::OffsetPointer);
    }
}

//============================================================================
// AssignExpression
//============================================================================
class AssignExpression extends Expression
{
    public $Left;
    public $Right;

    public function __construct($left, $right)
    {
        $this->Left = $left;
        $this->Right = $right;
    }

    public function getEvaluatedCType($ec): CType
    {
        return $this->Left->getEvaluatedCType($ec);
    }

    private function doEmitStructureAssignment($sexpr, $ec): void
    {
        $type = $this->getEvaluatedCType($ec);
        if ($type instanceof CArrayType) {
            $this->emitArrayStructuredInit($sexpr, $type, 0, $ec);
            $this->Left->emitPointer($ec);
        } else throw new \RuntimeException("Structured assignment of '" . $this->getEvaluatedCType($ec) . "' not supported");
    }

    private function emitArrayStructuredInit($sexpr, CArrayType $arrayType, int $baseOffset, $ec): void
    {
        $elementType = $arrayType->ElementType;
        $numItemValues = $elementType->getNumValues();
        $count = count($sexpr->Items);
        for ($i = 0; $i < $count; $i++) {
            $item = $sexpr->Items[$i];
            $itemOffset = $baseOffset + $i * $numItemValues;
            if ($item->Expression instanceof StructureExpression && $elementType instanceof CArrayType)
                $this->emitArrayStructuredInit($item->Expression, $elementType, $itemOffset, $ec);
            else {
                $item->Expression->emit($ec);
                $ec->emitCast($item->Expression->getEvaluatedCType($ec), $elementType);
                $this->Left->emitPointer($ec);
                $ec->emit(OpCode::LoadConstant, $itemOffset);
                $ec->emit(OpCode::OffsetPointer);
                $ec->emit(OpCode::StorePointer);
            }
        }
    }

    protected function doEmit($ec): void
    {
        if ($this->Right instanceof StructureExpression) {
            $this->doEmitStructureAssignment($this->Right, $ec);
            return;
        }
        $this->Right->emit($ec);

        if ($this->Left instanceof VariableExpression) {
            $v = $ec->resolveVariable($this->Left, null);
            if ($v->VariableType instanceof CReferenceType) {
                $ec->emitCast($this->Right->getEvaluatedCType($ec), $v->VariableType->InnerType);
                $ec->emit(OpCode::Dup);
                VariableExpression::emitLoadReferenceSlot($ec, $v);
                $ec->emit(OpCode::StorePointer);
            } elseif ($v->VariableType instanceof CStructType) {
                $ec->emitCast($this->Right->getEvaluatedCType($ec), $this->Left->getEvaluatedCType($ec));
                $numValues = $v->VariableType->getNumValues();
                for ($i = $numValues - 1; $i >= 0; $i--) {
                    if ($v->Scope === VariableScope::Global) $ec->emit(OpCode::StoreGlobal, $v->Address + $i);
                    elseif ($v->Scope === VariableScope::Local) $ec->emit(OpCode::StoreLocal, $v->Address + $i);
                    elseif ($v->Scope === VariableScope::Arg) $ec->emit(OpCode::StoreArg, $v->Address + $i);
                    else throw new \RuntimeException("Assigning struct to scope '" . $v->Scope . "'");
                }
                for ($i = 0; $i < $numValues; $i++) {
                    if ($v->Scope === VariableScope::Global) $ec->emit(OpCode::LoadGlobal, $v->Address + $i);
                    elseif ($v->Scope === VariableScope::Local) $ec->emit(OpCode::LoadLocal, $v->Address + $i);
                    elseif ($v->Scope === VariableScope::Arg) $ec->emit(OpCode::LoadArg, $v->Address + $i);
                    else throw new \RuntimeException("Loading struct from scope '" . $v->Scope . "'");
                }
            } else {
                $ec->emitCast($this->Right->getEvaluatedCType($ec), $this->Left->getEvaluatedCType($ec));
                $ec->emit(OpCode::Dup);
                if ($v->Scope === VariableScope::Global) $ec->emit(OpCode::StoreGlobal, $v->Address);
                elseif ($v->Scope === VariableScope::Local) $ec->emit(OpCode::StoreLocal, $v->Address);
                elseif ($v->Scope === VariableScope::Arg) $ec->emit(OpCode::StoreArg, $v->Address);
                elseif ($v->Scope === VariableScope::Function_) {
                    $ec->emit(OpCode::Pop);
                    $ec->Report->error(1656, "Cannot assign to `" . $this->Left->VariableName . "` because it is a function");
                } else throw new \RuntimeException("Assigning to scope '" . $v->Scope . "'");
            }
        } elseif ($this->Left->getCanEmitPointer()) {
            $ec->emitCast($this->Right->getEvaluatedCType($ec), $this->Left->getEvaluatedCType($ec));
            $ec->emit(OpCode::Dup);
            $this->Left->emitPointer($ec);
            $ec->emit(OpCode::StorePointer);
        } else {
            $ec->Report->error(131, "The left-hand side of an assignment must be a variable or an addressable memory location");
        }
    }

    public function __toString(): string
    {
        return $this->Left . " = " . $this->Right;
    }
}

//============================================================================
// ConditionalExpression
//============================================================================
class ConditionalExpression extends Expression
{
    public $Condition;
    public $TrueValue;
    public $FalseValue;

    public function __construct($condition, $trueValue, $falseValue)
    {
        $this->Condition = $condition;
        $this->TrueValue = $trueValue;
        $this->FalseValue = $falseValue;
    }

    public function getEvaluatedCType($ec): CType
    {
        return $this->TrueValue->getEvaluatedCType($ec);
    }

    protected function doEmit($ec): void
    {
        $falseLabel = $ec->defineLabel();
        $endLabel = $ec->defineLabel();
        $this->Condition->emit($ec);
        $ec->emitCastToBoolean($this->Condition->getEvaluatedCType($ec));
        $ec->emit(OpCode::BranchIfFalse, $falseLabel);
        $this->TrueValue->emit($ec);
        $ec->emit(OpCode::Jump, $endLabel);
        $ec->emitLabel($falseLabel);
        $this->FalseValue->emit($ec);
        $ec->emitLabel($endLabel);
    }
}

//============================================================================
// LogicExpression
//============================================================================
class LogicExpression extends Expression
{
    public $Left;
    public $Op;
    public $Right;

    public function __construct($left, int $op, $right)
    {
        $this->Left = $left;
        $this->Op = $op;
        $this->Right = $right;
    }

    protected function doEmit($ec): void
    {
        $shortCircuitLabel = $ec->defineLabel();
        $endLabel = $ec->defineLabel();
        $this->Left->emit($ec);
        $ec->emitCastToBoolean($this->Left->getEvaluatedCType($ec));
        if ($this->Op === LogicOp::And) $ec->emit(OpCode::BranchIfFalse, $shortCircuitLabel);
        else $ec->emit(OpCode::BranchIfTrue, $shortCircuitLabel);
        $this->Right->emit($ec);
        $ec->emitCastToBoolean($this->Right->getEvaluatedCType($ec));
        if ($this->Op === LogicOp::And) $ec->emit(OpCode::BranchIfFalse, $shortCircuitLabel);
        else $ec->emit(OpCode::BranchIfTrue, $shortCircuitLabel);
        if ($this->Op === LogicOp::And) $ec->emit(OpCode::LoadConstant, 1);
        else $ec->emit(OpCode::LoadConstant, 0);
        $ec->emit(OpCode::Jump, $endLabel);
        $ec->emitLabel($shortCircuitLabel);
        if ($this->Op === LogicOp::And) $ec->emit(OpCode::LoadConstant, 0);
        else $ec->emit(OpCode::LoadConstant, 1);
        $ec->emitLabel($endLabel);
    }

    public function getEvaluatedCType($ec): CType
    {
        return CBasicType::$Bool;
    }

    public function __toString(): string
    {
        return "(" . $this->Left . " " . $this->Op . " " . $this->Right . ")";
    }
}

//============================================================================
// RelationalExpression
//============================================================================
class RelationalExpression extends Expression
{
    public $Left;
    public $Op;
    public $Right;

    public function __construct($left, int $op, $right)
    {
        $this->Left = $left;
        $this->Op = $op;
        $this->Right = $right;
    }

    private function getOpString(): string
    {
        switch ($this->Op) {
            case RelationalOp::Equals:
                return "Equals";
            case RelationalOp::NotEquals:
                return "NotEquals";
            case RelationalOp::LessThan:
                return "LessThan";
            case RelationalOp::LessThanOrEqual:
                return "LessThanOrEqual";
            case RelationalOp::GreaterThan:
                return "GreaterThan";
            case RelationalOp::GreaterThanOrEqual:
                return "GreaterThanOrEqual";
            default:
                return "Unknown";
        }
    }

    protected function doEmit($ec): void
    {
        $leftType = $this->Left->getEvaluatedCType($ec);
        $rightType = $this->Right->getEvaluatedCType($ec);
        if (self::tryEmitBinaryOperatorCall($ec, $leftType, $rightType, $this->Left, $this->Right, self::relOpToOperatorName($this->Op))) return;
        $aType = self::getArithmeticType($this->Left, $this->Right, $this->getOpString(), $ec);
        $this->Left->emit($ec);
        $ec->emitCast($leftType, $aType);
        $this->Right->emit($ec);
        $ec->emitCast($rightType, $aType);
        $ioff = $ec->getInstructionOffset($aType);
        switch ($this->Op) {
            case RelationalOp::Equals:
                $ec->emit(OpCode::equalToInt8() + $ioff);
                break;
            case RelationalOp::NotEquals:
                $ec->emit(OpCode::equalToInt8() + $ioff);
                $ec->emit(OpCode::notInt8() + 0);
                break;
            case RelationalOp::LessThan:
                $ec->emit(OpCode::lessThanInt8() + $ioff);
                break;
            case RelationalOp::LessThanOrEqual:
                $ec->emit(OpCode::greaterThanInt8() + $ioff);
                $ec->emit(OpCode::notInt8() + 0);
                break;
            case RelationalOp::GreaterThan:
                $ec->emit(OpCode::greaterThanInt8() + $ioff);
                break;
            case RelationalOp::GreaterThanOrEqual:
                $ec->emit(OpCode::lessThanInt8() + $ioff);
                $ec->emit(OpCode::notInt8() + 0);
                break;
            default:
                throw new \RuntimeException("Unsupported relational operator '" . $this->Op . "'");
        }
    }

    public function evalConstant($ec)
    {
        $leftType = $this->Left->getEvaluatedCType($ec);
        $rightType = $this->Right->getEvaluatedCType($ec);
        if ($leftType->getIsIntegral() && $rightType->getIsIntegral()) {
            $left = (int)$this->Left->evalConstant($ec);
            $right = (int)$this->Right->evalConstant($ec);
            switch ($this->Op) {
                case RelationalOp::Equals:
                    return $left === $right ? 1 : 0;
                case RelationalOp::NotEquals:
                    return $left !== $right ? 1 : 0;
                case RelationalOp::LessThan:
                    return $left < $right ? 1 : 0;
                case RelationalOp::LessThanOrEqual:
                    return $left <= $right ? 1 : 0;
                case RelationalOp::GreaterThan:
                    return $left > $right ? 1 : 0;
                case RelationalOp::GreaterThanOrEqual:
                    return $left >= $right ? 1 : 0;
                default:
                    throw new \RuntimeException("Unsupported relational operator '" . $this->Op . "'");
            }
        }
        return parent::evalConstant($ec);
    }

    public function getEvaluatedCType($ec): CType
    {
        $leftType = $this->Left->getEvaluatedCType($ec);
        $rightType = $this->Right->getEvaluatedCType($ec);
        $ft = self::tryResolveBinaryOperatorType($ec, $leftType, $rightType, self::relOpToOperatorName($this->Op));
        if ($ft !== null) return $ft->ReturnType;
        return CBasicType::$Bool;
    }

    public function __toString(): string
    {
        return "(" . $this->Left . " " . $this->Op . " " . $this->Right . ")";
    }
}

//============================================================================
// MemberFromReferenceExpression
//============================================================================
class MemberFromReferenceExpression extends Expression
{
    public $Left;
    public $MemberName;

    public function __construct($left, string $memberName)
    {
        $this->Left = $left;
        $this->MemberName = $memberName;
    }

    public function getCanEmitPointer(): bool
    {
        return true;
    }

    private static function findMember(CStructType $structType, string $name): ?CStructMember
    {
        return $structType->findMember($name);
    }

    public function getEvaluatedCType($ec): CType
    {
        $targetType = $this->Left->getEvaluatedCType($ec);
        if ($targetType instanceof CStructType) {
            $member = self::findMember($targetType, $this->MemberName);
            if ($member === null) {
                $ec->Report->error(1061, "'{1}' not found in '{0}'", $targetType->Name, $this->MemberName);
                return CBasicType::$SignedInt;
            }
            return $member->MemberType;
        }
        throw new \RuntimeException("Member type on " . get_class($targetType));
    }

    protected function doEmit($ec): void
    {
        $targetType = $this->Left->getEvaluatedCType($ec);
        if ($targetType instanceof CStructType) {
            $member = self::findMember($targetType, $this->MemberName);
            if ($member === null) $ec->Report->error(1061, "'{1}' not found in '{0}'", $targetType->Name, $this->MemberName);
            else {
                if ($member instanceof CStructMethod && $member->MemberType instanceof CFunctionType) {
                    $functionType = $member->MemberType;
                    if ($member->VTableSlotIndex !== null && $targetType->VTableGlobalAddress !== null) {
                        $this->Left->emitPointer($ec);
                        $ec->emit(OpCode::Dup);
                        $ec->emit(OpCode::LoadPointer);
                        $ec->emit(OpCode::LoadConstant, Value::pointer($member->VTableSlotIndex));
                        $ec->emit(OpCode::OffsetPointer);
                        $ec->emit(OpCode::LoadPointer);
                    } else {
                        $res = $ec->resolveMethodFunction($targetType, $member);
                        if ($res !== null) {
                            $this->Left->emitPointer($ec);
                            $ec->emit(OpCode::LoadConstant, Value::pointer($res->Address));
                        }
                    }
                } else {
                    if (!$this->Left->getCanEmitPointer()) {
                        $tempOffset = $ec->allocateTemp($targetType);
                        $this->Left->emit($ec);
                        $numValues = $targetType->getNumValues();
                        for ($i = $numValues - 1; $i >= 0; $i--) $ec->emit(OpCode::StoreLocal, $tempOffset + $i);
                        $ec->emit(OpCode::LoadConstant, Value::pointer($tempOffset));
                        $ec->emit(OpCode::LoadFramePointer);
                        $ec->emit(OpCode::OffsetPointer);
                    } else $this->Left->emitPointer($ec);
                    $ec->emit(OpCode::LoadConstant, Value::pointer($targetType->getFieldValueOffset($member, $ec)));
                    $ec->emit(OpCode::OffsetPointer);
                    $ec->emit(OpCode::LoadPointer);
                }
            }
        } else throw new \RuntimeException("Cannot read '" . $this->MemberName . "' on " . get_class($targetType));
    }

    protected function doEmitPointer($ec): void
    {
        $targetType = $this->Left->getEvaluatedCType($ec);
        if ($targetType instanceof CStructType) {
            $member = self::findMember($targetType, $this->MemberName);
            if ($member === null) $ec->Report->error(1061, "'{1}' not found in '{0}'", $targetType->Name, $this->MemberName);
            else {
                if ($member instanceof CStructMethod && $member->MemberType instanceof CFunctionType) $ec->Report->error(1656, "Cannot assign to '{0}'", $this->MemberName);
                else {
                    $this->Left->emitPointer($ec);
                    $ec->emit(OpCode::LoadConstant, Value::pointer($targetType->getFieldValueOffset($member, $ec)));
                    $ec->emit(OpCode::OffsetPointer);
                }
            }
        } else throw new \RuntimeException("Cannot write '" . $this->MemberName . "' on " . get_class($targetType));
    }

    public function __toString(): string
    {
        return $this->Left . "." . $this->MemberName;
    }
}

//============================================================================
// MemberFromPointerExpression
//============================================================================
class MemberFromPointerExpression extends Expression
{
    public $Left;
    public $MemberName;

    public function __construct($left, string $memberName)
    {
        $this->Left = $left;
        $this->MemberName = $memberName;
    }

    public function getCanEmitPointer(): bool
    {
        return true;
    }

    private static function findMember(CStructType $structType, string $name): ?CStructMember
    {
        return $structType->findMember($name);
    }

    public function getEvaluatedCType($ec): CType
    {
        $targetType = $this->Left->getEvaluatedCType($ec);
        $pType = ($targetType instanceof CPointerType) ? $targetType : null;
        if ($pType !== null && $pType->InnerType instanceof CStructType) {
            $structType = $pType->InnerType;
            $member = self::findMember($structType, $this->MemberName);
            if ($member === null) {
                $ec->Report->error(1061, "'{1}' not found in '{0}'", $structType->Name, $this->MemberName);
                return CBasicType::$SignedInt;
            }
            return $member->MemberType;
        }
        if ($pType !== null) {
            $ec->Report->error(1061, "'{1}' not found in '{0}'", $pType, $this->MemberName);
            return CBasicType::$SignedInt;
        }
        $ec->Report->error(1061, "-> cannot be used with '{0}'", $targetType);
        return CBasicType::$SignedInt;
    }

    protected function doEmit($ec): void
    {
        $targetType = $this->Left->getEvaluatedCType($ec);
        if ($targetType instanceof CPointerType && $targetType->InnerType instanceof CStructType) {
            $structType = $targetType->InnerType;
            $member = self::findMember($structType, $this->MemberName);
            if ($member === null) $ec->Report->error(1061, "'{1}' not found in '{0}'", $structType->Name, $this->MemberName);
            else {
                if ($member instanceof CStructMethod && $member->MemberType instanceof CFunctionType) {
                    $functionType = $member->MemberType;
                    if ($member->VTableSlotIndex !== null && $structType->VTableGlobalAddress !== null) {
                        $this->Left->emit($ec);
                        $ec->emit(OpCode::Dup);
                        $ec->emit(OpCode::LoadPointer);
                        $ec->emit(OpCode::LoadConstant, Value::pointer($member->VTableSlotIndex));
                        $ec->emit(OpCode::OffsetPointer);
                        $ec->emit(OpCode::LoadPointer);
                    } else {
                        $res = $ec->resolveMethodFunction($structType, $member);
                        if ($res !== null) {
                            $this->Left->emit($ec);
                            $ec->emit(OpCode::LoadConstant, Value::pointer($res->Address));
                        }
                    }
                } else {
                    $this->Left->emit($ec);
                    $ec->emit(OpCode::LoadConstant, Value::pointer($structType->getFieldValueOffset($member, $ec)));
                    $ec->emit(OpCode::OffsetPointer);
                    $ec->emit(OpCode::LoadPointer);
                }
            }
        } else throw new \RuntimeException("Cannot read '" . $this->MemberName . "' on " . get_class($targetType));
    }

    protected function doEmitPointer($ec): void
    {
        $targetType = $this->Left->getEvaluatedCType($ec);
        if ($targetType instanceof CPointerType && $targetType->InnerType instanceof CStructType) {
            $structType = $targetType->InnerType;
            $member = self::findMember($structType, $this->MemberName);
            if ($member === null) $ec->Report->error(1061, "'{1}' not found in '{0}'", $structType->Name, $this->MemberName);
            else {
                if ($member instanceof CStructMethod && $member->MemberType instanceof CFunctionType) $ec->Report->error(1656, "Cannot assign to '{0}'", $this->MemberName);
                else {
                    $this->Left->emit($ec);
                    $ec->emit(OpCode::LoadConstant, Value::pointer($structType->getFieldValueOffset($member, $ec)));
                    $ec->emit(OpCode::OffsetPointer);
                }
            }
        } else throw new \RuntimeException("Cannot write '" . $this->MemberName . "' on " . get_class($targetType));
    }

    public function __toString(): string
    {
        return $this->Left . "->" . $this->MemberName;
    }
}

//============================================================================
// ScopeResolutionExpression
//============================================================================
class ScopeResolutionExpression extends Expression
{
    public $TypeName;
    public $MemberName;

    public function __construct(string $typeName, string $memberName)
    {
        $this->TypeName = $typeName;
        $this->MemberName = $memberName;
    }

    public function getEvaluatedCType($ec): CType
    {
        $r = $ec->tryResolveQualifiedFunction($this->TypeName, $this->MemberName, null);
        return ($r !== null) ? $r->VariableType : CBasicType::$SignedInt;
    }

    protected function doEmit($ec): void
    {
        $r = $ec->tryResolveQualifiedFunction($this->TypeName, $this->MemberName, null);
        if ($r !== null) $r->emit($ec);
        else {
            $ec->Report->error(103, "'" . $this->TypeName . "::" . $this->MemberName . "' not found");
            $ec->emit(OpCode::LoadConstant, 0);
        }
    }

    public function __toString(): string
    {
        return $this->TypeName . "::" . $this->MemberName;
    }
}

//============================================================================
// SequenceExpression
//============================================================================
class SequenceExpression extends Expression
{
    public $First;
    public $Second;

    public function __construct($first, $second)
    {
        $this->First = $first;
        $this->Second = $second;
    }

    public function getEvaluatedCType($ec): CType
    {
        return $this->Second->getEvaluatedCType($ec);
    }

    protected function doEmit($ec): void
    {
        $this->First->emit($ec);
        $ec->emit(OpCode::Pop);
        $this->Second->emit($ec);
    }

    public function __toString(): string
    {
        return "(" . $this->First . ", " . $this->Second . ")";
    }
}

//============================================================================
// SizeOfExpression
//============================================================================
class SizeOfExpression extends Expression
{
    public $Query;

    public function __construct($query)
    {
        $this->Query = $query;
    }

    public function getEvaluatedCType($ec): CType
    {
        return CBasicType::$UnsignedLongInt;
    }

    protected function doEmit($ec): void
    {
        $ec->emit(OpCode::LoadConstant, $this->Query->getEvaluatedCType($ec)->getNumValues());
    }
}

//============================================================================
// SizeOfTypeExpression
//============================================================================
class SizeOfTypeExpression extends Expression
{
    public $TypeName;

    public function __construct(TypeName $typeName)
    {
        $this->TypeName = $typeName;
    }

    public function getEvaluatedCType($ec): CType
    {
        return CBasicType::$UnsignedLongInt;
    }

    protected function doEmit($ec): void
    {
        $ec->emit(OpCode::LoadConstant, $ec->resolveTypeName($this->TypeName)->getNumValues());
    }
}

//============================================================================
// StructureExpression / StructureExpressionItem
//============================================================================
class StructureExpression extends Expression
{
    public $Items;

    public function __construct()
    {
        $this->Items = [];
    }

    public function __toString(): string
    {
        $parts = [];
        foreach ($this->Items as $x) $parts[] = (string)$x->Expression;
        return "{ " . implode(", ", $parts) . " }";
    }

    public function getEvaluatedCType($ec): CType
    {
        return CType::void();
    }

    protected function doEmit($ec): void
    {
        throw new \RuntimeException(get_class($this) . ": Emit");
    }
}

class StructureExpressionItem
{
    public $Index = 0;
    public $Field;
    public $Expression;

    public function __construct(?string $field, $expression)
    {
        $this->Field = $field;
        $this->Expression = $expression;
    }
}

//============================================================================
// Statement subclasses
//============================================================================
class IfStatement extends Statement
{
    public $Condition;
    public $TrueStatement;
    public $FalseStatement;

    public function __construct($condition, $trueStatement, $falseStatement = null, ?Location $loc = null)
    {
        if ($condition === null) throw new \InvalidArgumentException("condition");
        if ($trueStatement === null) throw new \InvalidArgumentException("trueStatement");
        $this->Condition = $condition;
        $this->TrueStatement = $trueStatement;
        $this->FalseStatement = $falseStatement;
        $this->Location = $loc ?? Location::$Null;
    }

    protected function doEmit($ec): void
    {
        $endLabel = $ec->defineLabel();
        $this->Condition->emit($ec);
        $ec->emitCastToBoolean($this->Condition->getEvaluatedCType($ec));
        if ($this->FalseStatement === null) {
            $ec->emit(OpCode::BranchIfFalse, $endLabel);
            $this->TrueStatement->emit($ec);
        } else {
            $falseLabel = $ec->defineLabel();
            $ec->emit(OpCode::BranchIfFalse, $falseLabel);
            $this->TrueStatement->emit($ec);
            $ec->emit(OpCode::Jump, $endLabel);
            $ec->emitLabel($falseLabel);
            $this->FalseStatement->emit($ec);
        }
        $ec->emitLabel($endLabel);
    }

    public function __toString(): string
    {
        return "if (" . $this->Condition . ") " . $this->TrueStatement . ";";
    }

    public function addDeclarationToBlock($context): void
    {
        $this->TrueStatement->addDeclarationToBlock($context);
        if ($this->FalseStatement !== null) $this->FalseStatement->addDeclarationToBlock($context);
    }

    public function getAlwaysReturns(): bool
    {
        return $this->TrueStatement->getAlwaysReturns() && ($this->FalseStatement !== null ? $this->FalseStatement->getAlwaysReturns() : false);
    }
}

class WhileStatement extends Statement
{
    public $IsDo;
    public $Condition;
    public $Loop;

    public function __construct(bool $isDo, $condition, Block $loop)
    {
        $this->IsDo = $isDo;
        $this->Condition = $condition;
        $this->Loop = $loop;
    }

    protected function doEmit($parentContext): void
    {
        $condLabel = $parentContext->defineLabel();
        $loopLabel = $parentContext->defineLabel();
        $endLabel = $parentContext->defineLabel();
        $ec = $parentContext->pushLoop($endLabel, $condLabel);
        if ($this->IsDo) {
            $ec->emitLabel($loopLabel);
            $this->Loop->emit($ec);
            $ec->emitLabel($condLabel);
            $this->Condition->emit($ec);
            $ec->emitCastToBoolean($this->Condition->getEvaluatedCType($ec));
            $ec->emit(OpCode::BranchIfFalse, $endLabel);
            $ec->emit(OpCode::Jump, $condLabel);
        } else {
            $ec->emitLabel($condLabel);
            $this->Condition->emit($ec);
            $ec->emitCastToBoolean($this->Condition->getEvaluatedCType($ec));
            $ec->emit(OpCode::BranchIfFalse, $endLabel);
            $ec->emitLabel($loopLabel);
            $parentContext->beginBlock($this->Loop);
            $this->Loop->emit($ec);
            $ec->emit(OpCode::Jump, $condLabel);
        }
        $ec->emitLabel($endLabel);
    }

    public function getAlwaysReturns(): bool
    {
        return false;
    }

    public function __toString(): string
    {
        return $this->IsDo ? "do " . $this->Loop . " while(" . $this->Condition . ");" : "while (" . $this->Condition . ") " . $this->Loop . ";";
    }

    public function addDeclarationToBlock($context): void
    {
        $this->Loop->addDeclarationToBlock($context);
    }
}

class ForStatement extends Statement
{
    public $InitBlock;
    public $ContinueExpression;
    public $NextExpression;
    public $LoopBody;

    public function __construct($initStatement, $continueExpr, $loopBody, $nextExpr = null)
    {
        $this->InitBlock = new Block(VariableScope::Local);
        if ($initStatement !== null) $this->InitBlock->addStatement($initStatement);
        $this->ContinueExpression = $continueExpr;
        $this->LoopBody = $loopBody;
        if (func_num_args() > 3) $this->NextExpression = $nextExpr;
    }

    public function __toString(): string
    {
        return "for (" . $this->InitBlock . "; " . $this->ContinueExpression . "; " . $this->NextExpression . ") " . $this->LoopBody;
    }

    protected function doEmit($initialContext): void
    {
        $initialContext->beginBlock($this->InitBlock);
        foreach ($this->InitBlock->InitStatements as $s) $s->emit($initialContext);
        foreach ($this->InitBlock->Statements as $s) $s->emit($initialContext);
        $nextLabel = $initialContext->defineLabel();
        $endLabel = $initialContext->defineLabel();
        $ec = $initialContext->pushLoop($endLabel, $nextLabel);
        $conditionLabel = $ec->defineLabel();
        $ec->emitLabel($conditionLabel);
        if ($this->ContinueExpression !== null) {
            $this->ContinueExpression->emit($ec);
            $ec->emitCastToBoolean($this->ContinueExpression->getEvaluatedCType($ec));
            $ec->emit(OpCode::BranchIfFalse, $endLabel);
        }
        $this->LoopBody->emit($ec);
        $ec->emitLabel($nextLabel);
        if ($this->NextExpression !== null) {
            $this->NextExpression->emit($ec);
            $ec->emit(OpCode::Pop);
        }
        $ec->emit(OpCode::Jump, $conditionLabel);
        $ec->emitLabel($endLabel);
        $ec->endBlock();
    }

    public function addDeclarationToBlock($context): void
    {
        $this->InitBlock->addDeclarationToBlock($context);
        $this->LoopBody->addDeclarationToBlock($context);
    }

    public function getAlwaysReturns(): bool
    {
        return false;
    }
}

class SwitchStatement extends Statement
{
    public $Value;
    public $Cases;

    public function __construct($value, array $cases, ?Location $loc = null)
    {
        if ($value === null) throw new \InvalidArgumentException("value");
        if ($cases === null) throw new \InvalidArgumentException("cases");
        $this->Value = $value;
        $this->Cases = $cases;
        $this->Location = $loc ?? Location::$Null;
    }

    protected function doEmit($initialContext): void
    {
        $valueType = $this->Value->getEvaluatedCType($initialContext);
        $this->Value->emit($initialContext);
        if (count($this->Cases) === 0) {
            $initialContext->emit(OpCode::Pop);
            return;
        }
        $caseLabels = [];
        $defaultLabel = null;
        foreach ($this->Cases as $c) {
            $caseLabel = $initialContext->defineLabel();
            $caseLabels[] = $caseLabel;
            if ($c->Value === null) {
                if ($defaultLabel !== null) $initialContext->Report->error(139, "Duplicate default labels in switch");
                $defaultLabel = $caseLabel;
            }
        }
        $endLabel = $initialContext->defineLabel();
        $ec = $initialContext->pushLoop($endLabel, null);
        $ioff = $ec->getInstructionOffset($valueType);
        $eqOp = OpCode::equalToInt8() + $ioff;
        for ($ci = 0; $ci < count($this->Cases); $ci++) {
            $c = $this->Cases[$ci];
            $caseLabel = $caseLabels[$ci];
            if ($c->Value === null) continue;
            $ec->emit(OpCode::Dup);
            $c->Value->emit($ec);
            $ec->emitCast($c->Value->getEvaluatedCType($ec), $valueType);
            $ec->emit($eqOp);
            $ec->emit(OpCode::BranchIfTrue, $caseLabel);
        }
        if ($defaultLabel !== null) {
            $ec->emit(OpCode::Pop);
            $ec->emit(OpCode::Jump, $defaultLabel);
        } else {
            $ec->emit(OpCode::Pop);
            $ec->emit(OpCode::Jump, $endLabel);
        }
        for ($ci = 0; $ci < count($this->Cases); $ci++) {
            $c = $this->Cases[$ci];
            $caseLabel = $caseLabels[$ci];
            $ec->emitLabel($caseLabel);
            foreach ($c->Statements as $s) $s->emit($ec);
        }
        $ec->emitLabel($endLabel);
    }

    public function __toString(): string
    {
        return "switch (" . $this->Value . ") " . implode("", $this->Cases) . ";";
    }

    public function addDeclarationToBlock($context): void
    {
        foreach ($this->Cases as $c) {
            foreach ($c->Statements as $s) $s->addDeclarationToBlock($context);
        }
    }

    public function getAlwaysReturns(): bool
    {
        return false;
    }
}

class SwitchCase
{
    public $Value;
    public $Statements;

    public function __construct($value, array $statements)
    {
        $this->Value = $value;
        $this->Statements = $statements;
    }
}

class ReturnStatement extends Statement
{
    public $ReturnExpression;

    public function __construct($returnExpression = null)
    {
        $this->ReturnExpression = $returnExpression;
    }

    protected function doEmit($ec): void
    {
        $f = $ec->FunctionDecl;
        if ($f === null) {
            $ec->Report->error(1519, "Invalid return outside of function");
            return;
        }
        if ($this->ReturnExpression !== null) {
            if ($f->FunctionType->ReturnType->isVoid()) $ec->Report->error(127, "A return keyword must not be followed by any expression when the function returns void");
            else {
                $this->ReturnExpression->emit($ec);
                $ec->emitCast($this->ReturnExpression->getEvaluatedCType($ec), $f->FunctionType->ReturnType);
                $ec->emit(OpCode::Return);
            }
        } else {
            if ($f->FunctionType->ReturnType->isVoid()) $ec->emit(OpCode::Return);
            else $ec->Report->error(126, "A value is required for the return statement");
        }
    }

    public function addDeclarationToBlock($context): void
    {
    }

    public function getAlwaysReturns(): bool
    {
        return true;
    }
}

class BreakStatement extends Statement
{
    public function __construct()
    {
    }

    public function getAlwaysReturns(): bool
    {
        return false;
    }

    public function addDeclarationToBlock($context): void
    {
    }

    protected function doEmit($ec): void
    {
        if ($ec->BreakLabel !== null) $ec->emit(OpCode::Jump, $ec->BreakLabel);
        else $ec->Report->error(139, "No enclosing statement out of which to break");
    }
}

class ContinueStatement extends Statement
{
    public function __construct()
    {
    }

    public function getAlwaysReturns(): bool
    {
        return false;
    }

    public function addDeclarationToBlock($context): void
    {
    }

    protected function doEmit($ec): void
    {
        if ($ec->ContinueLabel !== null) $ec->emit(OpCode::Jump, $ec->ContinueLabel);
        else $ec->Report->error(139, "No enclosing statement out of which to continue");
    }
}

class ExpressionStatement extends Statement
{
    public $Expression;

    public function __construct($expr)
    {
        $this->Expression = $expr;
    }

    protected function doEmit($ec): void
    {
        if ($this->Expression !== null) {
            $this->Expression->emit($ec);
            $exprType = $this->Expression->getEvaluatedCType($ec);
            if ($exprType instanceof CStructType) {
                $numValues = $exprType->getNumValues();
                for ($i = 0; $i < $numValues; $i++) $ec->emit(OpCode::Pop);
            } else $ec->emit(OpCode::Pop);
        }
    }

    public function __toString(): string
    {
        return $this->Expression . ";";
    }

    public function addDeclarationToBlock($context): void
    {
    }

    public function getAlwaysReturns(): bool
    {
        return false;
    }
}

class EnumeratorStatement extends Statement
{
    public $Name;
    public $LiteralValue;

    public function __construct(string $left, $right = null)
    {
        if ($left === null) throw new \InvalidArgumentException("left");
        $this->Name = $left;
        $this->LiteralValue = $right;
    }

    public function getAlwaysReturns(): bool
    {
        return false;
    }

    public function __toString(): string
    {
        return $this->Name . " = " . $this->LiteralValue;
    }

    protected function doEmit($ec): void
    {
    }

    public function addDeclarationToBlock($context): void
    {
    }
}

class GotoStatement extends Statement
{
    public $Label;

    public function __construct(string $label, ?Location $location = null)
    {
        $this->Label = $label;
        $this->Location = $location ?? Location::$Null;
    }

    public function getAlwaysReturns(): bool
    {
        return false;
    }

    public function addDeclarationToBlock($context): void
    {
    }

    protected function doEmit($ec): void
    {
        $label = $ec->resolveGotoLabel($this->Label);
        if ($label !== null) $ec->emit(OpCode::Jump, $label);
        else $ec->Report->error(9999, "goto statement used outside of function body");
    }
}

class LabeledStatement extends Statement
{
    public $Label;
    public $Statement;

    public function __construct(string $label, $statement, ?Location $location = null)
    {
        $this->Label = $label;
        $this->Statement = $statement;
        $this->Location = $location ?? Location::$Null;
    }

    public function getAlwaysReturns(): bool
    {
        return $this->Statement->getAlwaysReturns();
    }

    public function addDeclarationToBlock($context): void
    {
        $this->Statement->addDeclarationToBlock($context);
    }

    protected function doEmit($ec): void
    {
        $label = $ec->defineGotoLabel($this->Label);
        if ($label !== null) $ec->emitLabel($label);
        $this->Statement->emit($ec);
    }
}

//============================================================================
// Declaration / Declarator
//============================================================================
abstract class Declaration extends Statement
{
    public $Specifiers;
    public $Declarator;
    public $Initializer;

    public function getAlwaysReturns(): bool
    {
        return false;
    }

    public function __construct($specs, $decl, $init)
    {
        $this->Specifiers = $specs;
        $this->Declarator = $decl;
        $this->Initializer = $init;
    }
}

abstract class Declarator
{
    public abstract function getDeclaredIdentifier(): string;

    public $StrongBinding = false;
    public $InnerDeclarator;

    public function __construct(?Declarator $innerDeclarator)
    {
        $this->InnerDeclarator = $innerDeclarator;
    }

    public function __toString(): string
    {
        return $this->getDeclaredIdentifier();
    }
}

class IdentifierDeclarator extends Declarator
{
    public $Identifier;
    public $Context = [];

    public function __construct(string $id)
    {
        parent::__construct(null);
        $this->Identifier = $id;
    }

    public function getDeclaredIdentifier(): string
    {
        return $this->Identifier;
    }

    public function push(string $id): IdentifierDeclarator
    {
        $this->Context[] = $this->Identifier;
        $this->Identifier = $id;
        return $this;
    }

    public function __toString(): string
    {
        return $this->Identifier;
    }
}

class ArrayDeclarator extends Declarator
{
    public $LengthExpression;
    public $TypeQualifiers = TypeQualifiers::None;
    public $LengthIsStatic = false;

    public function __construct(?Declarator $innerDeclarator, $length)
    {
        parent::__construct($innerDeclarator);
        $this->LengthExpression = $length;
    }

    public function getDeclaredIdentifier(): string
    {
        return $this->InnerDeclarator !== null ? $this->InnerDeclarator->getDeclaredIdentifier() : "";
    }
}

class FunctionDeclarator extends Declarator
{
    public $Parameters;

    public function __construct($innerDeclarator, ?array $parameters = null)
    {
        if ($parameters === null) {
            parent::__construct(null);
            $this->Parameters = $innerDeclarator;
        } else {
            parent::__construct($innerDeclarator);
            $this->Parameters = $parameters;
        }
    }

    public function getDeclaredIdentifier(): string
    {
        return $this->InnerDeclarator !== null ? $this->InnerDeclarator->getDeclaredIdentifier() : "";
    }

    public function getCouldBeCtorCall(): bool
    {
        return count($this->Parameters) === 0 || $this->allCtorArgs();
    }

    private function allCtorArgs(): bool
    {
        foreach ($this->Parameters as $x) {
            if ($x->CtorArgumentValue === null) return false;
        }
        return true;
    }

    public function __toString(): string
    {
        return $this->getDeclaredIdentifier() . "(" . implode(", ", $this->Parameters) . ")";
    }
}

class Pointer
{
    public $TypeQualifiers = TypeQualifiers::None;
    public $NextPointer;

    public function __construct($qual, ?Pointer $p = null)
    {
        $this->TypeQualifiers = $qual;
        $this->NextPointer = $p;
    }
}

class PointerDeclarator extends Declarator
{
    public $Pointer;

    public function __construct(Pointer $pointer, Declarator $decl)
    {
        parent::__construct($decl);
        $this->Pointer = $pointer;
    }

    public function getDeclaredIdentifier(): string
    {
        return $this->InnerDeclarator !== null ? $this->InnerDeclarator->getDeclaredIdentifier() : "";
    }
}

class ReferenceDeclarator extends Declarator
{
    public $Qualifiers;

    public function __construct(?Declarator $inner, int $qualifiers = TypeQualifiers::None)
    {
        parent::__construct($inner);
        $this->Qualifiers = $qualifiers;
    }

    public function getDeclaredIdentifier(): string
    {
        return $this->InnerDeclarator !== null ? $this->InnerDeclarator->getDeclaredIdentifier() : "";
    }
}

//============================================================================
// Initializer
//============================================================================
abstract class Initializer
{
    public $Designation;
}

class ExpressionInitializer extends Initializer
{
    public $Expression;

    public function __construct($expr)
    {
        $this->Expression = $expr;
    }

    public function __toString(): string
    {
        return (string)$this->Expression;
    }
}

class StructuredInitializer extends Initializer
{
    public $Initializers = [];

    public function __construct()
    {
        $this->Initializers = [];
    }

    public function add(Initializer $init): void
    {
        $this->Initializers[] = $init;
    }
}

class InitializerDesignation
{
    public $Designators;

    public function __construct(array $des)
    {
        $this->Designators = [];
        foreach ($des as $d) $this->Designators[] = $d;
    }
}

class InitializerDesignator
{
}

//============================================================================
// TypeName, TypeSpecifier, ParameterDeclaration
//============================================================================
class TypeName
{
    public $Specifiers;
    public $Declarator;

    public function __construct($specifiers, ?Declarator $declarator = null)
    {
        $this->Specifiers = $specifiers;
        $this->Declarator = $declarator;
    }

    public function __toString(): string
    {
        return implode(", ", $this->Specifiers->TypeSpecifiers);
    }
}

class TypeSpecifier
{
    public $Kind;
    public $Name;
    public $Body;
    public $BaseSpecifiers;

    public function __construct(int $kind, string $name, ?Block $body = null)
    {
        $this->Kind = $kind;
        $this->Name = $name;
        $this->Body = $body;
    }

    public function __toString(): string
    {
        return $this->Name;
    }
}

class ParameterDeclaration
{
    public $Name;
    public $DeclarationSpecifiers;
    public $Declarator;
    public $DefaultValue;
    public $CtorArgumentValue;

    public function __construct($specsOrName, ?Declarator $dec = null, $defaultValue = null)
    {
        if ($specsOrName instanceof Expression) {
            $this->Name = "";
            $this->CtorArgumentValue = $specsOrName;
        } elseif (is_string($specsOrName)) {
            $this->Name = $specsOrName;
        } elseif ($specsOrName instanceof DeclarationSpecifiers) {
            $this->DeclarationSpecifiers = $specsOrName;
            $this->Name = $dec !== null ? $dec->getDeclaredIdentifier() : "";
            $this->Declarator = $dec;
            $this->DefaultValue = $defaultValue;
        }
    }

    public function __toString(): string
    {
        return $this->DeclarationSpecifiers . " " . $this->Declarator;
    }
}

class VarParameter extends ParameterDeclaration
{
    public function __construct()
    {
        parent::__construct("...");
    }
}

//============================================================================
// FunctionDefinition
//============================================================================
class FunctionDefinition extends Statement
{
    public $Specifiers;
    public $Declarator;
    public $ParameterDeclarations;
    public $Body;

    public function __construct($specifiers, $declarator, ?array $parameterDeclarations, Block $body)
    {
        if ($specifiers === null) throw new \InvalidArgumentException("specifiers");
        if ($declarator === null) throw new \InvalidArgumentException("declarator");
        if ($body === null) throw new \InvalidArgumentException("body");
        $this->Specifiers = $specifiers;
        $this->Declarator = $declarator;
        $this->ParameterDeclarations = $parameterDeclarations;
        $this->Body = $body;
    }

    public function getAlwaysReturns(): bool
    {
        return false;
    }

    public function addDeclarationToBlock($context): void
    {
        $fdef = $this;
        $block = $context->Block;
        $ftype = $context->makeCType($fdef->Specifiers, $fdef->Declarator, null, $block);
        if ($ftype instanceof CFunctionType) {
            $name = $fdef->Declarator->getDeclaredIdentifier();
            $nameContext = "";
            if ($fdef->Declarator->InnerDeclarator instanceof IdentifierDeclarator) $nameContext = implode("::", $fdef->Declarator->InnerDeclarator->Context);
            $f = new CompiledFunction($name, $nameContext, $ftype, $fdef->Body);
            $block->Functions[] = $f;
        }
    }

    protected function doEmit($ec): void
    {
    }
}

//============================================================================
// DeclarationSpecifiers / InitDeclarator / MultiDeclaratorStatement
//============================================================================
class DeclarationSpecifiers
{
    public $StorageClassSpecifier = StorageClassSpecifier::None;
    public $TypeSpecifiers = [];
    public $FunctionSpecifier = FunctionSpecifier::None;
    public $TypeQualifiers = TypeQualifiers::None;

    public function __construct()
    {
        $this->TypeSpecifiers = [];
    }

    public function __toString(): string
    {
        if ($this->StorageClassSpecifier === StorageClassSpecifier::Auto) return "auto";
        $parts = [];
        foreach ($this->TypeSpecifiers as $ts) $parts[] = (string)$ts;
        return implode(" ", $parts);
    }
}

class InitDeclarator
{
    public $Declarator;
    public $Initializer;

    public function __construct($declarator, $initializer = null)
    {
        $this->Declarator = $declarator;
        $this->Initializer = $initializer;
    }

    public function __toString(): string
    {
        return (string)$this->Declarator;
    }
}

class MultiDeclaratorStatement extends Statement
{
    public $Specifiers;
    public $InitDeclarators;

    public function __construct($specifiers, ?array $initDeclarators)
    {
        $this->Specifiers = $specifiers;
        $this->InitDeclarators = $initDeclarators;
    }

    public function getAlwaysReturns(): bool
    {
        return false;
    }

    public function __toString(): string
    {
        $parts = [];
        foreach ($this->Specifiers->TypeSpecifiers as $ts) $parts[] = (string)$ts;
        $result = implode(" ", $parts);
        if ($this->InitDeclarators !== null) {
            $declParts = [];
            foreach ($this->InitDeclarators as $id) $declParts[] = (string)$id;
            $result .= " " . implode(", ", $declParts);
        }
        return $result;
    }

    protected function doEmit($ec): void
    {
        $multi = $this;
        if ($multi->InitDeclarators !== null) {
            foreach ($multi->InitDeclarators as $idecl) {
                if (($multi->Specifiers->StorageClassSpecifier & StorageClassSpecifier::Typedef) !== 0) {
                } else {
                    $ctype = $ec->makeCType($multi->Specifiers, $idecl->Declarator, $idecl->Initializer, null);
                    $name = $idecl->Declarator->getDeclaredIdentifier();
                    if ($ctype instanceof CFunctionType && !self::hasStronglyBoundPointer($idecl->Declarator)) {
                        if ($ctype->ReturnType instanceof CStructType && $idecl->Initializer === null && $idecl->Declarator instanceof FunctionDeclarator && $idecl->Declarator->getCouldBeCtorCall())
                            self::getCtorInitializerStatement($name, $ctype->ReturnType, $idecl->Declarator)->emit($ec);
                    } elseif ($idecl->Initializer !== null) {
                        $varExpr = new VariableExpression($name, Location::$Null, Location::$Null);
                        $initExpr = self::getInitializerExpression($idecl->Initializer);
                        (new ExpressionStatement(new AssignExpression($varExpr, $initExpr)))->emit($ec);
                    }
                }
            }
        }
    }

    public function addDeclarationToBlock($context): void
    {
        $multi = $this;
        $block = $context->Block;
        if ($multi->InitDeclarators !== null) {
            foreach ($multi->InitDeclarators as $idecl) {
                if (($multi->Specifiers->StorageClassSpecifier & StorageClassSpecifier::Typedef) !== 0) {
                    $name = $idecl->Declarator->getDeclaredIdentifier();
                    $block->Typedefs[$name] = $context->makeCType($multi->Specifiers, $idecl->Declarator, $idecl->Initializer, $block);
                } else {
                    $ctype = $context->makeCType($multi->Specifiers, $idecl->Declarator, $idecl->Initializer, $block);
                    $name = $idecl->Declarator->getDeclaredIdentifier();
                    if ($ctype instanceof CFunctionType && !self::hasStronglyBoundPointer($idecl->Declarator)) {
                        if ($ctype->ReturnType instanceof CStructType && $idecl->Initializer === null && $idecl->Declarator instanceof FunctionDeclarator && $idecl->Declarator->getCouldBeCtorCall()) {
                            $found = false;
                            foreach ($block->Variables as $v) {
                                if ($v->Name === $name) {
                                    $found = true;
                                    break;
                                }
                            }
                            if ($found) $context->Report->error(2086, "Redefinition of '{0}'", $name);
                            else {
                                $block->addVariable($name, $ctype->ReturnType);
                                $block->InitStatements[] = self::getCtorInitializerStatement($name, $ctype->ReturnType, $idecl->Declarator);
                            }
                        } else {
                            $nameContext = "";
                            if ($idecl->Declarator->InnerDeclarator instanceof IdentifierDeclarator && count($idecl->Declarator->InnerDeclarator->Context) > 0)
                                $nameContext = implode("::", $idecl->Declarator->InnerDeclarator->Context);
                            $block->Functions[] = new CompiledFunction($name, $nameContext, $ctype, null);
                        }
                    } else {
                        if ($ctype instanceof CArrayType && $ctype->Length === null && $idecl->Initializer !== null) {
                            if ($idecl->Initializer instanceof StructuredInitializer) {
                                $len = 0;
                                foreach ($idecl->Initializer->Initializers as $i) {
                                    if ($i->Designation === null) $len++;
                                    else {
                                        foreach ($i->Designation->Designators as $de) $len++;
                                    }
                                }
                                $ctype = new CArrayType($ctype->ElementType, $len);
                            }
                        }
                        $found = false;
                        foreach ($block->Variables as $v) {
                            if ($v->Name === $name) {
                                $found = true;
                                break;
                            }
                        }
                        if ($found) $context->Report->error(2086, "Redefinition of '{0}'", $name);
                        else $block->addVariable($name, $ctype ?? CBasicType::$SignedInt);
                    }
                    if ($idecl->Initializer !== null) {
                        $varExpr = new VariableExpression($name, Location::$Null, Location::$Null);
                        $initExpr = self::getInitializerExpression($idecl->Initializer);
                        $block->InitStatements[] = new ExpressionStatement(new AssignExpression($varExpr, $initExpr));
                    }
                }
            }
        } else {
            $ctype = $context->makeCType($multi->Specifiers, null, $block);
            if ($ctype instanceof CStructType) {
                $n = $ctype->Name;
                if ($n !== null && $n !== '') $block->Structures[$n] = $ctype;
            } elseif ($ctype instanceof CEnumType) {
                $n = $ctype->Name;
                if ($n === null || $n === '') $n = "e" . spl_object_id($ctype);
                $block->Enums[$n] = $ctype;
            }
        }
    }

    private static function getCtorInitializerStatement(string $name, CStructType $ctorDeclType, FunctionDeclarator $ctorDecl): ExpressionStatement
    {
        $varExpr = new VariableExpression($name, Location::$Null, Location::$Null);
        $memExpr = new MemberFromReferenceExpression($varExpr, $ctorDeclType->Name);
        $args = [];
        foreach ($ctorDecl->Parameters as $p) $args[] = $p->CtorArgumentValue;
        return new ExpressionStatement(new FuncallExpression($memExpr, $args));
    }

    private static function getInitializerExpression($init)
    {
        if ($init instanceof ExpressionInitializer) return $init->Expression;
        elseif ($init instanceof StructuredInitializer) {
            $sexpr = new StructureExpression();
            foreach ($init->Initializers as $i) {
                $e = self::getInitializerExpression($i);
                if ($i->Designation === null || count($i->Designation->Designators) === 0)
                    $sexpr->Items[] = new StructureExpressionItem(null, self::getInitializerExpression($i));
                else {
                    foreach ($i->Designation->Designators as $d) $sexpr->Items[] = new StructureExpressionItem((string)$d, $e);
                }
            }
            return $sexpr;
        } else throw new \RuntimeException((string)$init);
    }

    private static function hasStronglyBoundPointer($d): bool
    {
        if ($d === null) return false;
        if ($d instanceof PointerDeclarator && $d->StrongBinding) return true;
        return self::hasStronglyBoundPointer($d->InnerDeclarator);
    }
}

//============================================================================
// VirtualDeclarationStatement
//============================================================================
class VirtualDeclarationStatement extends Statement
{
    public $InnerDeclaration;
    public $IsVirtual = false;
    public $IsOverride = false;
    public $IsPureVirtual = false;

    public function __construct($innerDeclaration)
    {
        $this->InnerDeclaration = $innerDeclaration;
    }

    public function getAlwaysReturns(): bool
    {
        return false;
    }

    protected function doEmit($ec): void
    {
    }

    public function addDeclarationToBlock($context): void
    {
    }
}

//============================================================================
// VisibilityStatement
//============================================================================
class VisibilityStatement extends Statement
{
    public $Visibility;

    public function __construct(int $visibility)
    {
        $this->Visibility = $visibility;
    }

    public function getAlwaysReturns(): bool
    {
        throw new \RuntimeException("Not implemented");
    }

    protected function doEmit($ec): void
    {
    }

    public function addDeclarationToBlock($context): void
    {
    }
}

//============================================================================
// BaseSpecifier
//============================================================================
class BaseSpecifier
{
    public $Name;
    public $Visibility;

    public function __construct(string $name, ?int $visibility = null)
    {
        $this->Name = $name;
        $this->Visibility = $visibility;
    }

    public function __toString(): string
    {
        if ($this->Visibility !== null) {
            $vis = "";
            switch ($this->Visibility) {
                case DeclarationsVisibility::Public:
                    $vis = "public";
                    break;
                case DeclarationsVisibility::Private:
                    $vis = "private";
                    break;
                case DeclarationsVisibility::Protected:
                    $vis = "protected";
                    break;
            }
            return $vis . " " . $this->Name;
        }
        return $this->Name;
    }
}

//============================================================================
// Overload / ScoredMethod (used by FuncallExpression)
//============================================================================
class FuncallExpression extends Expression
{
    public $Function;
    public $Arguments;

    public function __construct($fun, ?array $args = null)
    {
        $this->Function = $fun;
        $this->Arguments = $args ?? [];
    }

    public function getEvaluatedCType($ec): CType
    {
        $argTypes = [];
        foreach ($this->Arguments as $a) $argTypes[] = $a->getEvaluatedCType($ec);
        $function = $this->resolveOverload($this->Function, $argTypes, $ec);
        $ft = $function->CType;
        return ($ft instanceof CFunctionType) ? $ft->ReturnType : CBasicType::$SignedInt;
    }

    protected function doEmit($ec): void
    {
        $argTypes = [];
        foreach ($this->Arguments as $a) $argTypes[] = $a->getEvaluatedCType($ec);
        $function = $this->resolveOverload($this->Function, $argTypes, $ec);
        $type = $function->CType;
        $numRequiredParameters = 0;
        if ($type instanceof CFunctionType) {
            foreach ($type->Parameters as $p) {
                if ($p->DefaultValue !== null) break;
                $numRequiredParameters++;
            }
            if (count($this->Arguments) < $numRequiredParameters) {
                $ec->Report->error(1501, "'{0}' takes {1} arguments, {2} provided", $this->Function, $numRequiredParameters, count($this->Arguments));
                return;
            }
        } else {
            $ec->Report->error(2064, "'{0}' does not evaluate to a function taking {1} arguments", $this->Function, count($this->Arguments));
            return;
        }

        for ($i = 0; $i < count($this->Arguments); $i++) {
            $paramType = $type->Parameters[$i]->ParameterType;
            if ($paramType instanceof CReferenceType) {
                if ($this->Arguments[$i]->getCanEmitPointer()) $this->Arguments[$i]->emitPointer($ec);
                else {
                    $tempOffset = $ec->allocateTemp($paramType->InnerType);
                    $this->Arguments[$i]->emit($ec);
                    $ec->emitCast($argTypes[$i], $paramType->InnerType);
                    $ec->emit(OpCode::StoreLocal, $tempOffset);
                    $ec->emit(OpCode::LoadConstant, Value::pointer($tempOffset));
                    $ec->emit(OpCode::LoadFramePointer);
                    $ec->emit(OpCode::OffsetPointer);
                }
            } else {
                $this->Arguments[$i]->emit($ec);
                $ec->emitCast($argTypes[$i], $paramType);
            }
        }
        for ($i = count($this->Arguments); $i < count($type->Parameters); $i++) {
            $v = $type->Parameters[$i]->DefaultValue ?? 0;
            $ec->emit(OpCode::LoadConstant, $v);
        }
        $function->emit($ec);
        if ($function->VTableSlotIndex !== null) $ec->emit(OpCode::CallVirtual, $function->VTableSlotIndex);
        else $ec->emit(OpCode::Call, count($type->Parameters));
        if ($type->ReturnType->isVoid()) $ec->emit(OpCode::LoadConstant, 0);
    }

    private function resolveOverload($function, array $argTypes, $ec)
    {
        if ($function instanceof MemberFromReferenceExpression) {
            $targetType = $function->Left->getEvaluatedCType($ec);
            if ($targetType instanceof CStructType) {
                $methods = $targetType->findMethods($function->MemberName);
                if (count($methods) === 0) {
                    $ec->Report->error(1061, "'{1}' not found in '{0}'", $targetType->Name, $function->MemberName);
                    return Overload::error();
                }
                $scoredMethods = [];
                foreach ($methods as $m) {
                    $mt = $m->MemberType;
                    if ($mt instanceof CFunctionType) {
                        $score = $mt->scoreParameterTypeMatches($argTypes);
                        if ($score > 0) $scoredMethods[] = new ScoredMethod($m, $score);
                    }
                }
                usort($scoredMethods, function ($a, $b) {
                    return $b->Score - $a->Score;
                });
                $bestMatch = count($scoredMethods) > 0 ? $scoredMethods[0] : null;
                if ($bestMatch === null) {
                    $ec->Report->error(1503, "'" . $function . "' argument type mismatch");
                    return Overload::error();
                }
                $method = $bestMatch->Method;
                if ($method->VTableSlotIndex !== null && $targetType->VTableGlobalAddress !== null) {
                    return new Overload($method->MemberType, function ($nec) use ($function) {
                        $function->Left->emitPointer($nec);
                    }, $method->VTableSlotIndex);
                } else {
                    $res = $ec->resolveMethodFunction($targetType, $method);
                    if ($res !== null) return new Overload($res->Function !== null ? $res->Function->FunctionType : null, function ($nec) use ($function, $res) {
                        $function->Left->emitPointer($nec);
                        $nec->emit(OpCode::LoadConstant, Value::pointer($res->Address));
                    });
                    return Overload::error();
                }
            } else {
                $ec->Report->error(119, "'" . $function->Left . "' is not valid in the given context");
                return Overload::error();
            }
        } elseif ($function instanceof MemberFromPointerExpression) {
            $targetType = $function->Left->getEvaluatedCType($ec);
            if ($targetType instanceof CPointerType && $targetType->InnerType instanceof CStructType) {
                $structType = $targetType->InnerType;
                $methods = $structType->findMethods($function->MemberName);
                if (count($methods) === 0) {
                    $ec->Report->error(1061, "'{1}' not found in '{0}'", $structType->Name, $function->MemberName);
                    return Overload::error();
                }
                $scoredMethods = [];
                foreach ($methods as $m) {
                    $mt = $m->MemberType;
                    if ($mt instanceof CFunctionType) {
                        $score = $mt->scoreParameterTypeMatches($argTypes);
                        if ($score > 0) $scoredMethods[] = new ScoredMethod($m, $score);
                    }
                }
                usort($scoredMethods, function ($a, $b) {
                    return $b->Score - $a->Score;
                });
                $bestMatch = count($scoredMethods) > 0 ? $scoredMethods[0] : null;
                if ($bestMatch === null) {
                    $ec->Report->error(1503, "'" . $function . "' argument type mismatch");
                    return Overload::error();
                }
                $method = $bestMatch->Method;
                if ($method->VTableSlotIndex !== null && $structType->VTableGlobalAddress !== null)
                    return new Overload($method->MemberType, function ($nec) use ($function) {
                        $function->Left->emit($nec);
                    }, $method->VTableSlotIndex);
                else {
                    $res = $ec->resolveMethodFunction($structType, $method);
                    if ($res !== null) return new Overload($res->Function !== null ? $res->Function->FunctionType : null, function ($nec) use ($function, $res) {
                        $function->Left->emit($nec);
                        $nec->emit(OpCode::LoadConstant, Value::pointer($res->Address));
                    });
                    return Overload::error();
                }
            } else {
                $ec->Report->error(119, "'" . $function->Left . "' is not valid for -> operator");
                return Overload::error();
            }
        } elseif ($function instanceof ScopeResolutionExpression) {
            $res = $ec->tryResolveQualifiedFunction($function->TypeName, $function->MemberName, $argTypes);
            if ($res !== null) return new Overload($res->VariableType, $res->VariableType instanceof CFunctionType ? [$res, 'emit'] : [$res, 'emitPointer']);
            else {
                $ec->Report->error(103, "'" . $function . "' not found");
                return Overload::error();
            }
        } elseif ($function instanceof VariableExpression) {
            $res = $ec->resolveVariable($function, $argTypes);
            if ($res !== null) return new Overload($res->VariableType, $res->VariableType instanceof CFunctionType ? [$res, 'emit'] : [$res, 'emitPointer']);
            return Overload::error();
        } else {
            return new Overload($function !== null ? $function->getEvaluatedCType($ec) : null, $function !== null ? [$function, 'emit'] : Overload::noEmit());
        }
    }

    public function __toString(): string
    {
        $sb = "" . $this->Function;
        $sb .= "(";
        $head = "";
        foreach ($this->Arguments as $a) {
            $sb .= $head;
            $sb .= $a->__toString();
            $head = ", ";
        }
        $sb .= ")";
        return $sb;
    }
}

class ScoredMethod
{
    public $Method;
    public $Score;

    public function __construct($method, int $score)
    {
        $this->Method = $method;
        $this->Score = $score;
    }
}

class Overload
{
    public $CType;
    public $emit;
    public $VTableSlotIndex;

    public function __construct($type = null, $emit = null, $vTableSlotIndex = null)
    {
        $this->CType = $type;
        $this->emit = $emit ?? function ($_) {
        };
        $this->VTableSlotIndex = $vTableSlotIndex;
    }

    public static function noEmit(): callable
    {
        return function ($_) {
        };
    }

    public static function error(): Overload
    {
        return new Overload(CBasicType::$SignedInt, function ($_) {
        });
    }
}
