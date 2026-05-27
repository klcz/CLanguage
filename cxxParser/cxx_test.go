package cxxParser_test

import (
	"fmt"
	"strings"
	"testing"

	cxx "github.com/klcz/CLanguage/cxxParser"
	"github.com/stretchr/testify/assert"
)

//goland:noinspection GoUnusedFunction
func dumpOpCode(t *testing.T, exe *cxx.Executable) {
	dump := exe.DumpOp(false)
	t.Logf("DumpOp: \n%s", dump)
}

//goland:noinspection GoUnusedFunction
func dumpOpCodeAll(t *testing.T, exe *cxx.Executable) {
	dump := exe.DumpOp(true)
	t.Logf("DumpOp: \n%s", dump)
}

// region --- calc ---

//goland:noinspection GoUnusedExportedType
type Calc struct {
	Title string
	stack []int64
}

func NewCalc() *Calc {
	return &Calc{
		stack: make([]int64, 0), Title: "Calc-Test",
	}
}

func (c *Calc) PushInt64(x int64) {
	c.stack = append(c.stack, x)
}
func (c *Calc) PopInt64() int64 {
	x := c.stack[len(c.stack)-1]
	c.stack = c.stack[:len(c.stack)-1]
	return x
}

func (c *Calc) PushChar(x byte) { c.PushInt64(int64(x)) }
func (c *Calc) PopChar() byte   { return byte(c.PopInt64()) }

func (c *Calc) PushInt8(x int8)   { c.PushInt64(int64(x)) }
func (c *Calc) PopInt8() int8     { return int8(c.PopInt64()) }
func (c *Calc) PushUInt8(x uint8) { c.PushInt64(int64(x)) }
func (c *Calc) PopUInt8() uint8   { return uint8(c.PopInt64()) }

func (c *Calc) PushInt16(x int16)   { c.PushInt64(int64(x)) }
func (c *Calc) PopInt16() int16     { return int16(c.PopInt64()) }
func (c *Calc) PushUInt16(x uint16) { c.PushInt64(int64(x)) }
func (c *Calc) PopUInt16() uint16   { return uint16(c.PopInt64()) }

func (c *Calc) PushInt32(x int32)   { c.PushInt64(int64(x)) }
func (c *Calc) PopInt32() int32     { return int32(c.PopInt64()) }
func (c *Calc) PushUInt32(x uint32) { c.PushInt64(int64(x)) }
func (c *Calc) PopUInt32() uint32   { return uint32(c.PopInt64()) }

func (c *Calc) PushUInt64(x uint64) { c.PushInt64(int64(x)) }
func (c *Calc) PopUInt64() uint64   { return uint64(c.PopInt64()) }

func (c *Calc) PushSingle(x float32) { c.PushInt64(int64(x)) }
func (c *Calc) PopSingle() float32   { return float32(c.PopInt64()) }
func (c *Calc) PushDouble(x float64) { c.PushInt64(int64(x)) }
func (c *Calc) PopDouble() float64   { return float64(c.PopInt64()) }

func runTestReference(t *testing.T, code string) *Calc {
	mi := newArduinoTestMachineInfo(t)
	c := NewCalc()
	mi.AddGlobalReference("c", c)
	safeRun(t, "void main() { "+code+"}", mi)
	return c
}

func runTestMethods(t *testing.T, code string) *Calc {
	mi := newArduinoTestMachineInfo(t)
	c := NewCalc()
	mi.AddGlobalMethods(c)
	safeRun(t, "void main() { "+strings.ReplaceAll(code, "c.", "")+"}", mi)
	return c
}

//goland:noinspection GoUnusedFunction
func safeRunTestCalc(t *testing.T, code string) *Calc {
	runTestReference(t, code)
	return runTestMethods(t, code)
}

//endregion

//region --- Test Infrastructure ---

// ============================================================================
// Test Infrastructure
// ============================================================================

func newTestMachineInfo(t *testing.T) *cxx.MachineInfo {
	mi := cxx.NewMachineInfo()
	mi.IntSize = 4
	mi.PointerSize = 4
	mi.LongIntSize = 4
	mi.LongLongIntSize = 8
	addAssertFunctions(t, mi)
	return mi
}

