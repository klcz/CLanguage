<?php

namespace parse;


class ParserInput
{
    public $Tokens;
    public $CurrentToken;
    private $index = -1;
    private $typedefs = [];

    public function __construct(array $tokens)
    {
        $this->Tokens = $tokens;
    }

    public function advance(): bool
    {
        if ($this->index + 1 < count($this->Tokens)) {
            $this->index++;
            $this->tryRegisterStructName();
            $this->CurrentToken = $this->Tokens[$this->index];
            return true;
        }
        return false;
    }

    private function tryRegisterStructName(): void
    {
        $tok = $this->Tokens[$this->index];
        if ($tok->Kind === TokenKind::STRUCT || $tok->Kind === TokenKind::CLASS_ || $tok->Kind === TokenKind::UNION) {
            if ($this->index + 2 < count($this->Tokens)) {
                $nameTok = $this->Tokens[$this->index + 1];
                $afterName = $this->Tokens[$this->index + 2];
                if ($nameTok->Kind === TokenKind::IDENTIFIER &&
                    ($afterName->Kind === ord('{') || $afterName->Kind === ord(':'))) {
                    $this->typedefs[$nameTok->getStringValue()] = true;
                }
            }
        }
    }

    public function token(): int
    {
        return $this->getCurrentToken()->Kind;
    }

    public function value()
    {
        $tok = $this->getCurrentToken();
        return $tok->Value ?? '';
    }

    public function getCurrentToken(): Token
    {
        $tok = $this->Tokens[$this->index];
        if ($tok->Kind === TokenKind::IDENTIFIER && isset($this->typedefs[$tok->getStringValue()])) {
            if ($this->index + 1 < count($this->Tokens) && $this->Tokens[$this->index + 1]->Kind === TokenKind::COLONCOLON) {
                return $tok;
            }
            return $tok->asKind(TokenKind::TYPE_NAME);
        }
        return $tok;
    }

    public function addTypedef(string $declaredIdentifier): void
    {
        $this->typedefs[$declaredIdentifier] = true;
    }
}




class LexedDocument
{
    public $Document;
    public $Tokens;

    public function __construct(Document $document, Report $report)
    {
        $this->Document = $document;
        $tokens = [];
        $lexer = new Lexer($document, $report);
        try {
            while ($lexer->advance()) {
                $tokens[] = $lexer->CurrentToken;
            }
        } catch (\RuntimeException $err) {
            $t = $lexer->CurrentToken;
            $report->error(9000, $t->Location, $t->EndLocation, 'Not Supported: ' . $err->getMessage());
        } catch (\Exception $ex) {
            $t = $lexer->CurrentToken;
            $report->error(9000, $t->Location, $t->EndLocation, 'Internal Error: ' . $ex->getMessage());
        }
        $this->Tokens = $tokens;
    }
}

// ============================================================================
// Dependency stubs (minimal definitions for types used by the type system)
// ============================================================================


class AbstractMessage
{
    public $MessageType = 'Info';
    public $Location;
    public $EndLocation;
    public $IsWarning = false;
    public $Code = 0;
    public $Text = '';

    public function __construct(string $type = 'Info', string $text = '')
    {
        $this->MessageType = $type;
        $this->Text = $text;
        $this->Location = Location::getNull();
        $this->EndLocation = Location::getNull();
    }

    public function getIsError(): bool
    {
        return !$this->IsWarning;
    }

    public function __toString(): string
    {
        if ($this->Location->getIsNull()) {
            return sprintf('%s C%04d: %s', strtolower($this->MessageType), $this->Code, $this->Text);
        }
        return sprintf(
            '%s(%d,%d,%d,%d): %s C%04d: %s',
            $this->Location->Document->Path,
            $this->Location->Line,
            $this->Location->Column,
            $this->EndLocation->Line,
            $this->EndLocation->Column,
            strtolower($this->MessageType),
            $this->Code,
            $this->Text
        );
    }
}

class ErrorMessage extends AbstractMessage
{
    public function __construct(int $code, Location $loc, Location $endLoc, string $error, array $extraInformation)
    {
        parent::__construct('Error', $error);
        $this->Code = $code;
        $this->Location = $loc;
        $this->EndLocation = $endLoc;
        $this->IsWarning = false;
    }
}

class WarningMessage extends AbstractMessage
{
    public function __construct(int $code, Location $loc, Location $endLoc, string $error, array $extraInformation)
    {
        parent::__construct('Warning', $error);
        $this->Code = $code;
        $this->Location = $loc;
        $this->EndLocation = $endLoc;
        $this->IsWarning = true;
    }
}

