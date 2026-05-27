package cxxParser

import (
	"fmt"
	"math"
	"path/filepath"
	"strconv"
	"strings"
	"unsafe"
)

// ============================================================================
// SystemHeaders (from SystemHeaders.cs)
// ============================================================================

const MathH = `
#define M_PI 3.1415926535897932384626433832795028841971693993751
`

// ── Location ────────────────────────────────────────────────────────────────

type Location struct {
	Document *Document
	Index    int
	Line     int
	Column   int
}

func NewLocation(document *Document, index, line, column int) Location {
	return Location{Document: document, Index: index, Line: line, Column: column}
}

func (l Location) IsNull() bool { return l.Document == nil }

func (l Location) String() string {
	if l.IsNull() {
		return "?(?,?)"
	}
	return fmt.Sprintf("%s(%d,%d)", l.Document, l.Line, l.Column)
}

func (l Location) Add(columnOffset int) Location {
	return Location{Document: l.Document, Index: l.Index + columnOffset, Line: l.Line, Column: l.Column + columnOffset}
}

var NullLocation = Location{}

// ── Document ────────────────────────────────────────────────────────────────

type Document struct {
	Path     string
	Content  string
	Encoding string
}

func NewDocument(path, content string) *Document {
	if path == "" {
		panic("Document path must be specified")
	}
	return &Document{Path: path, Content: content, Encoding: "UTF-8"}
}

func NewDocumentWithEncoding(path, content, encoding string) *Document {
	if path == "" {
		panic("Document path must be specified")
	}
	return &Document{Path: path, Content: content, Encoding: encoding}
}

func (d *Document) IsCompilable() bool {
	ext := strings.ToLower(filepath.Ext(d.Path))
	switch ext {
	case ".c", ".cpp", ".cxx", ".m", ".mpp", ".ino":
		return true
	}
	return false
}

func (d *Document) String() string { return d.Path }

// ── Token ───────────────────────────────────────────────────────────────────

type Token struct {
	Kind        TokenKind
	Location    Location
	EndLocation Location
	Value       interface{}
}

func NewToken(kind TokenKind, value interface{}, location, endLocation Location) Token {
	if value == nil {
		value = ""
	}
	return Token{Kind: kind, Value: value, Location: location, EndLocation: endLocation}
}

func NewTokenSimple(kind TokenKind, value interface{}) Token {
	return Token{Kind: kind, Value: value, Location: NullLocation, EndLocation: NullLocation}
}

func NewTokenChar(kind TokenKind) Token {
	return Token{Kind: kind, Location: NullLocation, EndLocation: NullLocation}
}

func (t Token) StringValue() string {
	if s, ok := t.Value.(string); ok {
		return s
	}
	if t.Value != nil {
		return fmt.Sprintf("%v", t.Value)
	}
	return ""
}

func (t Token) Text() string {
	if t.Location.IsNull() || t.EndLocation.IsNull() || t.Location.Document.Path != t.EndLocation.Document.Path {
		return ""
	}
	return t.Location.Document.Content[t.Location.Index:t.EndLocation.Index]
}

func (t Token) AsKind(kind TokenKind) Token {
	return Token{Kind: kind, Value: t.Value, Location: t.Location, EndLocation: t.EndLocation}
}

func (t Token) String() string {
	text := t.Text()
	if text == "" {
		if t.Value != nil {
			text = fmt.Sprintf("%v", t.Value)
		} else if t.Kind < 127 {
			text = string(rune(t.Kind))
		}
	}
	if t.Kind < 127 {
		return fmt.Sprintf("\"%s\"", text)
	}
	return fmt.Sprintf("\"%s\": %d", text, t.Kind)
}

// ── ColorSpan & SyntaxColor ─────────────────────────────────────────────────

type ColorSpan struct {
	Index  int
	Length int
	Color  SyntaxColor
}

type SyntaxColor int

const (
	SyntaxColorComment    SyntaxColor = 0
	SyntaxColorIdentifier SyntaxColor = 1
	SyntaxColorNumber     SyntaxColor = 2
	SyntaxColorString     SyntaxColor = 3
	SyntaxColorKeyword    SyntaxColor = 4
	SyntaxColorOperator   SyntaxColor = 5
	SyntaxColorFunction   SyntaxColor = 6
	SyntaxColorType       SyntaxColor = 7
)

func (c SyntaxColor) String() string {
	switch c {
	case SyntaxColorComment:
		return "Comment"
	case SyntaxColorIdentifier:
		return "Identifier"
	case SyntaxColorNumber:
		return "Number"
	case SyntaxColorString:
		return "String"
	case SyntaxColorKeyword:
		return "Keyword"
	case SyntaxColorOperator:
		return "Operator"
	case SyntaxColorFunction:
		return "Function"
	case SyntaxColorType:
		return "Type"
	}
	return "Unknown"
}

// ============================================================================
// CodeWriter (from CodeWriter.cs)
// ============================================================================

type CodeWriter struct {
	needsIndent bool
	indent      int
	buf         strings.Builder
}

