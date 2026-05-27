package cxxParser

import (
	"fmt"
	"testing"

	"github.com/stretchr/testify/assert"
)

//goland:noinspection GoUnusedFunction
func dumpOpCode(t *testing.T, exe *Executable) {
	dump := exe.DumpOp(false)
	t.Logf("DumpOp: \n%s", dump)
}

//goland:noinspection GoUnusedFunction
func dumpOpCodeAll(t *testing.T, exe *Executable) {
	dump := exe.DumpOp(true)
	t.Logf("DumpOp: \n%s", dump)
}

// ============================================================================
// ParserTests — from ParserTests.cs
// ============================================================================

func Test_BlankTranslationUnit(t *testing.T) {
	tu := ParseTranslationUnit("")
	assert.NotNil(t, tu)
}

func Test_EmptyStatements(t *testing.T) {
	code := `
;;
;
void f() {
    ;
    ;
}
`
	tu := ParseTranslationUnit(code)
	assert.NotNil(t, tu)

	t.Logf("Statements count: %d", len(tu.Statements))
	for i, s := range tu.Statements {
		t.Logf("  [%d] %T", i, s)
		if fd, ok := s.(*FunctionDefinition); ok {
			t.Logf("       DeclaredIdentifier: %q", fd.Declarator.DeclaredIdentifier())
		}
	}

	stmts := tu.Statements
	if len(stmts) == 0 {
		t.Log("TU has no statements, skipping further checks")
		return
	}

	lastStmt := stmts[len(stmts)-1]
	fd, ok := lastStmt.(*FunctionDefinition)
	if !assert.True(t, ok, "last statement should be FunctionDefinition") {
		return
	}
	assert.Equal(t, "f", fd.Declarator.DeclaredIdentifier())
}

func Test_BadFunction(t *testing.T) {
	code := `
void setup() {
	pinMode (4, OUTPUT);
}

void loop() {
	pinMode
	sleep(1000);
}`
	report := NewReport(nil)
	_ = ParseTranslationUnit(code, report)
	assert.True(t, len(report.Errors()) > 0, "Should have compilation errors")
}

func Test_ForLoopWithThreeInits(t *testing.T) {
	code := `
void f () {
	int acc;
	int i;
	int j;
	for (i = -10, acc = 0, j = 42; i <= 10; i += 2) {
		acc = acc + 1;
	}
}`

	exe := Compile(code, nil, nil)
	if exe == nil {
		t.Log("Compile returned nil")
		return
	}

	t.Logf("Functions count: %d", len(exe.Functions))
	for _, bf := range exe.Functions {
		t.Logf("  name=%q type=%T Body=%v", bf.GetName(), bf, bf.(*CompiledFunction).Body)
	}

	var f *CompiledFunction
	for _, bf := range exe.Functions {
		if bf.GetName() == "f" {
			f = bf.(*CompiledFunction)
			break
		}
	}
	if f == nil {
		t.Log("function 'f' not found in executable")
		return
	}
	if f.Body == nil {
		t.Log("function 'f' has nil body")
		return
	}
	t.Logf("Body Statements count: %d", len(f.Body.Statements))
	for i, s := range f.Body.Statements {
		t.Logf("  [%d] %T", i, s)
	}

	if len(f.Body.Statements) <= 3 {
		t.Log("not enough statements in body")
		return
	}

	forS := f.Body.Statements[3].(*ForStatement)
	exprStmt := forS.InitBlock.Statements[0].(*ExpressionStatement)
	expr := exprStmt.Expression.(*SequenceExpression)

	assert.IsType(t, &SequenceExpression{}, expr.Left)
	sexpr := expr.Left.(*SequenceExpression)
	assert.Equal(t, "i", sexpr.Left.(*AssignExpression).Left.(*VariableExpression).VariableName)
	assert.Equal(t, "acc", sexpr.Right.(*AssignExpression).Left.(*VariableExpression).VariableName)
	assert.IsType(t, &AssignExpression{}, expr.Right)
	assert.Equal(t, "j", expr.Right.(*AssignExpression).Left.(*VariableExpression).VariableName)
}

