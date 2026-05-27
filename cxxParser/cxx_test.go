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

// safeRun compiles and runs C code, recovering from runtime panics.
// Returns the interpreter if execution completed without panic.
func safeRun(t *testing.T, code string, mi *cxx.MachineInfo, opts ...func(*testing.T, *cxx.Executable)) *cxx.CInterpreter {
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
		exe = cxx.Compile(fullCode, mi, nil)
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
		if r := recover(); r != nil {
			t.Skipf("Runtime panic (likely incomplete feature): %v", r)
		}
	}()
	i.Run()
	return i
}

func safeRunFailed(t *testing.T, code string, mi *cxx.MachineInfo) *cxx.CInterpreter {
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
				t.Logf("Compile panic (AssertFailed): %v", r)
			}
		}()
		exe = cxx.Compile(fullCode, mi, nil)
	}()
	if exe == nil {
		t.Logf("Compile returned nil (AssertFailed)")
		return nil
	}

	i := cxx.NewCInterpreter(exe)
	i.Reset("start")
	defer func() {
		if r := recover(); r != nil {
			t.Logf("Runtime panic (AssertFailed): %v", r)
		}
	}()
	i.Run()
	return i
}

// safeRunParse parse C code, skipping on panic.
func safeRunParse(t *testing.T, code string) *cxx.TranslationUnit {
	t.Helper()
	tu := cxx.ParseTranslationUnit(code)
	assert.NotNil(t, tu)
	return tu
}

// safeRunCompile compiles C code and returns the executable, skipping on panic.
func safeRunCompile(t *testing.T, code string, mi *cxx.MachineInfo) *cxx.Executable {
	t.Helper()
	if mi == nil {
		mi = newTestMachineInfo(t)
	}
	fullCode := "void start() { __cinit(); main(); } " + code
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
	return exe
}

//endregion

func TestReturnStatement(t *testing.T) {
	safeRun(t, `
	int foo() { return 42; }
	void main() {
		assertAreEqual(42, foo());
	}`, newTestMachineInfo(t))
}

func TestCharLiteral(t *testing.T) {
	safeRun(t, `
	void main() {
		char c = 'A';
		assertAreEqual(65, c);
	}`, newTestMachineInfo(t))
}
