package cxxParser

import (
	"fmt"
	"strings"
)

// ── TypeQualifiers ──────────────────────────────────────────────────────────

type TypeQualifiers int

//goland:noinspection GoUnusedConst
const (
	TypeQualifiersNone     TypeQualifiers = 0
	TypeQualifiersConst    TypeQualifiers = 1
	TypeQualifiersRestrict TypeQualifiers = 2
	TypeQualifiersVolatile TypeQualifiers = 4
)

// ── FunctionSpecifier ───────────────────────────────────────────────────────

type FunctionSpecifier int

//goland:noinspection GoUnusedConst
const (
	FunctionSpecifierNone   FunctionSpecifier = 0
	FunctionSpecifierInline FunctionSpecifier = 1
)

// ── StorageClassSpecifier, DeclarationSpecifiers, InitDeclarator ────────────

type StorageClassSpecifier int

//goland:noinspection GoUnusedConst
const (
	StorageClassSpecifierNone     StorageClassSpecifier = 0
	StorageClassSpecifierTypedef  StorageClassSpecifier = 1
	StorageClassSpecifierExtern   StorageClassSpecifier = 2
	StorageClassSpecifierStatic   StorageClassSpecifier = 4
	StorageClassSpecifierAuto     StorageClassSpecifier = 8
	StorageClassSpecifierRegister StorageClassSpecifier = 16
)

type DeclarationSpecifiers struct {
	StorageClassSpecifier StorageClassSpecifier
	TypeSpecifiers        []*TypeSpecifier
	FunctionSpecifier     FunctionSpecifier
	TypeQualifiers        TypeQualifiers
}

func NewDeclarationSpecifiers() *DeclarationSpecifiers {
	return &DeclarationSpecifiers{
		TypeSpecifiers: make([]*TypeSpecifier, 0),
	}
}

func (d *DeclarationSpecifiers) String() string {
	if d.StorageClassSpecifier == StorageClassSpecifierAuto {
		return "auto"
	}
	parts := make([]string, len(d.TypeSpecifiers))
	for i, ts := range d.TypeSpecifiers {
		parts[i] = ts.String()
	}
	return strings.Join(parts, " ")
}

type InitDeclarator struct {
	Declarator  Declarator
	Initializer Initializer
}

func NewInitDeclarator(declarator Declarator, initializer Initializer) *InitDeclarator {
	return &InitDeclarator{Declarator: declarator, Initializer: initializer}
}

func (d *InitDeclarator) String() string {
	return d.Declarator.String()
}

// ── BaseSpecifier ───────────────────────────────────────────────────────────

type BaseSpecifier struct {
	Name       string
	Visibility *DeclarationsVisibility
}

func NewBaseSpecifier(name string, visibility *DeclarationsVisibility) *BaseSpecifier {
	return &BaseSpecifier{Name: name, Visibility: visibility}
}

func (b *BaseSpecifier) String() string {
	if b.Visibility != nil {
		return fmt.Sprintf("%s %s", visibilityString(*b.Visibility), b.Name)
	}
	return b.Name
}

func visibilityString(v DeclarationsVisibility) string {
	switch v {
	case DeclarationsVisibilityPublic:
		return "public"
	case DeclarationsVisibilityPrivate:
		return "private"
	case DeclarationsVisibilityProtected:
		return "protected"
	}
	return "unknown"
}

// ── DeclarationsVisibility & VisibilityStatement ───────────────────────────

type DeclarationsVisibility int

const (
	DeclarationsVisibilityPublic    DeclarationsVisibility = 0
	DeclarationsVisibilityPrivate   DeclarationsVisibility = 1
	DeclarationsVisibilityProtected DeclarationsVisibility = 2
)

type VisibilityStatement struct {
	StatementBase
	Visibility DeclarationsVisibility
}

func NewVisibilityStatement(visibility DeclarationsVisibility) *VisibilityStatement {
	return &VisibilityStatement{Visibility: visibility}
}

func (s *VisibilityStatement) AlwaysReturns() bool { panic("not implemented") }

//goland:noinspection GoUnusedParameter
func (s *VisibilityStatement) AddDeclarationToBlock(ctx *BlockContext) {}

//goland:noinspection GoUnusedParameter
func (s *VisibilityStatement) DoEmit(ec *EmitContext) {}
func (s *VisibilityStatement) Emit(ec *EmitContext)   { s.DoEmit(ec) }
func (s *VisibilityStatement) String() string         { return "" }

// ── VirtualDeclarationStatement ─────────────────────────────────────────────

type VirtualDeclarationStatement struct {
	StatementBase
	InnerDeclaration Statement
	IsVirtual        bool
	IsOverride       bool
	IsPureVirtual    bool
}

func NewVirtualDeclarationStatement(innerDeclaration Statement) *VirtualDeclarationStatement {
	return &VirtualDeclarationStatement{InnerDeclaration: innerDeclaration}
}

func (s *VirtualDeclarationStatement) AlwaysReturns() bool { return false }

//goland:noinspection GoUnusedParameter
func (s *VirtualDeclarationStatement) AddDeclarationToBlock(ctx *BlockContext) {}

//goland:noinspection GoUnusedParameter
func (s *VirtualDeclarationStatement) DoEmit(ec *EmitContext) {}
func (s *VirtualDeclarationStatement) Emit(ec *EmitContext)   { s.DoEmit(ec) }
func (s *VirtualDeclarationStatement) String() string         { return "" }

// ── Expression ──────────────────────────────────────────────────────────────

type ExpressionBase struct {
	Location    Location
	EndLocation Location
	HasErr      bool
}

func (e *ExpressionBase) GetLocation() Location    { return e.Location }
func (e *ExpressionBase) GetEndLocation() Location { return e.EndLocation }
func (e *ExpressionBase) HasError() bool           { return e.HasErr }
func (e *ExpressionBase) SetHasError(v bool)       { e.HasErr = v }

type Expression interface {
	GetLocation() Location
	GetEndLocation() Location
	HasError() bool
	SetHasError(bool)
	Emit(ec *EmitContext)
	EmitPointer(ec *EmitContext)
	CanEmitPointer() bool
	GetEvaluatedCType(ec *EmitContext) CType
	EvalConstant(ec *EmitContext) Value
	String() string
}

//goland:noinspection GoUnusedParameter
func defaultEmitPointer(ec *EmitContext) { panic("cannot get address") }

func defaultEvalConstant(expr Expression, ec *EmitContext) Value {
	ec.GetReport().Error(133, fmt.Sprintf("'%v' not constant", expr))
	return ValueOf(0)
}

// ── Statement ───────────────────────────────────────────────────────────────

type StatementBase struct {
	Location Location
}

func (b *StatementBase) GetLocation() Location { return b.Location }

type Statement interface {
	GetLocation() Location
	Emit(ec *EmitContext)
	AlwaysReturns() bool
	AddDeclarationToBlock(ctx *BlockContext)
	String() string
}

func ToBlock(s Statement) *Block {
	if b, ok := s.(*Block); ok {
		return b
	}
	b := NewBlock(VariableScopeLocal)
	b.AddStatement(s)
	return b
}

// ── Expression helper functions ─────────────────────────────────────────────

func GetPromotedType(expr Expression, op string, ec *EmitContext) CType {
	leftType := expr.GetEvaluatedCType(ec)

	if leftBasic := leftType.GetBasicType(); leftBasic != nil {
		return leftBasic.IntegerPromote(ec)
	}
	if laType, ok := leftType.(*CArrayType); ok {
		return laType.ElementType.Pointer()
	}
	if _, ok := leftType.(*CPointerType); ok {
		return leftType
	}
	ec.GetReport().Error(19, fmt.Sprintf("'%s' cannot be applied to operand of type '%v'", op, leftType))
	return CBasicTypeSignedInt
}

func GetArithmeticType(leftExpr, rightExpr Expression, op string, ec *EmitContext) CType {
	leftType := leftExpr.GetEvaluatedCType(ec)
	rightType := rightExpr.GetEvaluatedCType(ec)

	leftBasic := leftType.GetBasicType()
	rightBasic := rightType.GetBasicType()

	if leftBasic != nil && rightBasic != nil {
		return leftBasic.ArithmeticConvert(rightBasic, ec)
	}
	if lpType, ok := leftType.(*CPointerType); ok && rightBasic != nil {
		return lpType
	}
	if laType, ok := leftType.(*CArrayType); ok && rightBasic != nil {
		return laType.ElementType.Pointer()
	}
	if rpType, ok := rightType.(*CPointerType); ok && leftBasic != nil {
		return rpType
	}
	if raType, ok := rightType.(*CArrayType); ok && leftBasic != nil {
		return raType.ElementType.Pointer()
	}
	ec.GetReport().Error(19, fmt.Sprintf("'%s' cannot be applied to operands of type '%v' and '%v'", op, leftType, rightType))
	return CBasicTypeSignedInt
}

func BinopToOperatorName(op Binop) string {
	switch op {
	case BinopAdd:
		return "operator+"
	case BinopSubtract:
		return "operator-"
	case BinopMultiply:
		return "operator*"
	case BinopDivide:
		return "operator/"
	case BinopMod:
		return "operator%"
	case BinopShiftLeft:
		return "operator<<"
	case BinopShiftRight:
		return "operator>>"
	case BinopBinaryAnd:
		return "operator&"
	case BinopBinaryOr:
		return "operator|"
	case BinopBinaryXor:
		return "operator^"
	}
	return ""
}

func RelOpToOperatorName(op RelationalOp) string {
	switch op {
	case RelationalOpEquals:
		return "operator=="
	case RelationalOpNotEquals:
		return "operator!="
	case RelationalOpLessThan:
		return "operator<"
	case RelationalOpLessThanOrEqual:
		return "operator<="
	case RelationalOpGreaterThan:
		return "operator>"
	case RelationalOpGreaterThanOrEqual:
		return "operator>="
	}
	return ""
}

func UnopToOperatorName(op Unop) string {
	switch op {
	case UnopNegate:
		return "operator-"
	case UnopNot:
		return "operator!"
	case UnopBinaryComplement:
		return "operator~"
	}
	return ""
}

func FindBestOperatorMethod(structType *CStructType, operatorName string, argTypes []CType) *CStructMethod {
	methods := structType.FindMethods(operatorName)
	var best *CStructMethod
	bestScore := 0
	for _, m := range methods {
		if mt, ok := m.GetMemberType().(*CFunctionType); ok {
			score := mt.ScoreParameterTypeMatches(argTypes)
			if score > bestScore {
				bestScore = score
				best = m
			}
		}
	}
	return best
}

func TryResolveBinaryOperatorType(ec *EmitContext, leftType, rightType CType, operatorName string) *CFunctionType {
	if operatorName == "" {
		return nil
	}
	if st, ok := leftType.(*CStructType); ok {
		m := FindBestOperatorMethod(st, operatorName, []CType{rightType})
		if m != nil {
			if ft, ok := m.GetMemberType().(*CFunctionType); ok {
				return ft
			}
		}
		resolved := ec.TryResolveOperatorFunction(st.Name, operatorName, []CType{rightType})
		if resolved != nil {
			if rft, ok := resolved.VariableType.(*CFunctionType); ok {
				return rft
			}
		}
	}
	_, leftIsStruct := leftType.(*CStructType)
	_, rightIsStruct := rightType.(*CStructType)
	if leftIsStruct || rightIsStruct {
		res := ec.TryResolveVariable(operatorName, []CType{leftType, rightType})
		if res != nil {
			if fft, ok := res.VariableType.(*CFunctionType); ok {
				return fft
			}
		}
	}
	return nil
}