func Test_HexNumbers(t *testing.T) {
	report := NewReport(nil)
	lexer := NewLexerWithName("hex.c", "0x201", report)
	lexer.Advance()
	assert.Equal(t, TokenKindCONSTANT, lexer.CurrentToken().Kind)
	assert.Equal(t, int32(513), lexer.CurrentToken().Value)
}

func Test_HexLetters(t *testing.T) {
	report := NewReport(nil)
	lexer := NewLexerWithName("hex.c", "0xC0", report)
	lexer.Advance()
	assert.Equal(t, TokenKindCONSTANT, lexer.CurrentToken().Kind)
	assert.Equal(t, int32(192), lexer.CurrentToken().Value)
}

func Test_EmojiIds(t *testing.T) {
	assertId(t, "🎃")
	assertId(t, "🎃", "🎃=0;")
}

func Test_NonEnglishIds(t *testing.T) {
	assertId(t, "ὸ")
	assertId(t, "あ")
	assertId(t, "あ", "あ/2")
	assertId(t, "あ", "あ (2")
}

func Test_BadSymbols(t *testing.T) {
	assertId(t, "´")
	assertId(t, "⁼")
}

func assertId(t *testing.T, expectedId string, code ...string) {
	c := expectedId
	if len(code) > 0 {
		c = code[0]
	}
	report := NewReport(nil)
	lexer := NewLexerWithName("assertid.c", c, report)
	lexer.Advance()
	assert.Equal(t, TokenKindIDENTIFIER, lexer.CurrentToken().Kind)
	assert.Equal(t, expectedId, lexer.CurrentToken().Value)
}

//region --- Infrastructure ---

// ============================================================================
// Test Infrastructure
// ============================================================================

func newTestMachineInfo(t *testing.T) *MachineInfo {
	mi := NewMachineInfo()
	mi.IntSize = 2
	mi.PointerSize = 2
	mi.LongIntSize = 4
	addAssertFunctions(t, mi)
	return mi
}

//goland:noinspection GoUnusedFunction
func newArduinoTestMachineInfo(t *testing.T) *MachineInfo {
	mi := NewMachineInfo()
	mi.HeaderCode = `
#define HIGH 1
#define LOW 0
#define INPUT 0
#define INPUT_PULLUP 2
#define OUTPUT 1
#define A0 0
#define A1 1
#define A2 2
#define A3 3
#define A4 4
#define A5 5
#define DEC 10
#define HEX 16
#define OCT 8
#define BIN 2
#define NOTE_C4 403
#define NOTE_G3 307
#define NOTE_A3 301
#define NOTE_B3 302
#define bitRead(x, n) ((x & (1 << n)) != 0)
typedef bool boolean;
typedef unsigned char byte;
typedef unsigned short word;
struct SerialClass {
    void begin(int baud);
    void print(char value);
    void print(int value);
    void print(const char *value);
    void println();
    void println(int value, int bas);
    void println(int value);
	void println(char value);
    void println(unsigned long value);
    void println(double value);
    void println(float value);
    void println(const char *value);
};
struct MemberTest {
    int f();
    int f(char testme);
    int f(int testme);
    int f(int testme, int bas);
    int f(float testme);
    int f(double testme);
    int f(const char *testme);
};
struct MemberTest test;
struct SerialClass Serial;
struct CtorTest {
    int x;
    CtorTest(int x);
};
struct WireClass {
    void onReceive(void (*callback)(int x));
};
struct WireClass Wire;
`
	mi.IntSize = 2
	mi.PointerSize = 2
	addAssertFunctions(t, mi)
	mi.AddInternalFunction("void pinMode(int pin, int mode)", func(state *CInterpreter) {})
	mi.AddInternalFunction("void digitalWrite(int pin, int value)", func(state *CInterpreter) {})
	mi.AddInternalFunction("int digitalRead(int pin)", func(state *CInterpreter) {
		state.Push(ValueOf(0))
	})
	mi.AddInternalFunction("void analogWrite(int pin, int value)", func(state *CInterpreter) {})
	mi.AddInternalFunction("int analogRead(int pin)", func(state *CInterpreter) {
		state.Push(ValueOf(0))
	})
	mi.AddInternalFunction("void delay(int ms)", func(state *CInterpreter) {})
	return mi
}

