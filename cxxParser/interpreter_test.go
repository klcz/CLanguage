package cxxParser

import (
	"testing"
)

func TestInterpreterYieldingDelay(t *testing.T) {
	mi := newTestMachineInfo()
	hit := [3]bool{}
	mi.AddInternalFunction("int yieldingDelay(int ms)", func(state *CInterpreter) {
		ms := state.ReadArg(0).Int16Value
		hit[state.YieldedValue] = true
		if state.YieldedValue == 0 {
			state.Yield(1)
		} else if state.YieldedValue == 1 {
			state.Yield(2)
		} else {
			state.Yield(0)
			state.Push(ValueOf(int32(ms) * 1000))
		}
	})
	it := runCode(t, `
void main () {
    auto x = yieldingDelay(3);
    assertAreEqual (3000, x);
}`, mi)
	if it == nil {
		return
	}
	if !hit[0] {
		t.Error("expected hit[0] to be true after Run")
	}
	if hit[1] {
		t.Error("expected hit[1] to be false after Run")
	}
	it.Step(1)
	if !hit[1] {
		t.Error("expected hit[1] to be true after first Step")
	}
	if hit[2] {
		t.Error("expected hit[2] to be false after first Step")
	}
	it.Step(1)
	if !hit[2] {
		t.Error("expected hit[2] to be true after second Step")
	}
}

func TestInterpreterInfiniteRecursionThrows(t *testing.T) {
	defer func() {
		if r := recover(); r != nil {
			// Expected: ExecutionException (or any panic) from infinite recursion
		}
	}()
	runCode(t, `
int f (int n) {
	return f (n);
}
void main () {
	f (1);
}`, newTestMachineInfo())
	t.Error("Expected panic from infinite recursion but got none")
}

func TestInterpreterInfiniteLoopStopsEventually(t *testing.T) {
	runCode(t, `
void main () {
	while (true) {
	}
}`, newTestMachineInfo())
}

func TestInterpreterMutualRecursive(t *testing.T) {
	runCode(t, `
int m (int);
int f (int n) {
	return (n == 0) ? 1 : n - m (f (n - 1));
}
int m (int n) {
	return (n == 0) ? 0 : n - f (m (n - 1));
}
void main () {
	assertAreEqual (1, f (0));
	assertAreEqual (1, f (1));
	assertAreEqual (2, f (2));
	assertAreEqual (2, f (3));
	assertAreEqual (3, f (4));
	assertAreEqual (3, f (5));
	assertAreEqual (4, f (6));
	assertAreEqual (5, f (7));
	assertAreEqual (0, m (0));
	assertAreEqual (0, m (1));
	assertAreEqual (1, m (2));
	assertAreEqual (2, m (3));
	assertAreEqual (2, m (4));
	assertAreEqual (3, m (5));
	assertAreEqual (4, m (6));
	assertAreEqual (4, m (7));
}`, newTestMachineInfo())
}

func TestInterpreterRecursive(t *testing.T) {
	runCode(t, `
unsigned long fib (unsigned long n) {
	if (n == 0 || n == 1) {
		return n;
	}
	else {
		return fib (n - 1) + fib (n - 2);
	}
}
void main () {
	assertAreEqual (0, fib (0));
	assertAreEqual (1, fib (1));
	assertAreEqual (1, fib (2));
	assertAreEqual (2, fib (3));
	assertAreEqual (3, fib (4));
	assertAreEqual (5, fib (5));
	assertAreEqual (8, fib (6));
	assertAreEqual (13, fib (7));
}`, newTestMachineInfo())
}

func TestInterpreterOverwriteArgs(t *testing.T) {
	runCode(t, `
int abs (int x) {
	if (x < 0) x = -x;
	return x;
}
void main () {
	assertAreEqual (0, abs(0));
	assertAreEqual (101, abs(-101));
	assertAreEqual (101, abs(101));
}`, newTestMachineInfo())
}

func TestInterpreterVoidFunctionCalls(t *testing.T) {
	runCode(t, `
int output = 0;
void print (int v) {
	output += v;
}
void main () {
	print (1);
	print (2);
	print (3);
	assertAreEqual (6, output);
}`, newTestMachineInfo())
}

func TestInterpreterFunctionCallsWithValues(t *testing.T) {
	runCode(t, `
int mulMulDiv (long m1, long m2, long d) {
	return (m1 * m2) / d;
}
void main () {
	assertAreEqual (66, mulMulDiv (2, 100, 3));
}`, newArduinoTestMachineInfo())
}

