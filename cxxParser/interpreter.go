package cxxParser

import (
	"fmt"
	"strings"
)

// ============================================================================
// ExecutionException
// ============================================================================

type ExecutionException struct {
	message string
}

func NewExecutionException(message string) *ExecutionException {
	return &ExecutionException{message: message}
}

func (e *ExecutionException) Error() string {
	return e.message
}

// ============================================================================
// ExecutionFrame
// ============================================================================

type ExecutionFrame struct {
	FP       int
	IP       int
	Function BaseFunction
}

func NewExecutionFrame(function BaseFunction) *ExecutionFrame {
	return &ExecutionFrame{Function: function}
}

func (f *ExecutionFrame) String() string {
	return fmt.Sprintf("%d: %s", f.FP, f.Function.GetName())
}

// ============================================================================
// FieldAccessor — pre-computed field offset within a C struct
// ============================================================================

type FieldAccessor struct {
	Offset    int
	NumValues int
}

func NewFieldAccessor(offset, numValues int) FieldAccessor {
	return FieldAccessor{Offset: offset, NumValues: numValues}
}

func (a FieldAccessor) Get(stack []Value, basePtr int) Value {
	return stack[basePtr+a.Offset]
}

func (a FieldAccessor) Set(stack []Value, basePtr int, value Value) {
	stack[basePtr+a.Offset] = value
}

func (a FieldAccessor) GetAddress(basePtr int) int {
	return basePtr + a.Offset
}

// ============================================================================
// StructLayout — pre-computed layout of a C struct type
// ============================================================================

type StructLayout struct {
	Name       string
	NumValues  int
	fields     map[string]FieldAccessor
	fieldTypes map[string]CType
}

func NewStructLayout(structType *CStructType) *StructLayout {
	if structType == nil {
		panic("structType must not be nil")
	}
	sl := &StructLayout{
		Name:       structType.Name,
		NumValues:  structType.NumValues(),
		fields:     make(map[string]FieldAccessor),
		fieldTypes: make(map[string]CType),
	}
	sl.buildFieldMap(structType)
	return sl
}

func (sl *StructLayout) Field(name string) FieldAccessor {
	if a, ok := sl.fields[name]; ok {
		return a
	}
	panic(fmt.Sprintf("Field '%s' not found in struct '%s'", name, sl.Name))
}

func (sl *StructLayout) FieldLayout(name string) *StructLayout {
	memberType, ok := sl.fieldTypes[name]
	if !ok {
		panic(fmt.Sprintf("Field '%s' not found in struct '%s'", name, sl.Name))
	}
	if nestedStruct, ok := memberType.(*CStructType); ok {
		return NewStructLayout(nestedStruct)
	}
	panic(fmt.Sprintf("Field '%s' in struct '%s' is not a struct type (it is %T)", name, sl.Name, memberType))
}

func (sl *StructLayout) buildFieldMap(structType *CStructType) {
	if !structType.IsPolymorphic() && structType.BaseType == nil {
		offset := 0
		for _, m := range structType.Members {
			if _, ok := m.(*CStructField); ok {
				sl.fields[m.GetName()] = NewFieldAccessor(offset, m.GetMemberType().NumValues())
				sl.fieldTypes[m.GetName()] = m.GetMemberType()
				offset += m.GetMemberType().NumValues()
			}
		}
	} else {
		offset := 0
		if structType.IsPolymorphic() {
			offset = 1
		}
		if structType.BaseType != nil {
			offset = sl.addBaseFields(structType.BaseType, offset)
		}
		for _, m := range structType.Members {
			if _, ok := m.(*CStructField); ok {
				sl.fields[m.GetName()] = NewFieldAccessor(offset, m.GetMemberType().NumValues())
				sl.fieldTypes[m.GetName()] = m.GetMemberType()
				offset += m.GetMemberType().NumValues()
			}
		}
	}
}

func (sl *StructLayout) addBaseFields(structType *CStructType, offset int) int {
	if structType.BaseType != nil {
		offset = sl.addBaseFields(structType.BaseType, offset)
	}
	for _, m := range structType.Members {
		if _, ok := m.(*CStructField); ok {
			sl.fields[m.GetName()] = NewFieldAccessor(offset, m.GetMemberType().NumValues())
			sl.fieldTypes[m.GetName()] = m.GetMemberType()
			offset += m.GetMemberType().NumValues()
		}
	}
	return offset
}

// ============================================================================
// InternalFunction — compiled C# builtin
// ============================================================================

//goland:noinspection GoUnusedParameter
func (f *InternalFunction) Step(state *CInterpreter, frame *ExecutionFrame) {
	if f.Action != nil {
		f.Action(state)
	}
	if state.YieldedValue == 0 {
		state.Return()
	}
}

func (f *InternalFunction) String() string {
	if f.nameContext == "" {
		return f.name
	}
	return f.nameContext + "::" + f.name
}