func TryResolveUnaryOperatorType(ec *EmitContext, operandType CType, operatorName string) *CFunctionType {
	if operatorName == "" {
		return nil
	}
	if st, ok := operandType.(*CStructType); ok {
		m := FindBestOperatorMethod(st, operatorName, nil)
		if m != nil {
			if ft, ok := m.GetMemberType().(*CFunctionType); ok {
				return ft
			}
		}
		resolved := ec.TryResolveOperatorFunction(st.Name, operatorName, nil)
		if resolved != nil {
			if rft, ok := resolved.VariableType.(*CFunctionType); ok {
				return rft
			}
		}
		res := ec.TryResolveVariable(operatorName, []CType{operandType})
		if res != nil {
			if fft, ok := res.VariableType.(*CFunctionType); ok {
				return fft
			}
		}
	}
	return nil
}

func TryEmitBinaryOperatorCall(ec *EmitContext, leftType, rightType CType, left, right Expression, operatorName string) bool {
	if operatorName == "" {
		return false
	}
	argTypes := []CType{rightType}
	args := []Expression{right}

	if structType, ok := leftType.(*CStructType); ok {
		method := FindBestOperatorMethod(structType, operatorName, argTypes)
		if method != nil {
			funcType := method.GetMemberType().(*CFunctionType)
			resolved := ec.ResolveMethodFunction(structType, method)
			emitMemberOperatorCall(ec, structType, resolved, funcType, left, args, argTypes)
			return true
		}
		resolvedOp := ec.TryResolveOperatorFunction(structType.Name, operatorName, argTypes)
		if resolvedOp != nil {
			if rFuncType, ok := resolvedOp.VariableType.(*CFunctionType); ok {
				emitMemberOperatorCall(ec, structType, resolvedOp, rFuncType, left, args, argTypes)
				return true
			}
		}
	}

	_, leftIsStruct := leftType.(*CStructType)
	_, rightIsStruct := rightType.(*CStructType)
	if leftIsStruct || rightIsStruct {
		freeArgTypes := []CType{leftType, rightType}
		res := ec.TryResolveVariable(operatorName, freeArgTypes)
		if res != nil {
			if _, ok := res.VariableType.(*CFunctionType); ok {
				emitFreeStandingOperatorCall(ec, res, []Expression{left, right}, freeArgTypes)
				return true
			}
		}
	}
	return false
}

func TryEmitUnaryOperatorCall(ec *EmitContext, operandType CType, operand Expression, operatorName string) bool {
	if operatorName == "" {
		return false
	}
	if structType, ok := operandType.(*CStructType); ok {
		method := FindBestOperatorMethod(structType, operatorName, nil)
		if method != nil {
			funcType := method.GetMemberType().(*CFunctionType)
			resolved := ec.ResolveMethodFunction(structType, method)
			emitMemberOperatorCall(ec, structType, resolved, funcType, operand, nil, nil)
			return true
		}
		resolvedOp := ec.TryResolveOperatorFunction(structType.Name, operatorName, nil)
		if resolvedOp != nil {
			if rFuncType, ok := resolvedOp.VariableType.(*CFunctionType); ok {
				emitMemberOperatorCall(ec, structType, resolvedOp, rFuncType, operand, nil, nil)
				return true
			}
		}
		argTypes := []CType{operandType}
		res := ec.TryResolveVariable(operatorName, argTypes)
		if res != nil {
			if _, ok := res.VariableType.(*CFunctionType); ok {
				emitFreeStandingOperatorCall(ec, res, []Expression{operand}, argTypes)
				return true
			}
		}
	}
	return false
}

func emitMemberOperatorCall(ec *EmitContext, structType *CStructType, resolved *ResolvedVariable, funcType *CFunctionType, thisExpr Expression, args []Expression, argTypes []CType) {
	tempThisOffset := -1
	if !thisExpr.CanEmitPointer() {
		tempThisOffset = ec.AllocateTemp(structType)
		thisExpr.Emit(ec)
		numValues := structType.NumValues()
		for i := numValues - 1; i >= 0; i-- {
			ec.Emit(OpCodeStoreLocal, ValueOf(tempThisOffset+i))
		}
	}
	for i := range args {
		paramType := funcType.Parameters()[i].ParameterType
		emitOperatorArgument(ec, args[i], argTypes[i], paramType)
	}
	if tempThisOffset >= 0 {
		ec.Emit(OpCodeLoadConstant, ValuePointer(tempThisOffset))
		ec.Emit(OpCodeLoadFramePointer, ValueOf(0))
		ec.Emit(OpCodeOffsetPointer, ValueOf(0))
	} else {
		thisExpr.EmitPointer(ec)
	}
	ec.Emit(OpCodeLoadConstant, ValuePointer(resolved.Address))
	ec.Emit(OpCodeCall, ValueOf(len(funcType.Parameters())))

	if funcType.ReturnType.IsVoid() {
		ec.Emit(OpCodeLoadConstant, ValueOf(0))
	}
}

func emitFreeStandingOperatorCall(ec *EmitContext, resolvedFunc *ResolvedVariable, args []Expression, argTypes []CType) {
	funcType := resolvedFunc.VariableType.(*CFunctionType)

	for i := range args {
		paramType := funcType.Parameters()[i].ParameterType
		emitOperatorArgument(ec, args[i], argTypes[i], paramType)
	}
	resolvedFunc.Emit(ec)
	ec.Emit(OpCodeCall, ValueOf(len(funcType.Parameters())))

	if funcType.ReturnType.IsVoid() {
		ec.Emit(OpCodeLoadConstant, ValueOf(0))
	}
}

func emitOperatorArgument(ec *EmitContext, arg Expression, argType, paramType CType) {
	if refType, ok := paramType.(*CReferenceType); ok {
		if arg.CanEmitPointer() {
			arg.EmitPointer(ec)
		} else {
			innerType := refType.InnerType
			tempOffset := ec.AllocateTemp(innerType)
			arg.Emit(ec)
			ec.EmitCast(argType, innerType)
			numValues := innerType.NumValues()
			for i := numValues - 1; i >= 0; i-- {
				ec.Emit(OpCodeStoreLocal, ValueOf(tempOffset+i))
			}
			ec.Emit(OpCodeLoadConstant, ValuePointer(tempOffset))
			ec.Emit(OpCodeLoadFramePointer, ValueOf(0))
			ec.Emit(OpCodeOffsetPointer, ValueOf(0))
		}
	} else {
		arg.Emit(ec)
		ec.EmitCast(argType, paramType)
	}
}

// ── ConstantExpression ──────────────────────────────────────────────────────

type ConstantExpression struct {
	ExpressionBase
	Value        interface{}
	ConstantType CType
}

//goland:noinspection GoUnusedGlobalVariable
var (
	ConstantExpressionZero        = &ConstantExpression{Value: int64(0), ConstantType: CBasicTypeSignedInt}
	ConstantExpressionOne         = &ConstantExpression{Value: int64(1), ConstantType: CBasicTypeSignedInt}
	ConstantExpressionNegativeOne = &ConstantExpression{Value: int64(-1), ConstantType: CBasicTypeSignedInt}
	ConstantExpressionTrue        = &ConstantExpression{Value: true, ConstantType: CBasicTypeBool}
	ConstantExpressionFalse       = &ConstantExpression{Value: false, ConstantType: CBasicTypeBool}
)

func NewConstantExpression(val interface{}) *ConstantExpression {
	e := &ConstantExpression{Value: val}
	switch v := val.(type) {
	case string:
		e.ConstantType = CPointerTypePointerToConstChar
		_ = v
	case bool:
		e.ConstantType = CBasicTypeBool
	case uint8:
		e.ConstantType = CBasicTypeUnsignedChar
	case int8:
		e.ConstantType = CBasicTypeSignedChar
	case uint16:
		e.ConstantType = CBasicTypeUnsignedShortInt
	case int16:
		e.ConstantType = CBasicTypeSignedShortInt
	case uint32:
		e.ConstantType = CBasicTypeUnsignedInt
	case int32:
		e.ConstantType = CBasicTypeSignedInt
	case uint64:
		e.ConstantType = CBasicTypeUnsignedLongInt
	case int64:
		e.ConstantType = CBasicTypeSignedLongInt
	case float32:
		e.ConstantType = CBasicTypeFloat
	case float64:
		e.ConstantType = CBasicTypeDouble
	default:
		e.ConstantType = CBasicTypeSignedInt
	}
	return e
}

func NewConstantExpressionWithType(val interface{}, typ CType) *ConstantExpression {
	e := NewConstantExpression(val)
	e.ConstantType = typ
	return e
}

func fitsInSignedBytes(val int64, byteSize int) bool {
	switch byteSize {
	case 1:
		return val >= -128 && val <= 127
	case 2:
		return val >= -32768 && val <= 32767
	case 4:
		return val >= -2147483648 && val <= 2147483647
	case 8:
		return true
	}
	return false
}

func fitsInUnsignedBytes(val uint64, byteSize int) bool {
	switch byteSize {
	case 1:
		return val <= 255
	case 2:
		return val <= 65535
	case 4:
		return val <= 4294967295
	case 8:
		return true
	}
	return false
}

func (e *ConstantExpression) promoteIntConstant(intType *CIntType, ec *EmitContext) *CIntType {
	if intType == nil || ec == nil {
		return SignedInt
	}
	mi := ec.GetMachineInfo()
	curSize := intType.GetByteSize(ec)

	if intType.Signedness == Signed {
		val := toInt64(e.Value)
		if fitsInSignedBytes(val, curSize) {
			return intType
		}
		if mi.LongIntSize > curSize && fitsInSignedBytes(val, mi.LongIntSize) {
			return SignedLongInt
		}
		if mi.LongLongIntSize > curSize && fitsInSignedBytes(val, mi.LongLongIntSize) {
			return SignedLongLongInt
		}
	} else {
		val := toUint64(e.Value)
		if fitsInUnsignedBytes(val, curSize) {
			return intType
		}
		if mi.LongIntSize > curSize && fitsInUnsignedBytes(val, mi.LongIntSize) {
			return UnsignedLongInt
		}
		if mi.LongLongIntSize > curSize && fitsInUnsignedBytes(val, mi.LongLongIntSize) {
			return UnsignedLongLongInt
		}
	}
	return intType
}

func (e *ConstantExpression) GetEvaluatedCType(ec *EmitContext) CType {
	if intType, ok := e.ConstantType.(*CIntType); ok {
		return e.promoteIntConstant(intType, ec)
	}
	return e.ConstantType
}

func (e *ConstantExpression) CanEmitPointer() bool { return false }

func (e *ConstantExpression) Emit(ec *EmitContext) {
	cval := e.EvalConstant(ec)
	ec.Emit(OpCodeLoadConstant, cval)
}

func (e *ConstantExpression) EmitPointer(ec *EmitContext) { defaultEmitPointer(ec) }

func (e *ConstantExpression) EvalConstant(ec *EmitContext) Value {
	evalType := e.GetEvaluatedCType(ec)

	if intType, ok := evalType.(*CIntType); ok {
		size := intType.GetByteSize(ec)
		if intType.Signedness == Signed {
			val := toInt64(e.Value)
			switch size {
			case 1:
				return ValueOf(int64(int8(val)))
			case 2:
				return ValueOf(int64(int16(val)))
			case 4:
				return ValueOf(int64(int32(val)))
			case 8:
				return ValueOf(val)
			}
		} else {
			val := toUint64(e.Value)
			switch size {
			case 1:
				return ValueOf(uint64(uint8(val)))
			case 2:
				return ValueOf(uint64(uint16(val)))
			case 4:
				return ValueOf(uint64(uint32(val)))
			case 8:
				return ValueOf(val)
			}
		}
	}
	if _, ok := evalType.(*CBoolType); ok {
		if b, ok := e.Value.(bool); ok && b {
			return ValueOf(uint64(1))
		}
		return ValueOf(uint64(0))
	}
	if floatType, ok := evalType.(*CFloatType); ok {
		if floatType.Bits == 64 {
			return ValueOf(toFloat64(e.Value))
		}
		return ValueOf(float64(toFloat32(e.Value)))
	}
	if vs, ok := e.Value.(string); ok {
		return ec.self.GetConstantMemory(vs)
	}
	panic(fmt.Sprintf("Non-basic constants with type '%v'", e.ConstantType))
}

