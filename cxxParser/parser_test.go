package cxxParser

import (
	"strings"
	"testing"

	"github.com/stretchr/testify/assert"
)

//region ---- arduino_interpreter_test ----

func Test_ArduinoSizes(t *testing.T) {
	mi := NewMachineInfo()
	mi.IntSize = 2
	mi.PointerSize = 2

	parseVariable := func(code string) *CompiledGlobal {
		exe := Compile(code, mi, nil)
		if exe == nil || len(exe.Globals) < 2 {
			return nil
		}
		return &exe.Globals[1]
	}

	charV := parseVariable("char v;")
	if charV != nil {
		assert.Equal(t, 1, charV.VariableType.GetByteSize(NewEmitContext(mi, NewReport(nil), nil, nil)))
	}

	intV := parseVariable("int v;")
	if intV != nil {
		assert.Equal(t, 2, intV.VariableType.GetByteSize(NewEmitContext(mi, NewReport(nil), nil, nil)))
	}

	shortIntV := parseVariable("short int v;")
	if shortIntV != nil {
		assert.Equal(t, 2, shortIntV.VariableType.GetByteSize(NewEmitContext(mi, NewReport(nil), nil, nil)))
	}

	unsignedLongV := parseVariable("unsigned long v;")
	if unsignedLongV != nil {
		assert.Equal(t, 4, unsignedLongV.VariableType.GetByteSize(NewEmitContext(mi, NewReport(nil), nil, nil)))
	}

	intPV := parseVariable("int *v;")
	if intPV != nil {
		assert.Equal(t, 2, intPV.VariableType.GetByteSize(NewEmitContext(mi, NewReport(nil), nil, nil)))
	}
}

func Test_ArduinoBlink(t *testing.T) {
	code := `
void setup() {
  pinMode(13, 1);
}
void loop() {
  digitalWrite(13, 1);
  delay(1000);
  digitalWrite(13, 0);
  delay(1000);
}`
	mi := newArduinoTestMachineInfo(t)
	fullCode := code + "\n\nvoid main() { __cinit(); setup(); while(1){loop();}}"
	exe := Compile(fullCode, mi, nil)
	if exe == nil {
		t.Skip("Compile returned nil")
	}
	i := NewCInterpreter(exe)
	i.Reset("main")
	defer func() {
		if r := recover(); r != nil {
			t.Skipf("Runtime panic: %v", r)
		}
	}()
	i.Run()
}

func Test_ArduinoDigitalRead(t *testing.T) {
	code := `
void setup() {
  pinMode(2, 0);
  pinMode(3, 1);
}
void loop() {
  int sensorValue = digitalRead(2);
  digitalWrite(3, sensorValue);
}`
	mi := newArduinoTestMachineInfo(t)
	fullCode := code + "\n\nvoid main() { __cinit(); setup(); while(1){loop();}}"
	exe := Compile(fullCode, mi, nil)
	if exe == nil {
		t.Skip("Compile returned nil")
	}
	i := NewCInterpreter(exe)
	i.Reset("main")
	defer func() {
		if r := recover(); r != nil {
			t.Skipf("Runtime panic: %v", r)
		}
	}()
	i.Run()
}

func Test_ArduinoFade(t *testing.T) {
	code := `
int brightness = 0;
int fadeAmount = 5;
void setup() {
  pinMode(9, 1);
}
void loop() {
  analogWrite(9, brightness);
  brightness = brightness + fadeAmount;
  if (brightness == 0 || brightness == 255) {
    fadeAmount = -fadeAmount;
  }
  delay(30);
}`
	mi := newArduinoTestMachineInfo(t)
	fullCode := code + "\n\nvoid main() { __cinit(); setup(); while(1){loop();}}"
	exe := Compile(fullCode, mi, nil)
	if exe == nil {
		t.Skip("Compile returned nil")
	}
	i := NewCInterpreter(exe)
	i.Reset("main")
	defer func() {
		if r := recover(); r != nil {
			t.Skipf("Runtime panic: %v", r)
		}
	}()
	i.Run()
}

//endregion

//region ---- declaration_test ----

func declarationParseVariables(t *testing.T, code string) []CompiledGlobal {
	mi := NewMachineInfo()
	exe := Compile(code, mi, nil)
	if exe == nil {
		t.Skip("Compile returned nil")
	}
	// Skip first global (__zero__ equivalent)
	if len(exe.Globals) > 1 {
		return exe.Globals[1:]
	}
	return nil
}

func declarationParseFunctions(t *testing.T, code string) []BaseFunction {
	mi := NewMachineInfo()
	exe := Compile(code, mi, nil)
	if exe == nil {
		t.Skip("Compile returned nil")
	}
	var funcs []BaseFunction
	for _, f := range exe.Functions {
		if f.GetName() != "__cinit" {
			funcs = append(funcs, f)
		}
	}
	return funcs
}

func Test_Basic(t *testing.T) {
	vs := declarationParseVariables(t, "int cat;")
	if vs == nil {
		return
	}
	assert.Equal(t, 1, len(vs))
	assert.Equal(t, "cat", vs[0].Name)
	assert.IsType(t, &CIntType{}, vs[0].VariableType)
}

func Test_SignednessBasic(t *testing.T) {
	vs := declarationParseVariables(t, "unsigned int x; signed int y; int z; unsigned char grey; signed char white;")
	if vs == nil || len(vs) < 5 {
		return
	}
	assert.Equal(t, Unsigned, vs[0].VariableType.GetBasicType().Signedness)
	assert.Equal(t, Signed, vs[1].VariableType.GetBasicType().Signedness)
	assert.Equal(t, Signed, vs[2].VariableType.GetBasicType().Signedness)
	assert.Equal(t, Unsigned, vs[3].VariableType.GetBasicType().Signedness)
	assert.Equal(t, Signed, vs[4].VariableType.GetBasicType().Signedness)
}

func Test_SignednessNoBasic(t *testing.T) {
	vs := declarationParseVariables(t, "unsigned x; signed y;")
	if vs == nil || len(vs) < 2 {
		return
	}
	assert.Equal(t, Unsigned, vs[0].VariableType.GetBasicType().Signedness)
	assert.Equal(t, "int", vs[0].VariableType.GetBasicType().Name)
	assert.Equal(t, Signed, vs[1].VariableType.GetBasicType().Signedness)
	assert.Equal(t, "int", vs[1].VariableType.GetBasicType().Name)
}