// ============================================================================
// CompiledFunction — user C code compiled to bytecode
// ============================================================================

func (f *CompiledFunction) Init(state *CInterpreter) {
	if len(f.LocalVariables) == 0 {
		return
	}
	last := f.LocalVariables[len(f.LocalVariables)-1]
	state.SP += last.StackOffset + last.VariableType.NumValues()
}

func (f *CompiledFunction) String() string {
	return f.Name
}

func (f *CompiledFunction) Step(state *CInterpreter, frame *ExecutionFrame) {
	ip := frame.IP
	done := false

	for !done && ip < len(f.Instructions) && state.RemainingTime > 0 {
		i := f.Instructions[ip]
		state.RemainingTime -= state.CpuSpeed

		if state.SP < frame.FP {
			prevOp := "?"
			if ip-1 >= 0 {
				prevOp = fmt.Sprintf("%v", f.Instructions[ip-1])
			}
			panic(fmt.Errorf("%s %s@%d stack underflow", prevOp, f.Name, ip-1))
		}

		switch OpCode(i.Op) {
		// --- Stack ---
		case OpCodeDup:
			state.Stack[state.SP] = state.Stack[state.SP-1]
			state.SP++
			ip++

		case OpCodePop:
			state.SP--
			ip++

		// --- Control ---
		case OpCodeJump:
			if i.Label != nil {
				ip = i.Label.Index
			} else {
				panic("Jump label not set")
			}

		case OpCodeBranchIfFalse:
			a := state.Stack[state.SP-1]
			state.SP--
			if a.Int32Value == 0 {
				if i.Label != nil {
					ip = i.Label.Index
				} else {
					panic("BranchIfFalse label not set")
				}
			} else {
				ip++
			}

		case OpCodeBranchIfTrue:
			a := state.Stack[state.SP-1]
			state.SP--
			if a.Int32Value != 0 {
				if i.Label != nil {
					ip = i.Label.Index
				} else {
					panic("BranchIfTrue label not set")
				}
			} else {
				ip++
			}

		case OpCodeCall:
			a := state.Stack[state.SP-1]
			state.SP--
			ip++
			state.CallValue(a)
			done = true

		case OpCodeCallVirtual:
			vtableSlot := i.X.Int32Value
			thisAddr := state.Stack[state.SP-1].PointerValue
			vptr := state.Stack[thisAddr].PointerValue
			funcPtr := state.Stack[vptr+1+vtableSlot].PointerValue
			ip++
			state.Call(state.exe.Functions[funcPtr])
			done = true

		case OpCodeReturn:
			state.Return()
			done = true

		// --- Memory ---
		case OpCodeLoadConstant:
			state.Stack[state.SP] = i.X
			state.SP++
			ip++

		case OpCodeLoadFramePointer:
			state.Stack[state.SP] = ValueOf(frame.FP)
			state.SP++
			ip++

		case OpCodeLoadPointer:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = state.Stack[a.PointerValue]
			ip++

		case OpCodeStorePointer:
			a := state.Stack[state.SP-2]
			b := state.Stack[state.SP-1]
			state.Stack[b.PointerValue] = a
			state.SP -= 2
			ip++

		case OpCodeOffsetPointer:
			a := state.Stack[state.SP-2]
			b := state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.PointerValue + b.Int32Value)
			state.SP--
			ip++

		case OpCodeLoadGlobal:
			state.Stack[state.SP] = state.Stack[i.X.Int32Value]
			state.SP++
			ip++

		case OpCodeStoreGlobal:
			state.Stack[i.X.Int32Value] = state.Stack[state.SP-1]
			state.SP--
			ip++

		case OpCodeLoadArg:
			state.Stack[state.SP] = state.Stack[frame.FP+int(i.X.Int32Value)]
			state.SP++
			ip++

		case OpCodeStoreArg:
			state.Stack[frame.FP+int(i.X.Int32Value)] = state.Stack[state.SP-1]
			state.SP--
			ip++

		case OpCodeLoadLocal:
			state.Stack[state.SP] = state.Stack[frame.FP+int(i.X.Int32Value)]
			state.SP++
			ip++

		case OpCodeStoreLocal:
			state.Stack[frame.FP+int(i.X.Int32Value)] = state.Stack[state.SP-1]
			state.SP--
			ip++

		// --- Arithmetic: Add ---
		case OpCodeAddInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(int8(a.Int64Value) + int8(b.Int64Value))
			state.SP--
			ip++

		case OpCodeAddUInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint8(a.Int64Value) + uint8(b.Int64Value))
			state.SP--
			ip++

		case OpCodeAddInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(int16(a.Int64Value) + int16(b.Int64Value))
			state.SP--
			ip++

		case OpCodeAddUInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint16(a.Int64Value) + uint16(b.Int64Value))
			state.SP--
			ip++

		case OpCodeAddInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Int32Value + b.Int32Value)
			state.SP--
			ip++

		case OpCodeAddUInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint32(a.Int64Value) + uint32(b.Int64Value))
			state.SP--
			ip++

		case OpCodeAddInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Int64Value + b.Int64Value)
			state.SP--
			ip++

		case OpCodeAddUInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint64(a.Int64Value) + uint64(b.Int64Value))
			state.SP--
			ip++

		case OpCodeAddFloat32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Float32Value + b.Float32Value)
			state.SP--
			ip++

		case OpCodeAddFloat64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Float64Value + b.Float64Value)
			state.SP--
			ip++

		// --- Arithmetic: Subtract ---
		case OpCodeSubtractInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(int8(a.Int64Value) - int8(b.Int64Value))
			state.SP--
			ip++

		case OpCodeSubtractUInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint8(a.Int64Value) - uint8(b.Int64Value))
			state.SP--
			ip++

		case OpCodeSubtractInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(int16(a.Int64Value) - int16(b.Int64Value))
			state.SP--
			ip++

		case OpCodeSubtractUInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint16(a.Int64Value) - uint16(b.Int64Value))
			state.SP--
			ip++

		case OpCodeSubtractInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Int32Value - b.Int32Value)
			state.SP--
			ip++

		case OpCodeSubtractUInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint32(a.Int64Value) - uint32(b.Int64Value))
			state.SP--
			ip++

		case OpCodeSubtractInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Int64Value - b.Int64Value)
			state.SP--
			ip++

		case OpCodeSubtractUInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint64(a.Int64Value) - uint64(b.Int64Value))
			state.SP--
			ip++

		case OpCodeSubtractFloat32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Float32Value - b.Float32Value)
			state.SP--
			ip++

		case OpCodeSubtractFloat64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Float64Value - b.Float64Value)
			state.SP--
			ip++

		// --- Arithmetic: Multiply ---
		case OpCodeMultiplyInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(int8(a.Int64Value) * int8(b.Int64Value))
			state.SP--
			ip++

		case OpCodeMultiplyUInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint8(a.Int64Value) * uint8(b.Int64Value))
			state.SP--
			ip++

		case OpCodeMultiplyInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(int16(a.Int64Value) * int16(b.Int64Value))
			state.SP--
			ip++

		case OpCodeMultiplyUInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint16(a.Int64Value) * uint16(b.Int64Value))
			state.SP--
			ip++

		case OpCodeMultiplyInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Int32Value * b.Int32Value)
			state.SP--
			ip++

		case OpCodeMultiplyUInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint32(a.Int64Value) * uint32(b.Int64Value))
			state.SP--
			ip++

		case OpCodeMultiplyInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Int64Value * b.Int64Value)
			state.SP--
			ip++

		case OpCodeMultiplyUInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint64(a.Int64Value) * uint64(b.Int64Value))
			state.SP--
			ip++

		case OpCodeMultiplyFloat32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Float32Value * b.Float32Value)
			state.SP--
			ip++

		case OpCodeMultiplyFloat64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Float64Value * b.Float64Value)
			state.SP--
			ip++

		// --- Arithmetic: Divide ---
		case OpCodeDivideInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(int8(a.Int64Value) / int8(b.Int64Value))
			state.SP--
			ip++

		case OpCodeDivideUInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint8(a.Int64Value) / uint8(b.Int64Value))
			state.SP--
			ip++

		case OpCodeDivideInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(int16(a.Int64Value) / int16(b.Int64Value))
			state.SP--
			ip++

		case OpCodeDivideUInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint16(a.Int64Value) / uint16(b.Int64Value))
			state.SP--
			ip++

		case OpCodeDivideInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Int32Value / b.Int32Value)
			state.SP--
			ip++

		case OpCodeDivideUInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint32(a.Int64Value) / uint32(b.Int64Value))
			state.SP--
			ip++

		case OpCodeDivideInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Int64Value / b.Int64Value)
			state.SP--
			ip++

		case OpCodeDivideUInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint64(a.Int64Value) / uint64(b.Int64Value))
			state.SP--
			ip++

		case OpCodeDivideFloat32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Float32Value / b.Float32Value)
			state.SP--
			ip++

		case OpCodeDivideFloat64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Float64Value / b.Float64Value)
			state.SP--
			ip++

		// --- Arithmetic: Modulo ---
		case OpCodeModuloInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(int8(a.Int64Value) % int8(b.Int64Value))
			state.SP--
			ip++

		case OpCodeModuloUInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint8(a.Int64Value) % uint8(b.Int64Value))
			state.SP--
			ip++

		case OpCodeModuloInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(int16(a.Int64Value) % int16(b.Int64Value))
			state.SP--
			ip++

		case OpCodeModuloUInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint16(a.Int64Value) % uint16(b.Int64Value))
			state.SP--
			ip++

		case OpCodeModuloInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Int32Value % b.Int32Value)
			state.SP--
			ip++

		case OpCodeModuloUInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint32(a.Int64Value) % uint32(b.Int64Value))
			state.SP--
			ip++

		case OpCodeModuloInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Int64Value % b.Int64Value)
			state.SP--
			ip++

		case OpCodeModuloUInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint64(a.Int64Value) % uint64(b.Int64Value))
			state.SP--
			ip++

		// --- Arithmetic: Shift Left ---
		case OpCodeShiftLeftInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(int8(a.Int64Value) << uint(b.Int64Value))
			state.SP--
			ip++

		case OpCodeShiftLeftUInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint8(a.Int64Value) << uint(b.Int64Value))
			state.SP--
			ip++

		case OpCodeShiftLeftInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(int16(a.Int64Value) << uint(b.Int64Value))
			state.SP--
			ip++

		case OpCodeShiftLeftUInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint16(a.Int64Value) << uint(b.Int64Value))
			state.SP--
			ip++

		case OpCodeShiftLeftInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Int32Value << uint(b.Int64Value))
			state.SP--
			ip++

		case OpCodeShiftLeftUInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint32(a.Int64Value) << uint(b.Int64Value))
			state.SP--
			ip++

		case OpCodeShiftLeftInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Int64Value << uint(b.Int64Value))
			state.SP--
			ip++

		case OpCodeShiftLeftUInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint64(a.Int64Value) << uint(b.Int64Value))
			state.SP--
			ip++

		// --- Arithmetic: Shift Right ---
		case OpCodeShiftRightInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(int8(a.Int64Value) >> uint(b.Int64Value))
			state.SP--
			ip++

		case OpCodeShiftRightUInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint8(a.Int64Value) >> uint(b.Int64Value))
			state.SP--
			ip++

		case OpCodeShiftRightInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(int16(a.Int64Value) >> uint(b.Int64Value))
			state.SP--
			ip++

		case OpCodeShiftRightUInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint16(a.Int64Value) >> uint(b.Int64Value))
			state.SP--
			ip++

		case OpCodeShiftRightInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Int32Value >> uint(b.Int64Value))
			state.SP--
			ip++

		case OpCodeShiftRightUInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint32(a.Int64Value) >> uint(b.Int64Value))
			state.SP--
			ip++

		case OpCodeShiftRightInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Int64Value >> uint(b.Int64Value))
			state.SP--
			ip++

		case OpCodeShiftRightUInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint64(a.Int64Value) >> uint(b.Int64Value))
			state.SP--
			ip++

		// --- Bitwise: And ---
		case OpCodeBinaryAndInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(int8(a.Int64Value) & int8(b.Int64Value))
			state.SP--
			ip++

		case OpCodeBinaryAndUInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint8(a.Int64Value) & uint8(b.Int64Value))
			state.SP--
			ip++

		case OpCodeBinaryAndInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(int16(a.Int64Value) & int16(b.Int64Value))
			state.SP--
			ip++

		case OpCodeBinaryAndUInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint16(a.Int64Value) & uint16(b.Int64Value))
			state.SP--
			ip++

		case OpCodeBinaryAndInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Int32Value & b.Int32Value)
			state.SP--
			ip++

		case OpCodeBinaryAndUInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint32(a.Int64Value) & uint32(b.Int64Value))
			state.SP--
			ip++

		case OpCodeBinaryAndInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Int64Value & b.Int64Value)
			state.SP--
			ip++

		case OpCodeBinaryAndUInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint64(a.Int64Value) & uint64(b.Int64Value))
			state.SP--
			ip++

		case OpCodeBinaryOrInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(int8(a.Int64Value) | int8(b.Int64Value))
			state.SP--
			ip++

		case OpCodeBinaryOrUInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint8(a.Int64Value) | uint8(b.Int64Value))
			state.SP--
			ip++

		case OpCodeBinaryOrInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(int16(a.Int64Value) | int16(b.Int64Value))
			state.SP--
			ip++

		case OpCodeBinaryOrUInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint16(a.Int64Value) | uint16(b.Int64Value))
			state.SP--
			ip++

		case OpCodeBinaryOrInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Int32Value | b.Int32Value)
			state.SP--
			ip++

		case OpCodeBinaryOrUInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint32(a.Int64Value) | uint32(b.Int64Value))
			state.SP--
			ip++

		case OpCodeBinaryOrInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Int64Value | b.Int64Value)
			state.SP--
			ip++

		case OpCodeBinaryOrUInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint64(a.Int64Value) | uint64(b.Int64Value))
			state.SP--
			ip++

		case OpCodeBinaryXorInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(int8(a.Int64Value) ^ int8(b.Int64Value))
			state.SP--
			ip++

		case OpCodeBinaryXorUInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint8(a.Int64Value) ^ uint8(b.Int64Value))
			state.SP--
			ip++

		case OpCodeBinaryXorInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(int16(a.Int64Value) ^ int16(b.Int64Value))
			state.SP--
			ip++

		case OpCodeBinaryXorUInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint16(a.Int64Value) ^ uint16(b.Int64Value))
			state.SP--
			ip++

		case OpCodeBinaryXorInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Int32Value ^ b.Int32Value)
			state.SP--
			ip++

		case OpCodeBinaryXorUInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint32(a.Int64Value) ^ uint32(b.Int64Value))
			state.SP--
			ip++

		case OpCodeBinaryXorInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.Int64Value ^ b.Int64Value)
			state.SP--
			ip++

		case OpCodeBinaryXorUInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(uint64(a.Int64Value) ^ uint64(b.Int64Value))
			state.SP--
			ip++

		// --- Relational: EqualTo ---
		case OpCodeEqualToInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(int8(a.Int64Value) == int8(b.Int64Value))
			state.SP--
			ip++

		case OpCodeEqualToUInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(uint8(a.Int64Value) == uint8(b.Int64Value))
			state.SP--
			ip++

		case OpCodeEqualToInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(int16(a.Int64Value) == int16(b.Int64Value))
			state.SP--
			ip++

		case OpCodeEqualToUInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(uint16(a.Int64Value) == uint16(b.Int64Value))
			state.SP--
			ip++

		case OpCodeEqualToInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(a.Int32Value == b.Int32Value)
			state.SP--
			ip++

		case OpCodeEqualToInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(a.Int64Value == b.Int64Value)
			state.SP--
			ip++

		case OpCodeEqualToUInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(uint32(a.Int64Value) == uint32(b.Int64Value))
			state.SP--
			ip++

		case OpCodeEqualToUInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(uint64(a.Int64Value) == uint64(b.Int64Value))
			state.SP--
			ip++

		case OpCodeEqualToFloat32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(a.Float32Value == b.Float32Value)
			state.SP--
			ip++

		case OpCodeEqualToFloat64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(a.Float64Value == b.Float64Value)
			state.SP--
			ip++

		// --- Relational: LessThan ---
		case OpCodeLessThanInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(int8(a.Int64Value) < int8(b.Int64Value))
			state.SP--
			ip++

		case OpCodeLessThanUInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(uint8(a.Int64Value) < uint8(b.Int64Value))
			state.SP--
			ip++

		case OpCodeLessThanInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(int16(a.Int64Value) < int16(b.Int64Value))
			state.SP--
			ip++

		case OpCodeLessThanUInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(uint16(a.Int64Value) < uint16(b.Int64Value))
			state.SP--
			ip++

		case OpCodeLessThanInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(a.Int32Value < b.Int32Value)
			state.SP--
			ip++

		case OpCodeLessThanInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(a.Int64Value < b.Int64Value)
			state.SP--
			ip++

		case OpCodeLessThanUInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(uint32(a.Int64Value) < uint32(b.Int64Value))
			state.SP--
			ip++

		case OpCodeLessThanUInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(uint64(a.Int64Value) < uint64(b.Int64Value))
			state.SP--
			ip++

		case OpCodeLessThanFloat32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(a.Float32Value < b.Float32Value)
			state.SP--
			ip++

		case OpCodeLessThanFloat64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(a.Float64Value < b.Float64Value)
			state.SP--
			ip++

		// --- Relational: GreaterThan ---
		case OpCodeGreaterThanInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(int8(a.Int64Value) > int8(b.Int64Value))
			state.SP--
			ip++

		case OpCodeGreaterThanUInt8:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(uint8(a.Int64Value) > uint8(b.Int64Value))
			state.SP--
			ip++

		case OpCodeGreaterThanInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(int16(a.Int64Value) > int16(b.Int64Value))
			state.SP--
			ip++

		case OpCodeGreaterThanUInt16:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(uint16(a.Int64Value) > uint16(b.Int64Value))
			state.SP--
			ip++

		case OpCodeGreaterThanInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(a.Int32Value > b.Int32Value)
			state.SP--
			ip++

		case OpCodeGreaterThanInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(a.Int64Value > b.Int64Value)
			state.SP--
			ip++

		case OpCodeGreaterThanUInt32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(uint32(a.Int64Value) > uint32(b.Int64Value))
			state.SP--
			ip++

		case OpCodeGreaterThanUInt64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(uint64(a.Int64Value) > uint64(b.Int64Value))
			state.SP--
			ip++

		case OpCodeGreaterThanFloat32:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(a.Float32Value > b.Float32Value)
			state.SP--
			ip++

		case OpCodeGreaterThanFloat64:
			a, b := state.Stack[state.SP-2], state.Stack[state.SP-1]
			state.Stack[state.SP-2] = boolToValue(a.Float64Value > b.Float64Value)
			state.SP--
			ip++

		// --- Unary: Not ---
		case OpCodeNotInt8:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = boolToValue(int8(a.Int64Value) == 0)
			ip++

		case OpCodeNotUInt8:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = boolToValue(uint8(a.Int64Value) == 0)
			ip++

		case OpCodeNotInt16:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = boolToValue(int16(a.Int64Value) == 0)
			ip++

		case OpCodeNotUInt16:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = boolToValue(uint16(a.Int64Value) == 0)
			ip++

		case OpCodeNotInt32:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = boolToValue(a.Int32Value == 0)
			ip++

		case OpCodeNotUInt32:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = boolToValue(uint32(a.Int64Value) == 0)
			ip++

		case OpCodeNotInt64:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = boolToValue(a.Int64Value == 0)
			ip++

		case OpCodeNotUInt64:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = boolToValue(uint64(a.Int64Value) == 0)
			ip++

		// --- Unary: BinaryNot ---
		case OpCodeBinaryNotInt8:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = ValueOf(^int8(a.Int64Value))
			ip++

		case OpCodeBinaryNotUInt8:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = ValueOf(^uint8(a.Int64Value))
			ip++

		case OpCodeBinaryNotInt16:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = ValueOf(^int16(a.Int64Value))
			ip++

		case OpCodeBinaryNotUInt16:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = ValueOf(^uint16(a.Int64Value))
			ip++

		case OpCodeBinaryNotInt32:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = ValueOf(^a.Int32Value)
			ip++

		case OpCodeBinaryNotUInt32:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = ValueOf(^uint32(a.Int64Value))
			ip++

		case OpCodeBinaryNotInt64:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = ValueOf(^a.Int64Value)
			ip++

		case OpCodeBinaryNotUInt64:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = ValueOf(^uint64(a.Int64Value))
			ip++

			// --- Unary: Negate ---
		case OpCodeNegateInt8:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = ValueOf(-int8(a.Int64Value))
			ip++

		case OpCodeNegateUInt8:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = ValueOf(-uint8(a.Int64Value))
			ip++

		case OpCodeNegateInt16:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = ValueOf(-int16(a.Int64Value))
			ip++

		case OpCodeNegateUInt16:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = ValueOf(-uint16(a.Int64Value))
			ip++

		case OpCodeNegateInt32:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = ValueOf(-a.Int32Value)
			ip++

		case OpCodeNegateUInt32:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = ValueOf(-uint32(a.Int64Value))
			ip++

		case OpCodeNegateInt64:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = ValueOf(-a.Int64Value)
			ip++

		case OpCodeNegateUInt64:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = ValueOf(-uint64(a.Int64Value))
			ip++

		case OpCodeNegateFloat32:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = ValueOf(-a.Float32Value)
			ip++

		case OpCodeNegateFloat64:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = ValueOf(-a.Float64Value)
			ip++

		// --- Conversion opcodes ---
		// ConvertXtoY opcodes: base = OpCodeConvertInt8Int8, encoded as fromOffset*10 + toOffset
		default:
			if i.Op >= int(OpCodeConvertInt8Int8) && i.Op < int(OpCodeConvertInt8Int8)+100 {
				offset := i.Op - int(OpCodeConvertInt8Int8)
				fromType := offset / 10
				toType := offset % 10
				v := state.Stack[state.SP-1]
				state.Stack[state.SP-1] = convertValue(v, fromType, toType)
				ip++
			} else if i.Op >= int(OpCodeConvertPointerInt8) && i.Op <= int(OpCodeConvertPointerFloat64) {
				offset := i.Op - int(OpCodeConvertPointerInt8)
				toType := offset % 10
				v := state.Stack[state.SP-1]
				state.Stack[state.SP-1] = convertPointerValue(v, toType)
				ip++
			} else {
				panic(fmt.Sprintf("Unknown opcode %d at ip=%d in %s", i.Op, ip, f.Name))
			}
		}
	}

	frame.IP = ip
}

