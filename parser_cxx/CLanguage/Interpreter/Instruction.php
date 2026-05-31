<?php declare(strict_types=1);

namespace CLanguage\Interpreter;

use CLanguage\Value;

class Label
{
    public int $index = 0;

    public function __toString(): string
    {
        return "L_" . $this->index;
    }
}

class Instruction
{
    public OpCode $op;
    public Value $x;
    public ?Label $label = null;

    public function __construct(OpCode $op, Value|Label $val)
    {
        $this->op = $op;
        if ($val instanceof Label) {
            $this->label = $val;
        } else {
            $this->x = $val;
        }
    }

    public function __toString(): string
    {
        if ($this->label !== null) {
            return "{$this->op->name} {$this->label}";
        } else {
            return "{$this->op->name} {$this->x}";
        }
    }
}

enum OpCode: int
{
    case Nop = 0;
    case Pop = 1;
    case Dup = 2;

    #region Control

    case Jump = 3;
    case BranchIfFalse = 4;
    case BranchIfTrue = 5;

    case Call = 6;
    case CallVirtual = 7;
    case Return = 8;

    #endregion

    #region Memory

    case LoadConstant = 9;
    case LoadFramePointer = 10;

    case LoadArg = 11;
    case LoadLocal = 12;
    case LoadGlobal = 13;

    case LoadPointer = 14;

    case StoreArg = 15;
    case StoreLocal = 16;
    case StoreGlobal = 17;

    case StorePointer = 18;

    #endregion

    #region Arithmetic

    case OffsetPointer = 19;

    case AddInt8 = 20;
    case AddUInt8 = 21;
    case AddInt16 = 22;
    case AddUInt16 = 23;
    case AddInt32 = 24;
    case AddUInt32 = 25;
    case AddInt64 = 26;
    case AddUInt64 = 27;
    case AddFloat32 = 28;
    case AddFloat64 = 29;

    case SubtractInt8 = 30;
    case SubtractUInt8 = 31;
    case SubtractInt16 = 32;
    case SubtractUInt16 = 33;
    case SubtractInt32 = 34;
    case SubtractUInt32 = 35;
    case SubtractInt64 = 36;
    case SubtractUInt64 = 37;
    case SubtractFloat32 = 38;
    case SubtractFloat64 = 39;

    case MultiplyInt8 = 40;
    case MultiplyUInt8 = 41;
    case MultiplyInt16 = 42;
    case MultiplyUInt16 = 43;
    case MultiplyInt32 = 44;
    case MultiplyUInt32 = 45;
    case MultiplyInt64 = 46;
    case MultiplyUInt64 = 47;
    case MultiplyFloat32 = 48;
    case MultiplyFloat64 = 49;

    case DivideInt8 = 50;
    case DivideUInt8 = 51;
    case DivideInt16 = 52;
    case DivideUInt16 = 53;
    case DivideInt32 = 54;
    case DivideUInt32 = 55;
    case DivideInt64 = 56;
    case DivideUInt64 = 57;
    case DivideFloat32 = 58;
    case DivideFloat64 = 59;

    case ShiftLeftInt8 = 60;
    case ShiftLeftUInt8 = 61;
    case ShiftLeftInt16 = 62;
    case ShiftLeftUInt16 = 63;
    case ShiftLeftInt32 = 64;
    case ShiftLeftUInt32 = 65;
    case ShiftLeftInt64 = 66;
    case ShiftLeftUInt64 = 67;
    case ShiftLeftFloat32 = 68;
    case ShiftLeftFloat64 = 69;

    case ShiftRightInt8 = 70;
    case ShiftRightUInt8 = 71;
    case ShiftRightInt16 = 72;
    case ShiftRightUInt16 = 73;
    case ShiftRightInt32 = 74;
    case ShiftRightUInt32 = 75;
    case ShiftRightInt64 = 76;
    case ShiftRightUInt64 = 77;
    case ShiftRightFloat32 = 78;
    case ShiftRightFloat64 = 79;

    case ModuloInt8 = 80;
    case ModuloUInt8 = 81;
    case ModuloInt16 = 82;
    case ModuloUInt16 = 83;
    case ModuloInt32 = 84;
    case ModuloUInt32 = 85;
    case ModuloInt64 = 86;
    case ModuloUInt64 = 87;
    case ModuloFloat32 = 88;
    case ModuloFloat64 = 89;

    #endregion

    #region Relational