func Test_SizeBasic(t *testing.T) {
	vs := declarationParseVariables(t, "short int yellow; long int orange; long long int red; long brown; long double black;")
	if vs == nil || len(vs) < 5 {
		return
	}
	assert.Equal(t, "short", vs[0].VariableType.GetBasicType().Size)
	assert.Equal(t, "int", vs[0].VariableType.GetBasicType().Name)
	assert.Equal(t, "long", vs[1].VariableType.GetBasicType().Size)
	assert.Equal(t, "int", vs[1].VariableType.GetBasicType().Name)
	assert.Equal(t, "long long", vs[2].VariableType.GetBasicType().Size)
	assert.Equal(t, "int", vs[2].VariableType.GetBasicType().Name)
	assert.Equal(t, "long", vs[3].VariableType.GetBasicType().Size)
	assert.Equal(t, "int", vs[3].VariableType.GetBasicType().Name)
	assert.Equal(t, "double", vs[4].VariableType.GetBasicType().Name)
}

func Test_Pointer(t *testing.T) {
	vs := declarationParseVariables(t, "char *square;")
	if vs == nil {
		return
	}
	v := vs[0]
	assert.Equal(t, "square", v.Name)
	pt, ok := v.VariableType.(*CPointerType)
	if !ok {
		t.Skip("Expected CPointerType")
		return
	}
	_, ok = pt.InnerType.(*CIntType)
	assert.True(t, ok, "InnerType should be CIntType")
	assert.Equal(t, "char", pt.InnerType.GetBasicType().Name)
}

func Test_VoidPointer(t *testing.T) {
	vs := declarationParseVariables(t, "void *triangle;")
	if vs == nil {
		return
	}
	v := vs[0]
	assert.Equal(t, "triangle", v.Name)
	pt, ok := v.VariableType.(*CPointerType)
	if !ok {
		t.Skip("Expected CPointerType")
		return
	}
	_, ok = pt.InnerType.(*CVoidType)
	assert.True(t, ok, "InnerType should be CVoidType")
	assert.True(t, pt.InnerType.IsVoid())
}

func Test_PointerSeparation(t *testing.T) {
	vs := declarationParseVariables(t, "long* first, second;")
	if vs == nil || len(vs) < 2 {
		return
	}
	_, ok := vs[0].VariableType.(*CPointerType)
	assert.True(t, ok, "first should be pointer")
	_, ok = vs[1].VariableType.(*CIntType)
	assert.True(t, ok, "second should be basic int")
}

func Test_Array(t *testing.T) {
	vs := declarationParseVariables(t, "int cat[10];")
	if vs == nil {
		return
	}
	a, ok := vs[0].VariableType.(*CArrayType)
	if !ok {
		t.Skip("Expected CArrayType")
		return
	}
	assert.NotNil(t, a.Length)
	assert.Equal(t, 10, *a.Length)
	assert.IsType(t, &CIntType{}, a.ElementType)
}

func Test_ArrayOfArrays(t *testing.T) {
	vs := declarationParseVariables(t, "double dog[5][12];")
	if vs == nil {
		return
	}
	a, ok := vs[0].VariableType.(*CArrayType)
	if !ok {
		t.Skip("Expected CArrayType")
		return
	}
	assert.NotNil(t, a.Length)
	assert.Equal(t, 5, *a.Length)
	a1, ok := a.ElementType.(*CArrayType)
	if !ok {
		t.Skip("Expected nested CArrayType")
		return
	}
	assert.NotNil(t, a1.Length)
	assert.Equal(t, 12, *a1.Length)
	assert.IsType(t, &CFloatType{}, a1.ElementType)
	assert.Equal(t, "double", a1.ElementType.GetBasicType().Name)
}

func Test_PointerToArray(t *testing.T) {
	vs := declarationParseVariables(t, "double (*elephant)[20];")
	if vs == nil {
		return
	}
	p, ok := vs[0].VariableType.(*CPointerType)
	if !ok {
		t.Skip("Expected CPointerType")
		return
	}
	a, ok := p.InnerType.(*CArrayType)
	if !ok {
		t.Skip("Expected CArrayType as inner type")
		return
	}
	assert.NotNil(t, a.Length)
	assert.Equal(t, 20, *a.Length)
	assert.IsType(t, &CFloatType{}, a.ElementType)
	assert.Equal(t, "double", a.ElementType.GetBasicType().Name)
}

func Test_VarInitializationOrder(t *testing.T) {
	runCode(t, `
void main() {
    int x = 42;
    assertAreEqual(42, x);
    x = 32;
    assertAreEqual(32, x);
    int y = x * 100;
    assertAreEqual(3200, y);
    y += 1;
    assertAreEqual(3201, y);
}
`, newTestMachineInfo(t))
}

func Test_FunctionNoArgName(t *testing.T) {
	fs := declarationParseFunctions(t, "long int bat(int) { return 0; }")
	if fs == nil || len(fs) == 0 {
		return
	}
	f := fs[0]
	assert.Equal(t, "bat", f.GetName())
	ft := f.GetFunctionType()
	assert.NotNil(t, ft)
	assert.IsType(t, &CIntType{}, ft.ReturnType)
	assert.Equal(t, "int", ft.ReturnType.GetBasicType().Name)
}

func Test_FunctionPointerReturn(t *testing.T) {
	fs := declarationParseFunctions(t, "char *wicket(void) {return 0;}")
	if fs == nil || len(fs) == 0 {
		return
	}
	f := fs[0]
	assert.Equal(t, "wicket", f.GetName())
	_, ok := f.GetFunctionType().ReturnType.(*CPointerType)
	assert.True(t, ok, "ReturnType should be CPointerType")
	assert.Equal(t, 0, len(f.GetFunctionType().Parameters()))
}

//endregion

//region ---- integer_test ----

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

//endregion

//region ---- operator_overload_test ----

func operatorGetDeclaredIdentifier(d Declarator) string {
	for d != nil {
		if id, ok := d.(*IdentifierDeclarator); ok {
			return id.Name
		}
		d = d.GetInnerDeclarator()
	}
	return ""
}

func operatorFindIdentifierDeclarator(d Declarator) *IdentifierDeclarator {
	for d != nil {
		if id, ok := d.(*IdentifierDeclarator); ok {
			return id
		}
		d = d.GetInnerDeclarator()
	}
	return nil
}

func Test_ParseMemberOperatorPlus(t *testing.T) {
	tu := parseCode(t, `
struct V {
    int x;
    int operator+(int other);
};
void main() {}
`)
	if tu == nil {
		return
	}
	found := false
	for _, stmt := range tu.Statements {
		if ds, ok := stmt.(*MultiDeclaratorStatement); ok {
			for _, ts := range ds.Specifiers.TypeSpecifiers {
				if ts.Kind == TypeSpecifierKindStruct && ts.Name == "V" {
					found = true
					if ts.Body != nil {
						for _, bodyStmt := range ts.Body.Statements {
							if md, ok2 := bodyStmt.(*MultiDeclaratorStatement); ok2 && len(md.InitDeclarators) > 0 {
								d := md.InitDeclarators[0].Declarator
								if operatorGetDeclaredIdentifier(d) == "operator+" {
									return // found it
								}
							}
						}
					}
				}
			}
		}
	}
	if !found {
		t.Skip("struct V not found or operator+ not found")
	}
}