// --- Conversion helpers ---

func boolToValue(b bool) Value {
	if b {
		return ValueOf(1)
	}
	return ValueOf(0)
}

//goland:noinspection GoUnusedGlobalVariable
var typeSizes = []int{1, 1, 2, 2, 4, 4, 8, 8, 4, 8}

func convertValue(v Value, fromType, toType int) Value {
	val := v.Int64Value

	switch fromType {
	case 0: // Int8
		val = int64(int8(val))
	case 1: // Int16
		val = int64(int16(val))
	case 2: // Int32
		val = int64(int32(val))
	case 3: // Int64
	case 4: // UInt8
		val = int64(uint8(val))
	case 5: // UInt16
		val = int64(uint16(val))
	case 6: // UInt32
		val = int64(uint32(val))
	case 7: // UInt64
		val = int64(uint64(val))
	case 8: // Float32
		val = int64(v.Float32Value)
	case 9: // Float64
		val = int64(v.Float64Value)
	}

	switch toType {
	case 0: // Int8
		return ValueOf(int8(val))
	case 1: // Int16
		return ValueOf(int16(val))
	case 2: // Int32
		return ValueOf(int32(val))
	case 3: // Int64
		return ValueOf(val)
	case 4: // UInt8
		return ValueOf(uint8(val))
	case 5: // UInt16
		return ValueOf(uint16(val))
	case 6: // UInt32
		return ValueOf(uint32(val))
	case 7: // UInt64
		return ValueOf(uint64(val))
	case 8: // Float32
		return ValueOf(float32(val))
	case 9: // Float64
		return ValueOf(float64(val))
	}
	return v
}