func NewCodeWriter() *CodeWriter {
	return &CodeWriter{needsIndent: true}
}

func (w *CodeWriter) Code() string {
	return w.buf.String()
}

func (w *CodeWriter) String() string {
	return w.Code()
}

func (w *CodeWriter) Write(code string) *CodeWriter {
	lines := strings.Split(code, "\n")
	for i := 0; i < len(lines)-1; i++ {
		w.writeIndent()
		w.buf.WriteString(strings.TrimRight(lines[i], " \t\r"))
		w.buf.WriteString("\r\n")
		w.needsIndent = true
	}
	w.writeIndent()
	w.buf.WriteString(lines[len(lines)-1])
	return w
}

func (w *CodeWriter) WriteLine(code string) *CodeWriter {
	lines := strings.Split(code, "\n")
	for i := 0; i < len(lines); i++ {
		w.writeIndent()
		w.buf.WriteString(strings.TrimRight(lines[i], " \t\r"))
		w.buf.WriteString("\r\n")
		w.needsIndent = true
	}
	return w
}

func (w *CodeWriter) Indent() *CodeWriter {
	w.indent++
	return w
}

func (w *CodeWriter) Outdent() *CodeWriter {
	w.indent--
	return w
}

func (w *CodeWriter) Comment(comment string) *CodeWriter {
	return w.Write("/* " + comment + " */")
}

func (w *CodeWriter) writeIndent() {
	if w.needsIndent {
		w.needsIndent = false
		for i := 0; i < w.indent; i++ {
			w.buf.WriteString("    ")
		}
	}
}

// ============================================================================
// Value (from Value.cs)
// ============================================================================

type Value struct {
	Int64Value int64
}

func (v *Value) ToString() string {
	return FmtInt64(v.Int64Value, 10)
}

func (v *Value) String() string {
	return "Val<`0x" + FmtInt64(v.Int64Value, 16) + "`>"
}

//region ---- unsafe Value Union ----

type ValueNumber interface {
	int | int64 | uint64 | int32 | uint32 | int16 | uint16 | int8 | uint8 | float32 | float64
}

type _UnionValue[T ValueNumber] struct {
	_Value T
}

func UnionValue[T ValueNumber](v T) Value {
	val := Value{Int64Value: 0}
	(*_UnionValue[T])(unsafe.Pointer(&val))._Value = v
	return val
}

func (v *Value) UInt64Value() uint64 {
	return (*_UnionValue[uint64])(unsafe.Pointer(v))._Value
}
func (v *Value) Int32Value() int32 {
	return (*_UnionValue[int32])(unsafe.Pointer(v))._Value
}
func (v *Value) UInt32Value() uint32 {
	return (*_UnionValue[uint32])(unsafe.Pointer(v))._Value
}
func (v *Value) Int16Value() int16 {
	return (*_UnionValue[int16])(unsafe.Pointer(v))._Value
}
func (v *Value) UInt16Value() uint16 {
	return (*_UnionValue[uint16])(unsafe.Pointer(v))._Value
}
func (v *Value) Int8Value() int8 {
	return (*_UnionValue[int8])(unsafe.Pointer(v))._Value
}
func (v *Value) UInt8Value() uint8 {
	return (*_UnionValue[uint8])(unsafe.Pointer(v))._Value
}
func (v *Value) PointerValue() int32 {
	return (*_UnionValue[int32])(unsafe.Pointer(v))._Value
}
func (v *Value) CharValue() byte {
	return (*_UnionValue[byte])(unsafe.Pointer(v))._Value
}

func (v *Value) Float64Value() float64 {
	return (*_Float64Value)(unsafe.Pointer(v))._Value
}

func (v *Value) Float32Value() float32 {
	return (*_Float32Value)(unsafe.Pointer(v))._Value
}

type _Float32Value struct {
	_Value float32
	_mask  uint32
}
type _Float64Value struct {
	_Value float64
}

func ValueFloat32(v float32) Value {
	val := Value{Int64Value: 0}
	(*_Float32Value)(unsafe.Pointer(&val))._Value = v
	return val
}

func ValueFloat64(v float64) Value {
	val := Value{Int64Value: 0}
	(*_Float64Value)(unsafe.Pointer(&val))._Value = v
	return val
}

//endregion

func ValuePointer(address int) Value {
	return Value{Int64Value: int64(address)}
}

// ============================================================================
// Printer (from Report.cs)
// ============================================================================

type Printer interface {
	Print(msg string)
}

type SimplePrinter struct {
	LogLevel     string
	WarningCount int
	ErrorCount   int
}

func NewSimplePrinter() *SimplePrinter {
	return &SimplePrinter{}
}

func NewSimplePrinterLevel(logLevel string) *SimplePrinter {
	return &SimplePrinter{
		LogLevel: logLevel,
	}
}

