package cxxParser

import (
	"testing"

	"github.com/stretchr/testify/assert"
)

func TestStructLayoutSimpleStructFieldOffsets(t *testing.T) {
	servo := NewCStructType("Servo")
	servo.Members = append(servo.Members, NewCStructField("pin", SignedInt))
	servo.Members = append(servo.Members, NewCStructField("servoIndex", UnsignedChar))
	servo.Members = append(servo.Members, NewCStructField("min", SignedChar))
	servo.Members = append(servo.Members, NewCStructField("max", SignedChar))
	servo.Members = append(servo.Members, NewCStructField("lastDegrees", SignedInt))
	servo.Members = append(servo.Members, NewCStructField("lastMicroseconds", SignedInt))

	layout := NewStructLayout(servo)
	assert.Equal(t, "Servo", layout.Name)
	assert.Equal(t, 6, layout.NumValues)
	assert.Equal(t, 0, layout.Field("pin").Offset)
	assert.Equal(t, 1, layout.Field("servoIndex").Offset)
	assert.Equal(t, 2, layout.Field("min").Offset)
	assert.Equal(t, 3, layout.Field("max").Offset)
	assert.Equal(t, 4, layout.Field("lastDegrees").Offset)
	assert.Equal(t, 5, layout.Field("lastMicroseconds").Offset)
}

func TestStructLayoutFieldAccessorNumValues(t *testing.T) {
	s := NewCStructType("S")
	five := 5
	s.Members = append(s.Members, NewCStructField("x", SignedInt))
	s.Members = append(s.Members, NewCStructField("arr", NewCArrayType(SignedInt, &five)))
	s.Members = append(s.Members, NewCStructField("y", Float))

	layout := NewStructLayout(s)
	assert.Equal(t, 1, layout.Field("x").NumValues)
	assert.Equal(t, 5, layout.Field("arr").NumValues)
	assert.Equal(t, 1, layout.Field("y").NumValues)
	assert.Equal(t, 0, layout.Field("x").Offset)
	assert.Equal(t, 1, layout.Field("arr").Offset)
	assert.Equal(t, 6, layout.Field("y").Offset)
}

func TestStructLayoutFieldAccessorGetSet(t *testing.T) {
	s := NewCStructType("Point")
	s.Members = append(s.Members, NewCStructField("x", SignedInt))
	s.Members = append(s.Members, NewCStructField("y", SignedInt))

	layout := NewStructLayout(s)
	fx := layout.Field("x")
	fy := layout.Field("y")

	stack := make([]Value, 10)
	basePtr := 3

	fx.Set(stack, basePtr, ValueOf(42))
	fy.Set(stack, basePtr, ValueOf(99))

	assert.Equal(t, int32(42), fx.Get(stack, basePtr).Int32Value)
	assert.Equal(t, int32(99), fy.Get(stack, basePtr).Int32Value)
}

func TestStructLayoutFieldAccessorGetAddress(t *testing.T) {
	s := NewCStructType("S")
	s.Members = append(s.Members, NewCStructField("a", SignedInt))
	s.Members = append(s.Members, NewCStructField("b", SignedInt))

	layout := NewStructLayout(s)
	assert.Equal(t, 10, layout.Field("a").GetAddress(10))
	assert.Equal(t, 11, layout.Field("b").GetAddress(10))
}

func TestStructLayoutNestedStructFieldLayout(t *testing.T) {
	inner := NewCStructType("Inner")
	inner.Members = append(inner.Members, NewCStructField("a", SignedInt))
	inner.Members = append(inner.Members, NewCStructField("b", SignedInt))

	outer := NewCStructType("Outer")
	outer.Members = append(outer.Members, NewCStructField("x", SignedInt))
	outer.Members = append(outer.Members, NewCStructField("nested", inner))
	outer.Members = append(outer.Members, NewCStructField("y", SignedInt))

	layout := NewStructLayout(outer)
	assert.Equal(t, 4, layout.NumValues)
	assert.Equal(t, 0, layout.Field("x").Offset)
	assert.Equal(t, 1, layout.Field("nested").Offset)
	assert.Equal(t, 2, layout.Field("nested").NumValues)
	assert.Equal(t, 3, layout.Field("y").Offset)

	nestedLayout := layout.FieldLayout("nested")
	assert.Equal(t, "Inner", nestedLayout.Name)
	assert.Equal(t, 2, nestedLayout.NumValues)
	assert.Equal(t, 0, nestedLayout.Field("a").Offset)
	assert.Equal(t, 1, nestedLayout.Field("b").Offset)
}

