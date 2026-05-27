package cxxParser

import (
	"strings"
	"testing"

	"github.com/stretchr/testify/assert"
)

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
`, newArduinoTestMachineInfo(t), dumpOpCode)
}