func toInt64(v interface{}) int64 {
	switch x := v.(type) {
	case int64:
		return x
	case int:
		return int64(x)
	case uint64:
		return int64(x)
	case uint:
		return int64(x)
	case int32:
		return int64(x)
	case uint32:
		return int64(x)
	case uint8:
		return int64(x)
	case int8:
		return int64(x)
	case int16:
		return int64(x)
	case uint16:
		return int64(x)
	}
	return 0
}

func toUint64(v interface{}) uint64 {
	switch x := v.(type) {
	case uint64:
		return x
	case uint:
		return uint64(x)
	case int64:
		return uint64(x)
	case int:
		return uint64(x)
	case uint32:
		return uint64(x)
	case int32:
		return uint64(x)
	case uint8:
		return uint64(x)
	case int8:
		return uint64(x)
	case uint16:
		return uint64(x)
	case int16:
		return uint64(x)
	}
	return 0
}

func toFloat64(v interface{}) float64 {
	switch x := v.(type) {
	case float64:
		return x
	case float32:
		return float64(x)
	}
	return 0
}

func toFloat32(v interface{}) float32 {
	switch x := v.(type) {
	case float32:
		return x
	case float64:
		return float32(x)
	}
	return 0
}

func (e *ConstantExpression) String() string {
	return fmt.Sprintf("%v", e.Value)
}

// ── VariableExpression ──────────────────────────────────────────────────────

type VariableExpression struct {
	ExpressionBase
	VariableName string
}

func NewVariableExpression(val string, loc, endLoc Location) *VariableExpression {
	return &VariableExpression{
		VariableName: val,
		ExpressionBase: ExpressionBase{
			Location:    loc,
			EndLocation: endLoc,
		},
	}
}

func EmitLoadReferenceSlot(ec *EmitContext, variable *ResolvedVariable) {
	switch variable.Scope {
	case VariableScopeArg:
		ec.Emit(OpCodeLoadArg, ValueOf(variable.Address))
	case VariableScopeLocal:
		ec.Emit(OpCodeLoadLocal, ValueOf(variable.Address))
	case VariableScopeGlobal:
		ec.Emit(OpCodeLoadGlobal, ValueOf(variable.Address))
	default:
		panic(fmt.Sprintf("Cannot access reference variable scope '%v'", variable.Scope))
	}
}

func (e *VariableExpression) GetEvaluatedCType(ec *EmitContext) CType {
	typ := ec.ResolveVariable(e, nil).VariableType
	if refType, ok := typ.(*CReferenceType); ok {
		return refType.InnerType
	}
	return typ
}

func (e *VariableExpression) Emit(ec *EmitContext) {
	variable := ec.ResolveVariable(e, nil)
	if variable == nil {
		ec.Emit(OpCodeLoadConstant, ValueOf(0))
		return
	}

	if variable.Scope == VariableScopeFunction {
		ec.Emit(OpCodeLoadConstant, ValuePointer(variable.Address))
		return
	}

	if _, ok := variable.VariableType.(*CReferenceType); ok {
		EmitLoadReferenceSlot(ec, variable)
		ec.Emit(OpCodeLoadPointer, ValueOf(0))
		return
	}

	switch vt := variable.VariableType.(type) {
	case *CIntType, *CFloatType, *CBoolType, *CPointerType, *CEnumType:
		_ = vt
		switch variable.Scope {
		case VariableScopeArg:
			ec.Emit(OpCodeLoadArg, ValueOf(variable.Address))
		case VariableScopeGlobal:
			ec.Emit(OpCodeLoadGlobal, ValueOf(variable.Address))
		case VariableScopeLocal:
			ec.Emit(OpCodeLoadLocal, ValueOf(variable.Address))
		case VariableScopeConstant:
			ec.Emit(OpCodeLoadConstant, variable.Constant)
		default:
			panic(fmt.Sprintf("Cannot evaluate variable scope '%v'", variable.Scope))
		}

	case *CStructType:
		numValues := vt.NumValues()
		for i := 0; i < numValues; i++ {
			switch variable.Scope {
			case VariableScopeArg:
				ec.Emit(OpCodeLoadArg, ValueOf(variable.Address+i))
			case VariableScopeGlobal:
				ec.Emit(OpCodeLoadGlobal, ValueOf(variable.Address+i))
			case VariableScopeLocal:
				ec.Emit(OpCodeLoadLocal, ValueOf(variable.Address+i))
			default:
				panic(fmt.Sprintf("Cannot evaluate struct variable scope '%v'", variable.Scope))
			}
		}

	case *CArrayType:
		switch variable.Scope {
		case VariableScopeArg:
			ec.Emit(OpCodeLoadConstant, ValuePointer(variable.Address))
			ec.Emit(OpCodeLoadFramePointer, ValueOf(0))
			ec.Emit(OpCodeOffsetPointer, ValueOf(0))
		case VariableScopeGlobal:
			ec.Emit(OpCodeLoadConstant, ValuePointer(variable.Address))
		case VariableScopeLocal:
			ec.Emit(OpCodeLoadConstant, ValuePointer(variable.Address))
			ec.Emit(OpCodeLoadFramePointer, ValueOf(0))
			ec.Emit(OpCodeOffsetPointer, ValueOf(0))
		default:
			panic(fmt.Sprintf("Cannot evaluate array variable scope '%v'", variable.Scope))
		}

	default:
		panic(fmt.Sprintf("Cannot evaluate variable type '%v'", variable.VariableType))
	}
}

func (e *VariableExpression) CanEmitPointer() bool { return true }

func (e *VariableExpression) EmitPointer(ec *EmitContext) {
	res := ec.ResolveVariable(e, nil)
	if res == nil {
		ec.Emit(OpCodeLoadConstant, ValueOf(0))
		return
	}
	if _, ok := res.VariableType.(*CReferenceType); ok {
		EmitLoadReferenceSlot(ec, res)
	} else {
		res.EmitPointer(ec)
	}
}

func (e *VariableExpression) EvalConstant(ec *EmitContext) Value {
	res := ec.ResolveVariable(e, nil)
	if res != nil && res.Scope == VariableScopeConstant {
		return res.Constant
	}
	return defaultEvalConstant(e, ec)
}

func (e *VariableExpression) String() string { return e.VariableName }

// ── BinaryExpression & Binop ────────────────────────────────────────────────

type Binop int

const (
	BinopAdd        Binop = 0
	BinopSubtract   Binop = 1
	BinopMultiply   Binop = 2
	BinopDivide     Binop = 3
	BinopMod        Binop = 4
	BinopShiftLeft  Binop = 5
	BinopShiftRight Binop = 6
	BinopBinaryAnd  Binop = 7
	BinopBinaryOr   Binop = 8
	BinopBinaryXor  Binop = 9
)

type BinaryExpression struct {
	ExpressionBase
	Left  Expression
	Op    Binop
	Right Expression
}

func NewBinaryExpression(left Expression, op Binop, right Expression) *BinaryExpression {
	if left == nil {
		panic("left expression is nil")
	}
	if right == nil {
		panic("right expression is nil")
	}
	return &BinaryExpression{Left: left, Op: op, Right: right}
}

func getShiftPromotedType(typ CType, ec *EmitContext) CType {
	if basicType := typ.GetBasicType(); basicType != nil {
		return basicType.IntegerPromote(ec)
	}
	return typ
}

func (e *BinaryExpression) GetEvaluatedCType(ec *EmitContext) CType {
	leftType := e.Left.GetEvaluatedCType(ec)
	rightType := e.Right.GetEvaluatedCType(ec)
	ft := TryResolveBinaryOperatorType(ec, leftType, rightType, BinopToOperatorName(e.Op))
	if ft != nil {
		return ft.ReturnType
	}
	if e.Op == BinopShiftLeft || e.Op == BinopShiftRight {
		return getShiftPromotedType(leftType, ec)
	}
	return GetArithmeticType(e.Left, e.Right, fmt.Sprintf("%v", e.Op), ec)
}

func (e *BinaryExpression) Emit(ec *EmitContext) {
	leftType := e.Left.GetEvaluatedCType(ec)
	rightType := e.Right.GetEvaluatedCType(ec)

	if TryEmitBinaryOperatorCall(ec, leftType, rightType, e.Left, e.Right, BinopToOperatorName(e.Op)) {
		return
	}

	if e.Op == BinopShiftLeft || e.Op == BinopShiftRight {
		promotedLeft := getShiftPromotedType(leftType, ec)
		e.Left.Emit(ec)
		ec.EmitCast(leftType, promotedLeft)
		e.Right.Emit(ec)
		ec.EmitCast(rightType, promotedLeft)
		shiftOff := ec.GetInstructionOffset(promotedLeft)
		if e.Op == BinopShiftLeft {
			ec.Emit(OpCodeShiftLeftInt8+OpCode(shiftOff), ValueOf(0))
		} else {
			ec.Emit(OpCodeShiftRightInt8+OpCode(shiftOff), ValueOf(0))
		}
		return
	}

	aType := GetArithmeticType(e.Left, e.Right, fmt.Sprintf("%v", e.Op), ec)
	e.Left.Emit(ec)
	ec.EmitCast(leftType, aType)
	e.Right.Emit(ec)
	ec.EmitCast(rightType, aType)

	ioff := ec.GetInstructionOffset(aType)
	switch e.Op {
	case BinopAdd:
		ec.Emit(OpCodeAddInt8+OpCode(ioff), ValueOf(0))
	case BinopSubtract:
		ec.Emit(OpCodeSubtractInt8+OpCode(ioff), ValueOf(0))
	case BinopMultiply:
		ec.Emit(OpCodeMultiplyInt8+OpCode(ioff), ValueOf(0))
	case BinopDivide:
		ec.Emit(OpCodeDivideInt8+OpCode(ioff), ValueOf(0))
	case BinopMod:
		ec.Emit(OpCodeModuloInt8+OpCode(ioff), ValueOf(0))
	case BinopBinaryAnd:
		ec.Emit(OpCodeBinaryAndInt8+OpCode(ioff), ValueOf(0))
	case BinopBinaryOr:
		ec.Emit(OpCodeBinaryOrInt8+OpCode(ioff), ValueOf(0))
	case BinopBinaryXor:
		ec.Emit(OpCodeBinaryXorInt8+OpCode(ioff), ValueOf(0))
	default:
		panic(fmt.Sprintf("Unsupported binary operator '%v'", e.Op))
	}
}

func (e *BinaryExpression) EmitPointer(ec *EmitContext) { defaultEmitPointer(ec) }
func (e *BinaryExpression) CanEmitPointer() bool        { return false }

func (e *BinaryExpression) EvalConstant(ec *EmitContext) Value {
	leftType := e.Left.GetEvaluatedCType(ec)
	rightType := e.Right.GetEvaluatedCType(ec)

	if isIntegral(leftType) && isIntegral(rightType) {
		left := int(e.Left.EvalConstant(ec).Int64Value)
		right := int(e.Right.EvalConstant(ec).Int64Value)
		switch e.Op {
		case BinopAdd:
			return ValueOf(int64(left + right))
		case BinopSubtract:
			return ValueOf(int64(left - right))
		case BinopMultiply:
			return ValueOf(int64(left * right))
		case BinopDivide:
			return ValueOf(int64(left / right))
		case BinopMod:
			return ValueOf(int64(left % right))
		case BinopBinaryAnd:
			return ValueOf(int64(left & right))
		case BinopBinaryOr:
			return ValueOf(int64(left | right))
		case BinopBinaryXor:
			return ValueOf(int64(left ^ right))
		case BinopShiftLeft:
			return ValueOf(int64(left << uint(right)))
		case BinopShiftRight:
			return ValueOf(int64(left >> uint(right)))
		}
	}
	return defaultEvalConstant(e, ec)
}