class Printer
{
    public $WarningsCount = 0;
    public $ErrorsCount = 0;

    public function print(AbstractMessage $msg): void
    {
        if ($msg->IsWarning) {
            $this->WarningsCount++;
        } else {
            $this->ErrorsCount++;
        }
    }
}

class SavedPrinter extends Printer
{
    public $Messages = [];

    public function print(AbstractMessage $msg): void
    {
        parent::print($msg);
        $this->Messages[] = $msg;
    }
}

class TextWriterPrinter extends Printer
{
    private $output;

    public function __construct($output)
    {
        $this->output = $output;
    }

    public function print(AbstractMessage $msg): void
    {
        parent::print($msg);
        if (!$msg->Location->getIsNull()) {
            fwrite($this->output, $msg->Location . ' ');
        }
        fwrite($this->output, sprintf("%s C%04d: %s\n", $msg->MessageType, $msg->Code, $msg->Text));
    }
}

class Report
{
    private $reportingDisabled = 0;
    private $extraInformation = [];
    private $printer;
    private $previousErrors = [];

    public function __construct($printer = null)
    {
        $this->printer = $printer ?? new Printer();
    }

    public function getErrors(): array
    {
        return array_keys($this->previousErrors);
    }

    public function error(int $code, $loc, $endLoc, string $error): void
    {
        if ($this->reportingDisabled > 0) {
            return;
        }
        if (!($loc instanceof Location)) {
            $loc = Location::getNull();
        }
        if (!($endLoc instanceof Location)) {
            $endLoc = Location::getNull();
        }
        $msg = new ErrorMessage($code, $loc, $endLoc, $error, $this->extraInformation);
        $this->extraInformation = [];
        $key = $this->messageKey($msg);
        if (!isset($this->previousErrors[$key])) {
            $this->previousErrors[$key] = true;
            $this->printer->print($msg);
        }
    }

    public function warning(int $code, $loc, $endLoc, string $warning): void
    {
        if ($this->reportingDisabled > 0) {
            return;
        }
        if (!($loc instanceof Location)) {
            $loc = Location::getNull();
        }
        if (!($endLoc instanceof Location)) {
            $endLoc = Location::getNull();
        }
        $msg = new WarningMessage($code, $loc, $endLoc, $warning, $this->extraInformation);
        $this->extraInformation = [];
        $key = $this->messageKey($msg);
        if (!isset($this->previousErrors[$key])) {
            $this->previousErrors[$key] = true;
            $this->printer->print($msg);
        }
    }

    private function messageKey(AbstractMessage $msg): string
    {
        return $msg->Code . '|' . ($msg->Location->getIsNull() ? 'null' : $msg->Location->Line . ',' . $msg->Location->Column) . '|' . ($msg->IsWarning ? '1' : '0') . '|' . $msg->Text;
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
    public ?EmitContext $ParentContext;
    public ?CompiledFunction $FunctionDecl;

    public Report $Report;
    public MachineInfo $MachineInfo;

    public function __construct(MachineInfo $machineInfo, Report $report, ?CompiledFunction $fdecl, ?EmitContext $parentContext)
    {
        $this->ParentContext = $parentContext;
        $this->FunctionDecl = $fdecl;
        $this->Report = $report;
        $this->MachineInfo = $machineInfo;
    }

    public function extend(EmitContext $parentContext): EmitContext
    {
        return new EmitContext($parentContext->MachineInfo, $parentContext->Report, $parentContext->FunctionDecl, $parentContext);
    }
}

class BlockContext extends EmitContext
{
    public Block $Block;

    public function extendBlock(Block $block, EmitContext $parentContext): BlockContext
    {
        return new BlockContext($block, $parentContext->MachineInfo, $parentContext->Report, $parentContext->FunctionDecl, $parentContext);
    }

    public function __construct(Block $block, MachineInfo $machineInfo, Report $report, CompiledFunction $fdecl, EmitContext $parentContext)
    {
        parent::__construct($machineInfo, $report, $fdecl, $parentContext);
        $this->Block = $block;
    }
}

class CompiledVariable
{
    public string $Name;
    public CType $VariableType;
    public int $StackOffset;

    public function __construct(string $name, int $offset, CType $ctype)
    {
        $this->Name = $name;
        $this->VariableType = $ctype;
        $this->StackOffset = $offset;
    }
}

class CompiledFunction
{
    public ?Block $Body;
    public string $Name;
    public string $NameContext;
    public CFunctionType $FunctionType;