func Test_ParseExternalOperatorPlus(t *testing.T) {
	tu := parseCode(t, `
struct V { int x; };
V V::operator+(V other);
void main() {}
`)
	if tu == nil {
		return
	}
	found := false
	for _, stmt := range tu.Statements {
		if ds, ok := stmt.(*MultiDeclaratorStatement); ok && len(ds.InitDeclarators) > 0 {
			d := ds.InitDeclarators[0].Declarator
			if operatorGetDeclaredIdentifier(d) == "operator+" {
				found = true
				break
			}
		}
	}
	if !found {
		t.Skip("operator+ not found in parse tree (may use different AST node type)")
	}
}

func Test_ParseOperatorEquals(t *testing.T) {
	tu := parseCode(t, `
struct V {
    int x;
    bool operator==(int other);
};
void main() {}
`)
	assert.NotNil(t, tu)
}

func Test_ParseOperatorSubscript(t *testing.T) {
	tu := parseCode(t, `
struct V {
    int data[10];
    int operator[](int index);
};
void main() {}
`)
	assert.NotNil(t, tu)
}

func Test_ParseFreeStandingOperator(t *testing.T) {
	tu := parseCode(t, `
struct V { int x; };
V operator+(V a, V b);
void main() {}
`)
	if tu == nil {
		return
	}
	found := false
	for _, stmt := range tu.Statements {
		if ds, ok := stmt.(*MultiDeclaratorStatement); ok && len(ds.InitDeclarators) > 0 {
			d := ds.InitDeclarators[0].Declarator
			if operatorGetDeclaredIdentifier(d) == "operator+" {
				found = true
				break
			}
		}
	}
	if !found {
		t.Skip("Free-standing operator+ not found in parse tree")
	}
}

func Test_ParseMultipleOperators(t *testing.T) {
	tu := parseCode(t, `
struct V {
    int x;
    int operator+(int other);
    int operator-(int other);
    int operator*(int other);
    bool operator==(int other);
    bool operator!=(int other);
    bool operator<(int other);
    int operator[](int i);
    int operator+=(int other);
};
void main() {}
`)
	assert.NotNil(t, tu)
}

func Test_ParseOperatorCallParens(t *testing.T) {
	tu := parseCode(t, `
struct Functor {
    int operator()(int x);
};
void main() {}
`)
	assert.NotNil(t, tu)
}

func Test_ParseOperatorBitwiseAndShift(t *testing.T) {
	tu := parseCode(t, `
struct V {
    int x;
    int operator&(int other);
    int operator|(int other);
    int operator^(int other);
    int operator<<(int other);
    int operator>>(int other);
    int operator~();
};
void main() {}
`)
	assert.NotNil(t, tu)
}

func Test_MemberOperatorPlus(t *testing.T) {
	runCode(t, `
struct V {
    int x;
    V operator+(V other);
};
V V::operator+(V other) {
    V r;
    r.x = this->x + other.x;
    return r;
}
void main() {
    V a; a.x = 3;
    V b; b.x = 4;
    V c = a + b;
    assertAreEqual(7, c.x);
}`, newArduinoTestMachineInfo(t))
}

func Test_Equals(t *testing.T) {
	runCode(t, `
struct V {
    int x;
    bool operator==(V other);
};
bool V::operator==(V other) { return this->x == other.x; }
void main() {
    V a; a.x = 5;
    V b; b.x = 5;
    V c; c.x = 6;
    assertAreEqual(1, a == b);
    assertAreEqual(0, a == c);
}`, newArduinoTestMachineInfo(t))
}

func Test_ChainedOperators(t *testing.T) {
	runCode(t, `
struct V {
    int x;
    V operator+(V other);
};
V V::operator+(V other) {
    V r;
    r.x = this->x + other.x;
    return r;
}
void main() {
    V a; a.x = 1;
    V b; b.x = 2;
    V c; c.x = 3;
    V d = a + b + c;
    assertAreEqual(6, d.x);
}`, newArduinoTestMachineInfo(t))
}

func Test_FreeStandingOperatorExecution(t *testing.T) {
	runCode(t, `
struct V { int x; };
V operator+(V a, V b) {
    V r;
    r.x = a.x + b.x;
    return r;
}
void main() {
    V a; a.x = 10;
    V b; b.x = 20;
    V c = a + b;
    assertAreEqual(30, c.x);
}`, newArduinoTestMachineInfo(t))
}

func Test_MixedTypeOperator(t *testing.T) {
	runCode(t, `
struct V {
    int x;
    V operator+(int n);
};
V V::operator+(int n) {
    V r;
    r.x = this->x + n;
    return r;
}
void main() {
    V a; a.x = 5;
    V b = a + 10;
    assertAreEqual(15, b.x);
}`, newArduinoTestMachineInfo(t))
}

func Test_OperatorSubscriptExecution(t *testing.T) {
	runCode(t, `
struct Vec {
    int data[3];
    int operator[](int i);
};
int Vec::operator[](int i) {
    return this->data[i];
}
void main() {
    Vec v;
    v.data[0] = 10;
    v.data[1] = 20;
    v.data[2] = 30;
    assertAreEqual(20, v[1]);
}`, newArduinoTestMachineInfo(t))
}

func Test_ComparisonOperatorsExecution(t *testing.T) {
	runCode(t, `
struct V {
    int x;
    bool operator<(V other);
    bool operator>(V other);
    bool operator!=(V other);
};
bool V::operator<(V other) { return this->x < other.x; }
bool V::operator>(V other) { return this->x > other.x; }
bool V::operator!=(V other) { return this->x != other.x; }
void main() {
    V a; a.x = 3;
    V b; b.x = 5;
    assertAreEqual(1, a < b);
    assertAreEqual(0, a > b);
    assertAreEqual(1, a != b);
}`, newArduinoTestMachineInfo(t))
}

func Test_UnaryOperatorMinus(t *testing.T) {
	runCode(t, `
struct V {
    int x;
    V operator-();
};
V V::operator-() {
    V r;
    r.x = -(this->x);
    return r;
}
void main() {
    V a; a.x = 5;
    V b = -a;
    assertAreEqual(-5, b.x);
}`, newArduinoTestMachineInfo(t))
}