func convertPointerValue(v Value, toType int) Value {
	ptr := v.PointerValue
	switch toType {
	case 0:
		return ValueOf(int8(ptr))
	case 1:
		return ValueOf(int16(ptr))
	case 2:
		return ValueOf(ptr)
	case 3:
		return ValueOf(int64(ptr))
	case 4:
		return ValueOf(uint8(ptr))
	case 5:
		return ValueOf(uint16(ptr))
	case 6:
		return ValueOf(uint32(ptr))
	case 7:
		return ValueOf(uint64(ptr))
	case 8:
		return ValueOf(float32(ptr))
	case 9:
		return ValueOf(float64(ptr))
	}
	return v
}

// CInterpreter — implements the stack-based VM execution loop
// ============================================================================
// ============================================================================

func NewCInterpreter(exe *Executable) *CInterpreter {
	ci := &CInterpreter{
		Stack:    make([]Value, 4096),
		SP:       0,
		FI:       -1,
		CpuSpeed: 1000,
		exe:      exe,
	}
	maxFrames := 24
	ci.Frames = make([]ExecutionFrame, maxFrames)
	unusedFunc := &InternalFunction{
		name:         "unused",
		nameContext:  "",
		functionType: VoidProcedure,
	}
	for i := 0; i < maxFrames; i++ {
		ci.Frames[i] = ExecutionFrame{Function: unusedFunc}
	}
	return ci
}

