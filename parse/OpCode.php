<?php
namespace parse;

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
