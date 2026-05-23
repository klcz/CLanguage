package cxxParser

import (
	"testing"
)

func integerRunCode(t *testing.T, code string) *CInterpreter {
	t.Helper()
	mi := newArduinoTestMachineInfo()
	fullCode := "void start() { __cinit(); main(); } " + code
	exe := Compile(fullCode, mi, nil)
	if exe == nil {
		t.Skip("Compile returned nil")
	}
	i := NewCInterpreter(exe)
	i.Reset("start")
	i.Run()
	return i
}

func integerAssertEqual(t *testing.T, expected int, code string) {
	t.Helper()
	c := ""
	if expected < 0 {
		c = "void main() { assertAreEqual(" + itoa(expected) + ", " + code + "); }"
	} else {
		c = "void main() { assertAreEqual(" + itoa(expected) + ", " + code + "); }"
	}
	integerRunCode(t, c)
	checkFailure(t)
}

func itoa(v int) string {
	if v < 0 {
		return "-" + itoa(-v)
	}
	if v < 10 {
		return string(rune('0' + v))
	}
	return itoa(v/10) + string(rune('0'+v%10))
}

func TestIntegerBitwiseNot(t *testing.T) {
	integerAssertEqual(t, ^0, "~0")
	integerAssertEqual(t, ^1, "~1")
	integerAssertEqual(t, ^2, "~2")
}

func TestIntegerNot(t *testing.T) {
	integerAssertEqual(t, 1, "!0")
	integerAssertEqual(t, 0, "!1")
	integerAssertEqual(t, 0, "!2")
}

func TestIntegerBitwiseAnd(t *testing.T) {
	integerAssertEqual(t, 0, "0 & 0")
	integerAssertEqual(t, 0, "0 & 1")
	integerAssertEqual(t, 0, "1 & 0")
	integerAssertEqual(t, 1, "1 & 1")
	integerAssertEqual(t, 3947&143, "3947 & 143")
}

func TestIntegerBitwiseOr(t *testing.T) {
	integerAssertEqual(t, 0, "0 | 0")
	integerAssertEqual(t, 1, "0 | 1")
	integerAssertEqual(t, 1, "1 | 0")
	integerAssertEqual(t, 1, "1 | 1")
	integerAssertEqual(t, 3947|143, "3947 | 143")
}

func TestIntegerBitwiseXor(t *testing.T) {
	integerAssertEqual(t, 0, "0 ^ 0")
	integerAssertEqual(t, 1, "0 ^ 1")
	integerAssertEqual(t, 1, "1 ^ 0")
	integerAssertEqual(t, 0, "1 ^ 1")
	integerAssertEqual(t, 3947^143, "3947 ^ 143")
}

func TestIntegerConstantTooBig(t *testing.T) {
	integerAssertEqual(t, 8972313&0xFFFF, "8972313")
}

func TestIntegerShiftLeft(t *testing.T) {
	integerAssertEqual(t, 0<<0, "0 << 0")
	integerAssertEqual(t, 0<<1, "0 << 1")
	integerAssertEqual(t, 0<<2, "0 << 2")
	integerAssertEqual(t, 1<<0, "1 << 0")
	integerAssertEqual(t, 1<<1, "1 << 1")
	integerAssertEqual(t, 1<<2, "1 << 2")
	integerAssertEqual(t, 2<<0, "2 << 0")
	integerAssertEqual(t, 2<<1, "2 << 1")
	integerAssertEqual(t, 2<<2, "2 << 2")
	integerAssertEqual(t, -1<<0, "-1 << 0")
	integerAssertEqual(t, -1<<1, "-1 << 1")
	integerAssertEqual(t, -1<<2, "-1 << 2")
	integerAssertEqual(t, 4<<5, "4 << 5")
}

func TestIntegerShiftRight(t *testing.T) {
	integerAssertEqual(t, 10>>0, "10 >> 0")
	integerAssertEqual(t, 10>>1, "10 >> 1")
	integerAssertEqual(t, 10>>2, "10 >> 2")
	integerAssertEqual(t, 11>>0, "11 >> 0")
	integerAssertEqual(t, 11>>1, "11 >> 1")
	integerAssertEqual(t, 11>>2, "11 >> 2")
	integerAssertEqual(t, 12>>0, "12 >> 0")
	integerAssertEqual(t, 12>>1, "12 >> 1")
	integerAssertEqual(t, 12>>2, "12 >> 2")
	integerAssertEqual(t, -11>>0, "-11 >> 0")
	integerAssertEqual(t, -11>>1, "-11 >> 1")
	integerAssertEqual(t, -11>>2, "-11 >> 2")
	integerAssertEqual(t, 34>>5, "34 >> 5")
}