func (e *BinaryExpression) String() string {
	return fmt.Sprintf("(%v %v %v)", e.Left, e.Op, e.Right)
}

// ── UnaryExpression & Unop ──────────────────────────────────────────────────

type Unop int

const (
	UnopNone             Unop = 0
	UnopNot              Unop = 1
	UnopNegate           Unop = 2
	UnopBinaryComplement Unop = 3
	UnopPreIncrement     Unop = 4
	UnopPreDecrement     Unop = 5
	UnopPostIncrement    Unop = 6
	UnopPostDecrement    Unop = 7
)

type UnaryExpression struct {
	ExpressionBase
	Op    Unop
	Right Expression
}

func NewUnaryExpression(op Unop, right Expression) *UnaryExpression {
	return &UnaryExpression{Op: op, Right: right}
}

func (e *UnaryExpression) GetEvaluatedCType(ec *EmitContext) CType {
	rightType := e.Right.GetEvaluatedCType(ec)
	ft := TryResolveUnaryOperatorType(ec, rightType, UnopToOperatorName(e.Op))
	if ft != nil {
		return ft.ReturnType
	}
	if e.Op == UnopNot {
		return CBasicTypeSignedInt
	}
	return GetPromotedType(e.Right, fmt.Sprintf("%v", e.Op), ec)
}

func (e *UnaryExpression) Emit(ec *EmitContext) {
	rightType := e.Right.GetEvaluatedCType(ec)
	opName := UnopToOperatorName(e.Op)
	if opName != "" && TryEmitUnaryOperatorCall(ec, rightType, e.Right, opName) {
		return
	}

	switch e.Op {
	case UnopPreIncrement:
		ie := NewAssignExpression(e.Right, NewBinaryExpression(e.Right, BinopAdd, ConstantExpressionOne))
		ie.Emit(ec)

	case UnopPreDecrement:
		ie := NewAssignExpression(e.Right, NewBinaryExpression(e.Right, BinopAdd, ConstantExpressionNegativeOne))
		ie.Emit(ec)

	case UnopPostIncrement:
		e.Right.Emit(ec)
		ie := NewAssignExpression(e.Right, NewBinaryExpression(e.Right, BinopAdd, ConstantExpressionOne))
		ie.Emit(ec)
		ec.Emit(OpCodePop, ValueOf(0))

	case UnopPostDecrement:
		e.Right.Emit(ec)
		ie := NewAssignExpression(e.Right, NewBinaryExpression(e.Right, BinopAdd, ConstantExpressionNegativeOne))
		ie.Emit(ec)
		ec.Emit(OpCodePop, ValueOf(0))

	default:
		aType := e.GetEvaluatedCType(ec)
		e.Right.Emit(ec)
		ec.EmitCast(e.Right.GetEvaluatedCType(ec), aType)
		ioff := ec.GetInstructionOffset(aType)

		switch e.Op {
		case UnopNone:
		case UnopNegate:
			ec.Emit(OpCodeNegateInt8+OpCode(ioff), ValueOf(0))
		case UnopNot:
			ec.Emit(OpCodeNotInt8+OpCode(ioff), ValueOf(0))
		case UnopBinaryComplement:
			ec.Emit(OpCodeBinaryNotInt8+OpCode(ioff), ValueOf(0))
		default:
			panic(fmt.Sprintf("Unsupported unary operator '%v'", e.Op))
		}
	}
}

func (e *UnaryExpression) EmitPointer(ec *EmitContext) { defaultEmitPointer(ec) }
func (e *UnaryExpression) CanEmitPointer() bool        { return false }

func (e *UnaryExpression) EvalConstant(ec *EmitContext) Value {
	rightType := e.Right.GetEvaluatedCType(ec)
	if isIntegral(rightType) {
		right := int(e.Right.EvalConstant(ec).Int64Value)
		switch e.Op {
		case UnopNone:
			return ValueOf(int64(right))
		case UnopNot:
			if right == 0 {
				return ValueOf(int64(1))
			}
			return ValueOf(int64(0))
		case UnopNegate:
			return ValueOf(int64(-right))
		case UnopBinaryComplement:
			return ValueOf(int64(^right))
		case UnopPreIncrement:
			return ValueOf(int64(right + 1))
		case UnopPreDecrement:
			return ValueOf(int64(right - 1))
		case UnopPostIncrement:
			return ValueOf(int64(right))
		case UnopPostDecrement:
			return ValueOf(int64(right))
		}
	}
	return defaultEvalConstant(e, ec)
}

func (e *UnaryExpression) String() string {
	return fmt.Sprintf("(%v %v)", e.Op, e.Right)
}

// ── FuncallExpression ───────────────────────────────────────────────────────

type FuncallExpression struct {
	ExpressionBase
	Function  Expression
	Arguments []Expression
}

func NewFuncallExpression(fun Expression) *FuncallExpression {
	return &FuncallExpression{
		Function:  fun,
		Arguments: make([]Expression, 0),
	}
}

func NewFuncallExpressionWithArgs(fun Expression, args []Expression) *FuncallExpression {
	cp := make([]Expression, len(args))
	copy(cp, args)
	return &FuncallExpression{
		Function:  fun,
		Arguments: cp,
	}
}

type scoredMethod struct {
	method CStructMethod
	score  int
}

type overload struct {
	cType           CType
	emit            func(ec *EmitContext)
	vTableSlotIndex *int
}

var overloadNoEmit = func(ec *EmitContext) {}

func newOverload(cType CType, emitFn func(ec *EmitContext), vTableSlotIndex *int) overload {
	if emitFn == nil {
		emitFn = overloadNoEmit
	}
	return overload{cType: cType, emit: emitFn, vTableSlotIndex: vTableSlotIndex}
}

//goland:noinspection GoRedundantElseInIf
func (e *FuncallExpression) resolveOverload(function Expression, argTypes []CType, ec *EmitContext) overload {
	switch fun := function.(type) {
	case *MemberFromReferenceExpression:
		targetType := fun.Left.GetEvaluatedCType(ec)
		if structType, ok := targetType.(*CStructType); ok {
			methods := structType.FindMethods(fun.MemberName)
			if len(methods) == 0 {
				ec.GetReport().Errorf(1061, "'%v' not found in '%v'", fun.MemberName, structType.Name)
				return newOverload(CBasicTypeSignedInt, overloadNoEmit, nil)
			}
			scored := make([]scoredMethod, 0)
			for _, m := range methods {
				if mt, ok := m.GetMemberType().(*CFunctionType); ok {
					score := mt.ScoreParameterTypeMatches(argTypes)
					if score > 0 {
						scored = append(scored, scoredMethod{method: *m, score: score})
					}
				}
			}
			for i := 0; i < len(scored); i++ {
				for j := i + 1; j < len(scored); j++ {
					if scored[j].score > scored[i].score {
						scored[i], scored[j] = scored[j], scored[i]
					}
				}
			}
			var bestMatch *scoredMethod
			if len(scored) > 0 {
				bestMatch = &scored[0]
			}
			if bestMatch == nil {
				ec.GetReport().Error(1503, fmt.Sprintf("'%v' argument type mismatch", function))
				return newOverload(CBasicTypeSignedInt, overloadNoEmit, nil)
			}
			method := bestMatch.method
			if method.VTableSlotIndex != -1 && structType.VTableGlobalAddress != nil {
				functionType := method.GetMemberType().(*CFunctionType)
				return newOverload(
					functionType,
					func(nec *EmitContext) {
						fun.Left.EmitPointer(nec)
					},
					new(method.VTableSlotIndex),
				)
			} else {
				res := ec.ResolveMethodFunction(structType, &method)
				if res != nil {
					var ftype CType
					if res.Function != nil {
						ftype = res.Function.GetFunctionType()
					}
					addr := res.Address
					return newOverload(
						ftype,
						func(nec *EmitContext) {
							fun.Left.EmitPointer(nec)
							nec.Emit(OpCodeLoadConstant, ValuePointer(addr))
						},
						nil,
					)
				} else {
					return newOverload(CBasicTypeSignedInt, overloadNoEmit, nil)
				}
			}
		} else {
			ec.GetReport().Error(119, fmt.Sprintf("'%v' is not valid in the given context", fun.Left))
			return newOverload(CBasicTypeSignedInt, overloadNoEmit, nil)
		}

	case *MemberFromPointerExpression:
		targetType := fun.Left.GetEvaluatedCType(ec)
		if pType, ok := targetType.(*CPointerType); ok {
			if structType, ok := pType.InnerType.(*CStructType); ok {
				methods := structType.FindMethods(fun.MemberName)
				if len(methods) == 0 {
					ec.GetReport().Errorf(1061, "'%v' not found in '%v'", fun.MemberName, structType.Name)
					return newOverload(CBasicTypeSignedInt, overloadNoEmit, nil)
				}
				scored := make([]scoredMethod, 0)
				for _, m := range methods {
					if mt, ok := m.GetMemberType().(*CFunctionType); ok {
						score := mt.ScoreParameterTypeMatches(argTypes)
						if score > 0 {
							scored = append(scored, scoredMethod{method: *m, score: score})
						}
					}
				}
				for i := 0; i < len(scored); i++ {
					for j := i + 1; j < len(scored); j++ {
						if scored[j].score > scored[i].score {
							scored[i], scored[j] = scored[j], scored[i]
						}
					}
				}
				var bestMatch *scoredMethod
				if len(scored) > 0 {
					bestMatch = &scored[0]
				}
				if bestMatch == nil {
					ec.GetReport().Error(1503, fmt.Sprintf("'%v' argument type mismatch", function))
					return newOverload(CBasicTypeSignedInt, overloadNoEmit, nil)
				}
				method := bestMatch.method
				if method.VTableSlotIndex != -1 && structType.VTableGlobalAddress != nil {
					functionType := method.GetMemberType().(*CFunctionType)
					return newOverload(
						functionType,
						func(nec *EmitContext) {
							fun.Left.Emit(nec)
						},
						new(method.VTableSlotIndex),
					)
				} else {
					res := ec.ResolveMethodFunction(structType, &method)
					if res != nil {
						var ftype CType
						if res.Function != nil {
							ftype = res.Function.GetFunctionType()
						}
						addr := res.Address
						return newOverload(
							ftype,
							func(nec *EmitContext) {
								fun.Left.Emit(nec)
								nec.Emit(OpCodeLoadConstant, ValuePointer(addr))
							},
							nil,
						)
					} else {
						return newOverload(CBasicTypeSignedInt, overloadNoEmit, nil)
					}
				}
			}
		}
		ec.GetReport().Error(119, fmt.Sprintf("'%v' is not valid for -> operator", fun.Left))
		return newOverload(CBasicTypeSignedInt, overloadNoEmit, nil)

	case *ScopeResolutionExpression:
		res := ec.TryResolveQualifiedFunction(fun.TypeName, fun.MemberName, argTypes)
		if res != nil {
			if _, ok := res.VariableType.(*CFunctionType); ok {
				return newOverload(res.VariableType, res.Emit, nil)
			} else {
				return newOverload(res.VariableType, res.EmitPointer, nil)
			}
		} else {
			ec.GetReport().Error(103, fmt.Sprintf("'%v' not found", function))
			return newOverload(CBasicTypeSignedInt, overloadNoEmit, nil)
		}

	case *VariableExpression:
		res := ec.ResolveVariable(fun, argTypes)
		if res != nil {
			if _, ok := res.VariableType.(*CFunctionType); ok {
				return newOverload(res.VariableType, res.Emit, nil)
			} else {
				return newOverload(res.VariableType, res.EmitPointer, nil)
			}
		} else {
			return newOverload(CBasicTypeSignedInt, overloadNoEmit, nil)
		}

	default:
		var funType CType
		if function != nil {
			funType = function.GetEvaluatedCType(ec)
		}
		return newOverload(
			funType,
			func(nec *EmitContext) {
				if function != nil {
					function.Emit(nec)
				}
			},
			nil,
		)
	}
}

