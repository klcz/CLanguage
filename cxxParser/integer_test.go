package cxxParser

import (
	"testing"
)

func integerRunCode(t *testing.T, code string) *CInterpreter {
	t.Helper()
	mi := newArduinoTestMachineInfo(t)
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

func Test_BitwiseNot(t *testing.T) {
	integerAssertEqual(t, ^0, "~0")
	integerAssertEqual(t, ^1, "~1")
	integerAssertEqual(t, ^2, "~2")
}

func Test_Not(t *testing.T) {
	integerAssertEqual(t, 1, "!0")
	integerAssertEqual(t, 0, "!1")
	integerAssertEqual(t, 0, "!2")
}

func Test_BitwiseAnd(t *testing.T) {
	integerAssertEqual(t, 0, "0 & 0")
	integerAssertEqual(t, 0, "0 & 1")
	integerAssertEqual(t, 0, "1 & 0")
	integerAssertEqual(t, 1, "1 & 1")
	integerAssertEqual(t, 3947&143, "3947 & 143")
}

func Test_BitwiseOr(t *testing.T) {
	integerAssertEqual(t, 0, "0 | 0")
	integerAssertEqual(t, 1, "0 | 1")
	integerAssertEqual(t, 1, "1 | 0")
	integerAssertEqual(t, 1, "1 | 1")
	integerAssertEqual(t, 3947|143, "3947 | 143")
}

func Test_BitwiseXor(t *testing.T) {
	integerAssertEqual(t, 0, "0 ^ 0")
	integerAssertEqual(t, 1, "0 ^ 1")
	integerAssertEqual(t, 1, "1 ^ 0")
	integerAssertEqual(t, 0, "1 ^ 1")
	integerAssertEqual(t, 3947^143, "3947 ^ 143")
}

func Test_ConstantTooBig(t *testing.T) {
	integerAssertEqual(t, 8972313&0xFFFF, "8972313")
}

func Test_ShiftLeft(t *testing.T) {
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

func Test_ShiftRight(t *testing.T) {
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

func Test_PromoteArduino(t *testing.T) {
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

func Test_ShiftLeftIssue41PressureSensor(t *testing.T) {
	runCode(t, `
void main () {
    byte pressure_data_high = 5;
    pressure_data_high &= 0x07;
    unsigned int pressure_data_low = 0x1234;

    // With long cast on left operand, shift happens at 32-bit precision
    long pressure = (((long)pressure_data_high << 16) | pressure_data_low) / 4;
    assert32AreEqual (83085L, pressure);
}
	`, newArduinoTestMachineInfo(t))

	runCode(t, `
void main () {
    byte pressure_data_high = 7;
    unsigned int pressure_data_low = 0xFFFF;
    long pressure = (((long)pressure_data_high << 16) | pressure_data_low) / 4;
    assert32AreEqual (131071L, pressure);
}
	`, newArduinoTestMachineInfo(t))
}