func TestStructLayoutNestedStructReadWrite(t *testing.T) {
	inner := NewCStructType("Inner")
	inner.Members = append(inner.Members, NewCStructField("a", SignedInt))
	inner.Members = append(inner.Members, NewCStructField("b", SignedInt))

	outer := NewCStructType("Outer")
	outer.Members = append(outer.Members, NewCStructField("x", SignedInt))
	outer.Members = append(outer.Members, NewCStructField("nested", inner))
	outer.Members = append(outer.Members, NewCStructField("y", SignedInt))

	outerLayout := NewStructLayout(outer)
	nestedLayout := outerLayout.FieldLayout("nested")
	nestedField := outerLayout.Field("nested")

	stack := make([]Value, 10)
	basePtr := 0

	outerLayout.Field("x").Set(stack, basePtr, ValueOf(10))
	nestedPtr := nestedField.GetAddress(basePtr)
	nestedLayout.Field("a").Set(stack, nestedPtr, ValueOf(20))
	nestedLayout.Field("b").Set(stack, nestedPtr, ValueOf(30))
	outerLayout.Field("y").Set(stack, basePtr, ValueOf(40))

	assert.Equal(t, int32(10), stack[0].Int32Value)
	assert.Equal(t, int32(20), stack[1].Int32Value)
	assert.Equal(t, int32(30), stack[2].Int32Value)
	assert.Equal(t, int32(40), stack[3].Int32Value)

	assert.Equal(t, int32(20), nestedLayout.Field("a").Get(stack, nestedPtr).Int32Value)
	assert.Equal(t, int32(30), nestedLayout.Field("b").Get(stack, nestedPtr).Int32Value)
}

func TestStructLayoutFieldThrowsForUnknownField(t *testing.T) {
	s := NewCStructType("S")
	s.Members = append(s.Members, NewCStructField("x", SignedInt))

	layout := NewStructLayout(s)
	assert.Panics(t, func() { layout.Field("nonexistent") })
}

func TestStructLayoutFieldLayoutThrowsForNonStructField(t *testing.T) {
	s := NewCStructType("S")
	s.Members = append(s.Members, NewCStructField("x", SignedInt))

	layout := NewStructLayout(s)
	assert.Panics(t, func() { layout.FieldLayout("x") })
}

func TestStructLayoutPolymorphicStructFieldOffsetSkipsVptr(t *testing.T) {
	s := NewCStructType("Base")
	s.Members = append(s.Members, NewCStructField("x", SignedInt))
	method := NewCStructMethod("foo", NewCFunctionType(SignedInt, true, s))
	method.IsVirtual = true
	s.Members = append(s.Members, method)
	s.BuildVTable()

	layout := NewStructLayout(s)
	assert.Equal(t, 1, layout.Field("x").Offset)
}

func TestStructLayoutDerivedStructFieldOffsetIncludesBase(t *testing.T) {
	baseType := NewCStructType("Base")
	baseType.Members = append(baseType.Members, NewCStructField("x", SignedInt))
	method := NewCStructMethod("foo", NewCFunctionType(SignedInt, true, baseType))
	method.IsVirtual = true
	baseType.Members = append(baseType.Members, method)
	baseType.BuildVTable()

	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	derived.Members = append(derived.Members, NewCStructField("y", SignedInt))
	derived.BuildVTable()

	layout := NewStructLayout(derived)
	assert.Equal(t, 1, layout.Field("x").Offset)
	assert.Equal(t, 2, layout.Field("y").Offset)
}

func TestStructLayoutNonPolymorphicDerivedFieldOffset(t *testing.T) {
	baseType := NewCStructType("Base")
	baseType.Members = append(baseType.Members, NewCStructField("x", SignedInt))

	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	derived.Members = append(derived.Members, NewCStructField("y", SignedInt))

	layout := NewStructLayout(derived)
	assert.Equal(t, 0, layout.Field("x").Offset)
	assert.Equal(t, 1, layout.Field("y").Offset)
}

func TestStructLayoutMatchesCompiledOffsets(t *testing.T) {
	exe := compileCode(t, `
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
`, newArduinoTestMachineInfo())
	if exe == nil {
		return
	}
	var sVar *CompiledGlobal
	for i, g := range exe.Globals {
		if g.Name == "s" {
			sVar = &exe.Globals[i]
			break
		}
	}
	if sVar == nil {
		t.Skip("global 's' not found")
	}
	sType, ok := sVar.VariableType.(*CStructType)
	if !ok {
		t.Skip("s is not a struct type")
	}
	layout := NewStructLayout(sType)
	assert.Equal(t, 0, layout.Field("id").Offset)
	assert.Equal(t, 1, layout.Field("temperature").Offset)
	assert.Equal(t, 2, layout.Field("status").Offset)
}

func TestStructLayoutConstructorThrowsOnNull(t *testing.T) {
	assert.Panics(t, func() { NewStructLayout(nil) })
}