    case EqualToInt8 = 90;
    case EqualToUInt8 = 91;
    case EqualToInt16 = 92;
    case EqualToUInt16 = 93;
    case EqualToInt32 = 94;
    case EqualToUInt32 = 95;
    case EqualToInt64 = 96;
    case EqualToUInt64 = 97;
    case EqualToFloat32 = 98;
    case EqualToFloat64 = 99;

    case LessThanInt8 = 100;
    case LessThanUInt8 = 101;
    case LessThanInt16 = 102;
    case LessThanUInt16 = 103;
    case LessThanInt32 = 104;
    case LessThanUInt32 = 105;
    case LessThanInt64 = 106;
    case LessThanUInt64 = 107;
    case LessThanFloat32 = 108;
    case LessThanFloat64 = 109;

    case GreaterThanInt8 = 110;
    case GreaterThanUInt8 = 111;
    case GreaterThanInt16 = 112;
    case GreaterThanUInt16 = 113;
    case GreaterThanInt32 = 114;
    case GreaterThanUInt32 = 115;
    case GreaterThanInt64 = 116;
    case GreaterThanUInt64 = 117;
    case GreaterThanFloat32 = 118;
    case GreaterThanFloat64 = 119;

    #endregion

    #region Bitwise

    case BinaryAndInt8 = 120;
    case BinaryAndUInt8 = 121;
    case BinaryAndInt16 = 122;
    case BinaryAndUInt16 = 123;
    case BinaryAndInt32 = 124;
    case BinaryAndUInt32 = 125;
    case BinaryAndInt64 = 126;
    case BinaryAndUInt64 = 127;
    case BinaryAndFloat32 = 128;
    case BinaryAndFloat64 = 129;

    case BinaryOrInt8 = 130;
    case BinaryOrUInt8 = 131;
    case BinaryOrInt16 = 132;
    case BinaryOrUInt16 = 133;
    case BinaryOrInt32 = 134;
    case BinaryOrUInt32 = 135;
    case BinaryOrInt64 = 136;
    case BinaryOrUInt64 = 137;
    case BinaryOrFloat32 = 138;
    case BinaryOrFloat64 = 139;

    case BinaryXorInt8 = 140;
    case BinaryXorUInt8 = 141;
    case BinaryXorInt16 = 142;
    case BinaryXorUInt16 = 143;
    case BinaryXorInt32 = 144;
    case BinaryXorUInt32 = 145;
    case BinaryXorInt64 = 146;
    case BinaryXorUInt64 = 147;
    case BinaryXorFloat32 = 148;
    case BinaryXorFloat64 = 149;

    #endregion

    #region Unary

    case NotInt8 = 150;
    case NotUInt8 = 151;
    case NotInt16 = 152;
    case NotUInt16 = 153;
    case NotInt32 = 154;
    case NotUInt32 = 155;
    case NotInt64 = 156;
    case NotUInt64 = 157;
    case NotFloat32 = 158;
    case NotFloat64 = 159;

    case BinaryNotInt8 = 160;
    case BinaryNotUInt8 = 161;
    case BinaryNotInt16 = 162;
    case BinaryNotUInt16 = 163;
    case BinaryNotInt32 = 164;
    case BinaryNotUInt32 = 165;
    case BinaryNotInt64 = 166;
    case BinaryNotUInt64 = 167;
    case BinaryNotFloat32 = 168;
    case BinaryNotFloat64 = 169;

    case NegateInt8 = 170;
    case NegateUInt8 = 171;
    case NegateInt16 = 172;
    case NegateUInt16 = 173;
    case NegateInt32 = 174;
    case NegateUInt32 = 175;
    case NegateInt64 = 176;
    case NegateUInt64 = 177;
    case NegateFloat32 = 178;
    case NegateFloat64 = 179;

    #endregion

    #region Conversion

    case ConvertInt8Int8 = 180;
    case ConvertInt8UInt8 = 181;
    case ConvertInt8Int16 = 182;
    case ConvertInt8UInt16 = 183;
    case ConvertInt8Int32 = 184;
    case ConvertInt8UInt32 = 185;
    case ConvertInt8Int64 = 186;
    case ConvertInt8UInt64 = 187;
    case ConvertInt8Float32 = 188;
    case ConvertInt8Float64 = 189;

    case ConvertUInt8Int8 = 190;
    case ConvertUInt8UInt8 = 191;
    case ConvertUInt8Int16 = 192;
    case ConvertUInt8UInt16 = 193;
    case ConvertUInt8Int32 = 194;
    case ConvertUInt8UInt32 = 195;
    case ConvertUInt8Int64 = 196;
    case ConvertUInt8UInt64 = 197;
    case ConvertUInt8Float32 = 198;
    case ConvertUInt8Float64 = 199;