func (p *SimplePrinter) Print(msg string) {
	if len(p.LogLevel) == 0 {
		fmt.Print(msg)
		return
	}

	doPrint := false
	if p.LogLevel == "Fault" {
		doPrint = strings.HasPrefix(msg, "Fault")
	} else if p.LogLevel == "Error" {
		doPrint = strings.HasPrefix(msg, "Fault") || strings.HasPrefix(msg, "Error")
	} else if p.LogLevel == "Warning" {
		doPrint = strings.HasPrefix(msg, "Fault") || strings.HasPrefix(msg, "Error") ||
			strings.HasPrefix(msg, "Warning")
	} else if p.LogLevel == "Info" {
		doPrint = strings.HasPrefix(msg, "Fault") || strings.HasPrefix(msg, "Error") ||
			strings.HasPrefix(msg, "Warning") || strings.HasPrefix(msg, "Info")
	} else if p.LogLevel == "Debug" {
		doPrint = true
	}

	if doPrint {
		fmt.Print(msg)
	}
}

type SavedPrinter struct {
	Messages []string
}

func NewSavedPrinter() *SavedPrinter {
	return &SavedPrinter{Messages: make([]string, 0)}
}

func (p *SavedPrinter) Print(msg string) {
	p.Messages = append(p.Messages, msg)
}

// ============================================================================
// Report (from Report.cs)
// ============================================================================

type AbstractMessage struct {
	MessageType string
	IsWarning   bool
	Code        int
	Text        string
}

type Report struct {
	reportingDisabled int
	printer           Printer
	previousErrors    map[string]bool
	messages          []*AbstractMessage
}

func NewReport(printer Printer) *Report {
	if printer == nil {
		printer = NewSimplePrinter()
	}
	return &Report{
		printer:        printer,
		previousErrors: make(map[string]bool),
		messages:       make([]*AbstractMessage, 0),
	}
}

func (r *Report) Errors() []*AbstractMessage {
	return r.messages
}

func (r *Report) emitError(code int, text string) {
	if r.reportingDisabled > 0 {
		return
	}
	key := fmt.Sprintf("E%d", code)
	if !r.previousErrors[key] {
		msg := fmt.Sprintf("Error C%04d: %s\n", code, text)
		r.messages = append(r.messages, &AbstractMessage{
			MessageType: "Error", Code: code, Text: text, IsWarning: false,
		})
		r.printer.Print(msg)
	}
}

func (r *Report) emitWarning(code int, text string) {
	if r.reportingDisabled > 0 {
		return
	}
	key := fmt.Sprintf("W%d", code)
	if !r.previousErrors[key] {
		msg := fmt.Sprintf("Warning C%04d: %s\n", code, text)
		r.messages = append(r.messages, &AbstractMessage{
			MessageType: "Warning", Code: code, Text: text, IsWarning: true,
		})
		r.printer.Print(msg)
	}
}

func (r *Report) Error(code int, msg string) {
	r.emitError(code, msg)
}

func (r *Report) Errorf(code int, format string, args ...interface{}) {
	r.emitError(code, fmt.Sprintf(format, args...))
}

//goland:noinspection GoUnusedParameter
func (r *Report) ErrorAt(code int, loc Location, endLoc Location, msg string) {
	r.emitError(code, msg)
}

//goland:noinspection GoUnusedParameter
func (r *Report) ErrorAtf(code int, loc Location, endLoc Location, format string, args ...interface{}) {
	r.emitError(code, fmt.Sprintf(format, args...))
}

func (r *Report) ErrorCode(code int, args ...interface{}) {
	msg := ""
	switch code {
	case 103:
		if len(args) >= 2 {
			msg = fmt.Sprintf("'%v' '%v' not found", args[0], args[1])
		} else {
			msg = fmt.Sprint(args...)
		}
	default:
		msg = fmt.Sprint(args...)
	}
	r.emitError(code, msg)
}

func (r *Report) ErrorSimple(code int, msg string) {
	r.emitError(code, msg)
}

func (r *Report) ErrorSimpleFormat(code int, format string, args ...interface{}) {
	r.emitError(code, fmt.Sprintf(format, args...))
}

func (r *Report) Warning(code int, msg string) {
	r.emitWarning(code, msg)
}

func (r *Report) Warningf(code int, format string, args ...interface{}) {
	r.emitWarning(code, fmt.Sprintf(format, args...))
}

//goland:noinspection GoUnusedParameter
func (r *Report) WarningAt(code int, loc Location, endLoc Location, msg string) {
	r.emitWarning(code, msg)
}

//goland:noinspection GoUnusedParameter
func (r *Report) WarningAtf(code int, loc Location, endLoc Location, format string, args ...interface{}) {
	r.emitWarning(code, fmt.Sprintf(format, args...))
}

// ============================================================================
// MachineInfo (from MachineInfo.cs)
// ============================================================================
// MachineInfo (from MachineInfo.cs)
// ============================================================================

type MachineInfo struct {
	CharSize        int
	ShortIntSize    int
	IntSize         int
	LongIntSize     int
	LongLongIntSize int
	FloatSize       int
	DoubleSize      int
	LongDoubleSize  int
	PointerSize     int

	HeaderCode        string
	InternalFunctions []BaseFunction
	SystemHeadersCode map[string]string
}