func (ci *CInterpreter) Executable() *Executable {
	return ci.exe
}

func (ci *CInterpreter) ActiveFrame() *ExecutionFrame {
	if ci.FI >= 0 && ci.FI < len(ci.Frames) {
		return &ci.Frames[ci.FI]
	}
	return nil
}

func (ci *CInterpreter) CallStackDepth() int {
	return ci.FI
}

func (ci *CInterpreter) ReadMemory(address int) Value {
	return ci.Stack[address]
}

func (ci *CInterpreter) WriteMemory(address int, value Value) Value {
	ci.Stack[address] = value
	return value
}

func (ci *CInterpreter) ReadString(address int) string {
	var buf []byte
	for {
		b := byte(ci.Stack[address].Int64Value)
		if b == 0 {
			break
		}
		buf = append(buf, b)
		address++
	}
	return string(buf)
}

func (ci *CInterpreter) ReadThis() Value {
	frame := ci.ActiveFrame()
	if frame == nil {
		return ValueOf(0)
	}
	functionType := frame.Function.GetFunctionType()
	if functionType.IsInstance() {
		return ci.Stack[frame.FP-1]
	}
	return ValueOf(0)
}

func (ci *CInterpreter) ReadArg(index int) Value {
	frame := ci.ActiveFrame()
	if frame == nil {
		return ValueOf(0)
	}
	functionType := frame.Function.GetFunctionType()
	params := functionType.Parameters()
	if index < len(params) {
		return ci.Stack[frame.FP+params[index].Offset]
	} else if index == len(params) && functionType.IsInstance() {
		return ci.Stack[frame.FP-1]
	}
	panic(fmt.Sprintf("Cannot read argument #%d", index))
}