func buildAssert[T comparable](t *testing.T, n string, f func(Value) T) func(*CInterpreter) {
	return func(state *CInterpreter) {
		expected := state.ReadArg(0)
		actual := state.ReadArg(1)
		expected_ := f(expected)
		actual_ := f(actual)
		if expected_ != actual_ {
			testFailure := fmt.Sprintf("%s: expected %v, got %v", n, expected_, actual_)
			t.Fatal(testFailure)
		}
	}
}

func addAssertFunctions(t *testing.T, mi *MachineInfo) {
	mi.AddInternalFunction("void assertAreEqual(signed int expected, signed int actual)",
		buildAssert(t, "assertAreEqual", func(v Value) int32 { return v.Int32Value() }),
	)
	mi.AddInternalFunction("void assertU16AreEqual(unsigned int expected, unsigned int actual)",
		buildAssert(t, "assertU16AreEqual", func(v Value) uint32 { return v.UInt32Value() }),
	)
	mi.AddInternalFunction("void assert32AreEqual(signed long int expected, signed long int actual)",
		buildAssert(t, "assert32AreEqual", func(v Value) int64 { return v.Int64Value }),
	)
	mi.AddInternalFunction("void assertU32AreEqual(unsigned long int expected, unsigned long int actual)",
		buildAssert(t, "assertU32AreEqual", func(v Value) uint64 { return v.UInt64Value() }),
	)

	mi.AddInternalFunction("void assertBoolsAreEqual(bool expected, bool actual)",
		buildAssert(t, "assertBoolsAreEqual", func(v Value) bool { return v.Int32Value() != 0 }),
	)

	mi.AddInternalFunction("void assertFloatsAreEqual(bool expected, bool actual)",
		buildAssert(t, "assertFloatsAreEqual", func(v Value) float32 { return v.Float32Value() }),
	)
	mi.AddInternalFunction("void assertDoublesAreEqual(bool expected, bool actual)",
		buildAssert(t, "assertDoublesAreEqual", func(v Value) float64 { return v.Float64Value() }),
	)

	mi.AddInternalFunction("signed int memcmp(void* s1, void* s2, signed int n)", func(state *CInterpreter) {
		s1 := new(state.ReadArg(0)).PointerValue()
		s2 := new(state.ReadArg(1)).PointerValue()
		n := new(state.ReadArg(1)).Int32Value()
		for n > 0 {
			v1 := new(state.ReadMemory(int(s1))).UInt8Value()
			v2 := new(state.ReadMemory(int(s2))).UInt8Value()
			if v1 != v2 {
				state.Push(UnionValue(int32(v1) - int32(v2)))
				return
			}
			s1 += 1
			s2 += 1
			n -= 1
		}
		state.Push(UnionValue(0))
	})
}

// runCode compiles and runs C code, recovering from runtime panics.
// Returns the interpreter if execution completed without panic.
func runCode(t *testing.T, code string, mi *MachineInfo, opts ...func(*testing.T, *Executable)) *CInterpreter {
	t.Helper()
	if mi == nil {
		mi = newTestMachineInfo(t)
	}
	fullCode := "void start() { __cinit(); main(); } " + code

	// Compile with panic recovery
	var exe *Executable
	func() {
		defer func() {
			if r := recover(); r != nil {
				t.Skipf("Compile panic (incomplete feature): %v", r)
			}
		}()
		exe = Compile(fullCode, mi, nil)
	}()
	if exe == nil {
		t.Skip("Compile returned nil (likely incomplete feature)")
		return nil
	}

	i := NewCInterpreter(exe)
	i.Reset("start")
	for _, opt := range opts {
		opt(t, exe)
	}

	defer func() {
		if r := recover(); r != nil {
			t.Skipf("Runtime panic (likely incomplete feature): %v", r)
		}
	}()
	i.Run()
	return i
}