func (e *FuncallExpression) GetEvaluatedCType(ec *EmitContext) CType {
	argTypes := make([]CType, len(e.Arguments))
	for i, a := range e.Arguments {
		argTypes[i] = a.GetEvaluatedCType(ec)
	}
	ol := e.resolveOverload(e.Function, argTypes, ec)
	if ft, ok := ol.cType.(*CFunctionType); ok {
		return ft.ReturnType
	}
	return CBasicTypeSignedInt
}

func (e *FuncallExpression) Emit(ec *EmitContext) {
	argTypes := make([]CType, len(e.Arguments))
	for i, a := range e.Arguments {
		argTypes[i] = a.GetEvaluatedCType(ec)
	}
	ol := e.resolveOverload(e.Function, argTypes, ec)

	typ, ok := ol.cType.(*CFunctionType)
	if !ok {
		ec.GetReport().Errorf(2064, "'%v' does not evaluate to a function taking %d arguments", e.Function, len(e.Arguments))
		return
	}

	numRequiredParameters := 0
	for _, p := range typ.Parameters() {
		if p.DefaultValue != nil {
			break
		}
		numRequiredParameters++
	}
	if len(e.Arguments) < numRequiredParameters {
		ec.GetReport().Errorf(1501, "'%v' takes %d arguments, %d provided", e.Function, numRequiredParameters, len(e.Arguments))
		return
	}

	for i := range e.Arguments {
		paramType := typ.Parameters()[i].ParameterType
		if refType, ok := paramType.(*CReferenceType); ok {
			if e.Arguments[i].CanEmitPointer() {
				e.Arguments[i].EmitPointer(ec)
			} else {
				tempOffset := ec.AllocateTemp(refType.InnerType)
				e.Arguments[i].Emit(ec)
				ec.EmitCast(argTypes[i], refType.InnerType)
				ec.Emit(OpCodeStoreLocal, ValueOf(tempOffset))
				ec.Emit(OpCodeLoadConstant, ValuePointer(tempOffset))
				ec.Emit(OpCodeLoadFramePointer, ValueOf(0))
				ec.Emit(OpCodeOffsetPointer, ValueOf(0))
			}
		} else {
			e.Arguments[i].Emit(ec)
			ec.EmitCast(argTypes[i], paramType)
		}
	}
	for i := len(e.Arguments); i < len(typ.Parameters()); i++ {
		var v Value
		if typ.Parameters()[i].DefaultValue != nil {
			v = *typ.Parameters()[i].DefaultValue
		}
		ec.Emit(OpCodeLoadConstant, v)
	}

	ol.emit(ec)

	if ol.vTableSlotIndex != nil {
		ec.Emit(OpCodeCallVirtual, ValueOf(*ol.vTableSlotIndex))
	} else {
		ec.Emit(OpCodeCall, ValueOf(len(typ.Parameters())))
	}

	if typ.ReturnType.IsVoid() {
		ec.Emit(OpCodeLoadConstant, ValueOf(0))
	}
}

func (e *FuncallExpression) EmitPointer(ec *EmitContext)        { defaultEmitPointer(ec) }
func (e *FuncallExpression) CanEmitPointer() bool               { return false }
func (e *FuncallExpression) EvalConstant(ec *EmitContext) Value { return defaultEvalConstant(e, ec) }

func (e *FuncallExpression) String() string {
	var sb strings.Builder
	sb.WriteString(e.Function.String())
	sb.WriteString("(")
	for i, a := range e.Arguments {
		if i > 0 {
			sb.WriteString(", ")
		}
		sb.WriteString(a.String())
	}
	sb.WriteString(")")
	return sb.String()
}

// ── CastExpression ──────────────────────────────────────────────────────────

type CastExpression struct {
	ExpressionBase
	TypeName        *TypeName
	InnerExpression Expression
}

func NewCastExpression(typeName *TypeName, innerExpression Expression) *CastExpression {
	return &CastExpression{TypeName: typeName, InnerExpression: innerExpression}
}

func (e *CastExpression) GetEvaluatedCType(ec *EmitContext) CType {
	t := ec.ResolveTypeNameFromTypeName(e.TypeName)
	if t == nil {
		return CBasicTypeSignedInt
	}
	return t
}

func (e *CastExpression) Emit(ec *EmitContext) {
	rtype := e.GetEvaluatedCType(ec)
	itype := e.InnerExpression.GetEvaluatedCType(ec)
	e.InnerExpression.Emit(ec)
	ec.EmitCast(itype, rtype)
}

func (e *CastExpression) EvalConstant(ec *EmitContext) Value {
	return e.InnerExpression.EvalConstant(ec)
}
func (e *CastExpression) EmitPointer(ec *EmitContext) { defaultEmitPointer(ec) }
func (e *CastExpression) CanEmitPointer() bool        { return false }
func (e *CastExpression) String() string              { return fmt.Sprintf("(%v)%v", e.TypeName, e.InnerExpression) }

// ── ArrayElementExpression ──────────────────────────────────────────────────

type ArrayElementExpression struct {
	ExpressionBase
	Array        Expression
	ElementIndex Expression
}

func NewArrayElementExpression(array Expression, elementIndex Expression) *ArrayElementExpression {
	return &ArrayElementExpression{Array: array, ElementIndex: elementIndex}
}

func (e *ArrayElementExpression) GetEvaluatedCType(ec *EmitContext) CType {
	t := e.Array.GetEvaluatedCType(ec)
	switch typ := t.(type) {
	case *CArrayType:
		return typ.ElementType
	case *CPointerType:
		return typ.InnerType
	case *CStructType:
		indexType := e.ElementIndex.GetEvaluatedCType(ec)
		ft := TryResolveBinaryOperatorType(ec, t, indexType, "operator[]")
		if ft != nil {
			return ft.ReturnType
		}
		ec.GetReport().Error(601, "Left hand side of [ must be an array or pointer")
		return CTypeVoid
	default:
		ec.GetReport().Error(601, "Left hand side of [ must be an array or pointer")
		return CTypeVoid
	}
}

func (e *ArrayElementExpression) Emit(ec *EmitContext) {
	t := e.Array.GetEvaluatedCType(ec)
	if _, ok := t.(*CStructType); ok {
		indexType := e.ElementIndex.GetEvaluatedCType(ec)
		if TryEmitBinaryOperatorCall(ec, t, indexType, e.Array, e.ElementIndex, "operator[]") {
			return
		}
		ec.GetReport().Error(601, "Left hand side of [ must be an array or pointer")
		return
	}
	e.doEmitPointer(ec)
	if _, ok := e.GetEvaluatedCType(ec).(*CArrayType); ok {
	} else {
		ec.Emit(OpCodeLoadPointer, ValueOf(0))
	}
}

func (e *ArrayElementExpression) CanEmitPointer() bool { return e.Array.CanEmitPointer() }

func (e *ArrayElementExpression) EmitPointer(ec *EmitContext) {
	e.doEmitPointer(ec)
}

func (e *ArrayElementExpression) doEmitPointer(ec *EmitContext) {
	e.Array.Emit(ec)
	e.ElementIndex.Emit(ec)
	ec.Emit(OpCodeLoadConstant, ValueOf(int64(e.GetEvaluatedCType(ec).NumValues())))
	ec.Emit(OpCodeMultiplyInt32, ValueOf(0))
	ec.Emit(OpCodeOffsetPointer, ValueOf(0))
}

func (e *ArrayElementExpression) EvalConstant(ec *EmitContext) Value {
	return defaultEvalConstant(e, ec)
}

func (e *ArrayElementExpression) String() string {
	return fmt.Sprintf("%v[%v]", e.Array, e.ElementIndex)
}

// ── MemberFromReferenceExpression ───────────────────────────────────────────

type MemberFromReferenceExpression struct {
	ExpressionBase
	Left       Expression
	MemberName string
}

func NewMemberFromReferenceExpression(left Expression, memberName string) *MemberFromReferenceExpression {
	return &MemberFromReferenceExpression{Left: left, MemberName: memberName}
}

func findStructMember(structType *CStructType, name string) CStructMember {
	return structType.FindMember(name)
}

func (e *MemberFromReferenceExpression) GetEvaluatedCType(ec *EmitContext) CType {
	targetType := e.Left.GetEvaluatedCType(ec)
	if structType, ok := targetType.(*CStructType); ok {
		member := findStructMember(structType, e.MemberName)
		if member == nil {
			ec.GetReport().Errorf(1061, "'%v' not found in '%v'", e.MemberName, structType.Name)
			return CBasicTypeSignedInt
		}
		return member.GetMemberType()
	}
	panic(fmt.Sprintf("Member type on %T", targetType))
}

func (e *MemberFromReferenceExpression) CanEmitPointer() bool { return true }

func (e *MemberFromReferenceExpression) Emit(ec *EmitContext) {
	targetType := e.Left.GetEvaluatedCType(ec)
	if structType, ok := targetType.(*CStructType); ok {
		member := findStructMember(structType, e.MemberName)
		if member == nil {
			ec.GetReport().Errorf(1061, "'%v' not found in '%v'", e.MemberName, structType.Name)
			return
		}

		if method, ok := member.(*CStructMethod); ok {
			if _, ok := member.GetMemberType().(*CFunctionType); ok {
				if method.VTableSlotIndex != -1 && structType.VTableGlobalAddress != nil {
					e.Left.EmitPointer(ec)
					ec.Emit(OpCodeDup, ValueOf(0))
					ec.Emit(OpCodeLoadPointer, ValueOf(0))
					ec.Emit(OpCodeLoadConstant, ValuePointer(method.VTableSlotIndex))
					ec.Emit(OpCodeOffsetPointer, ValueOf(0))
					ec.Emit(OpCodeLoadPointer, ValueOf(0))
				} else {
					res := ec.ResolveMethodFunction(structType, method)
					if res != nil {
						e.Left.EmitPointer(ec)
						ec.Emit(OpCodeLoadConstant, ValuePointer(res.Address))
					}
				}
				return
			}
		}

		if e.Left.CanEmitPointer() {
			e.Left.EmitPointer(ec)
		} else {
			tempOffset := ec.AllocateTemp(structType)
			e.Left.Emit(ec)
			numValues := structType.NumValues()
			for i := numValues - 1; i >= 0; i-- {
				ec.Emit(OpCodeStoreLocal, ValueOf(tempOffset+i))
			}
			ec.Emit(OpCodeLoadConstant, ValuePointer(tempOffset))
			ec.Emit(OpCodeLoadFramePointer, ValueOf(0))
			ec.Emit(OpCodeOffsetPointer, ValueOf(0))
		}
		ec.Emit(OpCodeLoadConstant, ValuePointer(structType.GetFieldValueOffset(member, ec)))
		ec.Emit(OpCodeOffsetPointer, ValueOf(0))
		ec.Emit(OpCodeLoadPointer, ValueOf(0))
		return
	}
	panic(fmt.Sprintf("Cannot read '%s' on %T", e.MemberName, targetType))
}

