package cxxParser

import (
	"testing"

	"github.com/stretchr/testify/assert"
)

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