    public function __construct(string $name, string $nameContext, CFunctionType $functionType, ?Block $body)
    {
        $this->Name = $name;
        $this->NameContext = $nameContext;
        $this->CFunctionType = $functionType;
        $this->Body = $body;
    }
}


abstract class OpCode
{
    const Nop = 0;
    const Pop = 1;
    const Dup = 2;

    const Jump = 3;
    const BranchIfFalse = 4;
    const BranchIfTrue = 5;
    const Call = 6;
    const CallVirtual = 7;
    const Return = 8;

    const LoadConstant = 9;
    const LoadFramePointer = 10;
    const LoadArg = 11;
    const LoadLocal = 12;
    const LoadGlobal = 13;
    const LoadPointer = 14;
    const StoreArg = 15;
    const StoreLocal = 16;
    const StoreGlobal = 17;
    const StorePointer = 18;

    const OffsetPointer = 19;

    const AddInt8 = 20;
    const AddUInt8 = 21;
    const AddInt16 = 22;
    const AddUInt16 = 23;
    const AddInt32 = 24;
    const AddUInt32 = 25;
    const AddInt64 = 26;
    const AddUInt64 = 27;
    const AddFloat32 = 28;
    const AddFloat64 = 29;

    const SubtractInt8 = 30;
    const SubtractUInt8 = 31;
    const SubtractInt16 = 32;
    const SubtractUInt16 = 33;
    const SubtractInt32 = 34;
    const SubtractUInt32 = 35;
    const SubtractInt64 = 36;
    const SubtractUInt64 = 37;
    const SubtractFloat32 = 38;
    const SubtractFloat64 = 39;

    const MultiplyInt8 = 40;
    const MultiplyUInt8 = 41;
    const MultiplyInt16 = 42;
    const MultiplyUInt16 = 43;
    const MultiplyInt32 = 44;
    const MultiplyUInt32 = 45;
    const MultiplyInt64 = 46;
    const MultiplyUInt64 = 47;
    const MultiplyFloat32 = 48;
    const MultiplyFloat64 = 49;

    const DivideInt8 = 50;
    const DivideUInt8 = 51;
    const DivideInt16 = 52;
    const DivideUInt16 = 53;
    const DivideInt32 = 54;
    const DivideUInt32 = 55;
    const DivideInt64 = 56;
    const DivideUInt64 = 57;
    const DivideFloat32 = 58;
    const DivideFloat64 = 59;

    const ShiftLeftInt8 = 60;
    const ShiftLeftUInt8 = 61;
    const ShiftLeftInt16 = 62;
    const ShiftLeftUInt16 = 63;
    const ShiftLeftInt32 = 64;
    const ShiftLeftUInt32 = 65;
    const ShiftLeftInt64 = 66;
    const ShiftLeftUInt64 = 67;
    const ShiftLeftFloat32 = 68;
    const ShiftLeftFloat64 = 69;

    const ShiftRightInt8 = 70;
    const ShiftRightUInt8 = 71;
    const ShiftRightInt16 = 72;
    const ShiftRightUInt16 = 73;
    const ShiftRightInt32 = 74;
    const ShiftRightUInt32 = 75;
    const ShiftRightInt64 = 76;
    const ShiftRightUInt64 = 77;
    const ShiftRightFloat32 = 78;
    const ShiftRightFloat64 = 79;

    const ModuloInt8 = 80;
    const ModuloUInt8 = 81;
    const ModuloInt16 = 82;
    const ModuloUInt16 = 83;
    const ModuloInt32 = 84;
    const ModuloUInt32 = 85;
    const ModuloInt64 = 86;
    const ModuloUInt64 = 87;
    const ModuloFloat32 = 88;
    const ModuloFloat64 = 89;

    const EqualToInt8 = 90;
    const EqualToUInt8 = 91;
    const EqualToInt16 = 92;
    const EqualToUInt16 = 93;
    const EqualToInt32 = 94;
    const EqualToUInt32 = 95;
    const EqualToInt64 = 96;
    const EqualToUInt64 = 97;
    const EqualToFloat32 = 98;
    const EqualToFloat64 = 99;

    const LessThanInt8 = 100;
    const LessThanUInt8 = 101;
    const LessThanInt16 = 102;
    const LessThanUInt16 = 103;
    const LessThanInt32 = 104;
    const LessThanUInt32 = 105;
    const LessThanInt64 = 106;
    const LessThanUInt64 = 107;
    const LessThanFloat32 = 108;
    const LessThanFloat64 = 109;