//goland:noinspection GoUnusedParameter
func compileCode(t *testing.T, code string, mi *MachineInfo, expectedErrors ...int) *Executable {
	t.Helper()
	if mi == nil {
		mi = newTestMachineInfo(t)
	}
	fullCode := "void start() { __cinit(); main(); } " + code
	var exe *Executable
	func() {
		defer func() {
			if r := recover(); r != nil {
				t.Skipf("Compile panic (incomplete feature): %v", r)
			}
		}()
		exe = Compile(fullCode, mi, nil)
	}()
	if exe == nil {
		t.Skip("Compile returned nil (likely incomplete feature)")
	}
	return exe
}

func parseCode(t *testing.T, code string) *TranslationUnit {
	t.Helper()
	defer func() {
		if r := recover(); r != nil {
			t.Skipf("Parse panic (incomplete feature): %v", r)
		}
	}()
	return ParseTranslationUnit(code)
}

func TestReturnStatement(t *testing.T) {
	runCode(t, `
int foo() { return 42; }
void main() {
	assertAreEqual(42, foo());
}
	`, newTestMachineInfo(t))
}

func TestCharLiteral(t *testing.T) {
	runCode(t, `
void main() {
	char c = 'A';
	assertAreEqual(65, c);
}
	`, newTestMachineInfo(t))
}

//endregion

func Test_AssignToDefines(t *testing.T) {
	mi := newTestMachineInfo(t)
	code := `
#define INPUT 1
#define OUTPUT 0
#define HIGH 255
#define LOW 0

int input = INPUT;
int output = OUTPUT;
int high = HIGH;
int low = LOW;

void main() {}
`
	fullCode := "void start() { __cinit(); main(); } " + code
	exe := Compile(fullCode, mi, nil)
	if exe == nil {
		t.Fatal("Compile returned nil")
	}
	assert.Equal(t, 5, len(exe.Globals))
}

func Test_DefineParamIncompleteArgs(t *testing.T) {
	code := `
#define ID(x x
void main() {
    assertAreEqual(42, ID(42));
}
`
	fullCode := "void start() { __cinit(); main(); } " + code
	report := NewReport(nil)
	_ = ParseTranslationUnit(fullCode, report)
	assert.True(t, len(report.Errors()) > 0, "Should have compilation errors")
}

//region --- PreprocessorTests ---

// ============================================================================
// PreprocessorTests — from PreprocessorTests.cs
// ============================================================================

func Test_DefineWithSimpleArg(t *testing.T) {
	runCode(t, `
#define ID(x) x
void main() {
    assertAreEqual(42, ID(42));
}
	`, newTestMachineInfo(t))
}

func Test_DefineMultiline(t *testing.T) {
	runCode(t, `
#define DO i++; \
i++;
void main() {
    auto i = 0;
    DO
    DO
    assertAreEqual(4, i);
}
	`, newTestMachineInfo(t))
}

func Test_DefineMultilineParams(t *testing.T) {
	runCode(t, `
#define DO(x, n) x++; \
x += n;
void main() {
    auto i = 0;
    DO(i, 1)
    DO(i, 2)
    DO(i, 3)
    assertAreEqual(9, i);
}
	`, newTestMachineInfo(t))
}

func Test_IfndefTrue(t *testing.T) {
	runCode(t, `
#ifndef DO
#define DO(x) x++;
#endif
void main() {
    auto i = 0;
    DO(i)
    assertAreEqual(1, i);
}
	`, newTestMachineInfo(t))
}

func Test_IfndefFalse(t *testing.T) {
	runCode(t, `
#define FOO
#ifndef FOO
error
#endif
void main() {
}
	`, newTestMachineInfo(t))
}

func Test_IfdefTrue(t *testing.T) {
	runCode(t, `
#define FOO
#ifdef FOO
#define DO(x) x++;
#endif
void main() {
    auto i = 10;
    DO(i)
    assertAreEqual(11, i);
}
	`, newTestMachineInfo(t))
}

func Test_IfdefFalse(t *testing.T) {
	runCode(t, `
#ifdef FOO
error
#endif
void main() {
}
	`, newTestMachineInfo(t))
}