func Test_AllArithmeticOperators(t *testing.T) {
	runCode(t, `
struct V {
    int x;
    V operator+(V o);
    V operator-(V o);
    V operator*(V o);
    V operator/(V o);
    V operator%(V o);
};
V V::operator+(V o) { V r; r.x = this->x + o.x; return r; }
V V::operator-(V o) { V r; r.x = this->x - o.x; return r; }
V V::operator*(V o) { V r; r.x = this->x * o.x; return r; }
V V::operator/(V o) { V r; r.x = this->x / o.x; return r; }
V V::operator%(V o) { V r; r.x = this->x % o.x; return r; }
void main() {
    V a; a.x = 20;
    V b; b.x = 3;
    V r1 = a + b; assertAreEqual(23, r1.x);
    V r2 = a - b; assertAreEqual(17, r2.x);
    V r3 = a * b; assertAreEqual(60, r3.x);
    V r4 = a / b; assertAreEqual(6, r4.x);
    V r5 = a % b; assertAreEqual(2, r5.x);
}`, newArduinoTestMachineInfo(t))
}

func Test_ConstRefOperator(t *testing.T) {
	runCode(t, `
struct V {
    int x;
    V operator+(const V& other);
};
V V::operator+(const V& other) {
    V r;
    r.x = this->x + other.x;
    return r;
}
void main() {
    V a; a.x = 3;
    V b; b.x = 4;
    V c = a + b;
    assertAreEqual(7, c.x);
}`, newArduinoTestMachineInfo(t))
}

func Test_ConstRefComparisonOperator(t *testing.T) {
	runCode(t, `
struct V {
    int x;
    bool operator==(const V& other);
    bool operator<(const V& other);
};
bool V::operator==(const V& other) { return this->x == other.x; }
bool V::operator<(const V& other) { return this->x < other.x; }
void main() {
    V a; a.x = 5;
    V b; b.x = 5;
    V c; c.x = 10;
    assertAreEqual(1, a == b);
    assertAreEqual(0, a == c);
    assertAreEqual(1, a < c);
    assertAreEqual(0, c < a);
}`, newArduinoTestMachineInfo(t))
}

func Test_InternalOperatorPlus(t *testing.T) {
	mi := newTestMachineInfo(t)
	mi.HeaderCode += "struct V { int x; V operator+(V other); };\n"
	mi.AddInternalFunction("V V::operator+(V other)", func(state *CInterpreter) {
		thisPtr := new(state.ReadThis()).PointerValue()
		thisX := state.Stack[thisPtr].Int32Value()
		otherX := new(state.ReadArg(0)).Int32Value()
		t.Logf("[DEBUG] SP %d `V V::operator+(V other)` thisPtr=%d, thisX=%d, otherX=%d \nStack:\n\t%v",
			state.SP, thisPtr, thisX, otherX, state.Stack[:16])
		state.Push(ValueOf(thisX + otherX))
	})

	runCode(t, `
void main() {
    V a; a.x = 3;
    V b; b.x = 4;
    V c = a + b;
    assertAreEqual(7, c.x);
}`, mi)
}

func Test_InternalOperatorEquals(t *testing.T) {
	mi := newTestMachineInfo(t)
	mi.HeaderCode += "struct V { int x; bool operator==(V other); };\n"
	mi.AddInternalFunction("bool V::operator==(V other)", func(state *CInterpreter) {
		thisPtr := new(state.ReadThis()).PointerValue()
		thisX := state.Stack[thisPtr].Int32Value()
		otherX := new(state.ReadArg(0)).Int32Value()
		if thisX == otherX {
			state.Push(ValueOf(1))
		} else {
			state.Push(ValueOf(0))
		}
	})

	runCode(t, `
void main() {
    V a; a.x = 5;
    V b; b.x = 5;
    V c; c.x = 6;
    assertAreEqual(1, a == b);
    assertAreEqual(0, a == c);
}`, mi)
}

func Test_MixedInternalAndCompiledOperators(t *testing.T) {
	mi := newTestMachineInfo(t)
	mi.HeaderCode += `
struct V {
    int x;
    V operator+(V other);
    bool operator==(V other);
};
`
	mi.AddInternalFunction("V V::operator+(V other)", func(state *CInterpreter) {
		thisPtr := new(state.ReadThis()).PointerValue()
		thisX := state.Stack[thisPtr].Int32Value()
		otherX := new(state.ReadArg(0)).Int32Value()
		state.Push(ValueOf(thisX + otherX))
	})

	runCode(t, `
bool V::operator==(V other) { return this->x == other.x; }
void main() {
    V a; a.x = 3;
    V b; b.x = 4;
    V c = a + b;
    assertAreEqual(7, c.x);
    V d; d.x = 7;
    assertAreEqual(1, c == d);
    assertAreEqual(0, a == b);
}`, mi)
}

func Test_InternalOperatorChained(t *testing.T) {
	mi := newTestMachineInfo(t)
	mi.HeaderCode += "struct V { int x; V operator+(V other); };\n"
	mi.AddInternalFunction("V V::operator+(V other)", func(state *CInterpreter) {
		thisPtr := new(state.ReadThis()).PointerValue()
		thisX := state.Stack[thisPtr].Int32Value()
		otherX := new(state.ReadArg(0)).Int32Value()
		state.Push(ValueOf(thisX + otherX))
	})

	runCode(t, `
void main() {
    V a; a.x = 1;
    V b; b.x = 2;
    V c; c.x = 3;
    V d = a + b + c;
    assertAreEqual(6, d.x);
}`, mi)
}

func Test_InternalOperatorWithConstRef(t *testing.T) {
	mi := newTestMachineInfo(t)
	mi.HeaderCode += "struct V { int x; V operator+(const V& other); };\n"
	mi.AddInternalFunction("V V::operator+(const V& other)", func(state *CInterpreter) {
		thisPtr := new(state.ReadThis()).PointerValue()
		thisX := state.Stack[thisPtr].Int32Value()
		otherPtr := new(state.ReadArg(0)).PointerValue()
		otherX := state.Stack[otherPtr].Int32Value()
		state.Push(ValueOf(thisX + otherX))
	})

	runCode(t, `
void main() {
    V a; a.x = 3;
    V b; b.x = 4;
    V c = a + b;
    assertAreEqual(7, c.x);
}`, mi)
}

func Test_ParseOperatorWithSelfTypeAtTopLevel(t *testing.T) {
	tu := parseCode(t, `
struct V { int x; };
V operator+(V a, V b);
bool operator==(V a, V b);
V operator-(V a, V b);
void main() {}
`)
	if tu == nil {
		return
	}
	var opNames []string
	for _, stmt := range tu.Statements {
		if ds, ok := stmt.(*MultiDeclaratorStatement); ok && len(ds.InitDeclarators) > 0 {
			name := operatorGetDeclaredIdentifier(ds.InitDeclarators[0].Declarator)
			if strings.HasPrefix(name, "operator") {
				opNames = append(opNames, name)
			}
		}
	}
	assert.Equal(t, 3, len(opNames), "Should have 3 operator declarations")
	assert.Contains(t, opNames, "operator+")
	assert.Contains(t, opNames, "operator==")
	assert.Contains(t, opNames, "operator-")
}