func NewMachineInfo() *MachineInfo {
	m := &MachineInfo{
		CharSize:        1,
		ShortIntSize:    2,
		IntSize:         4,
		LongIntSize:     4,
		LongLongIntSize: 8,
		FloatSize:       4,
		DoubleSize:      8,
		LongDoubleSize:  8,
		PointerSize:     4,

		HeaderCode:        "",
		InternalFunctions: nil,
		SystemHeadersCode: make(map[string]string),
	}
	m.SystemHeadersCode["math.h"] = MathH
	return m
}

func (m *MachineInfo) GeneratedHeaderCode() string {
	w := NewCodeWriter()
	w.WriteLine("typedef char int8_t;")
	w.WriteLine("typedef unsigned char uint8_t;")
	if m.ShortIntSize == 2 {
		w.WriteLine("typedef short int16_t;")
		w.WriteLine("typedef unsigned short uint16_t;")
	}
	if m.IntSize == 4 {
		w.WriteLine("typedef int int32_t;")
		w.WriteLine("typedef unsigned int uint32_t;")
	} else if m.LongIntSize == 4 {
		w.WriteLine("typedef long int32_t;")
		w.WriteLine("typedef unsigned long uint32_t;")
	}
	w.Write(m.HeaderCode)
	return w.Code()
}

type FuncEval int

const (
	FuncEvalAuto FuncEval = iota
	FuncEvalDelay
	FuncEvalStatic
)

func (m *MachineInfo) AddInternalFunction(prototype string, action InternalFunctionAction) {
	m.InternalFunctions = append(m.InternalFunctions, NewInternalFunction(m, prototype, action, FuncEvalAuto))
}

func (m *MachineInfo) AddInternalFunctionDelay(prototype string, action InternalFunctionAction) {
	m.InternalFunctions = append(m.InternalFunctions, NewInternalFunction(m, prototype, action, FuncEvalDelay))
}

func (m *MachineInfo) AddInternalFunctionStatic(prototype string, action InternalFunctionAction) {
	m.InternalFunctions = append(m.InternalFunctions, NewInternalFunction(m, prototype, action, FuncEvalStatic))
}

func (m *MachineInfo) AddGlobalMethods(target any) {
	m.addTargetMethods("", target)
}

func (m *MachineInfo) AddGlobalReference(name string, target any) {
	if strings.TrimSpace(name) == "" {
		panic("Name must be specified")
	}
	m.addTargetMethods(strings.TrimSpace(name), target)
}

var csharpOperatorNames = map[string]string{
	"op_Addition":           "operator+",
	"op_Subtraction":        "operator-",
	"op_Multiply":           "operator*",
	"op_Division":           "operator/",
	"op_Modulus":            "operator%",
	"op_Equality":           "operator==",
	"op_Inequality":         "operator!=",
	"op_LessThan":           "operator<",
	"op_GreaterThan":        "operator>",
	"op_LessThanOrEqual":    "operator<=",
	"op_GreaterThanOrEqual": "operator>=",
	"op_BitwiseAnd":         "operator&",
	"op_BitwiseOr":          "operator|",
	"op_ExclusiveOr":        "operator^",
	"op_LeftShift":          "operator<<",
	"op_RightShift":         "operator>>",
	"op_UnaryNegation":      "operator-",
	"op_LogicalNot":         "operator!",
}

// addTargetMethods uses Go reflection to marshal C#-style methods.
// This is a simplified stub — the original C# code uses System.Linq.Expressions
// to dynamically generate and compile IL at runtime, which has no Go equivalent.
// Only basic struct field marshaling is supported.
func (m *MachineInfo) addTargetMethods(name string, target any) {
	if target == nil {
		panic("target must not be nil")
	}

	isRef := name != ""

	// Use reflect to discover methods and fields
	_ = isRef
	_ = csharpOperatorNames

	// NOT IMPLEMENTED: The original C# code uses System.Reflection + System.Linq.Expressions
	// (Expression.Lambda, Expression.Call, Expression.Compile, etc.) which generate and
	// compile IL at runtime. This cannot be replicated in pure Go.
	//
	// To marshal C# objects as C structs with callable methods, the C# code:
	//   1. Discovers methods via DeclaredMethods
	//   2. Generates C header code for the struct + methods
	//   3. Creates InternalFunctionAction delegates via compiled expression trees
	//
	// A Go equivalent would need to use reflect to generate C header code and
	// then manually implement each marshaled function based on known types.
}

//goland:noinspection GoUnusedParameter
func (m *MachineInfo) GetUnresolvedVariable(name string, argTypes []CType, context *EmitContext) *ResolvedVariable {
	return nil
}

// clrTypeToCode maps Go types to C type name strings.
//
//goland:noinspection GoUnusedFunction
func clrTypeToCode(t any) string {
	switch t.(type) {
	case voidType:
		return "void"
	case int:
		return "int"
	case int32:
		return "int"
	case int16:
		return "short"
	case int8:
		return "signed char"
	case uint8:
		return "unsigned char"
	case float32:
		return "float"
	case float64:
		return "double"
	case string:
		return "const char *"
	case uint:
		return "unsigned int"
	case uint32:
		return "unsigned int"
	case uint16:
		return "unsigned short"
	default:
		return ""
	}
}

