package cxxParser

import (
	"fmt"
)

func MapF[V BaseFunction](m []BaseFunction, action func(V)) bool {
	for _, obj := range m {
		if v, ok := obj.(V); ok {
			action(v)
		}
	}
	return true
}

type OpFuncT1 = func(*Value) Value
type OpFuncT2 = func(*Value, *Value) Value
type OpFuncItem[T OpFuncT1 | OpFuncT2] struct {
	base  OpCode
	funcs [10]T
}

func OpFunc1[T OpFuncT1](state *CInterpreter, opf *OpFuncItem[T], op OpCode) {
	a := &state.Stack[state.SP-1]
	state.Stack[state.SP-1] = (opf.funcs[(op-opf.base)%10])(a)
}

func OpFunc2[T OpFuncT2](state *CInterpreter, opf *OpFuncItem[T], op OpCode) {
	a, b := &state.Stack[state.SP-2], &state.Stack[state.SP-1]
	state.Stack[state.SP-2] = (opf.funcs[(op-opf.base)%10])(a, b)
	state.SP--
}

var _OpCodeNot = OpFuncItem[OpFuncT1]{
	OpCodeNotInt8, [10]OpFuncT1{
		func(a *Value) Value {
			return boolToValue(int8(a.Int64Value) == 0)
		},
		func(a *Value) Value {
			return boolToValue(uint8(a.Int64Value) == 0)
		},
		func(a *Value) Value {
			return boolToValue(int16(a.Int64Value) == 0)
		},
		func(a *Value) Value {
			return boolToValue(uint16(a.Int64Value) == 0)
		},
		func(a *Value) Value {
			return boolToValue(a.Int32Value() == 0)
		},
		func(a *Value) Value {
			return boolToValue(uint32(a.Int64Value) == 0)
		},
		func(a *Value) Value {
			return boolToValue(a.Int64Value == 0)
		},
		func(a *Value) Value {
			return boolToValue(uint64(a.Int64Value) == 0)
		},
		func(a *Value) Value {
			return boolToValue(int64(a.Float32Value()) == 0)
		},
		func(a *Value) Value {
			return boolToValue(int64(a.Float64Value()) == 0)
		},
	},
}

var _OpCodeBinaryNot = OpFuncItem[OpFuncT1]{
	OpCodeBinaryNotInt8, [10]OpFuncT1{
		func(a *Value) Value {
			return ValueOf(^int8(a.Int64Value))
		},
		func(a *Value) Value {
			return ValueOf(^uint8(a.Int64Value))
		},
		func(a *Value) Value {
			return ValueOf(^int16(a.Int64Value))
		},
		func(a *Value) Value {
			return ValueOf(^uint16(a.Int64Value))
		},
		func(a *Value) Value {
			return ValueOf(^a.Int32Value())
		},
		func(a *Value) Value {
			return ValueOf(^uint32(a.Int64Value))
		},
		func(a *Value) Value {
			return ValueOf(^a.Int64Value)
		},
		func(a *Value) Value {
			return ValueOf(^uint64(a.Int64Value))
		},
		func(a *Value) Value {
			return ValueOf(^int64(a.Float32Value()))
		},
		func(a *Value) Value {
			return ValueOf(^int64(a.Float64Value()))
		},
	},
}

var _OpCodeNegate = OpFuncItem[OpFuncT1]{
	OpCodeNegateInt8, [10]OpFuncT1{
		func(a *Value) Value {
			return ValueOf(-int8(a.Int64Value))
		},
		func(a *Value) Value {
			return ValueOf(-uint8(a.Int64Value))
		},
		func(a *Value) Value {
			return ValueOf(-int16(a.Int64Value))
		},
		func(a *Value) Value {
			return ValueOf(-uint16(a.Int64Value))
		},
		func(a *Value) Value {
			return ValueOf(-a.Int32Value())
		},
		func(a *Value) Value {
			return ValueOf(-uint32(a.Int64Value))
		},
		func(a *Value) Value {
			return ValueOf(-a.Int64Value)
		},
		func(a *Value) Value {
			return ValueOf(-uint64(a.Int64Value))
		}, func(a *Value) Value {
			return ValueOf(-a.Float32Value())
		},
		func(a *Value) Value {
			return ValueOf(-a.Float64Value())
		},
	},
}

//goland:noinspection GoUnusedGlobalVariable
var OpFuncV1 = struct {
	OpCodeNot       OpFuncItem[OpFuncT1]
	OpCodeBinaryNot OpFuncItem[OpFuncT1]
	OpCodeNegate    OpFuncItem[OpFuncT1]
}{
	OpCodeNot:       _OpCodeNot,
	OpCodeBinaryNot: _OpCodeBinaryNot,
	OpCodeNegate:    _OpCodeNegate,
}

