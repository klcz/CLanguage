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

		op := i.Op
		switch {
		// --- Stack ---
		case op == OpCodeDup:
			state.Stack[state.SP] = state.Stack[state.SP-1]
			state.SP++
			ip++

		case op == OpCodePop:
			state.SP--
			ip++

		// --- Control ---
		case op == OpCodeJump:
			if i.Label != nil {
				ip = i.Label.Index
			} else {
				panic("Jump label not set")
			}

		case op == OpCodeBranchIfFalse:
			a := state.Stack[state.SP-1]
			state.SP--
			if a.Int32Value() == 0 {
				if i.Label != nil {
					ip = i.Label.Index
				} else {
					panic("BranchIfFalse label not set")
				}
			} else {
				ip++
			}

		case op == OpCodeBranchIfTrue:
			a := state.Stack[state.SP-1]
			state.SP--
			if a.Int32Value() != 0 {
				if i.Label != nil {
					ip = i.Label.Index
				} else {
					panic("BranchIfTrue label not set")
				}
			} else {
				ip++
			}

		case op == OpCodeCall:
			a := state.Stack[state.SP-1]
			state.SP--
			ip++
			state.CallValue(a)
			done = true

		case op == OpCodeCallVirtual:
			vtableSlot := i.X.Int32Value()
			thisAddr := state.Stack[state.SP-1].PointerValue()
			vptr := state.Stack[thisAddr].PointerValue()
			funcPtr := state.Stack[vptr+1+vtableSlot].PointerValue()
			ip++
			state.Call(state.exe.Functions[funcPtr])
			done = true

		case op == OpCodeReturn:
			state.Return()
			done = true

		// --- Memory ---
		case op == OpCodeLoadConstant:
			state.Stack[state.SP] = i.X
			state.SP++
			ip++

		case op == OpCodeLoadFramePointer:
			state.Stack[state.SP] = ValueOf(frame.FP)
			state.SP++
			ip++

		case op == OpCodeLoadPointer:
			a := state.Stack[state.SP-1]
			state.Stack[state.SP-1] = state.Stack[a.PointerValue()]
			ip++

		case op == OpCodeStorePointer:
			a := state.Stack[state.SP-2]
			b := state.Stack[state.SP-1]
			state.Stack[b.PointerValue()] = a
			state.SP -= 2
			ip++

		case op == OpCodeOffsetPointer:
			a := state.Stack[state.SP-2]
			b := state.Stack[state.SP-1]
			state.Stack[state.SP-2] = ValueOf(a.PointerValue() + b.Int32Value())
			state.SP--
			ip++

		case op == OpCodeLoadGlobal:
			state.Stack[state.SP] = state.Stack[i.X.Int32Value()]
			state.SP++
			ip++

		case op == OpCodeStoreGlobal:
			state.Stack[i.X.Int32Value()] = state.Stack[state.SP-1]
			state.SP--
			ip++

		case op == OpCodeLoadArg:
			state.Stack[state.SP] = state.Stack[frame.FP+int(i.X.Int32Value())]
			state.SP++
			ip++

		case op == OpCodeStoreArg:
			state.Stack[frame.FP+int(i.X.Int32Value())] = state.Stack[state.SP-1]
			state.SP--
			ip++

		case op == OpCodeLoadLocal:
			val := state.Stack[frame.FP+int(i.X.Int32Value())]
			state.Stack[state.SP] = val
			state.SP++
			ip++

		case op == OpCodeStoreLocal:
			state.Stack[frame.FP+int(i.X.Int32Value())] = state.Stack[state.SP-1]
			state.SP--
			ip++

		// --- Arithmetic: Add ---
		case op >= OpCodeAddInt8 && op <= OpCodeAddFloat64:
			OpFunc2(state, &OpFuncV2.OpCodeAdd, op)
			ip++

		// --- Arithmetic: Subtract ---
		case op >= OpCodeSubtractInt8 && op <= OpCodeSubtractFloat64:
			OpFunc2(state, &OpFuncV2.OpCodeSubtract, op)
			ip++

		// --- Arithmetic: Multiply ---
		case op >= OpCodeMultiplyInt8 && op <= OpCodeMultiplyFloat64:
			OpFunc2(state, &OpFuncV2.OpCodeMultiply, op)
			ip++

		// --- Arithmetic: Divide ---
		case op >= OpCodeDivideInt8 && op <= OpCodeDivideFloat64:
			OpFunc2(state, &OpFuncV2.OpCodeDivide, op)
			ip++

		// --- Arithmetic: Modulo ---
		case op >= OpCodeModuloInt8 && op <= OpCodeModuloFloat64:
			OpFunc2(state, &OpFuncV2.OpCodeModulo, op)
			ip++

		// --- Arithmetic: Shift Left ---
		case op >= OpCodeShiftLeftInt8 && op <= OpCodeShiftLeftFloat64:
			OpFunc2(state, &OpFuncV2.OpCodeShiftLeft, op)
			ip++

		// --- Arithmetic: Shift Right ---
		case op >= OpCodeShiftRightInt8 && op <= OpCodeShiftRightFloat64:
			OpFunc2(state, &OpFuncV2.OpCodeShiftRight, op)
			ip++

		// --- Bitwise: And ---
		case op >= OpCodeBinaryAndInt8 && op <= OpCodeBinaryAndFloat64:
			OpFunc2(state, &OpFuncV2.OpCodeBinaryAnd, op)
			ip++

		// --- Bitwise: Or ---
		case op >= OpCodeBinaryOrInt8 && op <= OpCodeBinaryOrFloat64:
			OpFunc2(state, &OpFuncV2.OpCodeBinaryOr, op)
			ip++

		// --- Bitwise: Xor ---
		case op >= OpCodeBinaryXorInt8 && op <= OpCodeBinaryXorFloat64:
			OpFunc2(state, &OpFuncV2.OpCodeBinaryXor, op)
			ip++

		// --- Relational: EqualTo ---
		case op >= OpCodeEqualToInt8 && op <= OpCodeEqualToFloat64:
			OpFunc2(state, &OpFuncV2.OpCodeEqualTo, op)
			ip++

		// --- Relational: LessThan ---
		case op >= OpCodeLessThanInt8 && op <= OpCodeLessThanFloat64:
			OpFunc2(state, &OpFuncV2.OpCodeLessThan, op)
			ip++

		// --- Relational: GreaterThan ---
		case op >= OpCodeGreaterThanInt8 && op <= OpCodeGreaterThanFloat64:
			OpFunc2(state, &OpFuncV2.OpCodeGreaterThan, op)
			ip++

		// --- Unary: Not ---
		case op >= OpCodeNotInt8 && op <= OpCodeNotFloat64:
			OpFunc1(state, &OpFuncV1.OpCodeNot, op)
			ip++

		// --- Unary: BinaryNot ---
		case op >= OpCodeBinaryNotInt8 && op <= OpCodeBinaryNotFloat64:
			OpFunc1(state, &OpFuncV1.OpCodeBinaryNot, op)
			ip++

		// --- Unary: Negate ---
		case op >= OpCodeNegateInt8 && op <= OpCodeNegateFloat64:
			OpFunc1(state, &OpFuncV1.OpCodeNegate, op)
			ip++

		// --- Conversion opcodes ---
		// ConvertXtoY opcodes: base = OpCodeConvertInt8Int8, encoded as fromOffset*10 + toOffset
		default:
			if i.Op >= OpCodeConvertPointerInt8 && i.Op <= OpCodeConvertPointerFloat64 {
				offset := int(i.Op - OpCodeConvertPointerInt8)
				toType := offset % 10
				v := state.Stack[state.SP-1]
				state.Stack[state.SP-1] = convertPointerValue(v, toType)
				ip++
			} else if i.Op >= OpCodeConvertInt8Int8 && i.Op < OpCode(OpCodeConvertInt8Int8)+100 {
				offset := int(i.Op - OpCodeConvertInt8Int8)
				fromType := offset / 10
				toType := offset % 10
				v := state.Stack[state.SP-1]
				state.Stack[state.SP-1] = convertValue(v, fromType, toType)
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
		val = int64(v.Float32Value())
	case 9: // Float64
		val = int64(v.Float64Value())
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
	ptr := v.PointerValue()
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
	ci.Call(ci.exe.Functions[functionAddress.PointerValue()])
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