// voidType is a sentinel type for CLR void mapping
type voidType struct{}

//goland:noinspection GoUnusedGlobalVariable
var (
	Windows32 = &MachineInfo{
		CharSize:          1,
		ShortIntSize:      2,
		IntSize:           4,
		LongIntSize:       4,
		LongLongIntSize:   8,
		FloatSize:         4,
		DoubleSize:        8,
		LongDoubleSize:    8,
		PointerSize:       4,
		HeaderCode:        "",
		InternalFunctions: nil,
		SystemHeadersCode: map[string]string{"math.h": MathH},
	}

	Mac64 = &MachineInfo{
		CharSize:          1,
		ShortIntSize:      2,
		IntSize:           4,
		LongIntSize:       8,
		LongLongIntSize:   8,
		FloatSize:         4,
		DoubleSize:        8,
		LongDoubleSize:    8,
		PointerSize:       8,
		HeaderCode:        "",
		InternalFunctions: nil,
		SystemHeadersCode: map[string]string{"math.h": MathH},
	}
)

// ============================================================================
// CLanguageService (from CLanguageService.cs)
// ============================================================================

const DefaultCodePath = "main.cpp"

func ParseTranslationUnit(code string, extra ...any) *TranslationUnit {
	var report *Report
	var printer Printer

	for _, e := range extra {
		switch v := e.(type) {
		case *Report:
			report = v
		case Printer:
			printer = v
		}
	}

	if report == nil {
		if printer != nil {
			report = NewReport(printer)
		} else {
			report = NewReport(nil)
		}
	}

	parser := &CParser{}
	include := func(filePath string, relative bool) []Token { return nil }
	return parser.ParseTranslationUnitFromCode(DefaultCodePath, code, include, report)
}

func CreateInterpreter(code string, machineInfo *MachineInfo, printer Printer) *CInterpreter {
	exe := Compile(code, machineInfo, printer)
	return NewCInterpreter(exe)
}

func Compile(code string, machineInfo *MachineInfo, printer Printer) *Executable {
	report := NewReport(printer)
	mi := machineInfo
	if mi == nil {
		mi = NewMachineInfo()
	}
	doc := NewDocument(DefaultCodePath, code)
	options := NewCompilerOptions(mi, report, []*Document{doc})
	c := NewCCompiler(options)
	exe := c.Compile()
	return exe
}

func Colorize(code string, machineInfo *MachineInfo, printer Printer) []ColorSpan {
	report := NewReport(printer)
	mi := machineInfo
	if mi == nil {
		mi = NewMachineInfo()
	}

	doc := NewDocument(DefaultCodePath, code)
	lexed := NewLexedDocument(doc, report)

	compiler := NewCCompiler(NewCompilerOptions(mi, report, []*Document{doc}))
	exe := compiler.Compile()

	funcSet := make(map[string]bool)
	for _, f := range mi.InternalFunctions {
		funcSet[f.GetName()] = true
	}
	for _, g := range exe.Globals {
		if IsCStructType(g.VariableType) {
			funcSet[g.Name] = true
		}
	}

	var tokens []ColorSpan
	for _, tok := range lexed.Tokens {
		if tok.Kind == TokenKindEOL {
			continue
		}
		tokens = append(tokens, colorizeToken(tok, funcSet))
	}
	return tokens
}

func colorizeToken(token Token, funcSet map[string]bool) ColorSpan {
	return ColorSpan{
		Index:  token.Location.Index,
		Length: token.EndLocation.Index - token.Location.Index,
		Color:  getTokenColor(token, funcSet),
	}
}

func getTokenColor(token Token, funcSet map[string]bool) SyntaxColor {
	switch token.Kind {
	case TokenKindINT, TokenKindSHORT, TokenKindLONG, TokenKindCHAR,
		TokenKindFLOAT, TokenKindDOUBLE, TokenKindBOOL, TokenKindTYPE_NAME:
		return SyntaxColorType
	case TokenKindIDENTIFIER:
		if s, ok := token.Value.(string); ok {
			if funcSet[s] {
				return SyntaxColorFunction
			}
			switch s {
			case "uint8_t", "uint16_t", "uint32_t", "uint64_t",
				"int8_t", "int16_t", "int32_t", "int64_t", "boolean":
				return SyntaxColorType
			case "include", "define", "ifdef", "ifndef", "elif", "endif":
				return SyntaxColorKeyword
			}
		}
		return SyntaxColorIdentifier
	case TokenKindCONSTANT:
		if _, ok := token.Value.(byte); ok {
			return SyntaxColorString
		}
		return SyntaxColorNumber
	case TokenKindTRUE, TokenKindFALSE:
		return SyntaxColorNumber
	case TokenKindSTRING_LITERAL:
		return SyntaxColorString
	default:
		if token.Kind < 128 || isOperatorToken(token.Kind) {
			return SyntaxColorOperator
		} else if isKeywordToken(token.Kind) {
			switch token.StringValue() {
			case "unsigned", "signed":
				return SyntaxColorType
			default:
				return SyntaxColorKeyword
			}
		}
	}
	return SyntaxColorComment
}

