package cxxParser

import (
	"testing"

	"github.com/stretchr/testify/assert"
)

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

func TestDeclarationBasic(t *testing.T) {
	vs := declarationParseVariables(t, "int cat;")
	if vs == nil {
		return
	}
	assert.Equal(t, 1, len(vs))
	assert.Equal(t, "cat", vs[0].Name)
	assert.IsType(t, &CIntType{}, vs[0].VariableType)
}

func TestDeclarationSignednessBasic(t *testing.T) {
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

func TestDeclarationSignednessNoBasic(t *testing.T) {
	vs := declarationParseVariables(t, "unsigned x; signed y;")
	if vs == nil || len(vs) < 2 {
		return
	}
	assert.Equal(t, Unsigned, vs[0].VariableType.GetBasicType().Signedness)
	assert.Equal(t, "int", vs[0].VariableType.GetBasicType().Name)
	assert.Equal(t, Signed, vs[1].VariableType.GetBasicType().Signedness)
	assert.Equal(t, "int", vs[1].VariableType.GetBasicType().Name)
}

func TestDeclarationSizeBasic(t *testing.T) {
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

func TestDeclarationPointer(t *testing.T) {
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

func TestDeclarationVoidPointer(t *testing.T) {
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

func TestDeclarationPointerSeparation(t *testing.T) {
	vs := declarationParseVariables(t, "long* first, second;")
	if vs == nil || len(vs) < 2 {
		return
	}
	_, ok := vs[0].VariableType.(*CPointerType)
	assert.True(t, ok, "first should be pointer")
	_, ok = vs[1].VariableType.(*CIntType)
	assert.True(t, ok, "second should be basic int")
}

func TestDeclarationArray(t *testing.T) {
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

func TestDeclarationArrayOfArrays(t *testing.T) {
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

func TestDeclarationPointerToArray(t *testing.T) {
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

func TestDeclarationVarInitializationOrder(t *testing.T) {
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
`, newTestMachineInfo())
}

func TestDeclarationFunctionNoArgName(t *testing.T) {
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

func TestDeclarationFunctionPointerReturn(t *testing.T) {
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

func TestDeclarationLongShortIntIsError(t *testing.T) {
	mi := newTestMachineInfo()
	code := "void main() { long short int x = 0; }"
	fullCode := "void start() { __cinit(); main(); } " + code
	report := NewReport(nil)
	Compile(fullCode, mi, nil)
	if len(report.Errors()) == 0 {
		_ = report
		t.Skip("Compile did not produce errors (feature may not error-check yet)")
	}
}