func TestInterpreterVoidReturnEarlyOut(t *testing.T) {
	runCode(t, `
int x = 0;
void foo () {
	x = 42;
	return;
	x = 99;
}
void main () {
	foo ();
	assertAreEqual (42, x);
}`, newTestMachineInfo())
}

func TestInterpreterVoidReturnStackCorrectAfterEarlyOut(t *testing.T) {
	runCode(t, `
int x = 0;
void bar () {
	x = 10;
	return;
	x = 20;
}
int add (int a, int b) {
	return a + b;
}
void main () {
	int before = add (1, 2);
	bar ();
	int after = add (3, 4);
	assertAreEqual (3, before);
	assertAreEqual (7, after);
	assertAreEqual (10, x);
}`, newTestMachineInfo())
}

func TestInterpreterForLoop(t *testing.T) {
	runCode(t, `
void main () {
	int acc;
	int i;
	for (acc = 0, i = -10; i <= 10; i += 2) {
		acc = acc + 1;
	}
	assertAreEqual (11, acc);
}`, newTestMachineInfo())
}

func TestInterpreterForLoopWithBreak(t *testing.T) {
	runCode(t, `
void main () {
	int i;
	for (i = 0; i <= 10; i++) {
		if (i >= 5)
            break;
	}
	assertAreEqual (5, i);
}`, newTestMachineInfo())
}

func TestInterpreterForLoopWithContinue(t *testing.T) {
	runCode(t, `
void main () {
    int otherI = 0;
	int i;
	for (i = 0; i <= 10; i++) {
		if (i >= 5) {
            continue;
        }
        otherI++;
	}
	assertAreEqual (5, otherI);
}`, newTestMachineInfo())
}

func TestInterpreterForLoopInfinite(t *testing.T) {
	runCode(t, `
void main () {
	int i = 0;
	for (;;) {
		i++;
		if (i >= 10) break;
	}
	assertAreEqual (10, i);
}`, newTestMachineInfo())
}

func TestInterpreterForLoopEmptyCondition(t *testing.T) {
	runCode(t, `
void main () {
	int i;
	for (i = 0; ; i++) {
		if (i >= 5) break;
	}
	assertAreEqual (5, i);
}`, newTestMachineInfo())
}

func TestInterpreterForLoopEmptyIncrement(t *testing.T) {
	runCode(t, `
void main () {
	int i;
	for (i = 0; i < 10;) {
		i++;
	}
	assertAreEqual (10, i);
}`, newTestMachineInfo())
}

func TestInterpreterForLoopEmptyInit(t *testing.T) {
	runCode(t, `
void main () {
	int i = 0;
	for (; i < 5; i++) {
	}
	assertAreEqual (5, i);
}`, newTestMachineInfo())
}

func TestInterpreterWhileLoopWithBreak(t *testing.T) {
	runCode(t, `
void main () {
	int i = 0;
    while (i < 10) {
        i++;
        if (i == 5)
            break;
    }
	assertAreEqual (5, i);
}`, newTestMachineInfo())
}

func TestInterpreterWhileLoopWithContinue(t *testing.T) {
	runCode(t, `
void main () {
	int i = 0;
    int c = 0;
    while (i < 10) {
        i++;
        if (i > 5)
            continue;
        c++;
    }
	assertAreEqual (5, c);
}`, newTestMachineInfo())
}

func TestInterpreterDoWhileOnce(t *testing.T) {
	runCode(t, `
void main () {
	int i = 0;
    do {
        i++;
    } while (i > 1000);
	assertAreEqual (1, i);
}`, newTestMachineInfo())
}

func TestInterpreterDoWhileLoopWithBreak(t *testing.T) {
	runCode(t, `
void main () {
	int i = 0;
    do {
        i++;
        if (i == 5)
            break;
    } while (i < 10);
	assertAreEqual (5, i);
}`, newTestMachineInfo())
}

func TestInterpreterDoWhileLoopWithContinue(t *testing.T) {
	runCode(t, `
void main () {
	int i = 0;
    int c = 0;
    do {
        i++;
        if (i > 5)
            continue;
        c++;
    } while (i < 10);
	assertAreEqual (5, c);
    assertAreEqual (10, i);
}`, newTestMachineInfo())
}

func TestInterpreterLocalVariableInitialization(t *testing.T) {
	runCode(t, `
void main () {
	int a = 4;
	int b = 8;
	int c = a + b;
	assertAreEqual (12, c);
}`, newTestMachineInfo())
}

func TestInterpreterGlobalVariableInitialization(t *testing.T) {
	runCode(t, `
int a = 4;
int b = 8;
int c = a + b;
void main () {
    assertAreEqual (12, c);
}`, newTestMachineInfo())
}