func isOperatorToken(kind TokenKind) bool {
	return OperatorTokens[kind]
}

func isKeywordToken(kind TokenKind) bool {
	return KeywordTokens[kind]
}

func Run(code string) {
	RunInterpreter(code)
}

func Eval(expression string, includeCode string) any {
	codeToCompile := includeCode + `
auto __evalResult = ` + expression + `;
void start() {
    __cinit();
}
`
	exe := CompileCompiler(codeToCompile)
	global := findGlobalByName(exe.Globals, "__evalResult")
	interpreter := NewCInterpreter(exe)
	interpreter.Reset("start")
	interpreter.Run()

	globalType := global.VariableType
	resultValues := make([]Value, globalType.NumValues())
	copy(resultValues, interpreter.Stack[global.StackOffset:global.StackOffset+globalType.NumValues()])

	return globalType.GetClrValue(resultValues, exe.MachineInfo)
}

func IsCStructType(t CType) bool {
	_, ok := t.(*CStructType)
	return ok
}

func CompileCompiler(code string) *Executable {
	mi := NewMachineInfo()
	report := NewReport(nil)
	doc := NewDocument(DefaultCodePath, code)
	options := NewCompilerOptions(mi, report, []*Document{doc})
	c := NewCCompiler(options)
	return c.Compile()
}

func findGlobalByName(globals []CompiledGlobal, name string) CompiledGlobal {
	for _, g := range globals {
		if g.Name == name {
			return g
		}
	}
	panic(fmt.Sprintf("Global '%s' not found", name))
}

// ============================================================================
// CompiledVariable
// ============================================================================

type CompiledVariable struct {
	Name         string
	StackOffset  int
	VariableType CType
	InitialValue []Value
}

// ============================================================================
// CompiledFunction — assumed to exist
// ============================================================================

type CompiledFunction struct {
	Name           string
	FuncType       *CFunctionType
	Body           *Block
	Instructions   []Instruction
	LocalVariables []*CompiledVariable
	NameContext    string
	FunctionType   *CFunctionType
	Index          int
}

func NewCompiledFunction(name string, funcType *CFunctionType, body *Block) *CompiledFunction {
	return &CompiledFunction{
		Name:         name,
		FuncType:     funcType,
		FunctionType: funcType,
		Body:         body,
	}
}

func (f *CompiledFunction) GetName() string                 { return f.Name }
func (f *CompiledFunction) GetFunctionType() *CFunctionType { return f.FunctionType }
func (f *CompiledFunction) GetNameContext() string          { return f.NameContext }
func (f *CompiledFunction) GetInstructions() []Instruction  { return f.Instructions }
func (f *CompiledFunction) GetIndex() int                   { return f.Index }

// ============================================================================
// Executable — assumed to exist
// ============================================================================

type Executable struct {
	Functions     []BaseFunction
	Globals       []CompiledGlobal
	MachineInfo   *MachineInfo
	TypeHierarchy []*TypeHierarchyEntry
}

func NewExecutable(machineInfo *MachineInfo) *Executable {
	exe := &Executable{
		MachineInfo: machineInfo,
		Functions:   make([]BaseFunction, 0),
		Globals:     make([]CompiledGlobal, 0),
	}
	for _, f := range machineInfo.InternalFunctions {
		exe.Functions = append(exe.Functions, f)
	}
	return exe
}

func (e *Executable) GetTypeHierarchy() []*TypeHierarchyEntry {
	return e.TypeHierarchy
}

func (e *Executable) DumpOp(internalFunction bool) (s string) {
	m, g, t, f := e.MachineInfo, e.Globals, e.TypeHierarchy, e.Functions
	s = fmt.Sprintf("MachineInfo: { IntSize=%d, PointerSize=%d, LongIntSize=%d, DoubleSize=%d }\n",
		m.IntSize, m.PointerSize, m.LongIntSize, m.DoubleSize)

	if len(s) > 0 {
		s += "Globals:\n"
		for _, global := range g {
			vv := fmt.Sprintf("%v", global.InitialValue)
			if len(global.InitialValue) == 0 {
				vv = "null"
			}
			if _, ok := (global.VariableType).(*CArrayType); ok {
				vv = "[" + strings.Join(MapTo(global.InitialValue, func(i int, v Value) (string, bool) {
					return FmtInt64(v.Int64Value, 10), true
				}), ", ") + "]"
			}
			s += fmt.Sprintf("\t%s @%02d <%v> = %v\n",
				global.Name, global.StackOffset, global.VariableType, vv)
		}
	}

	if len(t) > 0 {
		s += "Types:\n"
		for _, typ := range t {
			s += fmt.Sprintf("\t%s <TypeId=%d Base=%d Name=%s>\n",
				typ.TypeName, typ.TypeId, typ.BaseTypeId, typ.TypeName)
		}
	}

	if len(f) > 0 {
		s += "Functions:\n"
		_ = internalFunction && MapF(f, func(i int, ifun *InternalFunction) {
			nc := IfAppend(ifun.GetNameContext(), "::")
			//goland:noinspection GoPrintFunctions
			s += fmt.Sprintf("\t%s #%02d `%v` %v\n",
				nc+ifun.GetName(), i, ifun.Action, ifun.GetFunctionType())
		})
		MapF(f, func(i int, cfun *CompiledFunction) {
			nc := IfAppend(cfun.GetNameContext(), "::")
			s += fmt.Sprintf("\t%s #%02d %v\n",
				nc+cfun.GetName(), i, cfun.GetFunctionType())
			oi := cfun.GetInstructions()
			for _, ins := range oi {
				op := strings.Replace(ins.Op.String(), "OpCode(", "Op(", 1)
				op = strings.Replace(op, "OpCode", "", 1)
				vv := ins.X.Int64Value
				s += fmt.Sprintf("\t\t%s %d\n",
					op, vv)
			}
		})
	}
	return
}