func (e *MemberFromReferenceExpression) EmitPointer(ec *EmitContext) {
	targetType := e.Left.GetEvaluatedCType(ec)
	if structType, ok := targetType.(*CStructType); ok {
		member := findStructMember(structType, e.MemberName)
		if member == nil {
			ec.GetReport().Errorf(1061, "'%v' not found in '%v'", e.MemberName, structType.Name)
			return
		}
		if method, ok := member.(*CStructMethod); ok {
			_ = method
			ec.GetReport().Errorf(1656, "Cannot assign to '%v'", e.MemberName)
			return
		}
		e.Left.EmitPointer(ec)
		ec.Emit(OpCodeLoadConstant, ValuePointer(structType.GetFieldValueOffset(member, ec)))
		ec.Emit(OpCodeOffsetPointer, ValueOf(0))
		return
	}
	panic(fmt.Sprintf("Cannot write '%s' on %T", e.MemberName, targetType))
}

func (e *MemberFromReferenceExpression) String() string {
	return fmt.Sprintf("%v.%s", e.Left, e.MemberName)
}

func (e *MemberFromReferenceExpression) EvalConstant(ec *EmitContext) Value {
	return defaultEvalConstant(e, ec)
}

// ── MemberFromPointerExpression ─────────────────────────────────────────────

type MemberFromPointerExpression struct {
	ExpressionBase
	Left       Expression
	MemberName string
}

func NewMemberFromPointerExpression(left Expression, memberName string) *MemberFromPointerExpression {
	return &MemberFromPointerExpression{Left: left, MemberName: memberName}
}

func (e *MemberFromPointerExpression) GetEvaluatedCType(ec *EmitContext) CType {
	targetType := e.Left.GetEvaluatedCType(ec)
	pType, ok := targetType.(*CPointerType)
	if ok && pType != nil {
		if structType, ok := pType.InnerType.(*CStructType); ok {
			member := findStructMember(structType, e.MemberName)
			if member == nil {
				ec.GetReport().Errorf(1061, "'%v' not found in '%v'", e.MemberName, structType.Name)
				return CBasicTypeSignedInt
			}
			return member.GetMemberType()
		}
		ec.GetReport().Errorf(1061, "'%v' not found in '%v'", pType, e.MemberName)
		return CBasicTypeSignedInt
	}
	ec.GetReport().Errorf(1061, "-> cannot be used with '%v'", targetType)
	return CBasicTypeSignedInt
}

func (e *MemberFromPointerExpression) CanEmitPointer() bool { return true }

func (e *MemberFromPointerExpression) Emit(ec *EmitContext) {
	targetType := e.Left.GetEvaluatedCType(ec)
	if pType, ok := targetType.(*CPointerType); ok {
		if structType, ok := pType.InnerType.(*CStructType); ok {
			member := findStructMember(structType, e.MemberName)
			if member == nil {
				ec.GetReport().Errorf(1061, "'%v' not found in '%v'", e.MemberName, structType.Name)
				return
			}

			if method, ok := member.(*CStructMethod); ok {
				if _, ok := member.GetMemberType().(*CFunctionType); ok {
					if method.VTableSlotIndex != -1 && structType.VTableGlobalAddress != nil {
						e.Left.Emit(ec)
						ec.Emit(OpCodeDup, ValueOf(0))
						ec.Emit(OpCodeLoadPointer, ValueOf(0))
						ec.Emit(OpCodeLoadConstant, ValuePointer(method.VTableSlotIndex))
						ec.Emit(OpCodeOffsetPointer, ValueOf(0))
						ec.Emit(OpCodeLoadPointer, ValueOf(0))
					} else {
						res := ec.ResolveMethodFunction(structType, method)
						if res != nil {
							e.Left.Emit(ec)
							ec.Emit(OpCodeLoadConstant, ValuePointer(res.Address))
						}
					}
					return
				}
			}

			e.Left.Emit(ec)
			ec.Emit(OpCodeLoadConstant, ValuePointer(structType.GetFieldValueOffset(member, ec)))
			ec.Emit(OpCodeOffsetPointer, ValueOf(0))
			ec.Emit(OpCodeLoadPointer, ValueOf(0))
			return
		}
	}
	panic(fmt.Sprintf("Cannot read '%s' on %T", e.MemberName, targetType))
}

func (e *MemberFromPointerExpression) EmitPointer(ec *EmitContext) {
	targetType := e.Left.GetEvaluatedCType(ec)
	if pType, ok := targetType.(*CPointerType); ok {
		if structType, ok := pType.InnerType.(*CStructType); ok {
			member := findStructMember(structType, e.MemberName)
			if member == nil {
				ec.GetReport().Errorf(1061, "'%v' not found in '%v'", e.MemberName, structType.Name)
				return
			}
			if method, ok := member.(*CStructMethod); ok {
				_ = method
				ec.GetReport().Errorf(1656, "Cannot assign to '%v'", e.MemberName)
				return
			}
			e.Left.Emit(ec)
			ec.Emit(OpCodeLoadConstant, ValuePointer(structType.GetFieldValueOffset(member, ec)))
			ec.Emit(OpCodeOffsetPointer, ValueOf(0))
			return
		}
	}
	panic(fmt.Sprintf("Cannot write '%s' on %T", e.MemberName, targetType))
}

func (e *MemberFromPointerExpression) String() string {
	return fmt.Sprintf("(*%v).%s", e.Left, e.MemberName)
}

func (e *MemberFromPointerExpression) EvalConstant(ec *EmitContext) Value {
	return defaultEvalConstant(e, ec)
}

// ── AddressOfExpression ─────────────────────────────────────────────────────

type AddressOfExpression struct {
	ExpressionBase
	InnerExpression Expression
}

func NewAddressOfExpression(innerExpression Expression) *AddressOfExpression {
	return &AddressOfExpression{InnerExpression: innerExpression}
}

func (e *AddressOfExpression) GetEvaluatedCType(ec *EmitContext) CType {
	return e.InnerExpression.GetEvaluatedCType(ec).Pointer()
}

func (e *AddressOfExpression) Emit(ec *EmitContext) {
	e.InnerExpression.EmitPointer(ec)
}

func (e *AddressOfExpression) EvalConstant(ec *EmitContext) Value {
	return defaultEvalConstant(e, ec)
}
func (e *AddressOfExpression) EmitPointer(ec *EmitContext) { defaultEmitPointer(ec) }
func (e *AddressOfExpression) CanEmitPointer() bool        { return false }
func (e *AddressOfExpression) String() string              { return fmt.Sprintf("&%v", e.InnerExpression) }

// ── DereferenceExpression ───────────────────────────────────────────────────

type DereferenceExpression struct {
	ExpressionBase
	InnerExpression Expression
}

func NewDereferenceExpression(innerExpression Expression) *DereferenceExpression {
	return &DereferenceExpression{InnerExpression: innerExpression}
}

func (e *DereferenceExpression) GetEvaluatedCType(ec *EmitContext) CType {
	it := e.InnerExpression.GetEvaluatedCType(ec)
	if pointerType, ok := it.(*CPointerType); ok {
		return pointerType.InnerType
	}
	ec.GetReport().Error(0, fmt.Sprintf("Cannot dereference values of type `%v`.", it))
	return CBasicTypeSignedInt
}

func (e *DereferenceExpression) Emit(ec *EmitContext) {
	e.InnerExpression.Emit(ec)
	ec.Emit(OpCodeLoadPointer, ValueOf(0))
}

func (e *DereferenceExpression) CanEmitPointer() bool { return true }

func (e *DereferenceExpression) EmitPointer(ec *EmitContext) {
	e.InnerExpression.Emit(ec)
}

func (e *DereferenceExpression) EvalConstant(ec *EmitContext) Value {
	return defaultEvalConstant(e, ec)
}

func (e *DereferenceExpression) String() string { return fmt.Sprintf("*%v", e.InnerExpression) }

// ── SizeOfExpression ────────────────────────────────────────────────────────

type SizeOfExpression struct {
	ExpressionBase
	Query Expression
}

func NewSizeOfExpression(query Expression) *SizeOfExpression {
	return &SizeOfExpression{Query: query}
}

//goland:noinspection GoUnusedParameter
func (e *SizeOfExpression) GetEvaluatedCType(ec *EmitContext) CType {
	return CBasicTypeUnsignedLongInt
}

func (e *SizeOfExpression) Emit(ec *EmitContext) {
	typ := e.Query.GetEvaluatedCType(ec)
	cval := ValueOf(int64(typ.NumValues()))
	ec.Emit(OpCodeLoadConstant, cval)
}

func (e *SizeOfExpression) EvalConstant(ec *EmitContext) Value {
	typ := e.Query.GetEvaluatedCType(ec)
	return ValueOf(int64(typ.NumValues()))
}
func (e *SizeOfExpression) EmitPointer(ec *EmitContext) { defaultEmitPointer(ec) }
func (e *SizeOfExpression) CanEmitPointer() bool        { return false }
func (e *SizeOfExpression) String() string              { return fmt.Sprintf("sizeof(%v)", e.Query) }

// ── SizeOfTypeExpression ────────────────────────────────────────────────────

type SizeOfTypeExpression struct {
	ExpressionBase
	TypeName *TypeName
}

func NewSizeOfTypeExpression(typeName *TypeName) *SizeOfTypeExpression {
	return &SizeOfTypeExpression{TypeName: typeName}
}

//goland:noinspection GoUnusedParameter
func (e *SizeOfTypeExpression) GetEvaluatedCType(ec *EmitContext) CType {
	return CBasicTypeUnsignedLongInt
}

func (e *SizeOfTypeExpression) Emit(ec *EmitContext) {
	typ := ec.ResolveTypeNameFromTypeName(e.TypeName)
	cval := ValueOf(int64(typ.NumValues()))
	ec.Emit(OpCodeLoadConstant, cval)
}

func (e *SizeOfTypeExpression) EvalConstant(ec *EmitContext) Value {
	typ := ec.ResolveTypeNameFromTypeName(e.TypeName)
	return ValueOf(int64(typ.NumValues()))
}
func (e *SizeOfTypeExpression) EmitPointer(ec *EmitContext) { defaultEmitPointer(ec) }
func (e *SizeOfTypeExpression) CanEmitPointer() bool        { return false }
func (e *SizeOfTypeExpression) String() string              { return fmt.Sprintf("sizeof(%v)", e.TypeName) }

// ── AssignExpression ────────────────────────────────────────────────────────

type AssignExpression struct {
	ExpressionBase
	Left  Expression
	Right Expression
}

func NewAssignExpression(left, right Expression) *AssignExpression {
	return &AssignExpression{Left: left, Right: right}
}

func (e *AssignExpression) GetEvaluatedCType(ec *EmitContext) CType {
	return e.Left.GetEvaluatedCType(ec)
}

func (e *AssignExpression) emitArrayStructuredInit(sexpr *StructureExpression, arrayType *CArrayType, baseOffset int, ec *EmitContext) {
	elementType := arrayType.ElementType
	numItemValues := elementType.NumValues()

	for i, item := range sexpr.Items {
		itemOffset := baseOffset + i*numItemValues

		if subExpr, ok := item.Expression.(*StructureExpression); ok {
			if subArrayType, ok := elementType.(*CArrayType); ok {
				e.emitArrayStructuredInit(subExpr, subArrayType, itemOffset, ec)
				continue
			}
		}

		item.Expression.Emit(ec)
		ec.EmitCast(item.Expression.GetEvaluatedCType(ec), elementType)
		e.Left.EmitPointer(ec)
		ec.Emit(OpCodeLoadConstant, ValueOf(int64(itemOffset)))
		ec.Emit(OpCodeOffsetPointer, ValueOf(0))
		ec.Emit(OpCodeStorePointer, ValueOf(0))
	}
}

func (e *AssignExpression) doEmitStructureAssignment(sexpr *StructureExpression, ec *EmitContext) {
	typ := e.GetEvaluatedCType(ec)
	if arrayType, ok := typ.(*CArrayType); ok {
		e.emitArrayStructuredInit(sexpr, arrayType, 0, ec)
		e.Left.EmitPointer(ec)
	} else {
		panic(fmt.Sprintf("Structured assignment of '%v' not supported", e.GetEvaluatedCType(ec)))
	}
}