func (ci *CInterpreter) CallValue(functionAddress Value) {
	ci.Call(ci.exe.Functions[functionAddress.PointerValue])
}

func (ci *CInterpreter) Call(function BaseFunction) {
	if ci.FI+1 >= len(ci.Frames) {
		name := function.GetName()
		cname := "?"
		if a := ci.ActiveFrame(); a != nil {
			cname = a.Function.GetName()
		}
		ci.Reset("")
		panic(NewExecutionException(fmt.Sprintf("Stack overflow while calling '%s' from '%s'", name, cname)))
	}

	ci.FI++
	frame := &ci.Frames[ci.FI]
	frame.Function = function
	frame.FP = ci.SP
	frame.IP = 0

	function.Init(ci)
}

func (ci *CInterpreter) Push(value Value) {
	ci.Stack[ci.SP] = value
	ci.SP++
}

func (ci *CInterpreter) Yield(yieldedValue int) {
	ci.YieldedValue = yieldedValue
}

func (ci *CInterpreter) Return() {
	frame := ci.ActiveFrame()
	if frame == nil {
		panic("Cannot call Return with no ActiveFrame")
	}

	ftype := frame.Function.GetFunctionType()
	numArgsAndLocals := 0
	for _, p := range ftype.Parameters() {
		numArgsAndLocals += p.ParameterType.NumValues()
	}
	if ftype.IsInstance() {
		numArgsAndLocals++
	}

	// CompiledFunction has LocalVariables
	if cf, ok := frame.Function.(*CompiledFunction); ok {
		for _, v := range cf.LocalVariables {
			numArgsAndLocals += v.VariableType.NumValues()
		}
	}

	numReturnVals := ftype.ReturnType.NumValues()
	newSP := ci.SP - numArgsAndLocals
	retSP := newSP - numReturnVals
	for i := 0; i < numReturnVals; i++ {
		ci.Stack[retSP+i] = ci.Stack[ci.SP-numReturnVals+i]
	}
	ci.SP = newSP

	ci.FI--
}

