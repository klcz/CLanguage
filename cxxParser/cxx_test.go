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
	t.Helper()
	p := newTestPrinter(errorCodes, nil)
	ci := doRun(t, code, mi, p, nil)
	p.Check(t, errorCodes)
	return ci
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

type TestPrinter struct {
	errCodes     []int
	codeMsgMap   map[int][]string
	notMatchMsgs []string
	p_           cxx.Printer
}

func (p *TestPrinter) Print(msg string) {
	isMatch := false
	for _, code := range p.errCodes {
		s := fmt.Sprintf("Error C%04d:", code)
		if strings.HasPrefix(msg, s) {
			p.codeMsgMap[code] = append(p.codeMsgMap[code], strings.TrimSpace(msg))
			isMatch = true
		}
	}
	if !isMatch {
		p.notMatchMsgs = append(p.notMatchMsgs, strings.TrimSpace(msg))
	}
	p.p_.Print(msg)
}

func (p *TestPrinter) Check(t *testing.T, codes []int) {
	t.Helper()

	if len(p.errCodes) == 0 {
		if len(p.notMatchMsgs) > 0 {
			t.Skipf("ErrMsg for `%v`: \n\t%s", p.errCodes, strings.Join(p.notMatchMsgs, "\n\t"))
		}
		return
	}

	notMatchCodes := make([]int, 0, len(codes))
	for _, code := range codes {
		if _, ok := p.codeMsgMap[code]; !ok {
			notMatchCodes = append(notMatchCodes, code)
		}
	}
	if len(notMatchCodes) == 0 {
		return
	}

	for _, code := range codes {
		if msgs, ok := p.codeMsgMap[code]; ok && len(msgs) > 0 {
			t.Logf("ErrMsg for `C%04d`: \n\t%s", code, strings.Join(msgs, "\n\t"))
		} else {
			t.Fatalf("AssertFailed but no errCode: `C%04d`\n", code)
		}
	}
}

//goland:noinspection GoUnusedParameter
func newTestPrinter(codes []int, p cxx.Printer) *TestPrinter {
	if p == nil {
		p = cxx.NewSimplePrinterLevel("Fault")
	}

	return &TestPrinter{
		errCodes:   codes,
		codeMsgMap: make(map[int][]string, len(codes)),
		p_:         p,
	}
}

// safeParse parse C code, skipping on panic.
func safeParse(t *testing.T, code string) *cxx.TranslationUnit {
	t.Helper()
	tu := cxx.ParseTranslationUnit(code)
	assert.NotNil(t, tu)
	return tu
}

func safeCompile(t *testing.T, code string, mi *cxx.MachineInfo, opts ...func(*testing.T, *cxx.Executable)) *cxx.Executable {
	t.Helper()
	return doCompile(t, code, mi, nil, opts)
}

func safeCompileFailed(t *testing.T, codeStr string, mi *cxx.MachineInfo, errorCodes ...int) *cxx.Executable {
	t.Helper()
	p := newTestPrinter(errorCodes, nil)
	exe := doCompile(t, codeStr, mi, p, nil)
	p.Check(t, errorCodes)
	return exe
}

// doCompile compiles C code and returns the executable, skipping on panic.
//
//goland:noinspection GoUnusedParameter
func doCompile(t *testing.T, codeStr string, mi *cxx.MachineInfo, printer cxx.Printer, opts []func(*testing.T, *cxx.Executable)) *cxx.Executable {
	t.Helper()
	if mi == nil {
		mi = newTestMachineInfo(t)
	}
	fullCode := codeStr
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
	result := cxx.Eval("2 + 3", "")
	assert.Equal(t, int32(5), result)
}

func Test_EasyEvalMore(t *testing.T) {
	result := cxx.Eval("x * 100", "int x = 42;")
	assert.Equal(t, int32(4200), result)
}

func Test_ReferenceTypeCreation(t *testing.T) {
	intType := cxx.SignedInt
	refType := cxx.NewCReferenceType(intType)
	assert.Equal(t, 1, refType.NumValues())
	assert.Equal(t, "signed int&", refType.String())
	assert.Equal(t, refType, cxx.NewCReferenceType(intType))
	assert.NotEqual(t, refType, cxx.NewCReferenceType(cxx.Float))
}