func (e *AssignExpression) Emit(ec *EmitContext) {
	if sexpr, ok := e.Right.(*StructureExpression); ok {
		e.doEmitStructureAssignment(sexpr, ec)
		return
	}

	e.Right.Emit(ec)

	if variable, ok := e.Left.(*VariableExpression); ok {
		v := ec.ResolveVariable(variable, nil)

		if refType, ok := v.VariableType.(*CReferenceType); ok {
			ec.EmitCast(e.Right.GetEvaluatedCType(ec), refType.InnerType)
			ec.Emit(OpCodeDup, ValueOf(0))
			EmitLoadReferenceSlot(ec, v)
			ec.Emit(OpCodeStorePointer, ValueOf(0))
			return
		}

		if structType, ok := v.VariableType.(*CStructType); ok {
			ec.EmitCast(e.Right.GetEvaluatedCType(ec), e.Left.GetEvaluatedCType(ec))
			numValues := structType.NumValues()
			for i := numValues - 1; i >= 0; i-- {
				switch v.Scope {
				case VariableScopeGlobal:
					ec.Emit(OpCodeStoreGlobal, ValueOf(v.Address+i))
				case VariableScopeLocal:
					ec.Emit(OpCodeStoreLocal, ValueOf(v.Address+i))
				case VariableScopeArg:
					ec.Emit(OpCodeStoreArg, ValueOf(v.Address+i))
				default:
					panic(fmt.Sprintf("Assigning struct to scope '%v'", v.Scope))
				}
			}
			for i := 0; i < numValues; i++ {
				switch v.Scope {
				case VariableScopeGlobal:
					ec.Emit(OpCodeLoadGlobal, ValueOf(v.Address+i))
				case VariableScopeLocal:
					ec.Emit(OpCodeLoadLocal, ValueOf(v.Address+i))
				case VariableScopeArg:
					ec.Emit(OpCodeLoadArg, ValueOf(v.Address+i))
				default:
					panic(fmt.Sprintf("Loading struct from scope '%v'", v.Scope))
				}
			}
			return
		}

		ec.EmitCast(e.Right.GetEvaluatedCType(ec), e.Left.GetEvaluatedCType(ec))
		ec.Emit(OpCodeDup, ValueOf(0))

		switch v.Scope {
		case VariableScopeGlobal:
			ec.Emit(OpCodeStoreGlobal, ValueOf(v.Address))
		case VariableScopeLocal:
			ec.Emit(OpCodeStoreLocal, ValueOf(v.Address))
		case VariableScopeArg:
			ec.Emit(OpCodeStoreArg, ValueOf(v.Address))
		case VariableScopeFunction:
			ec.Emit(OpCodePop, ValueOf(0))
			ec.GetReport().Error(1656, fmt.Sprintf("Cannot assign to `%v` because it is a function", variable.VariableName))
		default:
			panic(fmt.Sprintf("Assigning to scope '%v'", v.Scope))
		}
		return
	}

	if e.Left.CanEmitPointer() {
		ec.EmitCast(e.Right.GetEvaluatedCType(ec), e.Left.GetEvaluatedCType(ec))
		ec.Emit(OpCodeDup, ValueOf(0))
		e.Left.EmitPointer(ec)
		ec.Emit(OpCodeStorePointer, ValueOf(0))
	} else {
		ec.GetReport().Error(131, "The left-hand side of an assignment must be a variable or an addressable memory location")
	}
}

func (e *AssignExpression) EmitPointer(ec *EmitContext) { defaultEmitPointer(ec) }
func (e *AssignExpression) CanEmitPointer() bool        { return false }
func (e *AssignExpression) EvalConstant(ec *EmitContext) Value {
	return defaultEvalConstant(e, ec)
}
func (e *AssignExpression) String() string { return fmt.Sprintf("%v = %v", e.Left, e.Right) }

// ── ConditionalExpression ───────────────────────────────────────────────────

type ConditionalExpression struct {
	ExpressionBase
	Condition  Expression
	TrueValue  Expression
	FalseValue Expression
}

func NewConditionalExpression(condition, trueValue, falseValue Expression) *ConditionalExpression {
	return &ConditionalExpression{Condition: condition, TrueValue: trueValue, FalseValue: falseValue}
}

func (e *ConditionalExpression) GetEvaluatedCType(ec *EmitContext) CType {
	return e.TrueValue.GetEvaluatedCType(ec)
}

func (e *ConditionalExpression) Emit(ec *EmitContext) {
	falseLabel := ec.DefineLabel()
	endLabel := ec.DefineLabel()

	e.Condition.Emit(ec)
	ec.EmitCastToBoolean(e.Condition.GetEvaluatedCType(ec))
	ec.EmitBranch(OpCodeBranchIfFalse, &falseLabel)

	e.TrueValue.Emit(ec)
	ec.EmitBranch(OpCodeJump, &endLabel)

	ec.self.EmitLabel(&falseLabel)
	e.FalseValue.Emit(ec)

	ec.self.EmitLabel(&endLabel)
}

func (e *ConditionalExpression) EmitPointer(ec *EmitContext) { defaultEmitPointer(ec) }
func (e *ConditionalExpression) CanEmitPointer() bool        { return false }
func (e *ConditionalExpression) String() string {
	return fmt.Sprintf("%v ? %v : %v", e.Condition, e.TrueValue, e.FalseValue)
}

// ── LogicExpression & LogicOp ───────────────────────────────────────────────

type LogicOp int

const (
	LogicOpAnd LogicOp = 0
	LogicOpOr  LogicOp = 1
)

type LogicExpression struct {
	ExpressionBase
	Left  Expression
	Op    LogicOp
	Right Expression
}

func NewLogicExpression(left Expression, op LogicOp, right Expression) *LogicExpression {
	return &LogicExpression{Left: left, Op: op, Right: right}
}

//goland:noinspection GoUnusedParameter
func (e *LogicExpression) GetEvaluatedCType(ec *EmitContext) CType {
	return CBasicTypeBool
}

func (e *LogicExpression) Emit(ec *EmitContext) {
	shortCircuitLabel := ec.DefineLabel()
	endLabel := ec.DefineLabel()

	e.Left.Emit(ec)
	ec.EmitCastToBoolean(e.Left.GetEvaluatedCType(ec))

	switch e.Op {
	case LogicOpAnd:
		ec.EmitBranch(OpCodeBranchIfFalse, &shortCircuitLabel)
	case LogicOpOr:
		ec.EmitBranch(OpCodeBranchIfTrue, &shortCircuitLabel)
	}

	e.Right.Emit(ec)
	ec.EmitCastToBoolean(e.Right.GetEvaluatedCType(ec))

	switch e.Op {
	case LogicOpAnd:
		ec.EmitBranch(OpCodeBranchIfFalse, &shortCircuitLabel)
	case LogicOpOr:
		ec.EmitBranch(OpCodeBranchIfTrue, &shortCircuitLabel)
	}

	switch e.Op {
	case LogicOpAnd:
		ec.Emit(OpCodeLoadConstant, ValueOf(int64(1)))
	case LogicOpOr:
		ec.Emit(OpCodeLoadConstant, ValueOf(int64(0)))
	}
	ec.EmitBranch(OpCodeJump, &endLabel)

	ec.self.EmitLabel(&shortCircuitLabel)
	switch e.Op {
	case LogicOpAnd:
		ec.Emit(OpCodeLoadConstant, ValueOf(int64(0)))
	case LogicOpOr:
		ec.Emit(OpCodeLoadConstant, ValueOf(int64(1)))
	}

	ec.self.EmitLabel(&endLabel)
}

func (e *LogicExpression) EmitPointer(ec *EmitContext) { defaultEmitPointer(ec) }
func (e *LogicExpression) CanEmitPointer() bool        { return false }
func (e *LogicExpression) String() string              { return fmt.Sprintf("(%v %v %v)", e.Left, e.Op, e.Right) }
func (e *LogicExpression) EvalConstant(ec *EmitContext) Value { return defaultEvalConstant(e, ec) }

// ── ParameterDeclaration & VarParameter ─────────────────────────────────────

type ParameterDeclaration struct {
	Name                  string
	DeclarationSpecifiers *DeclarationSpecifiers
	Declarator            Declarator
	DefaultValue          Expression
	CtorArgumentValue     Expression
}

func NewParameterDeclaration(name string) *ParameterDeclaration {
	return &ParameterDeclaration{Name: name}
}

func NewParameterDeclarationCtor(ctorArgumentValue Expression) *ParameterDeclaration {
	return &ParameterDeclaration{
		Name:              "",
		CtorArgumentValue: ctorArgumentValue,
	}
}

func NewParameterDeclarationSpecs(specs *DeclarationSpecifiers) *ParameterDeclaration {
	return &ParameterDeclaration{
		DeclarationSpecifiers: specs,
		Name:                  "",
	}
}

func NewParameterDeclarationFull(specs *DeclarationSpecifiers, dec Declarator) *ParameterDeclaration {
	return &ParameterDeclaration{
		DeclarationSpecifiers: specs,
		Name:                  dec.DeclaredIdentifier(),
		Declarator:            dec,
	}
}

func NewParameterDeclarationDefault(specs *DeclarationSpecifiers, dec Declarator, defaultValue Expression) *ParameterDeclaration {
	return &ParameterDeclaration{
		DeclarationSpecifiers: specs,
		Name:                  dec.DeclaredIdentifier(),
		Declarator:            dec,
		DefaultValue:          defaultValue,
	}
}

func (p *ParameterDeclaration) String() string {
	if p.DeclarationSpecifiers != nil {
		return fmt.Sprintf("%v %v", p.DeclarationSpecifiers, p.Declarator)
	}
	return ""
}

type VarParameter struct {
	ParameterDeclaration
}

func NewVarParameter() *VarParameter {
	return &VarParameter{
		ParameterDeclaration: *NewParameterDeclaration("..."),
	}
}

// ── Initializer ─────────────────────────────────────────────────────────────

type Initializer interface {
	GetDesignation() *InitializerDesignation
	SetDesignation(d *InitializerDesignation)
}

type InitializerBase struct {
	Designation *InitializerDesignation
}

func (b *InitializerBase) GetDesignation() *InitializerDesignation  { return b.Designation }
func (b *InitializerBase) SetDesignation(d *InitializerDesignation) { b.Designation = d }

type ExpressionInitializer struct {
	InitializerBase
	Expression Expression
}

func NewExpressionInitializer(expr Expression) *ExpressionInitializer {
	return &ExpressionInitializer{Expression: expr}
}

func (e *ExpressionInitializer) String() string { return e.Expression.String() }

type StructuredInitializer struct {
	InitializerBase
	Initializers []Initializer
}

func NewStructuredInitializer() *StructuredInitializer {
	return &StructuredInitializer{
		Initializers: make([]Initializer, 0),
	}
}

func (s *StructuredInitializer) Add(init Initializer) {
	s.Initializers = append(s.Initializers, init)
}

type InitializerDesignation struct {
	Designators []*InitializerDesignator
}

func NewInitializerDesignation(des []*InitializerDesignator) *InitializerDesignation {
	cp := make([]*InitializerDesignator, len(des))
	copy(cp, des)
	return &InitializerDesignation{Designators: cp}
}

type InitializerDesignator struct{}

func (d *InitializerDesignator) String() string { return "" }

// ── TypeSpecifier ───────────────────────────────────────────────────────────

type TypeSpecifierKind int

const (
	TypeSpecifierKindBuiltin  TypeSpecifierKind = 0
	TypeSpecifierKindTypename TypeSpecifierKind = 1
	TypeSpecifierKindStruct   TypeSpecifierKind = 2
	TypeSpecifierKindClass    TypeSpecifierKind = 3
	TypeSpecifierKindUnion    TypeSpecifierKind = 4
	TypeSpecifierKindEnum     TypeSpecifierKind = 5
)