    case ConvertInt16Int8 = 200;
    case ConvertInt16UInt8 = 201;
    case ConvertInt16Int16 = 202;
    case ConvertInt16UInt16 = 203;
    case ConvertInt16Int32 = 204;
    case ConvertInt16UInt32 = 205;
    case ConvertInt16Int64 = 206;
    case ConvertInt16UInt64 = 207;
    case ConvertInt16Float32 = 208;
    case ConvertInt16Float64 = 209;

    case ConvertUInt16Int8 = 210;
    case ConvertUInt16UInt8 = 211;
    case ConvertUInt16Int16 = 212;
    case ConvertUInt16UInt16 = 213;
    case ConvertUInt16Int32 = 214;
    case ConvertUInt16UInt32 = 215;
    case ConvertUInt16Int64 = 216;
    case ConvertUInt16UInt64 = 217;
    case ConvertUInt16Float32 = 218;
    case ConvertUInt16Float64 = 219;

    case ConvertInt32Int8 = 220;
    case ConvertInt32UInt8 = 221;
    case ConvertInt32Int16 = 222;
    case ConvertInt32UInt16 = 223;
    case ConvertInt32Int32 = 224;
    case ConvertInt32UInt32 = 225;
    case ConvertInt32Int64 = 226;
    case ConvertInt32UInt64 = 227;
    case ConvertInt32Float32 = 228;
    case ConvertInt32Float64 = 229;

    case ConvertUInt32Int8 = 230;
    case ConvertUInt32UInt8 = 231;
    case ConvertUInt32Int16 = 232;
    case ConvertUInt32UInt16 = 233;
    case ConvertUInt32Int32 = 234;
    case ConvertUInt32UInt32 = 235;
    case ConvertUInt32Int64 = 236;
    case ConvertUInt32UInt64 = 237;
    case ConvertUInt32Float32 = 238;
    case ConvertUInt32Float64 = 239;

    case ConvertInt64Int8 = 240;
    case ConvertInt64UInt8 = 241;
    case ConvertInt64Int16 = 242;
    case ConvertInt64UInt16 = 243;
    case ConvertInt64Int32 = 244;
    case ConvertInt64UInt32 = 245;
    case ConvertInt64Int64 = 246;
    case ConvertInt64UInt64 = 247;
    case ConvertInt64Float32 = 248;
    case ConvertInt64Float64 = 249;

    case ConvertUInt64Int8 = 250;
    case ConvertUInt64UInt8 = 251;
    case ConvertUInt64Int16 = 252;
    case ConvertUInt64UInt16 = 253;
    case ConvertUInt64Int32 = 254;
    case ConvertUInt64UInt32 = 255;
    case ConvertUInt64Int64 = 256;
    case ConvertUInt64UInt64 = 257;
    case ConvertUInt64Float32 = 258;
    case ConvertUInt64Float64 = 259;

    case ConvertFloat32Int8 = 260;
    case ConvertFloat32UInt8 = 261;
    case ConvertFloat32Int16 = 262;
    case ConvertFloat32UInt16 = 263;
    case ConvertFloat32Int32 = 264;
    case ConvertFloat32UInt32 = 265;
    case ConvertFloat32Int64 = 266;
    case ConvertFloat32UInt64 = 267;
    case ConvertFloat32Float32 = 268;
    case ConvertFloat32Float64 = 269;

    case ConvertFloat64Int8 = 270;
    case ConvertFloat64UInt8 = 271;
    case ConvertFloat64Int16 = 272;
    case ConvertFloat64UInt16 = 273;
    case ConvertFloat64Int32 = 274;
    case ConvertFloat64UInt32 = 275;
    case ConvertFloat64Int64 = 276;
    case ConvertFloat64UInt64 = 277;
    case ConvertFloat64Float32 = 278;
    case ConvertFloat64Float64 = 279;

    case ConvertPointerInt8 = 280;
    case ConvertPointerUInt8 = 281;
    case ConvertPointerInt16 = 282;
    case ConvertPointerUInt16 = 283;
    case ConvertPointerInt32 = 284;
    case ConvertPointerUInt32 = 285;
    case ConvertPointerInt64 = 286;
    case ConvertPointerUInt64 = 287;
    case ConvertPointerFloat32 = 288;
    case ConvertPointerFloat64 = 289;

    #endregion
}