func (ci *CInterpreter) Reset(entrypoint string) {
	ci.entrypoint = nil
	for _, f := range ci.exe.Functions {
		if f.GetName() == entrypoint {
			ci.entrypoint = f
			break
		}
	}
	ci.reset()
}

func (ci *CInterpreter) reset() {
	ci.FI = -1
	ci.SP = 0
	for _, g := range ci.exe.Globals {
		if g.InitialValue != nil {
			for i := 0; i < len(g.InitialValue); i++ {
				ci.Stack[g.StackOffset+i] = g.InitialValue[i]
			}
		}
		ci.SP += g.VariableType.NumValues()
	}
	ci.SleepTime = 0
	if ci.entrypoint != nil {
		ci.Call(ci.entrypoint)
	}
}

func (ci *CInterpreter) Run() {
	ci.Step(1_000_000)
}

func (ci *CInterpreter) Step(microseconds int) {
	if ci.ActiveFrame() == nil {
		return
	}

	if microseconds <= ci.SleepTime {
		ci.SleepTime -= microseconds
		return
	}

	ci.RemainingTime = microseconds - ci.SleepTime
	ci.SleepTime = 0

	defer func() {
		if r := recover(); r != nil {
			ci.reset()
			panic(r)
		}
	}()

	a := ci.ActiveFrame()
	for a != nil && ci.RemainingTime > 0 {
		ci.RemainingTime -= ci.CpuSpeed
		a.Function.Step(ci, a)
		a = ci.ActiveFrame()
		if ci.YieldedValue != 0 {
			break
		}
	}
}