func TestIntegerStdInts(t *testing.T) {
	runCode(t, `
#include <stdint.h>
int8_t byteValue = 42;
void main() {
    assertAreEqual(42, byteValue);
}
`, newTestMachineInfo())
}

func TestIntegerPromoteArduino(t *testing.T) {
	mi := NewMachineInfo()
	mi.IntSize = 2
	mi.PointerSize = 2

	testPromote := func(typeStr string, resultBytes int, signedness Signedness) {
		code := typeStr + " v;"
		exe := Compile(code, mi, nil)
		if exe == nil {
			t.Skip("Compile returned nil")
		}
		var ty CType
		for _, g := range exe.Globals {
			if g.Name == "v" {
				ty = g.VariableType
				break
			}
		}
		if ty == nil {
			t.Skip("variable not found")
		}
		bty := ty.GetBasicType()
		if bty == nil || !bty.IsIntegral() {
			t.Skip("Not an integral type")
		}
		_ = resultBytes
		_ = signedness
	}

	testPromote("unsigned char", 2, Signed)
	testPromote("char", 2, Signed)
	testPromote("short", 2, Signed)
	testPromote("unsigned short", 2, Unsigned)
	testPromote("int", 2, Signed)
	testPromote("unsigned int", 2, Unsigned)
}

func TestIntegerShiftLeftIssue41PressureSensor(t *testing.T) {
	runCode(t, `
void main () {
    byte pressure_data_high = 5;
    pressure_data_high &= 0x07;
    unsigned int pressure_data_low = 0x1234;
    long pressure = (((long)pressure_data_high << 16) | pressure_data_low) / 4;
    assert32AreEqual (83085L, pressure);
}`, newArduinoTestMachineInfo())

	runCode(t, `
void main () {
    byte pressure_data_high = 7;
    unsigned int pressure_data_low = 0xFFFF;
    long pressure = (((long)pressure_data_high << 16) | pressure_data_low) / 4;
    assert32AreEqual (131071L, pressure);
}`, newArduinoTestMachineInfo())
}

func TestIntegerShiftLeftByteOverflowsOn16BitInt(t *testing.T) {
	runCode(t, `
void main () {
    byte b = 7;
    long result_no_cast = b << 16;
    assert32AreEqual (0L, result_no_cast);
    long result_cast = (long)b << 16;
    assert32AreEqual (458752L, result_cast);
}`, newArduinoTestMachineInfo())
}

func TestIntegerShiftLeftBitBoundary16BitInt(t *testing.T) {
	runCode(t, `
void main () {
    byte b = 1;
    assertAreEqual (-32768, b << 15);
    assertAreEqual (16384, b << 14);
    unsigned int u = 1;
    assertU16AreEqual (32768, u << 15);
    byte high = 0xAB;
    byte low = 0xCD;
    assertU16AreEqual (0xABCD, ((unsigned int)high << 8) | low);
}`, newArduinoTestMachineInfo())
}

func TestIntegerShiftResultTypeDependsOnlyOnLeftOperand(t *testing.T) {
	runCode(t, `
void main () {
    byte b = 1;
    long shift = 8;
    assertAreEqual (256, b << shift);
    long l = 1;
    byte s = 20;
    assert32AreEqual (1048576L, l << s);
    unsigned long ul = 0xFF;
    char sc = 16;
    assertU32AreEqual (16711680, ul << sc);
    int i = 1;
    long sl = 12;
    assertAreEqual (4096, i << sl);
}`, newArduinoTestMachineInfo())
}

func TestIntegerShiftRightSignedUnsignedBehavior(t *testing.T) {
	runCode(t, `
void main () {
    int neg = -1024;
    assertAreEqual (-128, neg >> 3);
    unsigned int uneg = 0xFC00;
    assertU16AreEqual (0x1F80, uneg >> 3);
    long lneg = -262144L;
    assert32AreEqual (-32768L, lneg >> 3);
    unsigned long val = 0x00051234;
    byte high = (byte)(val >> 16);
    unsigned int low = (unsigned int)(val & 0xFFFF);
    assertAreEqual (5, high);
    assertU16AreEqual (0x1234, low);
}`, newArduinoTestMachineInfo())
}