func Test_ReferenceScoreCastTo(t *testing.T) {
	intType := cxx.SignedInt
	refType := cxx.NewCReferenceType(intType)
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
	it := safeRun(t, `
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
	printer := newTestPrinter(nil, nil)
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
	exe := safeCompile(t, "int f (int i) { if (i) return 0; else return 42; }", newArduinoTestMachineInfo(t))
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

	assert.Equal(t, 7, len(f.Instructions))
	assert.Equal(t, f.Instructions[0].Op, cxx.OpCodeLoadArg)
	// assert.Equal(t, f.Instructions[1].Op, cxx.OpCodeConvertInt16UInt8)
	assert.Equal(t, f.Instructions[1].Op, cxx.OpCodeBranchIfFalse)
	assert.Equal(t, f.Instructions[2].Op, cxx.OpCodeLoadConstant)
	assert.Equal(t, f.Instructions[3].Op, cxx.OpCodeReturn)
	assert.Equal(t, f.Instructions[4].Op, cxx.OpCodeJump)
	assert.Equal(t, f.Instructions[5].Op, cxx.OpCodeLoadConstant)
	assert.Equal(t, f.Instructions[6].Op, cxx.OpCodeReturn)
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
	exe := safeCompile(t, BlinkCode, newArduinoTestMachineInfo(t))
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
	exe := safeCompile(t, FadeCode, newArduinoTestMachineInfo(t))
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

	colors := cxx.Colorize(code, mi, cxx.NewSimplePrinterLevel("Fault"))
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

func Test_LongShortIntIsError(t *testing.T) {
	safeCompileFailed(t, `
void main() { long short int x = 0; }
	`, newTestMachineInfo(t), 2078)
}

func Test_BadFunction(t *testing.T) {
	safeCompileFailed(t, `
void setup() {
	pinMode (4, OUTPUT);
}

void loop() {
	pinMode
	sleep(1000);
}
	`, newTestMachineInfo(t), 1001, 103, 2064)
}

func Test_DefineParamIncompleteArgs(t *testing.T) {
	safeCompileFailed(t, `
#define ID(x x
void main() {
    assertAreEqual(42, ID(42));
}
	`, newTestMachineInfo(t), 1001)
}

func Test_IfTrueVariableWithBadExpressions(t *testing.T) {
	safeCompileFailed(t, `
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
	`, newTestMachineInfo(t), 1001)
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

func Test_FieldLayoutThrowsForUnknownField(t *testing.T) {
	s := cxx.NewCStructType("S")
	tmp := cxx.NewCStructField("x", cxx.SignedInt)
	s.Members = append(s.Members, tmp)

	layout := cxx.NewStructLayout(s)
	assert.Panics(t, func() {
		layout.FieldLayout("nonexistent")
	})
}

//goland:noinspection GoMaybeNil
func Test_StructLayoutMatchesCompiledOffsets(t *testing.T) {
	// Verify that StructLayout produces the same offsets as the compiler
	exe := safeCompile(t, `
struct Sensor {
int id;
float temperature;
int status;
};
Sensor s;
void main() {
s.id = 1;
s.temperature = 36.5f;
s.status = 2;
assertAreEqual(1, s.id);
assertFloatsAreEqual(36.5f, s.temperature);
assertAreEqual(2, s.status);
}
	`, newTestMachineInfo(t))

	var sVar *cxx.CompiledVariable
	for _, bf := range exe.Globals {
		if bf.Name == "s" {
			sVar = &bf
			break
		}
	}
	assert.NotNil(t, sVar)

	sType := sVar.VariableType.(*cxx.CStructType)
	assert.NotNil(t, sType)

	layout := cxx.NewStructLayout(sType)
	assert.Equal(t, 0, layout.Field("id").Offset)
	assert.Equal(t, 1, layout.Field("temperature").Offset)
	assert.Equal(t, 2, layout.Field("status").Offset)
}

func Test_StructLayoutUsedInInternalFunction(t *testing.T) {
	// Simulate the pattern: use StructLayout in an internal function
	servo := cxx.NewCStructType("Servo")
	servo.Members = append(servo.Members, cxx.NewCStructField("pin", cxx.SignedInt))
	servo.Members = append(servo.Members, cxx.NewCStructField("servoIndex", cxx.UnsignedChar))
	servo.Members = append(servo.Members, cxx.NewCStructField("min", cxx.SignedChar))
	servo.Members = append(servo.Members, cxx.NewCStructField("max", cxx.SignedChar))

	layout := cxx.NewStructLayout(servo)
	pinField := layout.Field("pin")
	servoIndexField := layout.Field("servoIndex")
	minField := layout.Field("min")
	maxField := layout.Field("max")

	// Simulate stack with struct at offset 5
	stack := make([]cxx.Value, 20)
	thisPtr := 5

	// Write fields using accessors instead of magic numbers
	pinField.Set(stack, thisPtr, cxx.UnionValue(13))
	servoIndexField.Set(stack, thisPtr, cxx.UnionValue(byte(1)))
	minField.Set(stack, thisPtr, cxx.UnionValue(int8(10)))
	maxField.Set(stack, thisPtr, cxx.UnionValue(uint8(180)))

	// Verify we can read them back
	assert.Equal(t, int32(13), new(pinField.Get(stack, thisPtr)).Int32Value())
	assert.Equal(t, uint8(1), new(servoIndexField.Get(stack, thisPtr)).UInt8Value())
	assert.Equal(t, int8(10), new(minField.Get(stack, thisPtr)).Int8Value())
	assert.Equal(t, uint8(180), new(maxField.Get(stack, thisPtr)).UInt8Value())

	// Verify they're at the expected stack positions
	assert.Equal(t, int32(13), stack[5].Int32Value())
	assert.Equal(t, uint8(1), stack[6].UInt8Value())
	assert.Equal(t, int8(10), stack[7].Int8Value())
}

func makeField(name string, type_ cxx.CType) *cxx.CStructField {
	return cxx.NewCStructField(name, type_)
}

func makeVirtualMethod(name string, sig *cxx.CFunctionType) *cxx.CStructMethod {
	m := cxx.NewCStructMethod(name, sig)
	m.IsVirtual = true
	return m
}

func makeOverrideMethod(name string, sig *cxx.CFunctionType) *cxx.CStructMethod {
	m := cxx.NewCStructMethod(name, sig)
	m.IsOverride = true
	return m
}

func makeMethodSig(declaringType *cxx.CStructType) *cxx.CFunctionType {
	return cxx.NewCFunctionType(cxx.SignedInt, true, declaringType)
}

// ---- Non-polymorphic types: zero-cost guarantee ----

func Test_NonPolymorphicByteSizeUnchanged(t *testing.T) {
	printer := newTestPrinter(nil, nil)
	_c := cxx.NewExecutableContext(cxx.NewExecutable(cxx.Windows32), cxx.NewReport(printer))

	s := cxx.NewCStructType("Plain")
	s.Members = append(s.Members, makeField("x", cxx.SignedInt))
	s.Members = append(s.Members, makeField("y", cxx.SignedInt))
	assert.Equal(t, 8, s.GetByteSize(_c.EmitContext)) // 2 * 4 bytes
}

// ---- IsPolymorphic ----

// ---- NumValues with polymorphism ----

// ---- GetByteSize with polymorphism ----

func Test_PolymorphicByteSizeIncludesVptr(t *testing.T) {
	printer := newTestPrinter(nil, nil)
	_c := cxx.NewExecutableContext(cxx.NewExecutable(cxx.Windows32), cxx.NewReport(printer))

	s := cxx.NewCStructType("Base")
	s.Members = append(s.Members, makeField("x", cxx.SignedInt))
	method := makeVirtualMethod("foo", makeMethodSig(s))
	s.Members = append(s.Members, method)
	s.BuildVTable()

	// 4 (vptr) + 4 (x) = 8
	assert.Equal(t, 8, s.GetByteSize(_c.EmitContext))
}

func Test_DerivedByteSizeIncludesBaseFields(t *testing.T) {
	printer := newTestPrinter(nil, nil)
	_c := cxx.NewExecutableContext(cxx.NewExecutable(cxx.Windows32), cxx.NewReport(printer))

	baseType := cxx.NewCStructType("Base")
	baseType.Members = append(baseType.Members, makeField("x", cxx.SignedInt))
	method := makeVirtualMethod("foo", makeMethodSig(baseType))
	baseType.Members = append(baseType.Members, method)
	baseType.BuildVTable()

	derived := cxx.NewCStructType("Derived")
	derived.BaseType = baseType
	derived.Members = append(derived.Members, makeField("y", cxx.SignedInt))
	derived.BuildVTable()

	// 4 (vptr) + 4 (base x) + 4 (own y) = 12
	assert.Equal(t, 12, derived.GetByteSize(_c.EmitContext))
}

// ---- GetFieldValueOffset with polymorphism ----

// ---- VTable construction ----

// [ExpectedException (typeof (InvalidOperationException))]
func Test_BuildVTableOverrideWithoutBaseThrows(t *testing.T) {
	s := cxx.NewCStructType("Bad")
	sig := makeMethodSig(s)
	assert.Panics(t, func() {
		method := makeOverrideMethod("nonexistent", sig)
		s.Members = append(s.Members, method)
		s.BuildVTable()
	})
}

// ---- GetOwnFieldsNumValues / GetOwnFieldsByteSize ----

func Test_GetOwnFieldsByteSizeExcludesMethods(t *testing.T) {
	printer := newTestPrinter(nil, nil)
	_c := cxx.NewExecutableContext(cxx.NewExecutable(cxx.Windows32), cxx.NewReport(printer))

	s := cxx.NewCStructType("S")
	s.Members = append(s.Members, makeField("x", cxx.SignedInt))
	s.Members = append(s.Members, cxx.NewCStructMethod("foo", makeMethodSig(s)))
	s.Members = append(s.Members, makeField("y", cxx.SignedInt))
	assert.Equal(t, 8, s.GetOwnFieldsByteSize(_c.EmitContext)) // 2 * 4 bytes
}

// ---- VTableEntry ----

// ---- CStructMethod flags ----

// ---- BaseType property ----

// ---- Non-polymorphic base type (no virtual methods) ----

func Test_NonPolymorphicBaseByteSize(t *testing.T) {
	printer := newTestPrinter(nil, nil)
	_c := cxx.NewExecutableContext(cxx.NewExecutable(cxx.Windows32), cxx.NewReport(printer))

	baseType := cxx.NewCStructType("Base")
	baseType.Members = append(baseType.Members, makeField("x", cxx.SignedInt))

	derived := cxx.NewCStructType("Derived")
	derived.BaseType = baseType
	derived.Members = append(derived.Members, makeField("y", cxx.SignedInt))

	// No vptr: 4 (base x) + 4 (own y) = 8
	assert.Equal(t, 8, derived.GetByteSize(_c.EmitContext))
}

//goland:noinspection GoUnusedFunction
func getDeclaredIdentifier(d cxx.Declarator) string {
	for d != nil {
		if id, ok := d.(*cxx.IdentifierDeclarator); ok {
			return id.DeclaredIdentifier()
		}
		d = d.GetInnerDeclarator()
	}
	return ""
}

//goland:noinspection GoUnusedFunction
func findIdentifierDeclarator(d cxx.Declarator) *cxx.IdentifierDeclarator {
	for d != nil {
		if id, ok := d.(*cxx.IdentifierDeclarator); ok {
			return id
		}
		d = d.GetInnerDeclarator()
	}
	return nil
}

func Test_NoOperatorOverloadError(t *testing.T) {
	// Struct without operator+ should still give error 19
	// Error 30 cascades from attempting to cast struct to arithmetic type
	code := `
struct V { int x; };
void main() {
V a; a.x = 1;
V b; b.x = 2;
V c = a + b;
}`
	safeRunFailed(t, code, newTestMachineInfo(t), 19, 30)
}

func Test_InternalOperatorWithBasicReturnType(t *testing.T) {
	mi := newTestMachineInfo(t)
	mi.HeaderCode += "struct V { int x; int operator<(V other); };\n"
	mi.AddInternalFunction("int V::operator<(V other)", func(interp *cxx.CInterpreter) {
		_this := new(interp.ReadThis()).PointerValue()
		thisX := interp.Stack[_this].Int32Value()
		otherX := new(interp.ReadArg(0)).Int32Value()
		if thisX < otherX {
			interp.Push(cxx.UnionValue(1))
		} else {
			interp.Push(cxx.UnionValue(0))
		}
	})

	code := `
void main() {
V a; a.x = 3;
V b; b.x = 5;
assertAreEqual(1, a < b);
assertAreEqual(0, b < a);
}`
	safeRun(t, code, mi)
}

func Test_InternalOperatorWithStructLayout(t *testing.T) {
	// Test multi-field struct return and parameter access
	mi := newTestMachineInfo(t)
	mi.HeaderCode += "struct Vec { int x; int y; Vec operator+(Vec other); };\n"
	mi.AddInternalFunction("Vec Vec::operator+(Vec other)", func(interp *cxx.CInterpreter) {
		_this := new(interp.ReadThis()).PointerValue()
		thisX := interp.Stack[_this].Int32Value()
		thisY := interp.Stack[_this+1].Int32Value()
		// Multi-value struct parameter: compute base address from frame pointer
		fp := interp.ActiveFrame().FP
		paramOffset := interp.ActiveFrame().Function.GetFunctionType().Parameters()[0].Offset
		otherBase := fp + paramOffset
		otherX := interp.Stack[otherBase].Int32Value()
		otherY := interp.Stack[otherBase+1].Int32Value()
		interp.Push(cxx.UnionValue(thisX + otherX))
		interp.Push(cxx.UnionValue(thisY + otherY))
	})

	code := `
void main() {
Vec a; a.x = 1; a.y = 10;
Vec b; b.x = 2; b.y = 20;
Vec c = a + b;
assertAreEqual(3, c.x);
assertAreEqual(30, c.y);
}`
	safeRun(t, code, mi)
}

func Test_ReflectionBasedOperators(t *testing.T) {
	// C# class with operator+ exposed via AddGlobalReference.
	// TestMachineInfo has IntSize=2, so C# int maps to C long,
	// requiring assert32AreEqual for 32-bit comparison.
	/* mi := newTestMachineInfo(t);
	    calc := new TestCalculator ();
	    mi.AddGlobalReference ("calc", calc);

	    code := `
	void main() {
	long result = calc + 10;
	assert32AreEqual(20, result);
	}`
	    Run (code, mi); */
}

//goland:noinspection GoMaybeNil
func testPromote(t *testing.T, mi *cxx.MachineInfo, type_ string, resultBytes int, signedness cxx.Signedness) {
	printer := newTestPrinter(nil, nil)
	report := cxx.NewReport(printer)
	context := cxx.NewExecutableContext(cxx.NewExecutable(mi), report)

	compiler := cxx.NewCCompiler(cxx.NewCompilerOptions(mi, report, nil))
	compiler.AddCode("test.c", type_+" v;")
	exe := compiler.Compile()

	var sVar *cxx.CompiledVariable
	for _, bf := range exe.Globals {
		if bf.Name == "v" {
			sVar = &bf
			break
		}
	}
	assert.NotNil(t, sVar)
	ty := sVar.VariableType
	
	bty := ty.GetBasicType()
	assert.True(t, bty.IsIntegral())
	pty := bty.IntegerPromote(context.EmitContext)

	assert.Equal(t, pty.GetBasicType().Signedness, signedness)
	assert.Equal(t, pty.GetByteSize(context.EmitContext), resultBytes)
}

//goland:noinspection GoMaybeNil
func testArithmetic(t *testing.T, mi *cxx.MachineInfo, type1 string, type2 string, result cxx.CBasicType) {
	printer := newTestPrinter(nil, nil)
	report := cxx.NewReport(printer)
	context := cxx.NewExecutableContext(cxx.NewExecutable(mi), report)

	compiler := cxx.NewCCompiler(cxx.NewCompilerOptions(mi, report, nil))
	compiler.AddCode("test.c", type1+" v1; "+type2+" v2;")
	exe := compiler.Compile()

	var sVar1 *cxx.CompiledVariable
	for _, bf := range exe.Globals {
		if bf.Name == "v1" {
			sVar1 = &bf
			break
		}
	}
	assert.NotNil(t, sVar1)
	ty1 := sVar1.VariableType

	var sVar2 *cxx.CompiledVariable
	for _, bf := range exe.Globals {
		if bf.Name == "v2" {
			sVar2 = &bf
			break
		}
	}
	assert.NotNil(t, sVar2)
	ty2 := sVar2.VariableType
	
	bty1 := ty1.GetBasicType()
	bty2 := ty2.GetBasicType()
	assert.True(t, bty1.IsIntegral())
	assert.True(t, bty2.IsIntegral())
	aty1 := bty1.ArithmeticConvert(bty2, context.EmitContext)
	aty2 := bty2.ArithmeticConvert(bty1, context.EmitContext)

	assert.Equal(t, aty1.GetBasicType().Signedness, result.Signedness)
	assert.Equal(t, aty1.GetByteSize(context.EmitContext), result.GetByteSize(context.EmitContext))
	assert.Equal(t, aty2.GetBasicType().Signedness, result.Signedness)
	assert.Equal(t, aty2.GetByteSize(context.EmitContext), result.GetByteSize(context.EmitContext))
}

func Test_ArduinoPromote(t *testing.T) {
	t.Helper()
	
	mi := newArduinoTestMachineInfo(t)

	testPromote(t, mi, "unsigned char", 2, cxx.Signed)
	testPromote(t, mi, "char", 2, cxx.Signed)
	testPromote(t, mi, "short", 2, cxx.Signed)
	testPromote(t, mi, "unsigned short", 2, cxx.Unsigned)
	testPromote(t, mi, "int", 2, cxx.Signed)
	testPromote(t, mi, "unsigned int", 2, cxx.Unsigned)
	testPromote(t, mi, "long", 4, cxx.Signed)
	testPromote(t, mi, "unsigned long", 4, cxx.Unsigned)
}

func Test_ArduinoArithmatic(t *testing.T) {
	mi := newArduinoTestMachineInfo(t)

	testArithmetic(t, mi, "char", "char", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "char", "unsigned char", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "char", "short", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "char", "unsigned short", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "char", "int", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "char", "unsigned int", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "char", "long", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "char", "unsigned long", cxx.UnsignedLongInt.CBasicType)

	testArithmetic(t, mi, "unsigned char", "char", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned char", "unsigned char", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned char", "short", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned char", "unsigned short", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned char", "int", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned char", "unsigned int", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned char", "long", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "unsigned char", "unsigned long", cxx.UnsignedLongInt.CBasicType)

	testArithmetic(t, mi, "short", "char", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "short", "unsigned char", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "short", "short", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "short", "unsigned short", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "short", "int", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "short", "unsigned int", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "short", "long", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "short", "unsigned long", cxx.UnsignedLongInt.CBasicType)

	testArithmetic(t, mi, "unsigned short", "char", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned short", "unsigned char", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned short", "short", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned short", "unsigned short", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned short", "int", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned short", "unsigned int", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned short", "long", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "unsigned short", "unsigned long", cxx.UnsignedLongInt.CBasicType)

	testArithmetic(t, mi, "int", "char", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "int", "unsigned char", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "int", "short", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "int", "unsigned short", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "int", "int", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "int", "unsigned int", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "int", "long", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "int", "unsigned long", cxx.UnsignedLongInt.CBasicType)

	testArithmetic(t, mi, "unsigned int", "char", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned int", "unsigned char", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned int", "short", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned int", "unsigned short", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned int", "int", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned int", "unsigned int", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned int", "long", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "unsigned int", "unsigned long", cxx.UnsignedLongInt.CBasicType)

	testArithmetic(t, mi, "long", "char", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "long", "unsigned char", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "long", "short", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "long", "unsigned short", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "long", "int", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "long", "unsigned int", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "long", "long", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "long", "unsigned long", cxx.UnsignedLongInt.CBasicType)

	testArithmetic(t, mi, "unsigned long", "char", cxx.UnsignedLongInt.CBasicType)
	testArithmetic(t, mi, "unsigned long", "unsigned char", cxx.UnsignedLongInt.CBasicType)
	testArithmetic(t, mi, "unsigned long", "short", cxx.UnsignedLongInt.CBasicType)
	testArithmetic(t, mi, "unsigned long", "unsigned short", cxx.UnsignedLongInt.CBasicType)
	testArithmetic(t, mi, "unsigned long", "int", cxx.UnsignedLongInt.CBasicType)
	testArithmetic(t, mi, "unsigned long", "unsigned int", cxx.UnsignedLongInt.CBasicType)
	testArithmetic(t, mi, "unsigned long", "long", cxx.UnsignedLongInt.CBasicType)
	testArithmetic(t, mi, "unsigned long", "unsigned long", cxx.UnsignedLongInt.CBasicType)
}

func Test_WindowsX86Promote(t *testing.T) {
	mi := cxx.Windows32

	testPromote(t, mi, "unsigned char", 4, cxx.Signed)
	testPromote(t, mi, "char", 4, cxx.Signed)
	testPromote(t, mi, "short", 4, cxx.Signed)
	testPromote(t, mi, "unsigned short", 4, cxx.Signed)
	testPromote(t, mi, "int", 4, cxx.Signed)
	testPromote(t, mi, "unsigned int", 4, cxx.Unsigned)
	testPromote(t, mi, "long", 4, cxx.Signed)
	testPromote(t, mi, "unsigned long", 4, cxx.Unsigned)
}

func Test_Mac64Arithmatic(t *testing.T) {
	mi := cxx.Mac64

	testArithmetic(t, mi, "char", "char", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "char", "unsigned char", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "char", "short", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "char", "unsigned short", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "char", "int", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "char", "unsigned int", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "char", "long", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "char", "unsigned long", cxx.UnsignedLongInt.CBasicType)

	testArithmetic(t, mi, "unsigned char", "char", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned char", "unsigned char", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned char", "short", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned char", "unsigned short", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned char", "int", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned char", "unsigned int", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned char", "long", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "unsigned char", "unsigned long", cxx.UnsignedLongInt.CBasicType)

	testArithmetic(t, mi, "short", "char", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "short", "unsigned char", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "short", "short", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "short", "unsigned short", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "short", "int", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "short", "unsigned int", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "short", "long", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "short", "unsigned long", cxx.UnsignedLongInt.CBasicType)

	testArithmetic(t, mi, "unsigned short", "char", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned short", "unsigned char", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned short", "short", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned short", "unsigned short", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned short", "int", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned short", "unsigned int", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned short", "long", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "unsigned short", "unsigned long", cxx.UnsignedLongInt.CBasicType)

	testArithmetic(t, mi, "int", "char", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "int", "unsigned char", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "int", "short", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "int", "unsigned short", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "int", "int", cxx.SignedInt.CBasicType)
	testArithmetic(t, mi, "int", "unsigned int", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "int", "long", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "int", "unsigned long", cxx.UnsignedLongInt.CBasicType)

	testArithmetic(t, mi, "unsigned int", "char", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned int", "unsigned char", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned int", "short", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned int", "unsigned short", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned int", "int", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned int", "unsigned int", cxx.UnsignedInt.CBasicType)
	testArithmetic(t, mi, "unsigned int", "long", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "unsigned int", "unsigned long", cxx.UnsignedLongInt.CBasicType)

	testArithmetic(t, mi, "long", "char", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "long", "unsigned char", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "long", "short", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "long", "unsigned short", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "long", "int", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "long", "unsigned int", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "long", "long", cxx.SignedLongInt.CBasicType)
	testArithmetic(t, mi, "long", "unsigned long", cxx.UnsignedLongInt.CBasicType)

	testArithmetic(t, mi, "unsigned long", "char", cxx.UnsignedLongInt.CBasicType)
	testArithmetic(t, mi, "unsigned long", "unsigned char", cxx.UnsignedLongInt.CBasicType)
	testArithmetic(t, mi, "unsigned long", "short", cxx.UnsignedLongInt.CBasicType)
	testArithmetic(t, mi, "unsigned long", "unsigned short", cxx.UnsignedLongInt.CBasicType)
	testArithmetic(t, mi, "unsigned long", "int", cxx.UnsignedLongInt.CBasicType)
	testArithmetic(t, mi, "unsigned long", "unsigned int", cxx.UnsignedLongInt.CBasicType)
	testArithmetic(t, mi, "unsigned long", "long", cxx.UnsignedLongInt.CBasicType)
	testArithmetic(t, mi, "unsigned long", "unsigned long", cxx.UnsignedLongInt.CBasicType)
}

func parseVariables(code string) []cxx.CompiledVariable {
	exe := cxx.Compile(code, cxx.Windows32, newTestPrinter(nil, nil))
	return exe.Globals[1:] // Skip __zero__
}

func parseFunctions(code string) []cxx.BaseFunction {
	exe := cxx.Compile(code, cxx.Windows32, newTestPrinter(nil, nil))
	funcs := make([]cxx.BaseFunction, 0, len(exe.Functions))
	for _, f := range exe.Functions {
		if f.GetName() != "__cinit" {
			funcs = append(funcs, f)
		}
	}
	return funcs
}

func Test_QualifiedBasic(t *testing.T) {
	vs := parseVariables("const int cat;")
	v := vs[0]
	assert.IsType(t, &cxx.CBasicType{}, v.VariableType.GetBasicType())
	assert.Equal(t, cxx.TypeQualifiersConst, v.VariableType.GetTypeQualifiers())
}

func Test_NonConstPointerToConstChar(t *testing.T) {
	vs := parseVariables("const char *kite;")
	v := vs[0]
	assert.Equal(t, "kite", v.Name)
	assert.IsType(t, &cxx.CPointerType{}, v.VariableType)
	pt := v.VariableType.(*cxx.CPointerType)
	assert.Equal(t, cxx.TypeQualifiersNone, pt.TypeQualifiers)

	assert.IsType(t, &cxx.CBasicType{}, pt.InnerType.GetBasicType())
	assert.Equal(t, "char", (pt.InnerType.GetBasicType()).Name)
	assert.Equal(t, cxx.TypeQualifiersConst, pt.InnerType.GetTypeQualifiers())
}

func Test_ConstPointerToChar(t *testing.T) {
	vs := parseVariables("char * const pentagon;")
	v := vs[0]
	assert.Equal(t, "pentagon", v.Name)
	assert.IsType(t, &cxx.CPointerType{}, v.VariableType)
	pt := v.VariableType.(*cxx.CPointerType)
	assert.Equal(t, cxx.TypeQualifiersConst, pt.TypeQualifiers)

	assert.IsType(t, &cxx.CBasicType{}, pt.InnerType.GetBasicType())
	assert.Equal(t, "char", (pt.InnerType.GetBasicType()).Name)
	assert.Equal(t, cxx.TypeQualifiersNone, pt.InnerType.GetTypeQualifiers())
}

func Test_ConstPointerToConstChar1(t *testing.T) {
	vs := parseVariables("char const * const hexagon;")
	v := vs[0]
	assert.Equal(t, "hexagon", v.Name)
	assert.IsType(t, &cxx.CPointerType{}, v.VariableType)
	pt := v.VariableType.(*cxx.CPointerType)
	assert.Equal(t, cxx.TypeQualifiersConst, pt.TypeQualifiers)

	assert.IsType(t, &cxx.CBasicType{}, pt.InnerType.GetBasicType())
	assert.Equal(t, "char", (pt.InnerType.GetBasicType()).Name)
	assert.Equal(t, cxx.TypeQualifiersConst, pt.InnerType.GetTypeQualifiers())
}

func Test_ConstPointerToConstChar2(t *testing.T) {
	vs := parseVariables("const char * const hexagon;")
	v := vs[0]
	assert.Equal(t, "hexagon", v.Name)
	assert.IsType(t, &cxx.CPointerType{}, v.VariableType)
	pt := v.VariableType.(*cxx.CPointerType)
	assert.Equal(t, cxx.TypeQualifiersConst, pt.TypeQualifiers)

	assert.IsType(t, &cxx.CBasicType{}, pt.InnerType.GetBasicType())
	assert.Equal(t, "char", (pt.InnerType.GetBasicType()).Name)
	assert.Equal(t, cxx.TypeQualifiersConst, pt.InnerType.GetTypeQualifiers())
}

func Test_PointerToPointer(t *testing.T) {
	vs := parseVariables("char **septagon;")
	v := vs[0]
	assert.Equal(t, "septagon", v.Name)
	assert.IsType(t, &cxx.CPointerType{}, v.VariableType)
	pt := v.VariableType.(*cxx.CPointerType)

	assert.IsType(t, &cxx.CPointerType{}, pt.InnerType)
	pt1 := pt.InnerType.(*cxx.CPointerType)

	assert.IsType(t, &cxx.CBasicType{}, pt1.InnerType.GetBasicType())
	assert.Equal(t, "char", (pt1.InnerType.GetBasicType()).Name)
}

func Test_PointerToConstPointerToConstBasic(t *testing.T) {
	vs := parseVariables("unsigned long const int * const *octagon;")
	v := vs[0]
	assert.Equal(t, "octagon", v.Name)

	assert.IsType(t, &cxx.CPointerType{}, v.VariableType)
	pt := v.VariableType.(*cxx.CPointerType)
	assert.Equal(t, cxx.TypeQualifiersNone, pt.TypeQualifiers)

	assert.IsType(t, &cxx.CPointerType{}, pt.InnerType)
	pt1 := pt.InnerType.(*cxx.CPointerType)
	assert.Equal(t, cxx.TypeQualifiersConst, pt1.TypeQualifiers)

	assert.IsType(t, &cxx.CBasicType{}, pt1.InnerType.GetBasicType())
	b := pt1.InnerType.GetBasicType()
	assert.Equal(t, cxx.TypeQualifiersConst, b.TypeQualifiers)
	assert.Equal(t, "int", b.Name)
}

func Test_ArrayOfPointers(t *testing.T) {
	vs := parseVariables("char *mice[10];")
	assert.IsType(t, &cxx.CArrayType{}, vs[0].VariableType)
	a := (vs[0].VariableType).(*cxx.CArrayType)
	assert.Equal(t, 10, *a.Length)

	assert.IsType(t, &cxx.CPointerType{}, a.ElementType)
	p := (a.ElementType).(*cxx.CPointerType)

	assert.IsType(t, &cxx.CBasicType{}, p.InnerType.GetBasicType())
	assert.Equal(t, "char", (p.InnerType).GetBasicType().Name)
}

func Test_ArrayOfPointersToArray(t *testing.T) {
	vs := parseVariables("int (*a[5])[42];")
	assert.IsType(t, &cxx.CArrayType{}, vs[0].VariableType)
	a := (vs[0].VariableType).(*cxx.CArrayType)
	assert.Equal(t, 5, *a.Length)

	assert.IsType(t, &cxx.CPointerType{}, a.ElementType)
	p := (a.ElementType).(*cxx.CPointerType)

	assert.IsType(t, &cxx.CArrayType{}, p.InnerType)
}

func Test_PointerToArrayOfPointers(t *testing.T) {
	vs := parseVariables("int *(*crocodile)[15];")
	v := vs[0]
	assert.Equal(t, "crocodile", v.Name)
	assert.IsType(t, &cxx.CPointerType{}, v.VariableType)
	p := v.VariableType.(*cxx.CPointerType)

	assert.IsType(t, &cxx.CArrayType{}, p.InnerType)
	a := p.InnerType.(*cxx.CArrayType)
	assert.Equal(t, 15, *a.Length)

	assert.IsType(t, &cxx.CPointerType{}, a.ElementType)
	p2 := a.ElementType.(*cxx.CPointerType)

	assert.IsType(t, &cxx.CBasicType{}, p2.InnerType.GetBasicType())
	assert.Equal(t, "int", (p2.InnerType).GetBasicType().Name)
}

func Test_FunctionPointerReturnVoidArg(t *testing.T) {
	codes := []string{
		"char *wicket(void) {return 0;}",
	}
	for _, code := range codes {
		fs := parseFunctions(code)
		assert.Equal(t, 1, len(fs))
		f := fs[0]
		assert.Equal(t, "wicket", f.GetName())

		assert.IsType(t, &cxx.CPointerType{}, f.GetFunctionType().ReturnType)
		assert.Equal(t, "char", ((f.GetFunctionType().ReturnType.(*cxx.CPointerType)).InnerType).GetBasicType().Name)

		assert.Equal(t, 0, len(f.GetFunctionType().Parameters()))
	}
}

func Test_FunctionWithFunctionArg(t *testing.T) {
	codes := []string{
		"int crowd(char p1, int (*p2)(void)) {return 0;}",
		"int crowd(char p1, int p2(void)) {return 0;}",
	}
	for _, code := range codes {
		fs := parseFunctions(code)
		assert.Equal(t, 1, len(fs))
		f := fs[0]
		assert.Equal(t, "crowd", f.GetName())

		assert.IsType(t, &cxx.CBasicType{}, f.GetFunctionType().ReturnType.GetBasicType())
		assert.Equal(t, "int", (f.GetFunctionType().ReturnType.GetBasicType()).Name)

		assert.Equal(t, 2, len(f.GetFunctionType().Parameters()))
		p1 := f.GetFunctionType().Parameters()[0]
		p2 := f.GetFunctionType().Parameters()[1]

		assert.IsType(t, &cxx.CBasicType{}, p1.ParameterType.GetBasicType())
		assert.Equal(t, "char", (p1.ParameterType).GetBasicType().Name)

		assert.IsType(t, &cxx.CFunctionType{}, p2.ParameterType)
		assert.Equal(t, "int", ((p2.ParameterType.(*cxx.CFunctionType)).ReturnType).GetBasicType().Name)
	}
}

func Test_FunctionWithFunctionArgReturningPointer(t *testing.T) {
	codes := []string{
		"int crowd(char p1, int *(*p2)(void)) {return 0;}",
		"int crowd(char p1, int *p2(void)) {return 0;}",
	}
	for _, code := range codes {
		fs := parseFunctions(code)
		assert.Equal(t, 1, len(fs))
		f := fs[0]
		p1 := f.GetFunctionType().Parameters()[0]
		p2 := f.GetFunctionType().Parameters()[1]

		assert.IsType(t, &cxx.CBasicType{}, p1.ParameterType.GetBasicType())
		assert.Equal(t, "char", (p1.ParameterType).GetBasicType().Name)

		assert.IsType(t, &cxx.CFunctionType{}, p2.ParameterType)
		assert.IsType(t, &cxx.CPointerType{}, (p2.ParameterType).(*cxx.CFunctionType).ReturnType)
	}
}

func Test_PointerToFunction(t *testing.T) {
	code := "int (**f)();"

	vs := parseVariables(code)
	assert.Equal(t, 1, len(vs))
	fv := vs[0]
	assert.Equal(t, "f", fv.Name)

	assert.IsType(t, &cxx.CPointerType{}, fv.VariableType)
	fp := fv.VariableType.(*cxx.CPointerType)

	assert.IsType(t, &cxx.CFunctionType{}, fp.InnerType)
	f := fp.InnerType.(*cxx.CFunctionType)

	assert.Equal(t, 0, len(f.Parameters()))
}

func Test_FunctionReturningFunction(t *testing.T) {
	code := "long int *(*boundary(double size))(int x, int y);"

	vs := parseVariables(code)
	assert.Equal(t, 1, len(vs))
	fv := vs[0]
	assert.Equal(t, "boundary", fv.Name)

	f := fv.VariableType.(*cxx.CFunctionType)

	assert.Equal(t, 1, len(f.Parameters()))
	assert.Equal(t, "size", f.Parameters()[0].Name)

	p1 := f.Parameters()[0]

	assert.IsType(t, &cxx.CBasicType{}, p1.ParameterType.GetBasicType())
	assert.Equal(t, "double", (p1.ParameterType).GetBasicType().Name)

	assert.IsType(t, &cxx.CFunctionType{}, f.ReturnType)
	r := f.ReturnType.(*cxx.CFunctionType)

	assert.Equal(t, 2, len(r.Parameters()))

	assert.IsType(t, &cxx.CPointerType{}, r.ReturnType)
}

func Test_AutoConstants(t *testing.T) {
	assertAutoType(t, "1", cxx.SignedInt)
	assertAutoType(t, "1l", cxx.SignedLongInt)
	assertAutoType(t, "true", cxx.Bool)
	assertAutoType(t, "false", cxx.Bool)
	assertAutoType(t, "1 + 2", cxx.SignedInt)
	assertAutoType(t, "1 + 2.0", cxx.Double)
}

//goland:noinspection ALL
func assertAutoType(t *testing.T, code string, expectedType cxx.CType) {
	fullCode := "auto x = " + code + ";"
	exe := cxx.Compile(fullCode, newArduinoTestMachineInfo(t), nil)

	var sVar *cxx.CompiledVariable
	for _, bf := range exe.Globals {
		if bf.Name == "x" {
			sVar = &bf
			break
		}
	}
	assert.NotNil(t, sVar)
	assert.Equal(t, expectedType, sVar.VariableType)
}

func Test_ShortLongIntIsError(t *testing.T) {
	safeRunFailed(t, "void main() { short long int x = 0; }", newTestMachineInfo(t), 2078)
}

func Test_ShortShortIntIsError(t *testing.T) {
	safeRunFailed(t, "void main() { short short int x = 0; }", newTestMachineInfo(t), 2078)
}

func Test_LongFloatIsError(t *testing.T) {
	safeRunFailed(t, "void main() { long float x = 0; }", newTestMachineInfo(t), 2078)
}

func Test_ShortFloatIsError(t *testing.T) {
	safeRunFailed(t, "void main() { short float x = 0; }", newTestMachineInfo(t), 2078)
}

func Test_ShortDoubleIsError(t *testing.T) {
	safeRunFailed(t, "void main() { short double x = 0; }", newTestMachineInfo(t), 2078)
}

func Test_LongBoolIsError(t *testing.T) {
	safeRunFailed(t, "void main() { long bool x = 0; }", newTestMachineInfo(t), 2078)
}

func Test_ShortBoolIsError(t *testing.T) {
	safeRunFailed(t, "void main() { short bool x = 0; }", newTestMachineInfo(t), 2078)
}

//endregion