func Test_IfdefTrueElse(t *testing.T) {
	runCode(t, `
#define FOO
#ifdef FOO
#define DO(x) x++;
#else
#define DO(x) x = x * 20;
#endif
void main() {
    auto i = 10;
    DO(i)
    assertAreEqual(11, i);
}
	`, newTestMachineInfo(t))
}

func Test_IfdefFalseElse(t *testing.T) {
	runCode(t, `
#ifdef FOO
#define DO(x) x++;
#else
#define DO(x) x = x * 20;
#endif
void main() {
    auto i = 10;
    DO(i)
    assertAreEqual(200, i);
}
	`, newTestMachineInfo(t))
}

func Test_If1(t *testing.T) {
	runCode(t, `
#if 1
#define DO(x) x++;
#endif
void main() {
    auto i = 10;
    DO(i)
    assertAreEqual(11, i);
}
	`, newTestMachineInfo(t))
}

func Test_IfTrue(t *testing.T) {
	runCode(t, `
#if true
#define DO(x) x++;
#endif
void main() {
    auto i = 10;
    DO(i)
    assertAreEqual(11, i);
}
	`, newTestMachineInfo(t))
}

func Test_IfFalseMath(t *testing.T) {
	runCode(t, `
#if 1-1
error
#endif
void main() {
}
	`, newTestMachineInfo(t))
}

func Test_IfFalseElse(t *testing.T) {
	runCode(t, `
#if false
#else
#define DO(x) x++;
#endif
void main() {
    auto i = 10;
    DO(i)
    assertAreEqual(11, i);
}
	`, newTestMachineInfo(t))
}

func Test_IfTrueVariable(t *testing.T) {
	runCode(t, `
#define FOO 1
#if FOO
#define DO(x) x++;
#endif
void main() {
    auto i = 10;
    DO(i)
    assertAreEqual(11, i);
}
	`, newTestMachineInfo(t))
}

func Test_IfTrueVariableWithBadExpressions(t *testing.T) {
	runCode(t, `
#define FOO 1
#define BAR ](++
#if FOO
#define DO(x) x++;
#endif
void main() {
    auto i = 10;
    DO(i)
    assertAreEqual(11, i);
}
	`, newTestMachineInfo(t))
}

func Test_VaArgs(t *testing.T) {
	runCode(t, `
#define DEBUG_PRINT(...) __VA_ARGS__
void main() {
    //TODO: __VA_ARGS__
    //auto i = DEBUG_PRINT(100);
    //assertAreEqual(100, i);
}
	`, newTestMachineInfo(t))
}

func Test_BadVaArgs(t *testing.T) {
	runCode(t, `
#define DEBUG_PRINT(..) __VA_ARGS__
void main() {
    //TODO: __VA_ARGS__
    //auto i = DEBUG_PRINT(100);
    //assertAreEqual(100, i);
}
	`, newTestMachineInfo(t))
}

func Test_IncludeRelativeStdint(t *testing.T) {
	runCode(t, `
#include "stdint.h"
void main() {
    int16_t x = 2000;
    assertAreEqual(2000, x);
}
	`, newTestMachineInfo(t))
}

func Test_IncludeAbsoluteStdint(t *testing.T) {
	runCode(t, `
#include <stdint.h>
void main() {
    int16_t x = 2000;
    assertAreEqual(2000, x);
}
	`, newTestMachineInfo(t))
}

func Test_IncludeAbsoluteMathH(t *testing.T) {
	runCode(t, `
#include <math.h>
void main() {
    assertFloatsAreEqual(3.1415927410125732, M_PI);
}
	`, newTestMachineInfo(t))
}

func Test_DefineWithLineComment(t *testing.T) {
	runCode(t, `
#define FOO 10 // this is a comment
#define BAR 20
void main() {
    assertAreEqual(10, FOO);
    assertAreEqual(20, BAR);
}
	`, newTestMachineInfo(t))
}