func TestInterpreterAddressOfLocal(t *testing.T) {
	runCode(t, `
void main () {
    int a = 4;
    int *pa = &a;
    assertAreEqual (4, *pa);
    a = *pa + 1;
    assertAreEqual (5, *pa);
}`, newTestMachineInfo())
}

func TestInterpreterAddressOfGlobal(t *testing.T) {
	runCode(t, `
int a = 0;
void main () {
    int *pa = &a;
    assertAreEqual (0, *pa);
    a = *pa + 1;
    assertAreEqual (1, *pa);
}`, newTestMachineInfo())
}

func TestInterpreterPreDecrement(t *testing.T) {
	runCode(t, `
void main () {
    int a = 100;
    assertAreEqual (99, --a);
}`, newTestMachineInfo())
}

func TestInterpreterPreIncrement(t *testing.T) {
	runCode(t, `
void main () {
    int a = 100;
    assertAreEqual (101, ++a);
}`, newTestMachineInfo())
}

func TestInterpreterPostDecrement(t *testing.T) {
	runCode(t, `
void main () {
    int a = 100;
    assertAreEqual (100, a--);
    assertAreEqual (99, a);
}`, newTestMachineInfo())
}

func TestInterpreterPostIncrement(t *testing.T) {
	runCode(t, `
void main () {
    int a = 100;
    assertAreEqual (100, a++);
    assertAreEqual (101, a);
}`, newTestMachineInfo())
}

func TestInterpreterPostIncrementPointer(t *testing.T) {
	runCode(t, `
int a[] = { 10, 11, 12 };
void main () {
    int* p = a;
    assertAreEqual (10, *p++);
    assertAreEqual (11, *p++);
    assertAreEqual (12, *p++);
}`, newArduinoTestMachineInfo())
}

func TestInterpreterPostDecrementPointer(t *testing.T) {
	runCode(t, `
int a[] = { 10, 11, 12 };
void main () {
    int* p = a + 2;
    assertAreEqual (12, *p--);
    assertAreEqual (11, *p--);
    assertAreEqual (10, *p--);
}`, newArduinoTestMachineInfo())
}

func TestInterpreterBoolAssignment(t *testing.T) {
	runCode(t, `
void main () {
    bool a = false;
    a = true;
    assertAreEqual (1, a);
}`, newTestMachineInfo())
}

func TestInterpreterBoolLoopEnd(t *testing.T) {
	runCode(t, `
void main () {
    int i = 0;
    bool b = true;
    while (b) {
        i++;
        b = i < 10;
    }    
    assertAreEqual (0, b ? 1 : 0);
    assertAreEqual (10, i);
}`, newTestMachineInfo())
}

func TestInterpreterArrayElementAssignment(t *testing.T) {
	runCode(t, `
int a[] = { 10, 11, 12, 13 };
void main () {
	assertAreEqual (10, a[0]);
	a[0] = 42;
	assertAreEqual (42, a[0]);
	assertAreEqual (12, a[2]);
	a[2] *= 1000;
	assertAreEqual (12000, a[2]);
}
`, newArduinoTestMachineInfo())
}

func TestInterpreterPointerAssignment(t *testing.T) {
	runCode(t, `
int a[] = { 10, 11, 12, 13 };
void assign(int *p) { *p = 42; }
void main () {
	assertAreEqual (10, a[0]);
	assign(&a[0]);
	assertAreEqual (42, a[0]);
	assertAreEqual (12, a[2]);
	assign(a + 2);
	assertAreEqual (42, a[2]);
	assertAreEqual (13, a[3]);
	assign(3 + a);
	assertAreEqual (42, a[3]);
	int c = 33;
	assertAreEqual (33, c);
	assign(&c);
	assertAreEqual (42, c);
}
`, newArduinoTestMachineInfo())
}

func TestInterpreterVoidCallbackFunction(t *testing.T) {
	runCode(t, `
int result = 0;
void callback(int x) {
	result = 10 * x;
}
void callCallback(void (*cb)(int), int cbx) {
	cb(cbx);
}
void main () {
	callCallback(callback, 12);
	assertAreEqual(120, result);
}
`, newArduinoTestMachineInfo())
}

func TestInterpreterIntCallbackFunction(t *testing.T) {
	runCode(t, `
int callback(int x) {
	return 10 * x;
}
int callCallback(int (*cb)(int), int cbx) {
	return cb(cbx);
}
void main () {
	int result = callCallback(callback, 123);
	assertAreEqual(1230, result);
}
`, newArduinoTestMachineInfo())
}