// ============================================================================
// CompiledGlobal — assumed to exist
// ============================================================================

type CompiledGlobal = CompiledVariable

// ============================================================================
// CInterpreter — assumed to exist
// ============================================================================

type CInterpreter struct {
	Stack         []Value
	SP            int
	Frames        []ExecutionFrame
	FI            int
	YieldedValue  int
	SleepTime     int
	RemainingTime int
	CpuSpeed      int
	exe           *Executable
	entrypoint    BaseFunction
}

// ============================================================================
// Label — assumed to exist
// ============================================================================

type Label struct {
	Index int
}

// ============================================================================
// Instruction — assumed to exist
// ============================================================================

type Instruction struct {
	Op    OpCode
	X     Value
	Label *Label
}

// ============================================================================
// BaseFunction and InternalFunctionAction — assumed to exist
// ============================================================================

type BaseFunction interface {
	GetName() string
	GetFunctionType() *CFunctionType
	GetNameContext() string
	GetInstructions() []Instruction
	GetIndex() int
	Init(state *CInterpreter)
	Step(state *CInterpreter, frame *ExecutionFrame)
}

type InternalFunctionAction func(state *CInterpreter)

type InternalFunction struct {
	name         string
	functionType *CFunctionType
	nameContext  string
	instructions []Instruction
	index        int
	Action       InternalFunctionAction

	functionTypeDelay func(ec TypeResolve) *CFunctionType
}

//goland:noinspection GoUnusedParameter
func NewInternalFunction(m *MachineInfo, prototype string, action InternalFunctionAction, funcEval FuncEval) *InternalFunction {
	proto := strings.TrimSpace(prototype)

	parenIdx := strings.Index(proto, "(")
	if parenIdx < 0 {
		return &InternalFunction{name: prototype, functionType: VoidProcedure, Action: action}
	}

	beforeParen := strings.TrimSpace(proto[:parenIdx])
	parts := strings.Fields(beforeParen)
	if len(parts) == 0 {
		return &InternalFunction{name: prototype, functionType: VoidProcedure, Action: action}
	}

	name := parts[len(parts)-1]
	nameContext := ""
	parenJdx := strings.LastIndex(name, "::")
	if parenJdx >= 0 {
		nameContext = name[:parenJdx]
		name = name[parenJdx+2:]
	}

	returnTypeStr := strings.Join(parts[:len(parts)-1], " ")
	paramsStr := proto[parenIdx+1:]
	if endIdx := strings.LastIndex(paramsStr, ")"); endIdx >= 0 {
		paramsStr = paramsStr[:endIdx]
	}
	paramsStr = strings.TrimSpace(paramsStr)

	functionType, notFound := BuildFunctionTypeByStr(nameContext, returnTypeStr, paramsStr, parseCTypeName)
	if funcEval == FuncEvalStatic || (funcEval == FuncEvalAuto && len(notFound) == 0) {
		return &InternalFunction{name: name, nameContext: nameContext, functionType: functionType, Action: action}
	}

	functionTypeDelay := func(resolve TypeResolve) *CFunctionType {
		functionTypeD, notFoundD := BuildFunctionTypeByStr(nameContext, returnTypeStr, paramsStr, func(name string) CType {
			if typ := parseCTypeName(name); typ != nil {
				return typ
			}
			return resolve.ResolveTypeNameString(name)
		})
		_ = notFoundD
		return functionTypeD
	}
	return &InternalFunction{name: name, nameContext: nameContext, functionType: functionType, Action: action, functionTypeDelay: functionTypeDelay}
}

func resolveQualifiedCXXType(typeStr string, typeResolve func(string) CType) CType {
	s := strings.TrimSpace(typeStr)
	if strings.HasPrefix(s, "const ") {
		s = strings.TrimSpace(s[6:])
	}
	isRef := false
	if strings.HasSuffix(s, "&") {
		s = strings.TrimSuffix(s, "&")
		s = strings.TrimSpace(s)
		isRef = true
	}
	inner := typeResolve(s)
	if inner == nil {
		return nil
	}
	if isRef {
		return NewCReferenceType(inner)
	}
	return inner
}