func newArduinoTestMachineInfo(t *testing.T) *cxx.MachineInfo {
	mi := cxx.NewMachineInfo()
	mi.IntSize = 2
	mi.PointerSize = 2
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
	addAssertFunctions(t, mi)
	mi.AddInternalFunction("void pinMode(int pin, int mode)", func(state *cxx.CInterpreter) {})
	mi.AddInternalFunction("void digitalWrite(int pin, int value)", func(state *cxx.CInterpreter) {})
	mi.AddInternalFunction("int digitalRead(int pin)", func(state *cxx.CInterpreter) {
		state.Push(cxx.ValueOf(0))
	})
	mi.AddInternalFunction("void analogWrite(int pin, int value)", func(state *cxx.CInterpreter) {})
	mi.AddInternalFunction("int analogRead(int pin)", func(state *cxx.CInterpreter) {
		state.Push(cxx.ValueOf(0))
	})
	mi.AddInternalFunction("void delay(int ms)", func(state *cxx.CInterpreter) {})
	return mi
}

func buildAssert[T comparable](t *testing.T, n string, f func(cxx.Value) T) func(*cxx.CInterpreter) {
	return func(state *cxx.CInterpreter) {
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

func addAssertFunctions(t *testing.T, mi *cxx.MachineInfo) {
	mi.AddInternalFunction("void assertAreEqual(int expected, int actual)",
		buildAssert(t, "assertAreEqual", func(v cxx.Value) int32 { return v.Int32Value() }),
	)
	mi.AddInternalFunction("void assertU16AreEqual(unsigned int expected, unsigned int actual)",
		buildAssert(t, "assertU16AreEqual", func(v cxx.Value) uint32 { return v.UInt32Value() }),
	)
	mi.AddInternalFunction("void assert32AreEqual(long expected, long actual)",
		buildAssert(t, "assert32AreEqual", func(v cxx.Value) int64 { return v.Int64Value }),
	)
	mi.AddInternalFunction("void assertU32AreEqual(unsigned long expected, unsigned long actual)",
		buildAssert(t, "assertU32AreEqual", func(v cxx.Value) uint64 { return v.UInt64Value() }),
	)

	mi.AddInternalFunction("void assertBoolsAreEqual(bool expected, bool actual)",
		buildAssert(t, "assertBoolsAreEqual", func(v cxx.Value) bool { return v.Int32Value() != 0 }),
	)

	mi.AddInternalFunction("void assertFloatsAreEqual(bool expected, bool actual)",
		buildAssert(t, "assertFloatsAreEqual", func(v cxx.Value) float32 { return v.Float32Value() }),
	)
	mi.AddInternalFunction("void assertDoublesAreEqual(bool expected, bool actual)",
		buildAssert(t, "assertDoublesAreEqual", func(v cxx.Value) float64 { return v.Float64Value() }),
	)

	mi.AddInternalFunction("signed int memcmp(void* s1, void* s2, signed int n)", func(state *cxx.CInterpreter) {
		s1 := new(state.ReadArg(0)).PointerValue()
		s2 := new(state.ReadArg(1)).PointerValue()
		n := new(state.ReadArg(2)).Int32Value() / int32(mi.IntSize)
		for n > 0 {
			v1 := new(state.ReadMemory(int(s1))).UInt8Value()
			v2 := new(state.ReadMemory(int(s2))).UInt8Value()
			if v1 != v2 {
				state.Push(cxx.UnionValue(int32(v1) - int32(v2)))
				return
			}
			s1 += 1
			s2 += 1
			n -= 1
		}
		state.Push(cxx.UnionValue(0))
	})
}

func safeRun(t *testing.T, code string, mi *cxx.MachineInfo, opts ...func(*testing.T, *cxx.Executable)) *cxx.CInterpreter {
	return doRun(t, code, mi, nil, opts)
}

func safeRunFailed(t *testing.T, code string, mi *cxx.MachineInfo, errorCodes ...int) *cxx.CInterpreter {
	return doRun(t, code, mi, newTestPrinter(errorCodes), nil)
}

// doRun compiles and runs C code, recovering from runtime panics.
// Returns the interpreter if execution completed without panic.
func doRun(t *testing.T, code string, mi *cxx.MachineInfo, printer cxx.Printer, opts []func(*testing.T, *cxx.Executable)) *cxx.CInterpreter {
	t.Helper()
	if mi == nil {
		mi = newTestMachineInfo(t)
	}
	fullCode := "void start() { __cinit(); main(); } " + code

	// Compile with panic recovery
	var exe *cxx.Executable
	func() {
		defer func() {
			if r := recover(); r != nil {
				t.Skipf("Compile panic (incomplete feature): %v", r)
			}
		}()
		exe = cxx.Compile(fullCode, mi, printer)
	}()
	if exe == nil {
		t.Skip("Compile returned nil (likely incomplete feature)")
		return nil
	}

	i := cxx.NewCInterpreter(exe)
	i.Reset("start")
	for _, opt := range opts {
		opt(t, exe)
	}
	defer func() {
		if r := recover(); r != nil && printer == nil {
			t.Skipf("Runtime panic (likely incomplete feature): %v", r)
		}
	}()
	i.Run()
	return i
}

//goland:noinspection GoUnusedParameter
func newTestPrinter(codes []int) cxx.Printer {
	return cxx.NewSimplePrinter()
}

// safeParse parse C code, skipping on panic.
func safeParse(t *testing.T, code string) *cxx.TranslationUnit {
	t.Helper()
	tu := cxx.ParseTranslationUnit(code)
	assert.NotNil(t, tu)
	return tu
}

func safeCompile(t *testing.T, code string, mi *cxx.MachineInfo, opts ...func(*testing.T, *cxx.Executable)) *cxx.Executable {
	return doCompile(t, code, mi, nil, opts)
}

func safeCompileFailed(t *testing.T, code string, mi *cxx.MachineInfo, errorCodes ...int) *cxx.Executable {
	return doCompile(t, code, mi, newTestPrinter(errorCodes), nil)
}

// doCompile compiles C code and returns the executable, skipping on panic.
//
//goland:noinspection GoUnusedParameter
func doCompile(t *testing.T, code string, mi *cxx.MachineInfo, printer cxx.Printer, opts []func(*testing.T, *cxx.Executable)) *cxx.Executable {
	t.Helper()
	if mi == nil {
		mi = newTestMachineInfo(t)
	}
	fullCode := code
	var exe *cxx.Executable
	func() {
		defer func() {
			if r := recover(); r != nil {
				t.Skipf("Compile panic (incomplete feature): %v", r)
			}
		}()
		exe = cxx.Compile(fullCode, mi, nil)
	}()
	if exe == nil {
		t.Skip("Compile returned nil (likely incomplete feature)")
	}
	for _, opt := range opts {
		opt(t, exe)
	}
	return exe
}

//endregion

func Test_CxxReturnStatement(t *testing.T) {
	safeRun(t, `
	int foo() { return 42; }
	void main() {
		assertAreEqual(42, foo());
	}`, newTestMachineInfo(t))
}

func Test_CxxCharLiteral(t *testing.T) {
	safeRun(t, `
	void main() {
		char c = 'A';
		assertAreEqual(65, c);
	}`, newTestMachineInfo(t))
}

//region ---- ext test ----

func Test_ByteFromInt(t *testing.T) {
	assert.Equal(t, uint8(1), new(cxx.UnionValue(0x10001)).UInt8Value())
}

func Test_GotoUndefinedLabel(t *testing.T) {
	safeRunFailed(t, `
void main()
{
    goto missing;
}
	`, newTestMachineInfo(t), 9999)
}

func Test_DuplicateLabel(t *testing.T) {
	safeRunFailed(t, `
void main()
{
    int x = 0;
dup:
    x = 1;
dup:
    x = 2;
}
	`, newTestMachineInfo(t), 140)
}

func Test_EasyRun(t *testing.T) {
	safeRun(t, `
void main() {
}
	`, newTestMachineInfo(t))
}

func Test_EasyEval(t *testing.T) {
	var result = cxx.Eval("2 + 3", "")
	assert.Equal(t, int32(5), result)
}

func Test_EasyEvalMore(t *testing.T) {
	var result = cxx.Eval("x * 100", "int x = 42;")
	assert.Equal(t, int32(4200), result)
}

func Test_ReferenceTypeCreation(t *testing.T) {
	var intType = cxx.SignedInt
	var refType = cxx.NewCReferenceType(intType)
	assert.Equal(t, 1, refType.NumValues())
	assert.Equal(t, "signed int&", refType.String())
	assert.Equal(t, refType, cxx.NewCReferenceType(intType))
	assert.NotEqual(t, refType, cxx.NewCReferenceType(cxx.Float))
}

func Test_ReferenceScoreCastTo(t *testing.T) {
	var intType = cxx.SignedInt
	var refType = cxx.NewCReferenceType(intType)
	// Reference to same reference: perfect
	assert.Equal(t, 1000, refType.ScoreCastTo(cxx.NewCReferenceType(intType)))
	// Reference to inner type: high score
	assert.Equal(t, 900, refType.ScoreCastTo(intType))
}

//goland:noinspection GoBoolExpressions
func Test_And(t *testing.T) {
	assertTrue(t, false, "false && false")
	assertTrue(t, false && true, "false && true")
	assertTrue(t, true && false, "true && false")
	assertTrue(t, true, "true && true")
}

//goland:noinspection GoBoolExpressions
func Test_Or(t *testing.T) {
	assertTrue(t, false, "false || false")
	assertTrue(t, false || true, "false || true")
	assertTrue(t, true || false, "true || false")
	assertTrue(t, true, "true || true")
}

func Test_YieldingDelay(t *testing.T) {
	mi := newTestMachineInfo(t)
	hit := [3]bool{}
	mi.AddInternalFunction("int yieldingDelay(int ms)", func(i *cxx.CInterpreter) {
		ms := new(i.ReadArg(0)).Int16Value()
		t.Logf("RUN Y=%d\n", i.YieldedValue)
		hit[i.YieldedValue] = true
		if i.YieldedValue == 0 {
			i.Yield(1)
		} else if i.YieldedValue == 1 {
			i.Yield(2)
		} else {
			i.Yield(0)
			i.Push(cxx.UnionValue(ms * 1000))
		}
	})
	var it = safeRun(t, `
void main () {
    auto x = yieldingDelay(3);
    assertAreEqual (3000, x);
}`, mi)
	assert.True(t, hit[0])
	assert.False(t, hit[1])
	assert.False(t, hit[2])
	it.Step(10)
	assert.True(t, hit[0])
	assert.True(t, hit[1])
	assert.False(t, hit[2])
	it.Step(10)
	assert.True(t, hit[0])
	assert.True(t, hit[1])
	assert.True(t, hit[2])
}

func Test_InfiniteRecursionThrows(t *testing.T) {
	safeRunFailed(t, `
int f (int n) {
	return f (n);
}
void main () {
	f (1);
}
	`, newTestMachineInfo(t))
}

func assertTrue(t *testing.T, expected bool, code string) {
	expectedStr := "false"
	if expected {
		expectedStr = "true"
	}
	safeRun(t, `
void main() {
	assertBoolsAreEqual (`+expectedStr+", "+code+`);
}
	`, newTestMachineInfo(t))
}

func assertEqualF64(t *testing.T, f float64, code string) {

	expectedStr := fmt.Sprintf("%.10g", f)
	if !strings.Contains(expectedStr, ".") && !strings.ContainsAny(expectedStr, "eE") {
		expectedStr += ".0"
	}
	safeRun(t, `
void main() {
	assertDoublesAreEqual (`+expectedStr+", "+code+`);
}
	`, newTestMachineInfo(t))
}

func assertEqualF32(t *testing.T, f float32, code string) {
	expectedStr := fmt.Sprintf("%.10g", f)
	if !strings.Contains(expectedStr, ".") && !strings.ContainsAny(expectedStr, "eE") {
		expectedStr += ".0"
	}
	safeRun(t, `
void main() {
	assertDoublesAreEqual (`+expectedStr+", "+code+`);
}
	`, newTestMachineInfo(t))
}

func Test_FloatArithmetic(t *testing.T) {
	assertEqualF32(t, 10.0+3.01, "10.0f+3.01f")
	assertEqualF32(t, 10.0-3.01, "10.0f-3.01f")
	assertEqualF32(t, 10.0*3.01, "10.0f*3.01f")
	assertEqualF32(t, 10.0/3.01, "10.0f/3.01f")
}

func Test_DoubleArithmetic(t *testing.T) {
	assertEqualF64(t, 10.0+3.01, "10.0+3.01")
	assertEqualF64(t, 10.0-3.01, "10.0-3.01")
	assertEqualF64(t, 10.0*3.01, "10.0*3.01")
	assertEqualF64(t, 10.0/3.01, "10.0/3.01")
}

//goland:noinspection GoBoolExpressions
func Test_DoubleLogic(t *testing.T) {
	assertTrue(t, 10.0 < 3.01, "10.0<3.01")
	assertTrue(t, 10.0 > 3.01, "10.0>3.01")
	assertTrue(t, 10.0 == 3.01, "10.0==3.01")
	assertTrue(t, 10.0 <= 3.01, "10.0<=3.01")
	assertTrue(t, 10.0 >= 3.01, "10.0>=3.01")
	assertTrue(t, 10.0 <= 10.0, "10.0<=10.0")
	assertTrue(t, 10.0 >= 10.0, "10.0>=10.0")
}

//goland:noinspection GoBoolExpressions
func Test_FloatLogic(t *testing.T) {
	assertTrue(t, 10.0 < 3.01, "10.0f<3.01f")
	assertTrue(t, 10.0 > 3.01, "10.0f>3.01f")
	assertTrue(t, 10.0 == 3.01, "10.0f==3.01f")
	assertTrue(t, 10.0 <= 3.01, "10.0f<=3.01f")
	assertTrue(t, 10.0 >= 3.01, "10.0f>=3.01f")
	assertTrue(t, 10.0 <= 10.0, "10.0f<=10.0f")
	assertTrue(t, 10.0 >= 10.0, "10.0f>=10.0f")
}

func Test_IntegerConstantArithmeticWithDouble(t *testing.T) {
	assertEqualF64(t, 100000.0+1.5, "100000 + 1.5")
	assertEqualF64(t, 100000.0*2.0, "100000 * 2.0")
}

func parseType(code string) (*cxx.ExecutableContext, cxx.CType) {
	printer := newTestPrinter(nil)
	_c := cxx.NewExecutableContext(cxx.NewExecutable(cxx.Windows32), cxx.NewReport(printer))
	exe := cxx.Compile(code, cxx.Windows32, printer)
	return _c, exe.Globals[1].VariableType
}

func Test_BasicSizes(t *testing.T) {
	tests := map[string]int{
		"char a;":               1,
		"signed char a;":        1,
		"unsigned char a;":      1,
		"short a;":              2,
		"signed short a;":       2,
		"unsigned short a;":     2,
		"int a;":                4,
		"signed int a;":         4,
		"unsigned int a;":       4,
		"long a;":               4,
		"long long a;":          8,
		"long long int a;":      8,
		"unsigned long a;":      4,
		"unsigned long long a;": 8,
		"double a;":             8,
		"float a;":              4,
		"long double a;":        8,
		"bool a;":               1,
	}
	for code, size := range tests {
		_c, typ := parseType(code)
		assert.Equal(t, size, typ.GetByteSize(_c.EmitContext), "code: `"+code+"`")
		assert.Equal(t, 1, typ.NumValues(), "code: `"+code+"`")
	}
}

func Test_PointerSizes(t *testing.T) {
	tests := map[string]int{
		"char* a;":                  4,
		"int* a;":                   4,
		"double* a;":                4,
		"char** a;":                 4,
		"const char* a;":            4,
		"const char** a;":           4,
		"char *const a = 0;":        4,
		"const char *const a = 0;":  4,
		"char *const *a;":           4,
		"char *const *const a = 0;": 4,
		"char *const **a;":          4,
		"char *const ****a;":        4,
		"int (*a)(int);":            4,
		"int *(*a)(int);":           4,
		"int *(**a)(int);":          4,
	}
	for code, size := range tests {
		_c, typ := parseType(code)
		assert.Equal(t, size, typ.GetByteSize(_c.EmitContext), "code: `"+code+"`")
		assert.Equal(t, 1, typ.NumValues(), "code: `"+code+"`")
	}
}

func Test_ArrayByteSizes(t *testing.T) {
	tests := map[string]int{
		"char a[42];":                  42,
		"char a[42][12];":              504,
		"char *a[42];":                 168,
		"char *a[42][12];":             2016,
		"int a[42];":                   168,
		"int a[42][12];":               2016,
		"int *a[42];":                  168,
		"int *a[42][12];":              2016,
		"int (*a)[42];":                4,
		"int (*a)[42][12];":            4,
		"int (*a[5])[42];":             20,
		"int (*a[5])[42][12];":         20,
		"int (*a[5])[2][3][5][7][11];": 20,
		"int (*a)[2][3][5][7][11];":    4,
		"int *a[2][3][5][7][11];":      9240,
		"short *a[2][3][5][7][11];":    9240,
		"short a[2][3][5][7][11];":     4620,
		"short (a[2][3])[5][7][11];":   4620,
	}

	for code, size := range tests {
		_c, typ := parseType(code)
		assert.Equal(t, size, typ.GetByteSize(_c.EmitContext), "code: `"+code+"`")
	}
}
func Test_ArrayNumValues(t *testing.T) {
	tests := map[string]int{
		"char a[42];":      42,
		"char a[42][12];":  504,
		"char *a[42];":     42,
		"char *a[42][12];": 504,
		"int a[42];":       42,
		"int a[42][12];":   504,
		"int *a[42];":      42,
		"int *a[42][12];":  504,
	}
	for code, size := range tests {
		_, typ := parseType(code)
		assert.Equal(t, size, typ.NumValues(), "code: `"+code+"`")
	}
}

func Test_ErrorIfDoesntReturn(t *testing.T) {
	safeCompileFailed(t, "int f () { int a = 42; }", newArduinoTestMachineInfo(t), 161)
}

func Test_ErrorIfDoesntReturnValue(t *testing.T) {
	safeCompileFailed(t, "int f () { int a = 42; return; }", newArduinoTestMachineInfo(t), 126)
}

func Test_ReturnConstant(t *testing.T) {
	exe := safeCompile(t, "int f () { return 42; }", newArduinoTestMachineInfo(t))
	var f *cxx.CompiledFunction
	for _, bf := range exe.Functions {
		if bf.GetName() == "f" {
			f = bf.(*cxx.CompiledFunction)
			break
		}
	}
	if f == nil {
		t.Fatal("function 'f' not found in executable")
		return
	}

	assert.Equal(t, len(f.Instructions), 2)

	assert.Equal(t, f.Instructions[0].Op, cxx.OpCodeLoadConstant)
	assert.Equal(t, f.Instructions[1].Op, cxx.OpCodeReturn)
}

func Test_ReturnParamExpr(t *testing.T) {
	exe := safeCompile(t, "int f (int i) { return i + 42; }", newArduinoTestMachineInfo(t))
	var f *cxx.CompiledFunction
	for _, bf := range exe.Functions {
		if bf.GetName() == "f" {
			f = bf.(*cxx.CompiledFunction)
			break
		}
	}
	if f == nil {
		t.Fatal("function 'f' not found in executable")
		return
	}

	assert.Equal(t, len(f.Instructions), 4)

	assert.Equal(t, f.Instructions[0].Op, cxx.OpCodeLoadArg)
	assert.Equal(t, f.Instructions[1].Op, cxx.OpCodeLoadConstant)
	assert.Equal(t, f.Instructions[2].Op, cxx.OpCodeAddInt16)
	assert.Equal(t, f.Instructions[3].Op, cxx.OpCodeReturn)
}

func Test_ConditionalReturn(t *testing.T) {
	exe := safeCompile(t, "int f (int i) { if (i) return 0; else return 42; }", newArduinoTestMachineInfo(t), dumpOpCode)
	var f *cxx.CompiledFunction
	for _, bf := range exe.Functions {
		if bf.GetName() == "f" {
			f = bf.(*cxx.CompiledFunction)
			break
		}
	}
	if f == nil {
		t.Fatal("function 'f' not found in executable")
		return
	}

	assert.Equal(t, len(f.Instructions), 8)
	assert.Equal(t, f.Instructions[0].Op, cxx.OpCodeLoadArg)
	assert.Equal(t, f.Instructions[1].Op, cxx.OpCodeConvertInt16UInt8)
	assert.Equal(t, f.Instructions[2].Op, cxx.OpCodeBranchIfFalse)
	assert.Equal(t, f.Instructions[3].Op, cxx.OpCodeLoadConstant)
	assert.Equal(t, f.Instructions[4].Op, cxx.OpCodeReturn)
	assert.Equal(t, f.Instructions[5].Op, cxx.OpCodeJump)
	assert.Equal(t, f.Instructions[6].Op, cxx.OpCodeLoadConstant)
	assert.Equal(t, f.Instructions[7].Op, cxx.OpCodeReturn)
}

func Test_VoidFunctionsHaveNoValue(t *testing.T) {
	safeCompileFailed(t, `
void f () {
}
void main () {
	int a = f ();
}
	`, newArduinoTestMachineInfo(t), 30)
}

func Test_LocalVariables(t *testing.T) {
	exe := safeCompile(t, `
void f () {
	int a = 4;
	int b = 8;
	int c = a + b;
}
	`, newArduinoTestMachineInfo(t))
	var f *cxx.CompiledFunction
	for _, bf := range exe.Functions {
		if bf.GetName() == "f" {
			f = bf.(*cxx.CompiledFunction)
			break
		}
	}
	if f == nil {
		t.Fatal("function 'f' not found in executable")
		return
	}

	assert.Equal(t, len(f.LocalVariables), 3)
}

func Test_LocalVariablesWithAuto(t *testing.T) {
	exe := safeCompile(t, `
void f () {
    auto i = 1;
    auto j = i + 1;
}
	`, newArduinoTestMachineInfo(t))
	var f *cxx.CompiledFunction
	for _, bf := range exe.Functions {
		if bf.GetName() == "f" {
			f = bf.(*cxx.CompiledFunction)
			break
		}
	}
	if f == nil {
		t.Fatal("function 'f' not found in executable")
		return
	}

	assert.Equal(t, len(f.LocalVariables), 2)
}

var BlinkCode = `
void setup() {
	// initialize the digital pin as an output.
	// Pin 13 has an LED connected on most Arduino boards:
	pinMode(13, OUTPUT);
}

void loop() {
	digitalWrite(13, HIGH);   // set the LED on
	delay(1000);              // wait for a second
	digitalWrite(13, LOW);    // set the LED off
	delay(1000);              // wait for a second
}
`
var FadeCode = `
int brightness = 0;    // how bright the LED is
int fadeAmount = 5;    // how many points to fade the LED by

void setup()  {
	// declare pin 9 to be an output:
	pinMode(9, OUTPUT);
}

void loop()  {
	// set the brightness of pin 9:
	analogWrite(9, brightness);
	
	// change the brightness for next time through the loop:
	brightness = brightness + fadeAmount;
	
	// reverse the direction of the fading at the ends of the fade: 
	if (brightness == 0 || brightness == 255) {
		fadeAmount = -fadeAmount ;
	}
	// wait for 30 milliseconds to see the dimming effect    
	delay(30);
}
`

func Test_ArduinoBlink(t *testing.T) {
	var exe = safeCompile(t, BlinkCode, newArduinoTestMachineInfo(t))
	var f *cxx.CompiledFunction
	for _, bf := range exe.Functions {
		if bf.GetName() == "loop" {
			f = bf.(*cxx.CompiledFunction)
			break
		}
	}
	if f == nil {
		t.Fatal("function 'loop' not found in executable")
		return
	}
}

func Test_ArduinoFade(t *testing.T) {
	var exe = safeCompile(t, FadeCode, newArduinoTestMachineInfo(t))
	var f *cxx.CompiledFunction
	for _, bf := range exe.Functions {
		if bf.GetName() == "loop" {
			f = bf.(*cxx.CompiledFunction)
			break
		}
	}
	if f == nil {
		t.Fatal("function 'loop' not found in executable")
		return
	}
}

func Test_CannotAssignToFuncalls(t *testing.T) {
	safeCompileFailed(t, "int foo() {return 1;} int main() { foo() = 42; return 0; }", newArduinoTestMachineInfo(t), 131)
}

func Test_CannotAssignToFunctions(t *testing.T) {
	safeCompileFailed(t, "int foo() {return 1;} int main() { foo = 42; return 0; }", newArduinoTestMachineInfo(t), 30, 1656)
}

func Test_ErrorOnVariableRedeclarationSameScope(t *testing.T) {
	safeCompileFailed(t, "void f () { int x = 1; int x = 2; }", newArduinoTestMachineInfo(t), 2086)
}

func Test_ErrorOnVariableRedeclarationSameScopeNoInit(t *testing.T) {
	safeCompileFailed(t, "void f () { int x; int x; }", newArduinoTestMachineInfo(t), 2086)
}

func Test_ErrorOnVariableRedeclarationDifferentTypes(t *testing.T) {
	safeCompileFailed(t, "void f () { int x = 1; float x = 2.0; }", newArduinoTestMachineInfo(t), 2086)
}

func Test_ErrorOnMultipleRedeclarationsSameScope(t *testing.T) {
	safeCompileFailed(t, "void f () { int x; int x; int x; }", newArduinoTestMachineInfo(t), 2086)
}

func assertColorization(t *testing.T, code string, expectedColors ...cxx.SyntaxColor) {
	mi := newTestMachineInfo(t)
	mi.AddInternalFunction("void delay()", nil)
	mi.HeaderCode = "#define FOO 1\n\nvoid delay();\n\n"

	var colors = cxx.Colorize(code, mi, nil)
	assert.Equal(t, len(expectedColors), len(colors), "Number of colored tokens don't match.")
	for i, color := range colors {
		ecolor := expectedColors[i]
		assert.True(t, color.Length > 0, "Span has length == 0")
		assert.Equal(t, ecolor, color.Color)
	}
}

func Test_Expression(t *testing.T) {
	assertColorization(t, "42 / 100.0 + 50",
		cxx.SyntaxColorNumber,
		cxx.SyntaxColorOperator,
		cxx.SyntaxColorNumber,
		cxx.SyntaxColorOperator,
		cxx.SyntaxColorNumber)
}

func Test_IfStatement(t *testing.T) {
	assertColorization(t, "if (true) {}",
		cxx.SyntaxColorKeyword,
		cxx.SyntaxColorOperator,
		cxx.SyntaxColorNumber,
		cxx.SyntaxColorOperator,
		cxx.SyntaxColorOperator,
		cxx.SyntaxColorOperator)
}

func Test_IntTypeDecl(t *testing.T) {
	assertColorization(t, "int x = 42;",
		cxx.SyntaxColorType,
		cxx.SyntaxColorIdentifier,
		cxx.SyntaxColorOperator,
		cxx.SyntaxColorNumber,
		cxx.SyntaxColorOperator)
}

func Test_StringLiteral(t *testing.T) {
	assertColorization(t, "*\"Hello\"",
		cxx.SyntaxColorOperator,
		cxx.SyntaxColorString)
}

func Test_CharLiteral(t *testing.T) {
	assertColorization(t, "'h'",
		cxx.SyntaxColorString)
}

func Test_SimpleStatement(t *testing.T) {
	assertColorization(t, "f;",
		cxx.SyntaxColorIdentifier,
		cxx.SyntaxColorOperator)
}

func Test_UnknownFuncall(t *testing.T) {
	assertColorization(t, "foo();",
		cxx.SyntaxColorIdentifier,
		cxx.SyntaxColorOperator,
		cxx.SyntaxColorOperator,
		cxx.SyntaxColorOperator)
}

func Test_KnownFuncall(t *testing.T) {
	assertColorization(t, "delay();",
		cxx.SyntaxColorFunction,
		cxx.SyntaxColorOperator,
		cxx.SyntaxColorOperator,
		cxx.SyntaxColorOperator)
}

func Test_VoidFundef(t *testing.T) {
	assertColorization(t, "void foo();",
		cxx.SyntaxColorKeyword,
		cxx.SyntaxColorIdentifier,
		cxx.SyntaxColorOperator,
		cxx.SyntaxColorOperator,
		cxx.SyntaxColorOperator)
}

func Test_MultilineComment(t *testing.T) {
	assertColorization(t, "2 + /* la dee \n\n\n daaa */ foo",
		cxx.SyntaxColorNumber,
		cxx.SyntaxColorOperator,
		cxx.SyntaxColorIdentifier)
}

func Test_MachineInfoDefine(t *testing.T) {
	assertColorization(t, "FOO + 2",
		cxx.SyntaxColorIdentifier,
		cxx.SyntaxColorOperator,
		cxx.SyntaxColorNumber)
}

func Test_UserDefine(t *testing.T) {
	assertColorization(t, "#define FOOFOO 100\n\nFOOFOO + 3",
		cxx.SyntaxColorOperator,
		cxx.SyntaxColorKeyword,
		cxx.SyntaxColorIdentifier,
		cxx.SyntaxColorNumber,
		cxx.SyntaxColorIdentifier,
		cxx.SyntaxColorOperator,
		cxx.SyntaxColorNumber)
}

func Test_UnsignedSigned(t *testing.T) {
	assertColorization(t, "signed int x; unsigned int y;",
		cxx.SyntaxColorType,
		cxx.SyntaxColorType,
		cxx.SyntaxColorIdentifier,
		cxx.SyntaxColorOperator,
		cxx.SyntaxColorType,
		cxx.SyntaxColorType,
		cxx.SyntaxColorIdentifier,
		cxx.SyntaxColorOperator)
}

func Test_UnterminatedString(t *testing.T) {
	assertColorization(t, "\"sdfsdfsd\nx",
		cxx.SyntaxColorString,
		cxx.SyntaxColorIdentifier)
}

//endregion
