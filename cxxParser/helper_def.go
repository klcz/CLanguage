package cxxParser

import (
	"fmt"
)

// ============================================================================
// TokenKind — token constants matching the C# Parser.TokenKind
// ============================================================================

//goland:noinspection GoUnusedConst
const (
	TokenKindYYErrorCode       = 256
	TokenKindIDENTIFIER        = 257
	TokenKindCONSTANT          = 258
	TokenKindSTRING_LITERAL    = 259
	TokenKindSIZEOF            = 260
	TokenKindPTR_OP            = 261
	TokenKindINC_OP            = 262
	TokenKindDEC_OP            = 263
	TokenKindLEFT_OP           = 264
	TokenKindRIGHT_OP          = 265
	TokenKindLE_OP             = 266
	TokenKindGE_OP             = 267
	TokenKindEQ_OP             = 268
	TokenKindNE_OP             = 269
	TokenKindCOLONCOLON        = 270
	TokenKindAND_OP            = 271
	TokenKindOR_OP             = 272
	TokenKindMUL_ASSIGN        = 273
	TokenKindDIV_ASSIGN        = 274
	TokenKindMOD_ASSIGN        = 275
	TokenKindADD_ASSIGN        = 276
	TokenKindSUB_ASSIGN        = 277
	TokenKindLEFT_ASSIGN       = 278
	TokenKindRIGHT_ASSIGN      = 279
	TokenKindBINARY_AND_ASSIGN = 280
	TokenKindBINARY_XOR_ASSIGN = 281
	TokenKindBINARY_OR_ASSIGN  = 282
	TokenKindAND_ASSIGN        = 283
	TokenKindOR_ASSIGN         = 284
	TokenKindTYPE_NAME         = 285
	TokenKindPUBLIC            = 286
	TokenKindPRIVATE           = 287
	TokenKindPROTECTED         = 288
	TokenKindVIRTUAL           = 289
	TokenKindOVERRIDE          = 290
	TokenKindOPERATOR          = 291
	TokenKindTYPEDEF           = 292
	TokenKindEXTERN            = 293
	TokenKindSTATIC            = 294
	TokenKindAUTO              = 295
	TokenKindREGISTER          = 296
	TokenKindINLINE            = 297
	TokenKindRESTRICT          = 298
	TokenKindCHAR              = 299
	TokenKindSHORT             = 300
	TokenKindINT               = 301
	TokenKindLONG              = 302
	TokenKindSIGNED            = 303
	TokenKindUNSIGNED          = 304
	TokenKindFLOAT             = 305
	TokenKindDOUBLE            = 306
	TokenKindCONST             = 307
	TokenKindVOLATILE          = 308
	TokenKindVOID              = 309
	TokenKindBOOL              = 310
	TokenKindCOMPLEX           = 311
	TokenKindIMAGINARY         = 312
	TokenKindTRUE              = 313
	TokenKindFALSE             = 314
	TokenKindSTRUCT            = 315
	TokenKindCLASS             = 316
	TokenKindUNION             = 317
	TokenKindENUM              = 318
	TokenKindELLIPSIS          = 319
	TokenKindCASE              = 320
	TokenKindDEFAULT           = 321
	TokenKindIF                = 322
	TokenKindELSE              = 323
	TokenKindSWITCH            = 324
	TokenKindWHILE             = 325
	TokenKindDO                = 326
	TokenKindFOR               = 327
	TokenKindGOTO              = 328
	TokenKindCONTINUE          = 329
	TokenKindBREAK             = 330
	TokenKindRETURN            = 331
	TokenKindEOL               = 332
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
		return Value{Int64Value: int64(x), Int32Value: int32(x), Int16Value: int16(x), Int8Value: int8(x), PointerValue: int32(x)}
	case int64:
		return Value{Int64Value: x, Int32Value: int32(x), Int16Value: int16(x), Int8Value: int8(x), PointerValue: int32(x)}
	case int32:
		return Value{Int32Value: x, Int64Value: int64(x), Int16Value: int16(x), Int8Value: int8(x), PointerValue: x}
	case int16:
		return Value{Int16Value: x, Int32Value: int32(x), Int64Value: int64(x), Int8Value: int8(x), PointerValue: int32(x)}
	case int8:
		return Value{Int8Value: x, Int16Value: int16(x), Int32Value: int32(x), Int64Value: int64(x), PointerValue: int32(x)}
	case uint:
		return Value{UInt64Value: uint64(x), UInt32Value: uint32(x), UInt16Value: uint16(x), UInt8Value: uint8(x)}
	case uint64:
		return Value{UInt64Value: x, UInt32Value: uint32(x), UInt16Value: uint16(x), UInt8Value: uint8(x)}
	case uint32:
		return Value{UInt32Value: x, UInt64Value: uint64(x), UInt16Value: uint16(x), UInt8Value: uint8(x)}
	case uint16:
		return Value{UInt16Value: x, UInt32Value: uint32(x), UInt64Value: uint64(x), UInt8Value: uint8(x)}
	case uint8:
		return Value{UInt8Value: x, UInt16Value: uint16(x), UInt32Value: uint32(x), UInt64Value: uint64(x)}
	case float64:
		return Value{Float64Value: x}
	case float32:
		return Value{Float32Value: x}
	case bool:
		if x {
			return Value{Int32Value: 1, Int64Value: 1, Int16Value: 1, Int8Value: 1, PointerValue: 1}
		}
		return Value{Int32Value: 0, Int64Value: 0, Int16Value: 0, Int8Value: 0, PointerValue: 0}
	default:
		return Value{Int32Value: 0, Int64Value: 0, Int16Value: 0, Int8Value: 0, PointerValue: 0}
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