func BuildFunctionTypeByStr(nameContext, returnTypeStr, paramsStr string, typeResolve func(string) CType) (*CFunctionType, []string) {
	notFound := make([]string, 0)
	returnType := resolveQualifiedCXXType(returnTypeStr, typeResolve)
	if returnType == nil {
		returnType = SignedInt
		if len(returnTypeStr) > 0 {
			notFound = append(notFound, returnTypeStr)
		}
	}

	declaringTypeStr := nameContext
	parenJdx := strings.LastIndex(nameContext, "::")
	if parenJdx >= 0 {
		declaringTypeStr = nameContext[parenJdx+2:]
	}
	var declaringType CType
	if len(declaringTypeStr) > 0 {
		declaringType = typeResolve(declaringTypeStr)
		if declaringType == nil && len(declaringTypeStr) > 0 {
			notFound = append(notFound, declaringTypeStr)
		}
	}

	functionType := NewCFunctionType(returnType, declaringType != nil, declaringType)

	if paramsStr != "" && paramsStr != "void" {
		for _, ps := range splitFuncParams(paramsStr) {
			ps = strings.TrimSpace(ps)
			if ps == "" || ps == "..." {
				continue
			}
			paramParts := strings.Fields(ps)
			if len(paramParts) == 0 {
				continue
			}
			paramName := paramParts[len(paramParts)-1]
			if strings.HasPrefix(paramName, "*") {
				paramTypeStr := strings.Join(paramParts[:len(paramParts)-1], " ")
				paramName = strings.TrimPrefix(paramName, "*")
				pt := resolveQualifiedCXXType(paramTypeStr, typeResolve)
				if pt == nil {
					pt = SignedInt
					if len(paramTypeStr) > 0 {
						notFound = append(notFound, paramTypeStr)
					}
				}
				functionType.AddParameter(paramName, NewCPointerType(pt), nil)
			} else {
				paramTypeStr := strings.Join(paramParts[:len(paramParts)-1], " ")
				pt := resolveQualifiedCXXType(paramTypeStr, typeResolve)
				if pt == nil {
					pt = SignedInt
					if len(paramTypeStr) > 0 {
						notFound = append(notFound, paramTypeStr)
					}
				}
				functionType.AddParameter(paramName, pt, nil)
			}
		}
	}
	return functionType, notFound
}

func parseCTypeName(s string) CType {
	switch s {
	case "void":
		return CTypeVoid
	case "int":
		return SignedInt
	case "unsigned", "unsigned int":
		return UnsignedInt
	case "float":
		return Float
	case "double":
		return Double
	case "char":
		return SignedChar
	case "unsigned char":
		return UnsignedChar
	case "short", "short int":
		return SignedShortInt
	case "unsigned short", "unsigned short int":
		return UnsignedShortInt
	case "long", "long int":
		return SignedLongInt
	case "unsigned long", "unsigned long int":
		return UnsignedLongInt
	case "long long", "long long int":
		return SignedLongLongInt
	case "unsigned long long", "unsigned long long int":
		return UnsignedLongLongInt
	case "const char *", "const char*":
		return PointerToConstChar
	case "void *", "void*":
		return PointerToVoid
	case "bool":
		return Bool
	}
	if strings.HasSuffix(s, "*") {
		inner := parseCTypeName(strings.TrimSuffix(strings.TrimSpace(s), "*"))
		if inner != nil {
			return NewCPointerType(inner)
		}
	}
	return nil
}

func splitFuncParams(s string) []string {
	var result []string
	depth := 0
	start := 0
	for i, c := range s {
		switch c {
		case '(':
			depth++
		case ')':
			depth--
		case ',':
			if depth == 0 {
				result = append(result, s[start:i])
				start = i + 1
			}
		}
	}
	if start < len(s) {
		result = append(result, s[start:])
	}
	return result
}
func (f *InternalFunction) GetName() string                 { return f.name }
func (f *InternalFunction) GetFunctionType() *CFunctionType { return f.functionType }
func (f *InternalFunction) GetNameContext() string          { return f.nameContext }
func (f *InternalFunction) GetInstructions() []Instruction  { return f.instructions }
func (f *InternalFunction) GetIndex() int                   { return f.index }

//goland:noinspection GoUnusedParameter
func (f *InternalFunction) Init(state *CInterpreter) {}

// ============================================================================
// Constant slices needed by the parser
// ============================================================================

// CTypeVoid — minimal void type sentinel
var CTypeVoid CType = NewCVoidType()

func RunInterpreter(code string) {
	exe := Compile(code, nil, nil)
	interpreter := NewCInterpreter(exe)
	interpreter.Reset("start")
	interpreter.Run()
}

// Ensure math/big is not needed; use strconv throughout.
var _ = strconv.AppendUint
var _ = math.Float64frombits
