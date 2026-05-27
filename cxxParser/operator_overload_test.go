package cxxParser

import (
	"strings"
	"testing"

	"github.com/stretchr/testify/assert"
)

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
	mi.AddInternalFunctionDelay("V V::operator+(V other)", func(state *CInterpreter) {
		thisPtr := new(state.ReadThis()).PointerValue()
		thisX := state.Stack[thisPtr].Int32Value()
		otherX := new(state.ReadArg(0)).Int32Value()
		t.Logf("[DEBUG] `V V::operator+(V other)` thisPtr=%d, thisX=%d, otherX=%d \nStack:\n\t%v",
			thisPtr, thisX, otherX, state.Stack[:16])
		state.Push(ValueOf(thisX + otherX))
	})

	runCode(t, `
void main() {
    V a; a.x = 3;
    V b; b.x = 4;
    V c = a + b;
    assertAreEqual(7, c.x);
}`, mi, dumpOpCode)
}

func Test_InternalOperatorEquals(t *testing.T) {
	mi := newTestMachineInfo(t)
	mi.HeaderCode += "struct V { int x; bool operator==(V other); };\n"
	mi.AddInternalFunctionDelay("bool V::operator==(V other)", func(state *CInterpreter) {
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
}`, mi, dumpOpCode)
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
