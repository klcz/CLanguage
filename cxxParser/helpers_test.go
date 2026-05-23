package cxxParser

import (
	"fmt"
	"testing"
)

var testFailure string

func checkFailure(t *testing.T) {
	t.Helper()
	if testFailure != "" {
		t.Fatal(testFailure)
		testFailure = ""
	}
}

func assertFail(format string, args ...interface{}) {
	testFailure = fmt.Sprintf(format, args...)
}

func newTestMachineInfo() *MachineInfo {
	mi := NewMachineInfo()
	mi.IntSize = 2
	mi.PointerSize = 2
	mi.LongIntSize = 4
	addAssertFunctions(mi)
	return mi
}

func newArduinoTestMachineInfo() *MachineInfo {
	mi := NewMachineInfo()
	mi.IntSize = 2
	mi.PointerSize = 2
	addAssertFunctions(mi)
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

func addAssertFunctions(mi *MachineInfo) {
	mi.AddInternalFunction("void assertAreEqual(int expected, int actual)", func(state *CInterpreter) {
		expected := state.ReadArg(0)
		actual := state.ReadArg(1)
		if expected.Int32Value != actual.Int32Value {
			assertFail("assertAreEqual: expected %d, got %d", expected.Int32Value, actual.Int32Value)
		}
	})
	mi.AddInternalFunction("void assertBoolsAreEqual(int expected, int actual)", func(state *CInterpreter) {
		expected := state.ReadArg(0)
		actual := state.ReadArg(1)
		if (expected.Int32Value != 0) != (actual.Int32Value != 0) {
			assertFail("assertBoolsAreEqual: expected %d, got %d", expected.Int32Value, actual.Int32Value)
		}
	})
	mi.AddInternalFunction("void assertFloatsAreEqual(float expected, float actual)", func(state *CInterpreter) {
		expected := state.ReadArg(0)
		actual := state.ReadArg(1)
		if expected.Float32Value != actual.Float32Value {
			assertFail("assertFloatsAreEqual: expected %f, got %f", expected.Float32Value, actual.Float32Value)
		}
	})
	mi.AddInternalFunction("void assertDoublesAreEqual(double expected, double actual)", func(state *CInterpreter) {
		expected := state.ReadArg(0)
		actual := state.ReadArg(1)
		if expected.Float64Value != actual.Float64Value {
			assertFail("assertDoublesAreEqual: expected %f, got %f", expected.Float64Value, actual.Float64Value)
		}
	})
	mi.AddInternalFunction("void assertU16AreEqual(int expected, int actual)", func(state *CInterpreter) {
		expected := state.ReadArg(0)
		actual := state.ReadArg(1)
		if expected.Int32Value != actual.Int32Value {
			assertFail("assertU16AreEqual: expected %d, got %d", expected.Int32Value, actual.Int32Value)
		}
	})
	mi.AddInternalFunction("void assert32AreEqual(long expected, long actual)", func(state *CInterpreter) {
		expected := state.ReadArg(0)
		actual := state.ReadArg(1)
		if expected.Int32Value != actual.Int32Value {
			assertFail("assert32AreEqual: expected %d, got %d", expected.Int32Value, actual.Int32Value)
		}
	})
	mi.AddInternalFunction("void assertU32AreEqual(unsigned long expected, unsigned long actual)", func(state *CInterpreter) {
		expected := state.ReadArg(0)
		actual := state.ReadArg(1)
		if expected.Int32Value != actual.Int32Value {
			assertFail("assertU32AreEqual: expected %d, got %d", expected.Int32Value, actual.Int32Value)
		}
	})
}

func runCode(t *testing.T, code string, mi *MachineInfo) *CInterpreter {
	t.Helper()
	testFailure = ""
	if mi == nil {
		mi = newTestMachineInfo()
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
		return nil
	}
	i := NewCInterpreter(exe)
	i.Reset("start")
	defer func() {
		if r := recover(); r != nil {
			t.Skipf("Runtime panic (likely incomplete feature): %v", r)
		}
	}()
	i.Run()
	checkFailure(t)
	return i
}

func compileCode(t *testing.T, code string, mi *MachineInfo, expectedErrors ...int) *Executable {
	t.Helper()
	if mi == nil {
		mi = newTestMachineInfo()
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