func (ci *CInterpreter) RunFunction(functionAddress Value, microseconds int, args ...Value) Value {
	for _, arg := range args {
		ci.Push(arg)
	}
	ci.CallValue(functionAddress)
	return ci.stepFunction(microseconds, len(args))
}

func (ci *CInterpreter) stepFunction(microseconds int, argCount int) Value {
	if ci.ActiveFrame() == nil {
		return ValueOf(0)
	}

	parametersCount := len(ci.ActiveFrame().Function.GetFunctionType().Parameters())
	if argCount != parametersCount {
		panic(fmt.Sprintf("Expected %d arguments, got %d for function %s",
			parametersCount, argCount, ci.ActiveFrame().Function.GetName()))
	}

	startFI := ci.FI
	startReturnType := ci.ActiveFrame().Function.GetFunctionType().ReturnType

	if microseconds <= ci.SleepTime {
		ci.SleepTime -= microseconds
	} else {
		ci.RemainingTime = microseconds - ci.SleepTime
		ci.SleepTime = 0

		func() {
			defer func() {
				if r := recover(); r != nil {
					ci.reset()
					panic(r)
				}
			}()

			a := ci.ActiveFrame()
			for a != nil && ci.FI >= startFI && ci.RemainingTime > 0 {
				ci.RemainingTime -= ci.CpuSpeed
				a.Function.Step(ci, a)
				a = ci.ActiveFrame()
				if ci.YieldedValue != 0 {
					break
				}
			}
		}()
	}

	for ci.FI >= startFI {
		if a := ci.ActiveFrame(); a != nil {
			rt := a.Function.GetFunctionType().ReturnType
			if rt != nil && !rt.IsVoid() {
				n := rt.NumValues()
				for i := 0; i < n; i++ {
					ci.Stack[ci.SP] = ValueOf(0)
					ci.SP++
				}
			}
			ci.Return()
		} else {
			break
		}
	}

	returnValue := ValueOf(0)
	numReturnValues := 0
	if startReturnType != nil {
		numReturnValues = startReturnType.NumValues()
	}
	for i := 0; i < numReturnValues; i++ {
		ci.SP--
		returnValue = ci.Stack[ci.SP]
	}
	return returnValue
}

// Assembler CompiledFunction.Assembler property equivalent
func (f *CompiledFunction) Assembler() string {
	var sb strings.Builder
	for i, inst := range f.Instructions {
		sb.WriteString(fmt.Sprintf("%d: %v\n", i, inst))
	}
	return sb.String()
}