var _OpCodeAdd = OpFuncItem[OpFuncT2]{
	OpCodeAddInt8, [10]OpFuncT2{
		func(a, b *Value) Value {
			return ValueOf(int8(a.Int64Value) + int8(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint8(a.Int64Value) + uint8(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(int16(a.Int64Value) + int16(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint16(a.Int64Value) + uint16(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int32Value() + b.Int32Value())
		},
		func(a, b *Value) Value {
			return ValueOf(uint32(a.Int64Value) + uint32(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int64Value + b.Int64Value)
		},
		func(a, b *Value) Value {
			return ValueOf(uint64(a.Int64Value) + uint64(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Float32Value() + b.Float32Value())
		},
		func(a, b *Value) Value {
			return ValueOf(a.Float64Value() + b.Float64Value())
		},
	},
}

var _OpCodeSubtract = OpFuncItem[OpFuncT2]{
	OpCodeSubtractInt8, [10]OpFuncT2{
		func(a, b *Value) Value {
			return ValueOf(int8(a.Int64Value) - int8(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint8(a.Int64Value) - uint8(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(int16(a.Int64Value) - int16(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint16(a.Int64Value) - uint16(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int32Value() - b.Int32Value())
		},
		func(a, b *Value) Value {
			return ValueOf(uint32(a.Int64Value) - uint32(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int64Value - b.Int64Value)
		},
		func(a, b *Value) Value {
			return ValueOf(uint64(a.Int64Value) - uint64(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Float32Value() - b.Float32Value())
		},
		func(a, b *Value) Value {
			return ValueOf(a.Float64Value() - b.Float64Value())
		},
	},
}

var _OpCodeMultiply = OpFuncItem[OpFuncT2]{
	OpCodeMultiplyInt8, [10]OpFuncT2{
		func(a, b *Value) Value {
			return ValueOf(int8(a.Int64Value) * int8(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint8(a.Int64Value) * uint8(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(int16(a.Int64Value) * int16(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint16(a.Int64Value) * uint16(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int32Value() * b.Int32Value())
		},
		func(a, b *Value) Value {
			return ValueOf(uint32(a.Int64Value) * uint32(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int64Value * b.Int64Value)
		},
		func(a, b *Value) Value {
			return ValueOf(uint64(a.Int64Value) * uint64(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Float32Value() * b.Float32Value())
		},
		func(a, b *Value) Value {
			return ValueOf(a.Float64Value() * b.Float64Value())
		},
	},
}

var _OpCodeDivide = OpFuncItem[OpFuncT2]{
	OpCodeDivideInt8, [10]OpFuncT2{
		func(a, b *Value) Value {
			return ValueOf(int8(a.Int64Value) / int8(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint8(a.Int64Value) / uint8(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(int16(a.Int64Value) / int16(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint16(a.Int64Value) / uint16(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int32Value() / b.Int32Value())
		},
		func(a, b *Value) Value {
			return ValueOf(uint32(a.Int64Value) / uint32(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int64Value / b.Int64Value)
		},
		func(a, b *Value) Value {
			return ValueOf(uint64(a.Int64Value) / uint64(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Float32Value() / b.Float32Value())
		},
		func(a, b *Value) Value {
			return ValueOf(a.Float64Value() / b.Float64Value())
		},
	},
}

var _OpCodeModulo = OpFuncItem[OpFuncT2]{
	OpCodeModuloInt8, [10]OpFuncT2{
		func(a, b *Value) Value {
			return ValueOf(int8(a.Int64Value) % int8(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint8(a.Int64Value) % uint8(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(int16(a.Int64Value) % int16(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint16(a.Int64Value) % uint16(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int32Value() % b.Int32Value())
		},
		func(a, b *Value) Value {
			return ValueOf(uint32(a.Int64Value) % uint32(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int64Value % b.Int64Value)
		},
		func(a, b *Value) Value {
			return ValueOf(uint64(a.Int64Value) % uint64(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(int64(a.Float32Value()) % int64(b.Float32Value()))
		},
		func(a, b *Value) Value {
			return ValueOf(int64(a.Float64Value()) % int64(b.Float64Value()))
		},
	},
}

var _OpCodeShiftLeft = OpFuncItem[OpFuncT2]{
	OpCodeShiftLeftInt8, [10]OpFuncT2{
		func(a, b *Value) Value {
			return ValueOf(int8(a.Int64Value) << uint(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint8(a.Int64Value) << uint(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(int16(a.Int64Value) << uint(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint16(a.Int64Value) << uint(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int32Value() << uint(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint32(a.Int64Value) << uint(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int64Value << uint(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint64(a.Int64Value) << uint(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(int64(a.Float32Value()) << uint(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(int64(a.Float64Value()) << uint(b.Int64Value))
		},
	},
}

var _OpCodeShiftRight = OpFuncItem[OpFuncT2]{
	OpCodeShiftRightInt8, [10]OpFuncT2{
		func(a, b *Value) Value {
			return ValueOf(int8(a.Int64Value) >> uint(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint8(a.Int64Value) >> uint(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(int16(a.Int64Value) >> uint(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint16(a.Int64Value) >> uint(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int32Value() >> uint(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint32(a.Int64Value) >> uint(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int64Value >> uint(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint64(a.Int64Value) >> uint(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(int64(a.Float32Value()) >> uint(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(int64(a.Float64Value()) >> uint(b.Int64Value))
		},
	},
}

var _OpCodeBinaryAnd = OpFuncItem[OpFuncT2]{
	OpCodeBinaryAndInt8, [10]OpFuncT2{
		func(a, b *Value) Value {
			return ValueOf(int8(a.Int64Value) & int8(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint8(a.Int64Value) & uint8(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(int16(a.Int64Value) & int16(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint16(a.Int64Value) & uint16(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int32Value() & b.Int32Value())
		},
		func(a, b *Value) Value {
			return ValueOf(uint32(a.Int64Value) & uint32(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int64Value & b.Int64Value)
		},
		func(a, b *Value) Value {
			return ValueOf(uint64(a.Int64Value) & uint64(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(int64(a.Float32Value()) & int64(b.Float32Value()))
		},
		func(a, b *Value) Value {
			return ValueOf(int64(a.Float64Value()) & int64(b.Float64Value()))
		},
	},
}

var _OpCodeBinaryOr = OpFuncItem[OpFuncT2]{
	OpCodeBinaryOrInt8, [10]OpFuncT2{
		func(a, b *Value) Value {
			return ValueOf(int8(a.Int64Value) | int8(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint8(a.Int64Value) | uint8(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(int16(a.Int64Value) | int16(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint16(a.Int64Value) | uint16(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int32Value() | b.Int32Value())
		},
		func(a, b *Value) Value {
			return ValueOf(uint32(a.Int64Value) | uint32(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int64Value | b.Int64Value)
		},
		func(a, b *Value) Value {
			return ValueOf(uint64(a.Int64Value) | uint64(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(int64(a.Float32Value()) | int64(b.Float32Value()))
		},
		func(a, b *Value) Value {
			return ValueOf(int64(a.Float64Value()) | int64(b.Float64Value()))
		},
	},
}

var _OpCodeBinaryXor = OpFuncItem[OpFuncT2]{
	OpCodeBinaryXorInt8, [10]OpFuncT2{
		func(a, b *Value) Value {
			return ValueOf(int8(a.Int64Value) ^ int8(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint8(a.Int64Value) ^ uint8(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(int16(a.Int64Value) ^ int16(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(uint16(a.Int64Value) ^ uint16(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int32Value() ^ b.Int32Value())
		},
		func(a, b *Value) Value {
			return ValueOf(uint32(a.Int64Value) ^ uint32(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int64Value ^ b.Int64Value)
		},
		func(a, b *Value) Value {
			return ValueOf(uint64(a.Int64Value) ^ uint64(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(int64(a.Float32Value()) ^ int64(b.Float32Value()))
		},
		func(a, b *Value) Value {
			return ValueOf(int64(a.Float64Value()) ^ int64(b.Float64Value()))
		},
	},
}

var _OpCodeEqualTo = OpFuncItem[OpFuncT2]{
	OpCodeEqualToInt8, [10]OpFuncT2{
		func(a, b *Value) Value {
			return boolToValue(int8(a.Int64Value) == int8(b.Int64Value))
		},
		func(a, b *Value) Value {
			return boolToValue(uint8(a.Int64Value) == uint8(b.Int64Value))
		},
		func(a, b *Value) Value {
			return boolToValue(int16(a.Int64Value) == int16(b.Int64Value))
		},
		func(a, b *Value) Value {
			return boolToValue(uint16(a.Int64Value) == uint16(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int32Value() == b.Int32Value())
		},
		func(a, b *Value) Value {
			return boolToValue(uint32(a.Int64Value) == uint32(b.Int64Value))
		},
		func(a, b *Value) Value {
			return boolToValue(a.Int64Value == b.Int64Value)
		},
		func(a, b *Value) Value {
			return boolToValue(uint64(a.Int64Value) == uint64(b.Int64Value))
		},
		func(a, b *Value) Value {
			return boolToValue(a.Float32Value() == b.Float32Value())
		},
		func(a, b *Value) Value {
			return boolToValue(a.Float64Value() == b.Float64Value())
		},
	},
}

var _OpCodeLessThan = OpFuncItem[OpFuncT2]{
	OpCodeLessThanInt8, [10]OpFuncT2{
		func(a, b *Value) Value {
			return boolToValue(int8(a.Int64Value) < int8(b.Int64Value))
		},
		func(a, b *Value) Value {
			return boolToValue(uint8(a.Int64Value) < uint8(b.Int64Value))
		},
		func(a, b *Value) Value {
			return boolToValue(int16(a.Int64Value) < int16(b.Int64Value))
		},
		func(a, b *Value) Value {
			return boolToValue(uint16(a.Int64Value) < uint16(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int32Value() < b.Int32Value())
		},
		func(a, b *Value) Value {
			return boolToValue(uint32(a.Int64Value) < uint32(b.Int64Value))
		},
		func(a, b *Value) Value {
			return boolToValue(a.Int64Value < b.Int64Value)
		},
		func(a, b *Value) Value {
			return boolToValue(uint64(a.Int64Value) < uint64(b.Int64Value))
		},
		func(a, b *Value) Value {
			return boolToValue(a.Float32Value() < b.Float32Value())
		},
		func(a, b *Value) Value {
			return boolToValue(a.Float64Value() < b.Float64Value())
		},
	},
}

var _OpCodeGreaterThan = OpFuncItem[OpFuncT2]{
	OpCodeGreaterThanInt8, [10]OpFuncT2{
		func(a, b *Value) Value {
			return boolToValue(int8(a.Int64Value) > int8(b.Int64Value))
		},
		func(a, b *Value) Value {
			return boolToValue(uint8(a.Int64Value) > uint8(b.Int64Value))
		},
		func(a, b *Value) Value {
			return boolToValue(int16(a.Int64Value) > int16(b.Int64Value))
		},
		func(a, b *Value) Value {
			return boolToValue(uint16(a.Int64Value) > uint16(b.Int64Value))
		},
		func(a, b *Value) Value {
			return ValueOf(a.Int32Value() > b.Int32Value())
		},
		func(a, b *Value) Value {
			return boolToValue(uint32(a.Int64Value) > uint32(b.Int64Value))
		},
		func(a, b *Value) Value {
			return boolToValue(a.Int64Value > b.Int64Value)
		},
		func(a, b *Value) Value {
			return boolToValue(uint64(a.Int64Value) > uint64(b.Int64Value))
		},
		func(a, b *Value) Value {
			return boolToValue(a.Float32Value() > b.Float32Value())
		},
		func(a, b *Value) Value {
			return boolToValue(a.Float64Value() > b.Float64Value())
		},
	},
}

//goland:noinspection GoUnusedGlobalVariable
var OpFuncV2 = struct {
	OpCodeAdd         OpFuncItem[OpFuncT2]
	OpCodeSubtract    OpFuncItem[OpFuncT2]
	OpCodeMultiply    OpFuncItem[OpFuncT2]
	OpCodeDivide      OpFuncItem[OpFuncT2]
	OpCodeModulo      OpFuncItem[OpFuncT2]
	OpCodeShiftLeft   OpFuncItem[OpFuncT2]
	OpCodeShiftRight  OpFuncItem[OpFuncT2]
	OpCodeBinaryAnd   OpFuncItem[OpFuncT2]
	OpCodeBinaryOr    OpFuncItem[OpFuncT2]
	OpCodeBinaryXor   OpFuncItem[OpFuncT2]
	OpCodeEqualTo     OpFuncItem[OpFuncT2]
	OpCodeLessThan    OpFuncItem[OpFuncT2]
	OpCodeGreaterThan OpFuncItem[OpFuncT2]
	OpCodeDivide5     OpFuncItem[OpFuncT2]
	OpCodeDivide9     OpFuncItem[OpFuncT2]
	OpCodeDivide7     OpFuncItem[OpFuncT2]
}{
	OpCodeAdd:         _OpCodeAdd,
	OpCodeSubtract:    _OpCodeSubtract,
	OpCodeMultiply:    _OpCodeMultiply,
	OpCodeDivide:      _OpCodeDivide,
	OpCodeModulo:      _OpCodeModulo,
	OpCodeShiftLeft:   _OpCodeShiftLeft,
	OpCodeShiftRight:  _OpCodeShiftRight,
	OpCodeBinaryAnd:   _OpCodeBinaryAnd,
	OpCodeBinaryOr:    _OpCodeBinaryOr,
	OpCodeBinaryXor:   _OpCodeBinaryXor,
	OpCodeEqualTo:     _OpCodeEqualTo,
	OpCodeLessThan:    _OpCodeLessThan,
	OpCodeGreaterThan: _OpCodeGreaterThan,
}

func IfAppend(v string, append string) string {
	if len(v) == 0 {
		return ""
	}
	return v + append
}

func FmtInt(i int) string {
	return FmtInt64(int64(i), 10)
}

func FmtInt64(i int64, base int) string {
	const charset = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"
	if base < 2 {
		base = 2
	} else if base > 36 {
		base = 36
	}
	if i == 0 {
		return "0"
	}
	var neg bool
	var u uint64
	if i < 0 {
		neg = true
		u = uint64(-i)
	} else {
		u = uint64(i)
	}
	var buf [65]byte
	n := len(buf)
	b := uint64(base)
	for u > 0 {
		n--
		buf[n] = charset[u%b]
		u /= b
	}
	if neg {
		n--
		buf[n] = '-'
	}
	return string(buf[n:])
}

// ============================================================================
// TokenKind — token constants matching the C# Parser.TokenKind
// ============================================================================

type TokenKind int

//goland:noinspection GoUnusedConst
const (
	TokenKindYYErrorCode       TokenKind = 256
	TokenKindIDENTIFIER        TokenKind = 257
	TokenKindCONSTANT          TokenKind = 258
	TokenKindSTRING_LITERAL    TokenKind = 259
	TokenKindSIZEOF            TokenKind = 260
	TokenKindPTR_OP            TokenKind = 261
	TokenKindINC_OP            TokenKind = 262
	TokenKindDEC_OP            TokenKind = 263
	TokenKindLEFT_OP           TokenKind = 264
	TokenKindRIGHT_OP          TokenKind = 265
	TokenKindLE_OP             TokenKind = 266
	TokenKindGE_OP             TokenKind = 267
	TokenKindEQ_OP             TokenKind = 268
	TokenKindNE_OP             TokenKind = 269
	TokenKindCOLONCOLON        TokenKind = 270
	TokenKindAND_OP            TokenKind = 271
	TokenKindOR_OP             TokenKind = 272
	TokenKindMUL_ASSIGN        TokenKind = 273
	TokenKindDIV_ASSIGN        TokenKind = 274
	TokenKindMOD_ASSIGN        TokenKind = 275
	TokenKindADD_ASSIGN        TokenKind = 276
	TokenKindSUB_ASSIGN        TokenKind = 277
	TokenKindLEFT_ASSIGN       TokenKind = 278
	TokenKindRIGHT_ASSIGN      TokenKind = 279
	TokenKindBINARY_AND_ASSIGN TokenKind = 280
	TokenKindBINARY_XOR_ASSIGN TokenKind = 281
	TokenKindBINARY_OR_ASSIGN  TokenKind = 282
	TokenKindAND_ASSIGN        TokenKind = 283
	TokenKindOR_ASSIGN         TokenKind = 284
	TokenKindTYPE_NAME         TokenKind = 285
	TokenKindPUBLIC            TokenKind = 286
	TokenKindPRIVATE           TokenKind = 287
	TokenKindPROTECTED         TokenKind = 288
	TokenKindVIRTUAL           TokenKind = 289
	TokenKindOVERRIDE          TokenKind = 290
	TokenKindOPERATOR          TokenKind = 291
	TokenKindTYPEDEF           TokenKind = 292
	TokenKindEXTERN            TokenKind = 293
	TokenKindSTATIC            TokenKind = 294
	TokenKindAUTO              TokenKind = 295
	TokenKindREGISTER          TokenKind = 296
	TokenKindINLINE            TokenKind = 297
	TokenKindRESTRICT          TokenKind = 298
	TokenKindCHAR              TokenKind = 299
	TokenKindSHORT             TokenKind = 300
	TokenKindINT               TokenKind = 301
	TokenKindLONG              TokenKind = 302
	TokenKindSIGNED            TokenKind = 303
	TokenKindUNSIGNED          TokenKind = 304
	TokenKindFLOAT             TokenKind = 305
	TokenKindDOUBLE            TokenKind = 306
	TokenKindCONST             TokenKind = 307
	TokenKindVOLATILE          TokenKind = 308
	TokenKindVOID              TokenKind = 309
	TokenKindBOOL              TokenKind = 310
	TokenKindCOMPLEX           TokenKind = 311
	TokenKindIMAGINARY         TokenKind = 312
	TokenKindTRUE              TokenKind = 313
	TokenKindFALSE             TokenKind = 314
	TokenKindSTRUCT            TokenKind = 315
	TokenKindCLASS             TokenKind = 316
	TokenKindUNION             TokenKind = 317
	TokenKindENUM              TokenKind = 318
	TokenKindELLIPSIS          TokenKind = 319
	TokenKindCASE              TokenKind = 320
	TokenKindDEFAULT           TokenKind = 321
	TokenKindIF                TokenKind = 322
	TokenKindELSE              TokenKind = 323
	TokenKindSWITCH            TokenKind = 324
	TokenKindWHILE             TokenKind = 325
	TokenKindDO                TokenKind = 326
	TokenKindFOR               TokenKind = 327
	TokenKindGOTO              TokenKind = 328
	TokenKindCONTINUE          TokenKind = 329
	TokenKindBREAK             TokenKind = 330
	TokenKindRETURN            TokenKind = 331
	TokenKindEOL               TokenKind = 332
)

// ============================================================================
// OpCode type and constants — assumed to exist in the package
// These are provided as a minimal reference.
// ============================================================================

type OpCode int

//goland:noinspection GoUnusedConst
const (
	OpCodeNop OpCode = iota
	OpCodePop
	OpCodeDup
	OpCodeJump
	OpCodeBranchIfFalse
	OpCodeBranchIfTrue
	OpCodeCall
	OpCodeCallVirtual
	OpCodeReturn
	OpCodeLoadConstant
	OpCodeLoadFramePointer
	OpCodeLoadArg
	OpCodeLoadLocal
	OpCodeLoadGlobal
	OpCodeLoadPointer
	OpCodeStoreArg
	OpCodeStoreLocal
	OpCodeStoreGlobal
	OpCodeStorePointer
	OpCodeOffsetPointer

	OpCodeAddInt8
	OpCodeAddUInt8
	OpCodeAddInt16
	OpCodeAddUInt16
	OpCodeAddInt32
	OpCodeAddUInt32
	OpCodeAddInt64
	OpCodeAddUInt64
	OpCodeAddFloat32
	OpCodeAddFloat64

	OpCodeSubtractInt8
	OpCodeSubtractUInt8
	OpCodeSubtractInt16
	OpCodeSubtractUInt16
	OpCodeSubtractInt32
	OpCodeSubtractUInt32
	OpCodeSubtractInt64
	OpCodeSubtractUInt64
	OpCodeSubtractFloat32
	OpCodeSubtractFloat64

	OpCodeMultiplyInt8
	OpCodeMultiplyUInt8
	OpCodeMultiplyInt16
	OpCodeMultiplyUInt16
	OpCodeMultiplyInt32
	OpCodeMultiplyUInt32
	OpCodeMultiplyInt64
	OpCodeMultiplyUInt64
	OpCodeMultiplyFloat32
	OpCodeMultiplyFloat64

	OpCodeDivideInt8
	OpCodeDivideUInt8
	OpCodeDivideInt16
	OpCodeDivideUInt16
	OpCodeDivideInt32
	OpCodeDivideUInt32
	OpCodeDivideInt64
	OpCodeDivideUInt64
	OpCodeDivideFloat32
	OpCodeDivideFloat64

	OpCodeShiftLeftInt8
	OpCodeShiftLeftUInt8
	OpCodeShiftLeftInt16
	OpCodeShiftLeftUInt16
	OpCodeShiftLeftInt32
	OpCodeShiftLeftUInt32
	OpCodeShiftLeftInt64
	OpCodeShiftLeftUInt64
	OpCodeShiftLeftFloat32
	OpCodeShiftLeftFloat64

	OpCodeShiftRightInt8
	OpCodeShiftRightUInt8
	OpCodeShiftRightInt16
	OpCodeShiftRightUInt16
	OpCodeShiftRightInt32
	OpCodeShiftRightUInt32
	OpCodeShiftRightInt64
	OpCodeShiftRightUInt64
	OpCodeShiftRightFloat32
	OpCodeShiftRightFloat64

	OpCodeModuloInt8
	OpCodeModuloUInt8
	OpCodeModuloInt16
	OpCodeModuloUInt16
	OpCodeModuloInt32
	OpCodeModuloUInt32
	OpCodeModuloInt64
	OpCodeModuloUInt64
	OpCodeModuloFloat32
	OpCodeModuloFloat64

	OpCodeEqualToInt8
	OpCodeEqualToUInt8
	OpCodeEqualToInt16
	OpCodeEqualToUInt16
	OpCodeEqualToInt32
	OpCodeEqualToUInt32
	OpCodeEqualToInt64
	OpCodeEqualToUInt64
	OpCodeEqualToFloat32
	OpCodeEqualToFloat64

	OpCodeLessThanInt8
	OpCodeLessThanUInt8
	OpCodeLessThanInt16
	OpCodeLessThanUInt16
	OpCodeLessThanInt32
	OpCodeLessThanUInt32
	OpCodeLessThanInt64
	OpCodeLessThanUInt64
	OpCodeLessThanFloat32
	OpCodeLessThanFloat64

	OpCodeGreaterThanInt8
	OpCodeGreaterThanUInt8
	OpCodeGreaterThanInt16
	OpCodeGreaterThanUInt16
	OpCodeGreaterThanInt32
	OpCodeGreaterThanUInt32
	OpCodeGreaterThanInt64
	OpCodeGreaterThanUInt64
	OpCodeGreaterThanFloat32
	OpCodeGreaterThanFloat64

	OpCodeBinaryAndInt8
	OpCodeBinaryAndUInt8
	OpCodeBinaryAndInt16
	OpCodeBinaryAndUInt16
	OpCodeBinaryAndInt32
	OpCodeBinaryAndUInt32
	OpCodeBinaryAndInt64
	OpCodeBinaryAndUInt64
	OpCodeBinaryAndFloat32
	OpCodeBinaryAndFloat64

	OpCodeBinaryOrInt8
	OpCodeBinaryOrUInt8
	OpCodeBinaryOrInt16
	OpCodeBinaryOrUInt16
	OpCodeBinaryOrInt32
	OpCodeBinaryOrUInt32
	OpCodeBinaryOrInt64
	OpCodeBinaryOrUInt64
	OpCodeBinaryOrFloat32
	OpCodeBinaryOrFloat64

	OpCodeBinaryXorInt8
	OpCodeBinaryXorUInt8
	OpCodeBinaryXorInt16
	OpCodeBinaryXorUInt16
	OpCodeBinaryXorInt32
	OpCodeBinaryXorUInt32
	OpCodeBinaryXorInt64
	OpCodeBinaryXorUInt64
	OpCodeBinaryXorFloat32
	OpCodeBinaryXorFloat64

	OpCodeNotInt8
	OpCodeNotUInt8
	OpCodeNotInt16
	OpCodeNotUInt16
	OpCodeNotInt32
	OpCodeNotUInt32
	OpCodeNotInt64
	OpCodeNotUInt64
	OpCodeNotFloat32
	OpCodeNotFloat64

	OpCodeBinaryNotInt8
	OpCodeBinaryNotUInt8
	OpCodeBinaryNotInt16
	OpCodeBinaryNotUInt16
	OpCodeBinaryNotInt32
	OpCodeBinaryNotUInt32
	OpCodeBinaryNotInt64
	OpCodeBinaryNotUInt64
	OpCodeBinaryNotFloat32
	OpCodeBinaryNotFloat64

	OpCodeNegateInt8
	OpCodeNegateUInt8
	OpCodeNegateInt16
	OpCodeNegateUInt16
	OpCodeNegateInt32
	OpCodeNegateUInt32
	OpCodeNegateInt64
	OpCodeNegateUInt64
	OpCodeNegateFloat32
	OpCodeNegateFloat64

	OpCodeConvertInt8Int8
	OpCodeConvertInt8UInt8
	OpCodeConvertInt8Int16
	OpCodeConvertInt8UInt16
	OpCodeConvertInt8Int32
	OpCodeConvertInt8UInt32
	OpCodeConvertInt8Int64
	OpCodeConvertInt8UInt64
	OpCodeConvertInt8Float32
	OpCodeConvertInt8Float64

	OpCodeConvertUInt8Int8
	OpCodeConvertUInt8UInt8
	OpCodeConvertUInt8Int16
	OpCodeConvertUInt8UInt16
	OpCodeConvertUInt8Int32
	OpCodeConvertUInt8UInt32
	OpCodeConvertUInt8Int64
	OpCodeConvertUInt8UInt64
	OpCodeConvertUInt8Float32
	OpCodeConvertUInt8Float64

	OpCodeConvertInt16Int8
	OpCodeConvertInt16UInt8
	OpCodeConvertInt16Int16
	OpCodeConvertInt16UInt16
	OpCodeConvertInt16Int32
	OpCodeConvertInt16UInt32
	OpCodeConvertInt16Int64
	OpCodeConvertInt16UInt64
	OpCodeConvertInt16Float32
	OpCodeConvertInt16Float64

	OpCodeConvertUInt16Int8
	OpCodeConvertUInt16UInt8
	OpCodeConvertUInt16Int16
	OpCodeConvertUInt16UInt16
	OpCodeConvertUInt16Int32
	OpCodeConvertUInt16UInt32
	OpCodeConvertUInt16Int64
	OpCodeConvertUInt16UInt64
	OpCodeConvertUInt16Float32
	OpCodeConvertUInt16Float64

	OpCodeConvertInt32Int8
	OpCodeConvertInt32UInt8
	OpCodeConvertInt32Int16
	OpCodeConvertInt32UInt16
	OpCodeConvertInt32Int32
	OpCodeConvertInt32UInt32
	OpCodeConvertInt32Int64
	OpCodeConvertInt32UInt64
	OpCodeConvertInt32Float32
	OpCodeConvertInt32Float64

	OpCodeConvertUInt32Int8
	OpCodeConvertUInt32UInt8
	OpCodeConvertUInt32Int16
	OpCodeConvertUInt32UInt16
	OpCodeConvertUInt32Int32
	OpCodeConvertUInt32UInt32
	OpCodeConvertUInt32Int64
	OpCodeConvertUInt32UInt64
	OpCodeConvertUInt32Float32
	OpCodeConvertUInt32Float64

	OpCodeConvertInt64Int8
	OpCodeConvertInt64UInt8
	OpCodeConvertInt64Int16
	OpCodeConvertInt64UInt16
	OpCodeConvertInt64Int32
	OpCodeConvertInt64UInt32
	OpCodeConvertInt64Int64
	OpCodeConvertInt64UInt64
	OpCodeConvertInt64Float32
	OpCodeConvertInt64Float64

	OpCodeConvertUInt64Int8
	OpCodeConvertUInt64UInt8
	OpCodeConvertUInt64Int16
	OpCodeConvertUInt64UInt16
	OpCodeConvertUInt64Int32
	OpCodeConvertUInt64UInt32
	OpCodeConvertUInt64Int64
	OpCodeConvertUInt64UInt64
	OpCodeConvertUInt64Float32
	OpCodeConvertUInt64Float64

	OpCodeConvertFloat32Int8
	OpCodeConvertFloat32UInt8
	OpCodeConvertFloat32Int16
	OpCodeConvertFloat32UInt16
	OpCodeConvertFloat32Int32
	OpCodeConvertFloat32UInt32
	OpCodeConvertFloat32Int64
	OpCodeConvertFloat32UInt64
	OpCodeConvertFloat32Float32
	OpCodeConvertFloat32Float64

	OpCodeConvertFloat64Int8
	OpCodeConvertFloat64UInt8
	OpCodeConvertFloat64Int16
	OpCodeConvertFloat64UInt16
	OpCodeConvertFloat64Int32
	OpCodeConvertFloat64UInt32
	OpCodeConvertFloat64Int64
	OpCodeConvertFloat64UInt64
	OpCodeConvertFloat64Float32
	OpCodeConvertFloat64Float64

	OpCodeConvertPointerInt8
	OpCodeConvertPointerUInt8
	OpCodeConvertPointerInt16
	OpCodeConvertPointerUInt16
	OpCodeConvertPointerInt32
	OpCodeConvertPointerUInt32
	OpCodeConvertPointerInt64
	OpCodeConvertPointerUInt64
	OpCodeConvertPointerFloat32
	OpCodeConvertPointerFloat64
)

// ============================================================================
// yyParser — LALR parser interfaces and exceptions
// ============================================================================

type yyInput interface {
	advance() bool
	token() int
	value() interface{}
}

type yyException struct {
	message string
}

func (e yyException) Error() string { return e.message }

type yyUnexpectedEof struct {
	yyException
}

func newYyUnexpectedEof() yyUnexpectedEof {
	return yyUnexpectedEof{yyException{message: ""}}
}

// ============================================================================
// yyDebug — optional debugging interface
// ============================================================================

type yyDebug interface {
	push(state int, value interface{})
	lex(state int, token int, name string, value interface{})
	shift(from, to, errorFlag int)
	pop(state int)
	discard(state int, token int, name string, value interface{})
	reduce(from, to, rule int, text string, length int)
	gotoState(from, to int)
	accept(value interface{})
	error(message string)
	reject()
}

//goland:noinspection GoUnusedType
type yyDebugSimple struct{}

//goland:noinspection GoUnusedParameter
func (d *yyDebugSimple) println(s string) {}

func (d *yyDebugSimple) push(state int, value interface{}) {
	d.println("push\tstate " + fmt.Sprint(state) + "\tvalue " + fmt.Sprint(value))
}

//goland:noinspection GoUnusedParameter
func (d *yyDebugSimple) lex(state int, token int, name string, value interface{}) {
	d.println("lex\tstate " + fmt.Sprint(state) + "\treading " + name + "\tvalue " + fmt.Sprint(value))
}

//goland:noinspection GoUnusedParameter
func (d *yyDebugSimple) shift(from, to, errorFlag int) {
	d.println("shift\tfrom state " + fmt.Sprint(from) + " to " + fmt.Sprint(to))
}
func (d *yyDebugSimple) pop(state int) { d.println("pop\tstate " + fmt.Sprint(state) + "\ton error") }

//goland:noinspection GoUnusedParameter
func (d *yyDebugSimple) discard(state int, token int, name string, value interface{}) {
	d.println("discard\tstate " + fmt.Sprint(state) + "\ttoken " + name + "\tvalue " + fmt.Sprint(value))
}

//goland:noinspection GoUnusedParameter
func (d *yyDebugSimple) reduce(from, to, rule int, text string, length int) {
	d.println("reduce\tstate " + fmt.Sprint(from) + "\tuncover " + fmt.Sprint(to) + "\trule (" + fmt.Sprint(rule) + ") " + text)
}
func (d *yyDebugSimple) gotoState(from, to int) {
	d.println("goto\tfrom state " + fmt.Sprint(from) + " to " + fmt.Sprint(to))
}
func (d *yyDebugSimple) accept(value interface{}) { d.println("accept\tvalue " + fmt.Sprint(value)) }
func (d *yyDebugSimple) error(message string)     { d.println("error\t" + message) }
func (d *yyDebugSimple) reject()                  { d.println("reject") }

// ============================================================================
// ValueOf helper — converts a basic Go value into a Value struct
// ============================================================================

func ValueOf(v interface{}) Value {
	switch x := v.(type) {
	case int:
		return Value{Int64Value: int64(x)}
	case int64:
		return Value{Int64Value: x}
	case int32:
		return Value{Int64Value: int64(x)}
	case int16:
		return Value{Int64Value: int64(x)}
	case int8:
		return Value{Int64Value: int64(x)}
	case uint:
		return Value{Int64Value: int64(x)}
	case uint64:
		return Value{Int64Value: int64(x)}
	case uint32:
		return Value{Int64Value: int64(x)}
	case uint16:
		return Value{Int64Value: int64(x)}
	case uint8:
		return Value{Int64Value: int64(x)}
	case float64:
		return ValueFloat64(x)
	case float32:
		return ValueFloat32(x)
	case bool:
		if x {
			return Value{Int64Value: 1}
		}
		return Value{Int64Value: 0}
	default:
		return Value{Int64Value: 0}
	}
}

// ============================================================================
// NotImplementedException — for features not yet ported
// ============================================================================

type NotImplementedException struct {
	message string
}

func NewNotImplementedException(msg string) *NotImplementedException {
	return &NotImplementedException{message: msg}
}

func (e *NotImplementedException) Error() string {
	return e.message
}

type NotSupportedException struct {
	message string
}

func NewNotSupportedException(msg string) *NotSupportedException {
	return &NotSupportedException{message: msg}
}

func (e *NotSupportedException) Error() string {
	return e.message
}