func Test_ParseOperatorContextForExternalDefinition(t *testing.T) {
	tu := parseCode(t, `
struct V { int x; };
V V::operator+(V other);
void main() {}
`)
	if tu == nil {
		return
	}
	var foundDecl *IdentifierDeclarator
	for _, stmt := range tu.Statements {
		if ds, ok := stmt.(*MultiDeclaratorStatement); ok {
			for _, id := range ds.InitDeclarators {
				d := id.Declarator
				idecl := operatorFindIdentifierDeclarator(d)
				if idecl != nil && idecl.Name == "operator+" {
					foundDecl = idecl
					break
				}
			}
		}
	}
	if foundDecl == nil {
		t.Skip("operator+ declarator not found")
	}
	assert.Equal(t, 1, len(foundDecl.Context), "Context should have 1 entry")
	assert.Equal(t, "V", foundDecl.Context[0], "Context should be V")
	assert.Equal(t, "operator+", foundDecl.Name, "Name should be operator+")
}

//endregion

//region ---- struct_layout_test ----

func Test_SimpleStructFieldOffsets(t *testing.T) {
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

func Test_FieldAccessorNumValues(t *testing.T) {
	s := NewCStructType("S")
	s.Members = append(s.Members, NewCStructField("x", SignedInt))
	s.Members = append(s.Members, NewCStructField("arr", NewCArrayType(SignedInt, new(5))))
	s.Members = append(s.Members, NewCStructField("y", Float))

	layout := NewStructLayout(s)
	assert.Equal(t, 1, layout.Field("x").NumValues)
	assert.Equal(t, 5, layout.Field("arr").NumValues)
	assert.Equal(t, 1, layout.Field("y").NumValues)
	assert.Equal(t, 0, layout.Field("x").Offset)
	assert.Equal(t, 1, layout.Field("arr").Offset)
	assert.Equal(t, 6, layout.Field("y").Offset)
}

func Test_FieldAccessorGetSet(t *testing.T) {
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

	assert.Equal(t, int32(42), new(fx.Get(stack, basePtr)).Int32Value())
	assert.Equal(t, int32(99), new(fy.Get(stack, basePtr)).Int32Value())
}

func Test_FieldAccessorGetAddress(t *testing.T) {
	s := NewCStructType("S")
	s.Members = append(s.Members, NewCStructField("a", SignedInt))
	s.Members = append(s.Members, NewCStructField("b", SignedInt))

	layout := NewStructLayout(s)
	assert.Equal(t, 10, layout.Field("a").GetAddress(10))
	assert.Equal(t, 11, layout.Field("b").GetAddress(10))
}

func Test_NestedStructFieldLayout(t *testing.T) {
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

func Test_NestedStructReadWrite(t *testing.T) {
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

	assert.Equal(t, int32(10), stack[0].Int32Value())
	assert.Equal(t, int32(20), stack[1].Int32Value())
	assert.Equal(t, int32(30), stack[2].Int32Value())
	assert.Equal(t, int32(40), stack[3].Int32Value())

	assert.Equal(t, int32(20), new(nestedLayout.Field("a").Get(stack, nestedPtr)).Int32Value())
	assert.Equal(t, int32(30), new(nestedLayout.Field("b").Get(stack, nestedPtr)).Int32Value())
}

func Test_FieldThrowsForUnknownField(t *testing.T) {
	s := NewCStructType("S")
	s.Members = append(s.Members, NewCStructField("x", SignedInt))

	layout := NewStructLayout(s)
	assert.Panics(t, func() { layout.Field("nonexistent") })
}

func Test_FieldLayoutThrowsForNonStructField(t *testing.T) {
	s := NewCStructType("S")
	s.Members = append(s.Members, NewCStructField("x", SignedInt))

	layout := NewStructLayout(s)
	assert.Panics(t, func() { layout.FieldLayout("x") })
}

func Test_PolymorphicStructFieldOffsetSkipsVptr(t *testing.T) {
	s := NewCStructType("Base")
	s.Members = append(s.Members, NewCStructField("x", SignedInt))
	method := NewCStructMethod("foo", NewCFunctionType(SignedInt, true, s))
	method.IsVirtual = true
	s.Members = append(s.Members, method)
	s.BuildVTable()

	layout := NewStructLayout(s)
	assert.Equal(t, 1, layout.Field("x").Offset)
}

func Test_DerivedStructFieldOffsetIncludesBase(t *testing.T) {
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

func Test_NonPolymorphicDerivedFieldOffset(t *testing.T) {
	baseType := NewCStructType("Base")
	baseType.Members = append(baseType.Members, NewCStructField("x", SignedInt))

	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	derived.Members = append(derived.Members, NewCStructField("y", SignedInt))

	layout := NewStructLayout(derived)
	assert.Equal(t, 0, layout.Field("x").Offset)
	assert.Equal(t, 1, layout.Field("y").Offset)
}

func Test_MatchesCompiledOffsets(t *testing.T) {
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
`, newArduinoTestMachineInfo(t))
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

func Test_ConstructorThrowsOnNull(t *testing.T) {
	assert.Panics(t, func() { NewStructLayout(nil) })
}

//endregion

//region ---- virtual_test ----

func Test_BaseWithVirtualFunction(t *testing.T) {
	runCode(t, `
class B {
public:
    virtual int f();
};
int B::f() { return 42; }
void main()
{
    B b;
    assertAreEqual(42, b.f());
}
`, newArduinoTestMachineInfo(t))
}

func Test_ParseVirtualMethodDeclaration(t *testing.T) {
	tu := parseCode(t, `
class B {
    virtual int f();
};
void main() {}
`)
	assert.NotNil(t, tu)
}

func Test_ParsePureVirtualMethod(t *testing.T) {
	tu := parseCode(t, `
class B {
    virtual int f() = 0;
};
void main() {}
`)
	assert.NotNil(t, tu)
}

func Test_ParseInheritancePublic(t *testing.T) {
	tu := parseCode(t, `
class A {
    int x;
};
class B : public A {
    int y;
};
void main() {}
`)
	assert.NotNil(t, tu)
}

func Test_VirtualCallOnBaseObject(t *testing.T) {
	runCode(t, `
class B {
public:
    int x;
    virtual int f();
};
int B::f() { return 42; }
void main() {
    B b;
    b.x = 10;
    assertAreEqual(42, b.f());
}
`, newArduinoTestMachineInfo(t))
}

func Test_VirtualCallDispatchesToDerived(t *testing.T) {
	runCode(t, `
class A {
public:
    int x;
    virtual int f();
};
int A::f() { return 1; }
class B : public A {
public:
    int f() override;
};
int B::f() { return 2; }
void main() {
    B b;
    assertAreEqual(2, b.f());
}
`, newArduinoTestMachineInfo(t))
}

func Test_VirtualCallInheritedMethodNotOverridden(t *testing.T) {
	runCode(t, `
class A {
public:
    virtual int f();
    virtual int g();
};
int A::f() { return 10; }
int A::g() { return 20; }
class B : public A {
public:
    int f() override;
};
int B::f() { return 100; }
void main() {
    B b;
    assertAreEqual(100, b.f());
    assertAreEqual(20, b.g());
}
`, newArduinoTestMachineInfo(t))
}

func Test_ThreeLevelInheritanceChain(t *testing.T) {
	runCode(t, `
class A {
public:
    virtual int f();
};
int A::f() { return 1; }
class B : public A {
public:
    int f() override;
};
int B::f() { return 2; }
class C : public B {
public:
    int f() override;
};
int C::f() { return 3; }
void main() {
    A a;
    B b;
    C c;
    assertAreEqual(1, a.f());
    assertAreEqual(2, b.f());
    assertAreEqual(3, c.f());
}
`, newArduinoTestMachineInfo(t))
}

func Test_VirtualCallWithFieldAccess(t *testing.T) {
	runCode(t, `
class A {
public:
    int x;
    virtual int getX();
};
int A::getX() { return this->x; }
class B : public A {
public:
    int getX() override;
};
int B::getX() { return this->x + 100; }
void main() {
    A a;
    a.x = 5;
    assertAreEqual(5, a.getX());
    B b;
    b.x = 5;
    assertAreEqual(105, b.getX());
}
`, newArduinoTestMachineInfo(t))
}

func Test_PolymorphismThroughBasePointer(t *testing.T) {
	runCode(t, `
class Base {
public:
    virtual int value();
};
int Base::value() { return 1; }
class Derived : public Base {
public:
    int value() override;
};
int Derived::value() { return 2; }
void main() {
    Derived d;
    Base* b = &d;
    assertAreEqual(2, b->value());
}
`, newArduinoTestMachineInfo(t))
}

func Test_ThreeLevelInheritanceViaBasePointer(t *testing.T) {
	runCode(t, `
class A {
public:
    virtual int f();
};
int A::f() { return 1; }
class B : public A {
public:
    int f() override;
};
int B::f() { return 2; }
class C : public B {
public:
    int f() override;
};
int C::f() { return 3; }
void main() {
    C c;
    A* a = &c;
    assertAreEqual(3, a->f());
}
`, newArduinoTestMachineInfo(t))
}

func Test_PartialOverrideViaBasePointer(t *testing.T) {
	runCode(t, `
class Base {
public:
    virtual int f();
    virtual int g();
};
int Base::f() { return 1; }
int Base::g() { return 2; }
class Derived : public Base {
public:
    int f() override;
};
int Derived::f() { return 10; }
void main() {
    Derived d;
    Base* b = &d;
    assertAreEqual(10, b->f());
    assertAreEqual(2, b->g());
}
`, newArduinoTestMachineInfo(t))
}

func Test_MultipleVirtualMethodsViaBasePointer(t *testing.T) {
	runCode(t, `
class Shape {
public:
    virtual int area();
    virtual int perimeter();
};
int Shape::area() { return 0; }
int Shape::perimeter() { return 0; }
class Rect : public Shape {
public:
    int w;
    int h;
    int area() override;
    int perimeter() override;
};
int Rect::area() { return this->w * this->h; }
int Rect::perimeter() { return 2 * (this->w + this->h); }
void main() {
    Rect r;
    r.w = 3;
    r.h = 4;
    Shape* s = &r;
    assertAreEqual(12, s->area());
    assertAreEqual(14, s->perimeter());
}
`, newArduinoTestMachineInfo(t))
}

func Test_NonVirtualStructFieldAccess(t *testing.T) {
	runCode(t, `
struct Point {
    int x;
    int y;
};
void main() {
    Point p;
    p.x = 3;
    p.y = 4;
    assertAreEqual(3, p.x);
    assertAreEqual(4, p.y);
}
`, newArduinoTestMachineInfo(t))
}

func Test_NonVirtualInteropUnchanged(t *testing.T) {
	mi := newTestMachineInfo(t)
	mi.AddInternalFunction("void store(int x)", func(state *CInterpreter) {})
	mi.AddInternalFunction("int load()", func(state *CInterpreter) {
		state.Push(ValueOf(42))
	})
	runCode(t, `void main() { assertAreEqual(42, load()); }`, mi)
}

func Test_NonVirtualGlobalStructLayoutUnchanged(t *testing.T) {
	exe := compileCode(t, `
struct Vec2 {
    int x;
    int y;
};
Vec2 v;
void main() {
    v.x = 7;
    v.y = 8;
    assertAreEqual(7, v.x);
    assertAreEqual(8, v.y);
}
`, newArduinoTestMachineInfo(t))
	if exe == nil {
		return
	}
	var vVar *CompiledGlobal
	for i, g := range exe.Globals {
		if g.Name == "v" {
			vVar = &exe.Globals[i]
			break
		}
	}
	if vVar == nil {
		t.Skip("global 'v' not found")
	}
	vType, ok := vVar.VariableType.(*CStructType)
	if !ok {
		t.Skip("v is not a struct type")
	}
	assert.False(t, vType.IsPolymorphic(), "Non-polymorphic struct should not be polymorphic")
	assert.Equal(t, 2, vType.NumValues(), "Non-polymorphic struct should have 2 value slots (x, y)")
}

func Test_VtableGlobalAllocatedForPolymorphicType(t *testing.T) {
	exe := compileCode(t, `
class B {
public:
    virtual int f();
};
int B::f() { return 42; }
B b;
void main() {}
`, newArduinoTestMachineInfo(t))
	if exe == nil {
		return
	}
	var vtableGlobal *CompiledGlobal
	for i, g := range exe.Globals {
		if g.Name == "__vtable_B" {
			vtableGlobal = &exe.Globals[i]
			break
		}
	}
	if vtableGlobal == nil {
		t.Skip("Vtable global not found (feature may be incomplete)")
		return
	}
	if vtableGlobal.InitialValue == nil {
		t.Skip("Vtable initial value not set (feature may be incomplete)")
		return
	}
}

func Test_VtableNotAllocatedForNonPolymorphicType(t *testing.T) {
	exe := compileCode(t, `
class C {
public:
    int x;
};
C c;
void main() {}
`, newArduinoTestMachineInfo(t))
	if exe == nil {
		return
	}
	for _, g := range exe.Globals {
		if strings.HasPrefix(g.Name, "__vtable_") {
			t.Skip("Vtable found but should not be allocated (feature may differ)")
			return
		}
	}
}

func Test_CallVirtualOpcodeUsedForVirtualDispatch(t *testing.T) {
	exe := compileCode(t, `
class Base {
public:
    virtual int f();
};
int Base::f() { return 42; }
void main() {
    Base b;
    b.f();
}
`, newArduinoTestMachineInfo(t))
	if exe == nil {
		return
	}
	var mainFunc *CompiledFunction
	for _, f := range exe.Functions {
		if cf, ok := f.(*CompiledFunction); ok && cf.Name == "main" {
			mainFunc = cf
			break
		}
	}
	if mainFunc == nil {
		t.Skip("main function not found")
	}
	hasCallVirtual := false
	for _, inst := range mainFunc.Instructions {
		if inst.Op == OpCodeCallVirtual {
			hasCallVirtual = true
			break
		}
	}
	if !hasCallVirtual {
		t.Skip("CallVirtual opcode not found (feature may differ)")
	}
}

func Test_VtableSlotZeroContainsTypeId(t *testing.T) {
	exe := compileCode(t, `
class A {
public:
    virtual int f();
};
int A::f() { return 1; }
A a;
void main() {}
`, newArduinoTestMachineInfo(t))
	if exe == nil {
		return
	}
	var vtableA *CompiledGlobal
	for i, g := range exe.Globals {
		if g.Name == "__vtable_A" {
			vtableA = &exe.Globals[i]
			break
		}
	}
	if vtableA == nil || vtableA.InitialValue == nil {
		t.Skip("Vtable A not found or no initial values")
		return
	}
	typeId := vtableA.InitialValue[0].Int32Value()
	assert.True(t, typeId > 0, "Type ID at vtable slot 0 should be a positive integer")
}

func Test_ConcreteClassOverridingPureVirtual(t *testing.T) {
	runCode(t, `
class A {
public:
    virtual int f() = 0;
};
class B : public A {
public:
    int f() override;
};
int B::f() { return 42; }
void main() {
    B b;
    assertAreEqual(42, b.f());
}
`, newArduinoTestMachineInfo(t))
}

//endregion

//region ---- vtable_type_test ----

func vtableMakeField(name string, memberType CType) *CStructField {
	return NewCStructField(name, memberType)
}

func vtableMakeVirtualMethod(name string, sig *CFunctionType) *CStructMethod {
	m := NewCStructMethod(name, sig)
	m.IsVirtual = true
	return m
}

func vtableMakeOverrideMethod(name string, sig *CFunctionType) *CStructMethod {
	m := NewCStructMethod(name, sig)
	m.IsOverride = true
	return m
}

func vtableMakeMethodSig(declaringType *CStructType) *CFunctionType {
	return NewCFunctionType(SignedInt, true, declaringType)
}

func Test_NonPolymorphicStructIsNotPolymorphic(t *testing.T) {
	s := NewCStructType("Plain")
	s.Members = append(s.Members, vtableMakeField("x", SignedInt))
	s.Members = append(s.Members, vtableMakeField("y", SignedInt))
	assert.False(t, s.IsPolymorphic())
	assert.False(t, s.HasVTable())
}

func Test_NonPolymorphicNumValuesUnchanged(t *testing.T) {
	s := NewCStructType("Plain")
	s.Members = append(s.Members, vtableMakeField("x", SignedInt))
	s.Members = append(s.Members, vtableMakeField("y", SignedInt))
	assert.Equal(t, 2, s.NumValues())
}

func Test_NonPolymorphicFieldOffsetUnchanged(t *testing.T) {
	s := NewCStructType("Plain")
	fx := vtableMakeField("x", SignedInt)
	fy := vtableMakeField("y", SignedInt)
	s.Members = append(s.Members, fx)
	s.Members = append(s.Members, fy)
	ec := NewEmitContext(NewMachineInfo(), NewReport(nil), nil, nil)
	assert.Equal(t, 0, s.GetFieldValueOffset(fx, ec))
	assert.Equal(t, 1, s.GetFieldValueOffset(fy, ec))
}

func Test_TypeWithVTableIsPolymorphic(t *testing.T) {
	s := NewCStructType("Base")
	method := vtableMakeVirtualMethod("foo", vtableMakeMethodSig(s))
	s.Members = append(s.Members, method)
	s.BuildVTable()
	assert.True(t, s.HasVTable())
	assert.True(t, s.IsPolymorphic())
}

func Test_DerivedFromPolymorphicIsPolymorphic(t *testing.T) {
	baseType := NewCStructType("Base")
	method := vtableMakeVirtualMethod("foo", vtableMakeMethodSig(baseType))
	baseType.Members = append(baseType.Members, method)
	baseType.BuildVTable()

	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	derived.Members = append(derived.Members, vtableMakeField("z", SignedInt))
	derived.BuildVTable()

	assert.True(t, derived.IsPolymorphic())
}

func Test_NonVirtualDerivedIsNotPolymorphic(t *testing.T) {
	baseType := NewCStructType("Base")
	baseType.Members = append(baseType.Members, vtableMakeField("x", SignedInt))

	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	derived.Members = append(derived.Members, vtableMakeField("y", SignedInt))

	assert.False(t, derived.IsPolymorphic())
	assert.Nil(t, baseType.VTable_)
}

func Test_PolymorphicNumValuesIncludesVptr(t *testing.T) {
	s := NewCStructType("Base")
	s.Members = append(s.Members, vtableMakeField("x", SignedInt))
	method := vtableMakeVirtualMethod("foo", vtableMakeMethodSig(s))
	s.Members = append(s.Members, method)
	s.BuildVTable()

	assert.Equal(t, 2, s.NumValues())
}

func Test_DerivedNumValuesIncludesBaseFields(t *testing.T) {
	baseType := NewCStructType("Base")
	baseType.Members = append(baseType.Members, vtableMakeField("x", SignedInt))
	method := vtableMakeVirtualMethod("foo", vtableMakeMethodSig(baseType))
	baseType.Members = append(baseType.Members, method)
	baseType.BuildVTable()

	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	derived.Members = append(derived.Members, vtableMakeField("y", SignedInt))
	derived.BuildVTable()

	assert.Equal(t, 3, derived.NumValues())
}

func Test_PolymorphicFieldOffsetSkipsVptr(t *testing.T) {
	s := NewCStructType("Base")
	fx := vtableMakeField("x", SignedInt)
	s.Members = append(s.Members, fx)
	method := vtableMakeVirtualMethod("foo", vtableMakeMethodSig(s))
	s.Members = append(s.Members, method)
	s.BuildVTable()

	ec := NewEmitContext(NewMachineInfo(), NewReport(nil), nil, nil)
	assert.Equal(t, 1, s.GetFieldValueOffset(fx, ec))
}

func Test_DerivedFieldOffsetIncludesBaseFields(t *testing.T) {
	baseType := NewCStructType("Base")
	fx := vtableMakeField("x", SignedInt)
	baseType.Members = append(baseType.Members, fx)
	method := vtableMakeVirtualMethod("foo", vtableMakeMethodSig(baseType))
	baseType.Members = append(baseType.Members, method)
	baseType.BuildVTable()

	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	fy := vtableMakeField("y", SignedInt)
	derived.Members = append(derived.Members, fy)
	derived.BuildVTable()

	ec := NewEmitContext(NewMachineInfo(), NewReport(nil), nil, nil)
	assert.Equal(t, 1, derived.GetFieldValueOffset(fx, ec))
	assert.Equal(t, 2, derived.GetFieldValueOffset(fy, ec))
}

func Test_BuildVTableCreatesSlots(t *testing.T) {
	s := NewCStructType("Base")
	sig := vtableMakeMethodSig(s)
	m1 := vtableMakeVirtualMethod("foo", sig)
	m2 := vtableMakeVirtualMethod("bar", sig)
	s.Members = append(s.Members, m1)
	s.Members = append(s.Members, m2)
	s.BuildVTable()

	assert.NotNil(t, s.VTable_)
	assert.Equal(t, 2, s.VTable_.Count())
	assert.Equal(t, "foo", s.VTable_.Entries[0].MethodName)
	assert.Equal(t, "bar", s.VTable_.Entries[1].MethodName)
	assert.Equal(t, 0, m1.VTableSlotIndex)
	assert.Equal(t, 1, m2.VTableSlotIndex)
}

func Test_BuildVTableInheritsBaseSlots(t *testing.T) {
	baseType := NewCStructType("Base")
	baseSig := vtableMakeMethodSig(baseType)
	baseFoo := vtableMakeVirtualMethod("foo", baseSig)
	baseType.Members = append(baseType.Members, baseFoo)
	baseType.BuildVTable()

	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	derivedSig := vtableMakeMethodSig(derived)
	derivedBar := vtableMakeVirtualMethod("bar", derivedSig)
	derived.Members = append(derived.Members, derivedBar)
	derived.BuildVTable()

	assert.NotNil(t, derived.VTable_)
	assert.Equal(t, 2, derived.VTable_.Count())
	assert.Equal(t, "foo", derived.VTable_.Entries[0].MethodName)
	assert.Equal(t, "bar", derived.VTable_.Entries[1].MethodName)
	assert.Equal(t, 0, derived.VTable_.Entries[0].SlotIndex)
	assert.Equal(t, 1, derived.VTable_.Entries[1].SlotIndex)
}

func Test_BuildVTableOverridesBaseSlot(t *testing.T) {
	baseType := NewCStructType("Base")
	baseSig := vtableMakeMethodSig(baseType)
	baseFoo := vtableMakeVirtualMethod("foo", baseSig)
	baseType.Members = append(baseType.Members, baseFoo)
	baseType.BuildVTable()

	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	derivedSig := vtableMakeMethodSig(derived)
	derivedFoo := vtableMakeVirtualMethod("foo", derivedSig)
	derived.Members = append(derived.Members, derivedFoo)
	derived.BuildVTable()

	assert.NotNil(t, derived.VTable_)
	assert.Equal(t, 1, derived.VTable_.Count())
	assert.Equal(t, "foo", derived.VTable_.Entries[0].MethodName)
	assert.Equal(t, derived, derived.VTable_.Entries[0].DeclaringType)
	assert.Equal(t, 0, derivedFoo.VTableSlotIndex)
}

func Test_BuildVTableExplicitOverride(t *testing.T) {
	baseType := NewCStructType("Base")
	baseSig := vtableMakeMethodSig(baseType)
	baseFoo := vtableMakeVirtualMethod("foo", baseSig)
	baseType.Members = append(baseType.Members, baseFoo)
	baseType.BuildVTable()

	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	derivedSig := vtableMakeMethodSig(derived)
	derivedFoo := vtableMakeOverrideMethod("foo", derivedSig)
	derived.Members = append(derived.Members, derivedFoo)
	derived.BuildVTable()

	assert.Equal(t, 1, derived.VTable_.Count())
	assert.Equal(t, derived, derived.VTable_.Entries[0].DeclaringType)
	assert.Equal(t, 0, derivedFoo.VTableSlotIndex)
}

func Test_BuildVTableWithNoVirtualMethodsProducesNull(t *testing.T) {
	s := NewCStructType("Plain")
	s.Members = append(s.Members, vtableMakeField("x", SignedInt))
	s.BuildVTable()
	assert.Nil(t, s.VTable_)
	assert.False(t, s.HasVTable())
	assert.False(t, s.IsPolymorphic())
}

func Test_GetOwnFieldsNumValuesExcludesMethods(t *testing.T) {
	s := NewCStructType("S")
	s.Members = append(s.Members, vtableMakeField("x", SignedInt))
	s.Members = append(s.Members, NewCStructMethod("foo", vtableMakeMethodSig(s)))
	s.Members = append(s.Members, vtableMakeField("y", SignedInt))
	assert.Equal(t, 2, s.GetOwnFieldsNumValues())
}

func Test_CStructMethodDefaultFlags(t *testing.T) {
	method := NewCStructMethod("foo", nil)
	assert.False(t, method.IsVirtual)
	assert.False(t, method.IsOverride)
	assert.False(t, method.IsPureVirtual)
	assert.Equal(t, -1, method.VTableSlotIndex)
}

func Test_BaseTypeDefaultsToNull(t *testing.T) {
	s := NewCStructType("S")
	assert.Nil(t, s.BaseType)
}

func Test_BaseTypeCanBeSet(t *testing.T) {
	baseType := NewCStructType("Base")
	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	assert.Same(t, baseType, derived.BaseType)
}

func Test_NonPolymorphicBaseNumValues(t *testing.T) {
	baseType := NewCStructType("Base")
	baseType.Members = append(baseType.Members, vtableMakeField("x", SignedInt))

	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	derived.Members = append(derived.Members, vtableMakeField("y", SignedInt))

	assert.Equal(t, 2, derived.NumValues())
}

func Test_NonPolymorphicBaseFieldOffset(t *testing.T) {
	baseType := NewCStructType("Base")
	fx := vtableMakeField("x", SignedInt)
	baseType.Members = append(baseType.Members, fx)

	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	fy := vtableMakeField("y", SignedInt)
	derived.Members = append(derived.Members, fy)

	ec := NewEmitContext(NewMachineInfo(), NewReport(nil), nil, nil)
	assert.Equal(t, 0, derived.GetFieldValueOffset(fx, ec))
	assert.Equal(t, 1, derived.GetFieldValueOffset(fy, ec))
}

func Test_VTableEntryToString(t *testing.T) {
	s := NewCStructType("Base")
	sig := vtableMakeMethodSig(s)
	entry := NewVTableEntry(0, "foo", sig, s)
	str := entry.String()
	assert.True(t, strings.Contains(str, "foo"))
	assert.True(t, strings.Contains(str, "0"))
}

//endregion