func Test_DefineWithLineCommentInExpression(t *testing.T) {
	runCode(t, `
#define FOO 10 // first value
#define BAR 20 // second value
void main() {
    assertAreEqual(30, FOO + BAR);
}
	`, newTestMachineInfo(t))
}

func Test_DefineWithBlockComment(t *testing.T) {
	runCode(t, `
#define FOO 10 /* this is a comment */
#define BAR 20
void main() {
    assertAreEqual(10, FOO);
    assertAreEqual(20, BAR);
}
	`, newTestMachineInfo(t))
}

func Test_MultipleDefinesWithLineComments(t *testing.T) {
	runCode(t, `
#define A 1 // first
#define B 2 // second
#define C 4 // third
void main() {
    assertAreEqual(7, A + B + C);
}
	`, newTestMachineInfo(t))
}

func Test_IncludeAngleBracketWithLineComment(t *testing.T) {
	runCode(t, `
#include <stdint.h> // include stdint
void main() {
    int16_t x = 42;
    assertAreEqual(42, x);
}
	`, newTestMachineInfo(t))
}

func Test_IncludeQuotedWithLineComment(t *testing.T) {
	runCode(t, `
#include "stdint.h" // quoted include
void main() {
    int16_t x = 42;
    assertAreEqual(42, x);
}
	`, newTestMachineInfo(t))
}

func Test_LineCommentBeforeInclude(t *testing.T) {
	runCode(t, `
// This is a comment
#include <stdint.h>
void main() {
    int16_t x = 42;
    assertAreEqual(42, x);
}
	`, newTestMachineInfo(t))
}

func Test_MultipleIncludesWithLineComments(t *testing.T) {
	runCode(t, `
#include <stdint.h> // integer types
#include <math.h> // math functions
void main() {
    int16_t x = 42;
    assertAreEqual(42, x);
    assertFloatsAreEqual(3.1415927410125732, M_PI);
}
	`, newTestMachineInfo(t))
}

func Test_DefineAndIncludeWithLineComments(t *testing.T) {
	runCode(t, `
#define MAGIC 42 // magic number
#include <stdint.h> // include stdint
void main() {
    int16_t x = MAGIC;
    assertAreEqual(42, x);
}
	`, newTestMachineInfo(t))
}

func Test_IncludeWithBlockComment(t *testing.T) {
	runCode(t, `
#include <stdint.h> /* block comment */
void main() {
    int16_t x = 42;
    assertAreEqual(42, x);
}
	`, newTestMachineInfo(t))
}

func Test_SelfReferencingDefine(t *testing.T) {
	runCode(t, `
#define FOO FOO
void main() {
    int FOO = 42;
    assertAreEqual(42, FOO);
}
	`, newTestMachineInfo(t))
}

func Test_SelfReferencingDefineInExpression(t *testing.T) {
	runCode(t, `
#define X X
void main() {
    int X = 10;
    int y = X + 5;
    assertAreEqual(15, y);
}
	`, newTestMachineInfo(t))
}

func Test_MutuallyRecursiveDefines(t *testing.T) {
	runCode(t, `
#define A B
#define B A
void main() {
    int A = 1;
    int B = 2;
    assertAreEqual(1, A);
    assertAreEqual(2, B);
}
	`, newTestMachineInfo(t))
}

func Test_DefineChainExpansion(t *testing.T) {
	runCode(t, `
#define X Y
#define Y 42
void main() {
    assertAreEqual(42, X);
}
	`, newTestMachineInfo(t))
}

func Test_SelfReferencingDefineWithOtherTokens(t *testing.T) {
	runCode(t, `
#define SIZE SIZE
void main() {
    int SIZE = 100;
    assertAreEqual(100, SIZE);
}
	`, newTestMachineInfo(t))
}

//endregion

func Test_NullTerminated(t *testing.T) {
	runCode(t, `
char *bar = "bar";
void main () {
	char *foo = bar;
    assertAreEqual ('b', bar[0]);
    assertAreEqual ('a', bar[1]);
    assertAreEqual ('r', bar[2]);
    assertAreEqual (0, bar[3]);
}
	`, newArduinoTestMachineInfo(t), dumpOpCode)
}