    const GreaterThanInt8 = 110;
    const GreaterThanUInt8 = 111;
    const GreaterThanInt16 = 112;
    const GreaterThanUInt16 = 113;
    const GreaterThanInt32 = 114;
    const GreaterThanUInt32 = 115;
    const GreaterThanInt64 = 116;
    const GreaterThanUInt64 = 117;
    const GreaterThanFloat32 = 118;
    const GreaterThanFloat64 = 119;

    const BinaryAndInt8 = 120;
    const BinaryAndUInt8 = 121;
    const BinaryAndInt16 = 122;
    const BinaryAndUInt16 = 123;
    const BinaryAndInt32 = 124;
    const BinaryAndUInt32 = 125;
    const BinaryAndInt64 = 126;
    const BinaryAndUInt64 = 127;
    const BinaryAndFloat32 = 128;
    const BinaryAndFloat64 = 129;

    const BinaryOrInt8 = 130;
    const BinaryOrUInt8 = 131;
    const BinaryOrInt16 = 132;
    const BinaryOrUInt16 = 133;
    const BinaryOrInt32 = 134;
    const BinaryOrUInt32 = 135;
    const BinaryOrInt64 = 136;
    const BinaryOrUInt64 = 137;
    const BinaryOrFloat32 = 138;
    const BinaryOrFloat64 = 139;

    const BinaryXorInt8 = 140;
    const BinaryXorUInt8 = 141;
    const BinaryXorInt16 = 142;
    const BinaryXorUInt16 = 143;
    const BinaryXorInt32 = 144;
    const BinaryXorUInt32 = 145;
    const BinaryXorInt64 = 146;
    const BinaryXorUInt64 = 147;
    const BinaryXorFloat32 = 148;
    const BinaryXorFloat64 = 149;

    const NotInt8 = 150;
    const NotUInt8 = 151;
    const NotInt16 = 152;
    const NotUInt16 = 153;
    const NotInt32 = 154;
    const NotUInt32 = 155;
    const NotInt64 = 156;
    const NotUInt64 = 157;
    const NotFloat32 = 158;
    const NotFloat64 = 159;

    const BinaryNotInt8 = 160;
    const BinaryNotUInt8 = 161;
    const BinaryNotInt16 = 162;
    const BinaryNotUInt16 = 163;
    const BinaryNotInt32 = 164;
    const BinaryNotUInt32 = 165;
    const BinaryNotInt64 = 166;
    const BinaryNotUInt64 = 167;
    const BinaryNotFloat32 = 168;
    const BinaryNotFloat64 = 169;

    const NegateInt8 = 170;
    const NegateUInt8 = 171;
    const NegateInt16 = 172;
    const NegateUInt16 = 173;
    const NegateInt32 = 174;
    const NegateUInt32 = 175;
    const NegateInt64 = 176;
    const NegateUInt64 = 177;
    const NegateFloat32 = 178;
    const NegateFloat64 = 179;

    const ConvertInt8Int8 = 180;
    const ConvertInt8UInt8 = 181;
    const ConvertInt8Int16 = 182;
    const ConvertInt8UInt16 = 183;
    const ConvertInt8Int32 = 184;
    const ConvertInt8UInt32 = 185;
    const ConvertInt8Int64 = 186;
    const ConvertInt8UInt64 = 187;
    const ConvertInt8Float32 = 188;
    const ConvertInt8Float64 = 189;

    const ConvertUInt8Int8 = 190;
    const ConvertUInt8UInt8 = 191;
    const ConvertUInt8Int16 = 192;
    const ConvertUInt8UInt16 = 193;
    const ConvertUInt8Int32 = 194;
    const ConvertUInt8UInt32 = 195;
    const ConvertUInt8Int64 = 196;
    const ConvertUInt8UInt64 = 197;
    const ConvertUInt8Float32 = 198;
    const ConvertUInt8Float64 = 199;

    const ConvertInt16Int8 = 200;
    const ConvertInt16UInt8 = 201;
    const ConvertInt16Int16 = 202;
    const ConvertInt16UInt16 = 203;
    const ConvertInt16Int32 = 204;
    const ConvertInt16UInt32 = 205;
    const ConvertInt16Int64 = 206;
    const ConvertInt16UInt64 = 207;
    const ConvertInt16Float32 = 208;
    const ConvertInt16Float64 = 209;