type TypeSpecifier struct {
	Kind           TypeSpecifierKind
	Name           string
	Body           *Block
	BaseSpecifiers []*BaseSpecifier
}

func NewTypeSpecifier(kind TypeSpecifierKind, name string, body *Block) *TypeSpecifier {
	return &TypeSpecifier{Kind: kind, Name: name, Body: body}
}

func (t *TypeSpecifier) String() string { return t.Name }

// ── TypeName ────────────────────────────────────────────────────────────────

type TypeName struct {
	Specifiers *DeclarationSpecifiers
	Declarator Declarator
}

func NewTypeName(specifiers *DeclarationSpecifiers, declarator Declarator) *TypeName {
	return &TypeName{Specifiers: specifiers, Declarator: declarator}
}

func (t *TypeName) String() string {
	parts := make([]string, len(t.Specifiers.TypeSpecifiers))
	for i, ts := range t.Specifiers.TypeSpecifiers {
		parts[i] = ts.String()
	}
	return strings.Join(parts, ", ")
}

// ── EnumeratorStatement ─────────────────────────────────────────────────────

type EnumeratorStatement struct {
	StatementBase
	Name         string
	LiteralValue Expression
}

func NewEnumeratorStatement(left string, right Expression) *EnumeratorStatement {
	return &EnumeratorStatement{Name: left, LiteralValue: right}
}

func (s *EnumeratorStatement) AlwaysReturns() bool { return false }

//goland:noinspection GoUnusedParameter
func (s *EnumeratorStatement) AddDeclarationToBlock(ctx *BlockContext) {}

//goland:noinspection GoUnusedParameter
func (s *EnumeratorStatement) DoEmit(ec *EmitContext) {}
func (s *EnumeratorStatement) Emit(ec *EmitContext)   { s.DoEmit(ec) }
func (s *EnumeratorStatement) String() string {
	return fmt.Sprintf("%s = %v", s.Name, s.LiteralValue)
}

// ── MultiDeclaratorStatement ────────────────────────────────────────────────

type MultiDeclaratorStatement struct {
	StatementBase
	Specifiers      *DeclarationSpecifiers
	InitDeclarators []*InitDeclarator
}

func NewMultiDeclaratorStatement(specifiers *DeclarationSpecifiers, initDeclarators []*InitDeclarator) *MultiDeclaratorStatement {
	return &MultiDeclaratorStatement{
		Specifiers:      specifiers,
		InitDeclarators: initDeclarators,
	}
}

func (s *MultiDeclaratorStatement) AlwaysReturns() bool { return false }

func hasStronglyBoundPointer(d Declarator) bool {
	if d == nil {
		return false
	}
	if pd, ok := d.(*PointerDeclarator); ok && pd.StrongBinding {
		return true
	}
	return hasStronglyBoundPointer(d.GetInnerDeclarator())
}

func getCtorInitializerStatement(name string, ctorDeclType *CStructType, ctorDecl *FunctionDeclarator) *ExpressionStatement {
	varExpr := NewVariableExpression(name, NullLocation, NullLocation)
	memExpr := NewMemberFromReferenceExpression(varExpr, ctorDeclType.Name)
	args := make([]Expression, 0)
	for _, p := range ctorDecl.Parameters {
		if p.CtorArgumentValue != nil {
			args = append(args, p.CtorArgumentValue)
		}
	}
	callExpr := NewFuncallExpressionWithArgs(memExpr, args)
	return NewExpressionStatement(callExpr)
}

func getInitExpr(init Initializer) Expression {
	switch i := init.(type) {
	case *ExpressionInitializer:
		return i.Expression
	case *StructuredInitializer:
		sexpr := NewStructureExpression()
		for _, sub := range i.Initializers {
			e := getInitExpr(sub)
			_ = e
			var field string
			if sub.GetDesignation() != nil && len(sub.GetDesignation().Designators) > 0 {
				field = sub.GetDesignation().Designators[0].String()
			}
			item := NewStructureExpressionItem(field, getInitExpr(sub))
			sexpr.Items = append(sexpr.Items, item)
		}
		return sexpr
	default:
		panic(fmt.Sprintf("unsupported initializer: %v", init))
	}
}

func (s *MultiDeclaratorStatement) DoEmit(ec *EmitContext) {
	if s.InitDeclarators == nil {
		return
	}
	for _, idecl := range s.InitDeclarators {
		if (s.Specifiers.StorageClassSpecifier & StorageClassSpecifierTypedef) != 0 {
			continue
		}
		ctype := ec.MakeCType(s.Specifiers, idecl.Declarator, idecl.Initializer, nil)
		name := idecl.Declarator.DeclaredIdentifier()

		if ftype, ok := ctype.(*CFunctionType); ok && !hasStronglyBoundPointer(idecl.Declarator) {
			if ctorDeclType, ok := ftype.ReturnType.(*CStructType); ok && idecl.Initializer == nil {
				if ctorDecl, ok := idecl.Declarator.(*FunctionDeclarator); ok && ctorDecl.CouldBeCtorCall() {
					getCtorInitializerStatement(name, ctorDeclType, ctorDecl).Emit(ec)
				}
			}
		} else if idecl.Initializer != nil {
			varExpr := NewVariableExpression(name, NullLocation, NullLocation)
			initExpr := getInitExpr(idecl.Initializer)
			NewExpressionStatement(NewAssignExpression(varExpr, initExpr)).Emit(ec)
		}
	}
}

func (s *MultiDeclaratorStatement) Emit(ec *EmitContext) { s.DoEmit(ec) }

func (s *MultiDeclaratorStatement) String() string {
	parts := make([]string, len(s.Specifiers.TypeSpecifiers))
	for i, ts := range s.Specifiers.TypeSpecifiers {
		parts[i] = ts.String()
	}
	result := strings.Join(parts, " ")
	if s.InitDeclarators != nil {
		declParts := make([]string, len(s.InitDeclarators))
		for i, d := range s.InitDeclarators {
			declParts[i] = d.String()
		}
		result += " " + strings.Join(declParts, ", ")
	}
	return result
}

func hashEnumType(e *CEnumType) int {
	h := 0
	for _, m := range e.Members {
		h = h*31 + m.Value
	}
	return h
}

func (s *MultiDeclaratorStatement) AddDeclarationToBlock(ctx *BlockContext) {
	block := ctx.Block
	if s.InitDeclarators != nil {
	nextDecl:
		for _, idecl := range s.InitDeclarators {
			if (s.Specifiers.StorageClassSpecifier & StorageClassSpecifierTypedef) != 0 {
				name := idecl.Declarator.DeclaredIdentifier()
				ttype := ctx.MakeCType(s.Specifiers, idecl.Declarator, idecl.Initializer, block)
				block.Typedefs[name] = ttype
			} else {
				ctype := ctx.MakeCType(s.Specifiers, idecl.Declarator, idecl.Initializer, block)
				name := idecl.Declarator.DeclaredIdentifier()

				if ftype, ok := ctype.(*CFunctionType); ok && !hasStronglyBoundPointer(idecl.Declarator) {
					if ctorDeclType, ok := ftype.ReturnType.(*CStructType); ok && idecl.Initializer == nil {
						if ctorDecl, ok := idecl.Declarator.(*FunctionDeclarator); ok && ctorDecl.CouldBeCtorCall() {
							for _, v := range block.Variables {
								if v.Name == name {
									ctx.GetReport().Errorf(2086, "Redefinition of '%s'", name)
									continue nextDecl
								}
							}
							block.AddVariable(name, ctorDeclType)
							callStmt := getCtorInitializerStatement(name, ctorDeclType, ctorDecl)
							block.InitStatements = append(block.InitStatements, callStmt)
							continue nextDecl
						}
					}
					nameContext := ""
					if ndecl, ok := idecl.Declarator.GetInnerDeclarator().(*IdentifierDeclarator); ok && len(ndecl.Context) > 0 {
						nameContext = strings.Join(ndecl.Context, "::")
					}
					f := &CompiledFunction{Name: name, NameContext: nameContext, FunctionType: ftype, Body: nil}
					block.Functions = append(block.Functions, f)
				} else {
					if atype, ok := ctype.(*CArrayType); ok && atype.Length == nil && idecl.Initializer != nil {
						if structInit, ok := idecl.Initializer.(*StructuredInitializer); ok {
							length := 0
							for _, sub := range structInit.Initializers {
								if sub.GetDesignation() == nil {
									length++
								} else {
									for range sub.GetDesignation().Designators {
										length++
									}
								}
							}
							atype = &CArrayType{ElementType: atype.ElementType, Length: &length}
						}
					}
					for _, v := range block.Variables {
						if v.Name == name {
							ctx.GetReport().Errorf(2086, "Redefinition of '%s'", name)
							continue nextDecl
						}
					}
					if ctype == nil {
						ctype = CBasicTypeSignedInt
					}
					block.AddVariable(name, ctype)
				}

				if idecl.Initializer != nil {
					varExpr := NewVariableExpression(name, NullLocation, NullLocation)
					initExpr := getInitExpr(idecl.Initializer)
					block.InitStatements = append(block.InitStatements, NewExpressionStatement(NewAssignExpression(varExpr, initExpr)))
				}
			}
		}
	} else {
		ctype := ctx.MakeCType(s.Specifiers, nil, nil, block)
		if structType, ok := ctype.(*CStructType); ok {
			n := structType.Name
			if n != "" {
				block.Structures[n] = structType
			}
		} else if enumType, ok := ctype.(*CEnumType); ok {
			n := enumType.Name
			if n == "" {
				n = fmt.Sprintf("e%d", hashEnumType(enumType))
			}
			block.Enums[n] = enumType
		}
	}
}

// ── FunctionDefinition ──────────────────────────────────────────────────────

type FunctionDefinition struct {
	StatementBase
	Specifiers            *DeclarationSpecifiers
	Declarator            Declarator
	ParameterDeclarations []*Declaration
	Body                  *Block
}

func NewFunctionDefinition(specifiers *DeclarationSpecifiers, declarator Declarator, parameterDeclarations []*Declaration, body *Block) *FunctionDefinition {
	return &FunctionDefinition{
		Specifiers:            specifiers,
		Declarator:            declarator,
		ParameterDeclarations: parameterDeclarations,
		Body:                  body,
	}
}

func (f *FunctionDefinition) AlwaysReturns() bool { return false }

func (f *FunctionDefinition) AddDeclarationToBlock(ctx *BlockContext) {
	block := ctx.Block
	ftype := ctx.MakeCType(f.Specifiers, f.Declarator, nil, block)
	if ftype == nil {
		return
	}
	if ftypeCFunc, ok := ftype.(*CFunctionType); ok {
		name := f.Declarator.DeclaredIdentifier()
		nameContext := ""
		if ndecl, ok := f.Declarator.GetInnerDeclarator().(*IdentifierDeclarator); ok && len(ndecl.Context) > 0 {
			nameContext = strings.Join(ndecl.Context, "::")
		}
		cf := &CompiledFunction{Name: name, NameContext: nameContext, FunctionType: ftypeCFunc, Body: f.Body}
		block.Functions = append(block.Functions, cf)
	}
}

//goland:noinspection GoUnusedParameter
func (f *FunctionDefinition) DoEmit(ec *EmitContext) {
	// Emitted by the compiler
}

func (f *FunctionDefinition) Emit(ec *EmitContext) { f.DoEmit(ec) }
func (f *FunctionDefinition) String() string       { return f.Declarator.String() }

// ── Helper for isIntegral ───────────────────────────────────────────────────

func isIntegral(t CType) bool {
	if _, ok := t.(*CIntType); ok {
		return true
	}
	if _, ok := t.(*CBoolType); ok {
		return true
	}
	if _, ok := t.(*CEnumType); ok {
		return true
	}
	return false
}