    const ConvertUInt16Int8 = 210;
    const ConvertUInt16UInt8 = 211;
    const ConvertUInt16Int16 = 212;
    const ConvertUInt16UInt16 = 213;
    const ConvertUInt16Int32 = 214;
    const ConvertUInt16UInt32 = 215;
    const ConvertUInt16Int64 = 216;
    const ConvertUInt16UInt64 = 217;
    const ConvertUInt16Float32 = 218;
    const ConvertUInt16Float64 = 219;

    const ConvertInt32Int8 = 220;
    const ConvertInt32UInt8 = 221;
    const ConvertInt32Int16 = 222;
    const ConvertInt32UInt16 = 223;
    const ConvertInt32Int32 = 224;
    const ConvertInt32UInt32 = 225;
    const ConvertInt32Int64 = 226;
    const ConvertInt32UInt64 = 227;
    const ConvertInt32Float32 = 228;
    const ConvertInt32Float64 = 229;

    const ConvertUInt32Int8 = 230;
    const ConvertUInt32UInt8 = 231;
    const ConvertUInt32Int16 = 232;
    const ConvertUInt32UInt16 = 233;
    const ConvertUInt32Int32 = 234;
    const ConvertUInt32UInt32 = 235;
    const ConvertUInt32Int64 = 236;
    const ConvertUInt32UInt64 = 237;
    const ConvertUInt32Float32 = 238;
    const ConvertUInt32Float64 = 239;

    const ConvertInt64Int8 = 240;
    const ConvertInt64UInt8 = 241;
    const ConvertInt64Int16 = 242;
    const ConvertInt64UInt16 = 243;
    const ConvertInt64Int32 = 244;
    const ConvertInt64UInt32 = 245;
    const ConvertInt64Int64 = 246;
    const ConvertInt64UInt64 = 247;
    const ConvertInt64Float32 = 248;
    const ConvertInt64Float64 = 249;

    const ConvertUInt64Int8 = 250;
    const ConvertUInt64UInt8 = 251;
    const ConvertUInt64Int16 = 252;
    const ConvertUInt64UInt16 = 253;
    const ConvertUInt64Int32 = 254;
    const ConvertUInt64UInt32 = 255;
    const ConvertUInt64Int64 = 256;
    const ConvertUInt64UInt64 = 257;
    const ConvertUInt64Float32 = 258;
    const ConvertUInt64Float64 = 259;

    const ConvertFloat32Int8 = 260;
    const ConvertFloat32UInt8 = 261;
    const ConvertFloat32Int16 = 262;
    const ConvertFloat32UInt16 = 263;
    const ConvertFloat32Int32 = 264;
    const ConvertFloat32UInt32 = 265;
    const ConvertFloat32Int64 = 266;
    const ConvertFloat32UInt64 = 267;
    const ConvertFloat32Float32 = 268;
    const ConvertFloat32Float64 = 269;

    const ConvertFloat64Int8 = 270;
    const ConvertFloat64UInt8 = 271;
    const ConvertFloat64Int16 = 272;
    const ConvertFloat64UInt16 = 273;
    const ConvertFloat64Int32 = 274;
    const ConvertFloat64UInt32 = 275;
    const ConvertFloat64Int64 = 276;
    const ConvertFloat64UInt64 = 277;
    const ConvertFloat64Float32 = 278;
    const ConvertFloat64Float64 = 279;

    const ConvertPointerInt8 = 280;
    const ConvertPointerUInt8 = 281;
    const ConvertPointerInt16 = 282;
    const ConvertPointerUInt16 = 283;
    const ConvertPointerInt32 = 284;
    const ConvertPointerUInt32 = 285;
    const ConvertPointerInt64 = 286;
    const ConvertPointerUInt64 = 287;
    const ConvertPointerFloat32 = 288;
    const ConvertPointerFloat64 = 289;
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

    public static $ConstChar;
    public static $UnsignedChar;
    public static $SignedChar;
    public static $UnsignedShortInt;
    public static $SignedShortInt;
    public static $UnsignedInt;
    public static $SignedInt;
    public static $UnsignedLongInt;
    public static $SignedLongInt;
    public static $UnsignedLongLongInt;
    public static $SignedLongLongInt;
    public static $Float;
    public static $Double;
    public static $Bool;
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
                case 1:
                    return $values[0]->int8Value;
                case 2:
                    return $values[0]->int16Value;
                case 4:
                    return $values[0]->int32Value;
                default:
                    return $values[0]->int64Value;
            }
        } else {
            switch ($byteSize) {
                case 1:
                    return $values[0]->uint8Value;
                case 2:
                    return $values[0]->uint16Value;
                case 4:
                    return $values[0]->uint32Value;
                default:
                    return $values[0]->uint64Value;
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
