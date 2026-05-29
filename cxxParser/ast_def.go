package cxxParser

import (
	"fmt"
)

// ── VariableScope ────────────────────────────────────────────────────────────

type VariableScope int

const (
	VariableScopeLocal    VariableScope = 0
	VariableScopeGlobal   VariableScope = 1
	VariableScopeFunction VariableScope = 2
	VariableScopeArg      VariableScope = 3
	VariableScopeConstant VariableScope = 4
)

// ── Declarator interface ─────────────────────────────────────────────────────

type Declarator interface {
	DeclaredIdentifier() string
	GetInnerDeclarator() Declarator
	SetInnerDeclarator(d Declarator)
	GetStrongBinding() bool
	String() string
}

// ── Pointer ──────────────────────────────────────────────────────────────────

type Pointer struct {
	TypeQualifiers TypeQualifiers
	NextPointer    *Pointer
}

func NewPointer(qualifiers TypeQualifiers, next *Pointer) *Pointer {
	return &Pointer{TypeQualifiers: qualifiers, NextPointer: next}
}

// ── IdentifierDeclarator ─────────────────────────────────────────────────────

type IdentifierDeclarator struct {
	Name    string
	Context []string
}

func NewIdentifierDeclarator(name string) *IdentifierDeclarator {
	return &IdentifierDeclarator{Name: name}
}

func (d *IdentifierDeclarator) Push(name string) *IdentifierDeclarator {
	if d.Context == nil {
		d.Context = []string{d.Name}
	} else {
		d.Context = append(d.Context, d.Name)
	}
	d.Name = name
	return d
}

func (d *IdentifierDeclarator) DeclaredIdentifier() string { return d.Name }

func (d *IdentifierDeclarator) GetInnerDeclarator() Declarator { return nil }

func (d *IdentifierDeclarator) SetInnerDeclarator(Declarator) {}

func (d *IdentifierDeclarator) GetStrongBinding() bool { return false }

func (d *IdentifierDeclarator) String() string { return d.Name }

// ── PointerDeclarator ────────────────────────────────────────────────────────

type PointerDeclarator struct {
	Pointer         *Pointer
	InnerDeclarator Declarator
	StrongBinding   bool
}

func NewPointerDeclarator(ptr *Pointer, inner Declarator) *PointerDeclarator {
	return &PointerDeclarator{Pointer: ptr, InnerDeclarator: inner}
}

func (d *PointerDeclarator) DeclaredIdentifier() string {
	if d.InnerDeclarator != nil {
		return d.InnerDeclarator.DeclaredIdentifier()
	}
	return ""
}

func (d *PointerDeclarator) GetInnerDeclarator() Declarator { return d.InnerDeclarator }

func (d *PointerDeclarator) SetInnerDeclarator(inner Declarator) { d.InnerDeclarator = inner }

func (d *PointerDeclarator) GetStrongBinding() bool { return d.StrongBinding }

func (d *PointerDeclarator) String() string {
	if d.InnerDeclarator != nil {
		return "*" + d.InnerDeclarator.String()
	}
	return "*"
}

// ── ReferenceDeclarator ──────────────────────────────────────────────────────

type ReferenceDeclarator struct {
	InnerDeclarator Declarator
	Qualifiers      TypeQualifiers
}

func NewReferenceDeclarator(inner Declarator) *ReferenceDeclarator {
	return &ReferenceDeclarator{InnerDeclarator: inner}
}

func NewReferenceDeclaratorWithQualifiers(inner Declarator, q TypeQualifiers) *ReferenceDeclarator {
	return &ReferenceDeclarator{InnerDeclarator: inner, Qualifiers: q}
}

func (d *ReferenceDeclarator) DeclaredIdentifier() string {
	if d.InnerDeclarator != nil {
		return d.InnerDeclarator.DeclaredIdentifier()
	}
	return ""
}

func (d *ReferenceDeclarator) GetInnerDeclarator() Declarator { return d.InnerDeclarator }

func (d *ReferenceDeclarator) SetInnerDeclarator(inner Declarator) { d.InnerDeclarator = inner }

func (d *ReferenceDeclarator) GetStrongBinding() bool { return false }

func (d *ReferenceDeclarator) String() string {
	if d.InnerDeclarator != nil {
		return "&" + d.InnerDeclarator.String()
	}
	return "&"
}

// ── ArrayDeclarator ──────────────────────────────────────────────────────────

type ArrayDeclarator struct {
	InnerDeclarator  Declarator
	TypeQualifiers   TypeQualifiers
	LengthExpression Expression
	IsStatic         bool
}

func NewArrayDeclarator(inner Declarator, q TypeQualifiers, length Expression, isStatic bool) *ArrayDeclarator {
	return &ArrayDeclarator{
		InnerDeclarator:  inner,
		TypeQualifiers:   q,
		LengthExpression: length,
		IsStatic:         isStatic,
	}
}

func NewArrayDeclaratorSimple(inner Declarator, length Expression) *ArrayDeclarator {
	return &ArrayDeclarator{
		InnerDeclarator:  inner,
		LengthExpression: length,
	}
}

func (d *ArrayDeclarator) DeclaredIdentifier() string {
	if d.InnerDeclarator != nil {
		return d.InnerDeclarator.DeclaredIdentifier()
	}
	return ""
}

func (d *ArrayDeclarator) GetInnerDeclarator() Declarator { return d.InnerDeclarator }

func (d *ArrayDeclarator) SetInnerDeclarator(inner Declarator) { d.InnerDeclarator = inner }

func (d *ArrayDeclarator) GetStrongBinding() bool { return false }

func (d *ArrayDeclarator) String() string {
	if d.InnerDeclarator != nil {
		return d.InnerDeclarator.String() + "[]"
	}
	return "[]"
}

// ── FunctionDeclarator ───────────────────────────────────────────────────────

type FunctionDeclarator struct {
	InnerDeclarator Declarator
	Parameters      []*ParameterDeclaration
}

func NewFunctionDeclarator(inner Declarator, params []*ParameterDeclaration) *FunctionDeclarator {
	return &FunctionDeclarator{
		InnerDeclarator: inner,
		Parameters:      params,
	}
}

func (d *FunctionDeclarator) DeclaredIdentifier() string {
	if d.InnerDeclarator != nil {
		return d.InnerDeclarator.DeclaredIdentifier()
	}
	return ""
}

func (d *FunctionDeclarator) GetInnerDeclarator() Declarator { return d.InnerDeclarator }

func (d *FunctionDeclarator) SetInnerDeclarator(inner Declarator) { d.InnerDeclarator = inner }

func (d *FunctionDeclarator) GetStrongBinding() bool { return false }

func (d *FunctionDeclarator) String() string {
	if d.InnerDeclarator != nil {
		return d.InnerDeclarator.String() + "()"
	}
	return "()"
}

func (d *FunctionDeclarator) CouldBeCtorCall() bool {
	if d.InnerDeclarator != nil {
		id, ok := d.InnerDeclarator.(*IdentifierDeclarator)
		return ok && id.Name == d.DeclaredIdentifier()
	}
	return false
}

// ── MakeArrayDeclarator ──────────────────────────────────────────────────────

func MakeArrayDeclarator(inner Declarator, q TypeQualifiers, length Expression, isStatic bool) Declarator {
	return NewArrayDeclarator(inner, q, length, isStatic)
}

// ── Declaration interface ────────────────────────────────────────────────────

type Declaration interface {
	fmt.Stringer
}

// ExpressionStatement (needed before Block because Block may reference it)

type ExpressionStatement struct {
	StatementBase
	Expression Expression
}

func NewExpressionStatement(expr Expression) *ExpressionStatement {
	return &ExpressionStatement{Expression: expr}
}

func (s *ExpressionStatement) AlwaysReturns() bool { return false }

//goland:noinspection GoUnusedParameter
func (s *ExpressionStatement) AddDeclarationToBlock(ctx *BlockContext) {}

func (s *ExpressionStatement) DoEmit(ec *EmitContext) {
	if s.Expression != nil {
		s.Expression.Emit(ec)
		ec.Emit(OpCodePop, ValueOf(0))
	}
}

func (s *ExpressionStatement) Emit(ec *EmitContext) { s.DoEmit(ec) }

func (s *ExpressionStatement) String() string {
	if s.Expression != nil {
		return s.Expression.String()
	}
	return ""
}

// ── Block ────────────────────────────────────────────────────────────────────

type Block struct {
	StatementBase
	Statements     []Statement
	Variables      []*CompiledVariable
	Typedefs       map[string]CType
	Structures     map[string]*CStructType
	Functions      []*CompiledFunction
	Enums          map[string]*CEnumType
	InitStatements []Statement
	Scope          VariableScope
}

func NewBlock(scope VariableScope) *Block {
	return &Block{
		Scope:      scope,
		Typedefs:   make(map[string]CType),
		Structures: make(map[string]*CStructType),
		Enums:      make(map[string]*CEnumType),
	}
}

func NewBlockWithStatements(scope VariableScope, statements []Statement) *Block {
	b := NewBlock(scope)
	b.Statements = statements
	return b
}

func (b *Block) AddStatement(s Statement) {
	b.Statements = append(b.Statements, s)
}

func (b *Block) AddVariable(name string, ctype CType) {
	b.Variables = append(b.Variables, &CompiledVariable{Name: name, VariableType: ctype})
}

func (b *Block) AlwaysReturns() bool {
	for _, s := range b.Statements {
		if s.AlwaysReturns() {
			return true
		}
	}
	return false
}

func (b *Block) AddDeclarationToBlock(ctx *BlockContext) {
	subCtx := NewBlockContextFull(b, ctx.GetMachineInfo(), ctx.GetReport(), ctx.GetFunctionDecl(), ctx.EmitContext)
	for _, s := range b.Statements {
		s.AddDeclarationToBlock(subCtx)
	}
}

func (b *Block) DoEmit(ec *EmitContext) {
	/*
	   ec.BeginBlock (this);
	   // Emit vptr initialization for local polymorphic variables
	   foreach (var v in Variables) {
	       if (v.VariableType is CStructType st && st.IsPolymorphic && st.VTableGlobalAddress.HasValue) {
	           // StorePointer pops: [value, address] and stores value at address
	           ec.Emit (OpCode.LoadConstant, Value.Pointer (st.VTableGlobalAddress.Value)); // value = vtable addr
	           ec.Emit (OpCode.LoadFramePointer);                                           // FP
	           ec.Emit (OpCode.LoadConstant, Value.Pointer (v.StackOffset));                // local offset
	           ec.Emit (OpCode.OffsetPointer);                                              // FP + offset = &variable
	           ec.Emit (OpCode.StorePointer);                                               // store vtable addr at variable[0]
	       }
	   }
	   foreach (var s in Statements) {
	       s.Emit (ec);
	   }
	   ec.EndBlock ();
	*/
	ec.self.BeginBlock(b)
	// Emit vptr initialization for local polymorphic variables
	for _, v := range b.Variables {
		if st, ok := v.VariableType.(*CStructType); ok {
			if st.IsPolymorphic() && st.VTableGlobalAddress != nil {
				// StorePointer pops: [value, address] and stores value at address
				ec.Emit(OpCodeLoadConstant, ValuePointer(*st.VTableGlobalAddress)) // value = vtable addr
				ec.Emit(OpCodeLoadFramePointer, UnionValue(0))                     // FP
				ec.Emit(OpCodeLoadConstant, ValuePointer(v.StackOffset))           // local offset
				ec.Emit(OpCodeOffsetPointer, UnionValue(0))                        // FP + offset = &variable
				ec.Emit(OpCodeStorePointer, UnionValue(0))                         // store vtable addr at variable[0]
			}
		}
	}

	for _, s := range b.Statements {
		s.Emit(ec)
	}
	ec.self.EndBlock()
}

func (b *Block) Emit(ec *EmitContext) {
	b.DoEmit(ec)
}

func (b *Block) String() string { return "{...}" }

// ── TranslationUnit ──────────────────────────────────────────────────────────

type TranslationUnit struct {
	Block
	FilePath string
	Name     string
}

func NewTranslationUnit(filePath string) *TranslationUnit {
	return &TranslationUnit{
		Block:    *NewBlock(VariableScopeGlobal),
		FilePath: filePath,
		Name:     sanitizeName(filePath),
	}
}

func sanitizeName(filePath string) string {
	for i := len(filePath) - 1; i >= 0; i-- {
		if filePath[i] == '/' || filePath[i] == '\\' {
			filePath = filePath[i+1:]
			break
		}
	}
	for i := 0; i < len(filePath); i++ {
		c := filePath[i]
		if !((c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z') || (c >= '0' && c <= '9') || c == '_') {
			filePath = filePath[:i] + "_" + filePath[i+1:]
		}
	}
	return filePath
}

//goland:noinspection GoUnusedParameter
func (tu *TranslationUnit) AddDeclarationToBlock(ctx *BlockContext) {}

// ── ScopeResolutionExpression ────────────────────────────────────────────────

type ScopeResolutionExpression struct {
	ExpressionBase
	Left       string
	Right      string
	TypeName   string
	MemberName string
}

func NewScopeResolutionExpression(left, right string) *ScopeResolutionExpression {
	return &ScopeResolutionExpression{Left: left, Right: right, TypeName: left, MemberName: right}
}

func (e *ScopeResolutionExpression) GetEvaluatedCType(ec *EmitContext) CType {
	rv := ec.TryResolveQualifiedFunction(e.TypeName, e.MemberName, nil)
	if rv != nil {
		return rv.VariableType
	}
	return CBasicTypeSignedInt
}

func (e *ScopeResolutionExpression) Emit(ec *EmitContext) {
	rv := ec.TryResolveQualifiedFunction(e.TypeName, e.MemberName, nil)
	if rv != nil {
		rv.Emit(ec)
	} else {
		ec.GetReport().Error(103, fmt.Sprintf("'%s::%s' not found", e.TypeName, e.MemberName))
		ec.Emit(OpCodeLoadConstant, ValueOf(0))
	}
}

func (e *ScopeResolutionExpression) EmitPointer(ec *EmitContext) { defaultEmitPointer(ec) }

func (e *ScopeResolutionExpression) CanEmitPointer() bool { return false }

func (e *ScopeResolutionExpression) EvalConstant(ec *EmitContext) Value {
	return defaultEvalConstant(e, ec)
}

func (e *ScopeResolutionExpression) String() string {
	return fmt.Sprintf("%s::%s", e.Left, e.Right)
}

// ── RelationalOp & RelationalExpression ──────────────────────────────────────

type RelationalOp int

const (
	RelationalOpLessThan           RelationalOp = 0
	RelationalOpGreaterThan        RelationalOp = 1
	RelationalOpLessThanOrEqual    RelationalOp = 2
	RelationalOpGreaterThanOrEqual RelationalOp = 3
	RelationalOpEquals             RelationalOp = 4
	RelationalOpNotEquals          RelationalOp = 5
)

func (op RelationalOp) String() string {
	switch op {
	case RelationalOpEquals:
		return "Equals"
	case RelationalOpNotEquals:
		return "NotEquals"
	case RelationalOpLessThan:
		return "LessThan"
	case RelationalOpGreaterThan:
		return "GreaterThan"
	case RelationalOpLessThanOrEqual:
		return "LessThanOrEqual"
	case RelationalOpGreaterThanOrEqual:
		return "GreaterThanOrEqual"
	default:
		return "Unknown"
	}
}

type RelationalExpression struct {
	ExpressionBase
	Left  Expression
	Op    RelationalOp
	Right Expression
}

func NewRelationalExpression(left Expression, op RelationalOp, right Expression) *RelationalExpression {
	return &RelationalExpression{Left: left, Op: op, Right: right}
}

func (e *RelationalExpression) GetEvaluatedCType(ec *EmitContext) CType {
	leftType := e.Left.GetEvaluatedCType(ec)
	rightType := e.Right.GetEvaluatedCType(ec)
	if ft := TryResolveBinaryOperatorType(ec, leftType, rightType, e.Op.String()); ft != nil {
		return ft.ReturnType
	}
	return CBasicTypeBool
}

func (e *RelationalExpression) Emit(ec *EmitContext) {
	leftType := e.Left.GetEvaluatedCType(ec)
	rightType := e.Right.GetEvaluatedCType(ec)
	if TryEmitBinaryOperatorCall(ec, leftType, rightType, e.Left, e.Right, RelOpToOperatorName(e.Op)) {
		return
	}

	aType := GetArithmeticType(e.Left, e.Right, e.Op.String(), ec)

	e.Left.Emit(ec)
	ec.EmitCast(leftType, aType)
	e.Right.Emit(ec)
	ec.EmitCast(rightType, aType)

	ioff := ec.GetInstructionOffset(aType)
	// The comparison result is always CBasicTypeSignedInt, use its offset for Not opcodes
	relIoff := ec.GetInstructionOffset(CBasicTypeSignedInt)
	switch e.Op {
	case RelationalOpLessThan:
		ec.Emit(OpCodeLessThanInt8+OpCode(ioff), ValueOf(0))
	case RelationalOpGreaterThan:
		ec.Emit(OpCodeGreaterThanInt8+OpCode(ioff), ValueOf(0))
	case RelationalOpLessThanOrEqual:
		ec.Emit(OpCodeGreaterThanInt8+OpCode(ioff), ValueOf(0))
		ec.Emit(OpCodeNotInt8+OpCode(relIoff), ValueOf(0))
	case RelationalOpGreaterThanOrEqual:
		ec.Emit(OpCodeLessThanInt8+OpCode(ioff), ValueOf(0))
		ec.Emit(OpCodeNotInt8+OpCode(relIoff), ValueOf(0))
	case RelationalOpEquals:
		ec.Emit(OpCodeEqualToInt8+OpCode(ioff), ValueOf(0))
	case RelationalOpNotEquals:
		ec.Emit(OpCodeEqualToInt8+OpCode(ioff), ValueOf(0))
		ec.Emit(OpCodeNotInt8+OpCode(relIoff), ValueOf(0))
	}
}

func (e *RelationalExpression) EmitPointer(ec *EmitContext) { defaultEmitPointer(ec) }

func (e *RelationalExpression) CanEmitPointer() bool { return false }

func (e *RelationalExpression) EvalConstant(ec *EmitContext) Value {
	leftType := e.Left.GetEvaluatedCType(ec)
	rightType := e.Right.GetEvaluatedCType(ec)
	if isIntegral(leftType) && isIntegral(rightType) {
		left := e.Left.EvalConstant(ec).Int64Value
		right := e.Right.EvalConstant(ec).Int64Value
		switch e.Op {
		case RelationalOpLessThan:
			return boolToValue(left < right)
		case RelationalOpGreaterThan:
			return boolToValue(left > right)
		case RelationalOpLessThanOrEqual:
			return boolToValue(left <= right)
		case RelationalOpGreaterThanOrEqual:
			return boolToValue(left >= right)
		case RelationalOpEquals:
			return boolToValue(left == right)
		case RelationalOpNotEquals:
			return boolToValue(left != right)
		}
	}
	return defaultEvalConstant(e, ec)
}

func (e *RelationalExpression) String() string {
	opStr := ""
	switch e.Op {
	case RelationalOpLessThan:
		opStr = "<"
	case RelationalOpGreaterThan:
		opStr = ">"
	case RelationalOpLessThanOrEqual:
		opStr = "<="
	case RelationalOpGreaterThanOrEqual:
		opStr = ">="
	case RelationalOpEquals:
		opStr = "=="
	case RelationalOpNotEquals:
		opStr = "!="
	}
	return fmt.Sprintf("(%v %v %v)", e.Left, opStr, e.Right)
}

// ── SequenceExpression ───────────────────────────────────────────────────────

type SequenceExpression struct {
	ExpressionBase
	Left  Expression
	Right Expression
}

func NewSequenceExpression(left, right Expression) *SequenceExpression {
	return &SequenceExpression{Left: left, Right: right}
}

func (e *SequenceExpression) GetEvaluatedCType(ec *EmitContext) CType {
	return e.Right.GetEvaluatedCType(ec)
}

func (e *SequenceExpression) Emit(ec *EmitContext) {
	e.Left.Emit(ec)
	ec.Emit(OpCodePop, ValueOf(0))
	e.Right.Emit(ec)
}

func (e *SequenceExpression) EmitPointer(ec *EmitContext) { defaultEmitPointer(ec) }

func (e *SequenceExpression) CanEmitPointer() bool { return false }

func (e *SequenceExpression) EvalConstant(ec *EmitContext) Value {
	return e.Right.EvalConstant(ec)
}

func (e *SequenceExpression) String() string {
	return fmt.Sprintf("(%v, %v)", e.Left, e.Right)
}

// ── IfStatement ──────────────────────────────────────────────────────────────

type IfStatement struct {
	StatementBase
	Condition     Expression
	ThenStatement Statement
	ElseStatement Statement
}

func NewIfStatement(cond Expression, thenStmt Statement, location Location) *IfStatement {
	return &IfStatement{
		StatementBase: StatementBase{Location: location},
		Condition:     cond,
		ThenStatement: thenStmt,
	}
}

func NewIfElseStatement(cond Expression, thenStmt, elseStmt Statement, location Location) *IfStatement {
	return &IfStatement{
		StatementBase: StatementBase{Location: location},
		Condition:     cond,
		ThenStatement: thenStmt,
		ElseStatement: elseStmt,
	}
}

func (s *IfStatement) AlwaysReturns() bool {
	if s.ElseStatement != nil {
		return s.ThenStatement.AlwaysReturns() && s.ElseStatement.AlwaysReturns()
	}
	return false
}

//goland:noinspection GoUnusedParameter
func (s *IfStatement) AddDeclarationToBlock(ctx *BlockContext) {}

func (s *IfStatement) DoEmit(ec *EmitContext) {
	falseLabel := ec.DefineLabel()
	endLabel := ec.DefineLabel()

	s.Condition.Emit(ec)
	ec.EmitBranchP(OpCodeBranchIfFalse, &falseLabel)

	s.ThenStatement.Emit(ec)
	ec.EmitBranchP(OpCodeJump, &endLabel)

	ec.self.EmitLabel(&falseLabel)
	if s.ElseStatement != nil {
		s.ElseStatement.Emit(ec)
	}

	ec.self.EmitLabel(&endLabel)
}

func (s *IfStatement) Emit(ec *EmitContext) { s.DoEmit(ec) }

func (s *IfStatement) String() string {
	if s.ElseStatement != nil {
		return fmt.Sprintf("if(%v) %v else %v", s.Condition, s.ThenStatement, s.ElseStatement)
	}
	return fmt.Sprintf("if(%v) %v", s.Condition, s.ThenStatement)
}

// ── SwitchStatement & SwitchCase ─────────────────────────────────────────────

type SwitchCase struct {
	Value Expression
	Body  []Statement
}

func NewSwitchCase(value Expression, body []Statement) *SwitchCase {
	return &SwitchCase{Value: value, Body: body}
}

type SwitchStatement struct {
	StatementBase
	Expression Expression
	Cases      []*SwitchCase
}

func NewSwitchStatement(expr Expression, cases []*SwitchCase, location Location) *SwitchStatement {
	return &SwitchStatement{
		StatementBase: StatementBase{Location: location},
		Expression:    expr,
		Cases:         cases,
	}
}

func (s *SwitchStatement) AlwaysReturns() bool {
	for _, c := range s.Cases {
		if c.Body == nil || len(c.Body) == 0 {
			return false
		}
		lastStmt := c.Body[len(c.Body)-1]
		if !lastStmt.AlwaysReturns() {
			return false
		}
	}
	return len(s.Cases) > 0
}

//goland:noinspection GoUnusedParameter
func (s *SwitchStatement) AddDeclarationToBlock(ctx *BlockContext) {}

func (s *SwitchStatement) DoEmit(ec *EmitContext) {
	s.Expression.Emit(ec)

	if len(s.Cases) == 0 {
		ec.Emit(OpCodePop, ValueOf(0))
		return
	}

	caseLabels := make([]Label, len(s.Cases))
	defaultIdx := -1
	for i, c := range s.Cases {
		caseLabels[i] = ec.DefineLabel()
		if c.Value == nil {
			if defaultIdx >= 0 {
				ec.GetReport().Error(139, "Duplicate default labels in switch")
			}
			defaultIdx = i
		}
	}
	endLabel := ec.DefineLabel()

	valueType := s.Expression.GetEvaluatedCType(ec)
	lc := ec.PushLoop(&endLabel, nil)
	ioff := lc.GetInstructionOffset(valueType)
	eqOp := OpCodeEqualToInt8 + OpCode(ioff)

	for i, c := range s.Cases {
		if c.Value == nil {
			continue
		}
		lc.Emit(OpCodeDup, ValueOf(0))
		c.Value.Emit(lc)
		lc.EmitCast(c.Value.GetEvaluatedCType(lc), valueType)
		lc.Emit(eqOp, ValueOf(0))
		lc.EmitBranchP(OpCodeBranchIfTrue, &caseLabels[i])
	}
	if defaultIdx >= 0 {
		lc.Emit(OpCodePop, ValueOf(0))
		lc.EmitBranchP(OpCodeJump, &caseLabels[defaultIdx])
	} else {
		lc.Emit(OpCodePop, ValueOf(0))
		lc.EmitBranchP(OpCodeJump, &endLabel)
	}

	for i, c := range s.Cases {
		lc.EmitLabel(&caseLabels[i])
		for _, stmt := range c.Body {
			stmt.Emit(lc)
		}
	}

	lc.EmitLabel(&endLabel)
}

func (s *SwitchStatement) Emit(ec *EmitContext) { s.DoEmit(ec) }

func (s *SwitchStatement) String() string { return fmt.Sprintf("switch(%v)", s.Expression) }

// ── WhileStatement ───────────────────────────────────────────────────────────

type WhileStatement struct {
	StatementBase
	IsDoWhile bool
	Condition Expression
	Body      *Block
}

func NewWhileStatement(isDoWhile bool, cond Expression, body *Block) *WhileStatement {
	return &WhileStatement{IsDoWhile: isDoWhile, Condition: cond, Body: body}
}

func (s *WhileStatement) AlwaysReturns() bool { return false }

//goland:noinspection GoUnusedParameter
func (s *WhileStatement) AddDeclarationToBlock(ctx *BlockContext) {
	s.Body.AddDeclarationToBlock(ctx)
}

func (s *WhileStatement) DoEmit(ec *EmitContext) {
	condLabel := ec.DefineLabel()
	loopLabel := ec.DefineLabel()
	endLabel := ec.DefineLabel()

	lc := ec.PushLoop(&endLabel, &condLabel)

	if s.IsDoWhile {
		lc.EmitLabel(&loopLabel)
		s.Body.Emit(lc)
		lc.EmitLabel(&condLabel)
		s.Condition.Emit(lc)
		lc.EmitBranchP(OpCodeBranchIfFalse, &endLabel)
		lc.EmitBranchP(OpCodeJump, &condLabel)
	} else {
		lc.EmitLabel(&condLabel)
		s.Condition.Emit(lc)
		lc.EmitBranchP(OpCodeBranchIfFalse, &endLabel)
		lc.EmitLabel(&loopLabel)
		s.Body.Emit(lc)
		lc.EmitBranchP(OpCodeJump, &condLabel)
	}
	lc.EmitLabel(&endLabel)
}

func (s *WhileStatement) Emit(ec *EmitContext) { s.DoEmit(ec) }

func (s *WhileStatement) String() string {
	if s.IsDoWhile {
		return fmt.Sprintf("do %v while(%v)", s.Body, s.Condition)
	}
	return fmt.Sprintf("while(%v) %v", s.Condition, s.Body)
}

// ── ForStatement ─────────────────────────────────────────────────────────────

type ForStatement struct {
	StatementBase
	InitBlock *Block
	Condition Expression
	Increment Expression
	Body      *Block
}

func NewForStatement(init Statement, cond Expression, body *Block) *ForStatement {
	ib := NewBlock(VariableScopeLocal)
	if init != nil {
		ib.AddStatement(init)
	}
	return &ForStatement{InitBlock: ib, Body: body, Condition: cond}
}

func NewForFullStatement(init Statement, cond Expression, incr Expression, body *Block) *ForStatement {
	ib := NewBlock(VariableScopeLocal)
	if init != nil {
		ib.AddStatement(init)
	}
	return &ForStatement{InitBlock: ib, Condition: cond, Increment: incr, Body: body}
}

func (s *ForStatement) AlwaysReturns() bool { return false }

func (s *ForStatement) AddDeclarationToBlock(ctx *BlockContext) {
	s.InitBlock.AddDeclarationToBlock(ctx)
	s.Body.AddDeclarationToBlock(ctx)
}

func (s *ForStatement) DoEmit(ec *EmitContext) {
	ec.self.BeginBlock(s.InitBlock)
	for _, stmt := range s.InitBlock.InitStatements {
		stmt.Emit(ec)
	}
	for _, stmt := range s.InitBlock.Statements {
		stmt.Emit(ec)
	}

	nextLabel := ec.DefineLabel()
	endLabel := ec.DefineLabel()

	lc := ec.PushLoop(&endLabel, &nextLabel)

	condLabel := lc.DefineLabel()
	lc.EmitLabel(&condLabel)
	if s.Condition != nil {
		s.Condition.Emit(lc)
		lc.EmitBranchP(OpCodeBranchIfFalse, &endLabel)
	}

	s.Body.Emit(lc)

	lc.EmitLabel(&nextLabel)
	if s.Increment != nil {
		s.Increment.Emit(lc)
		lc.Emit(OpCodePop, ValueOf(0))
	}
	lc.EmitBranchP(OpCodeJump, &condLabel)

	lc.EmitLabel(&endLabel)
	lc.EndBlock()
}

func (s *ForStatement) Emit(ec *EmitContext) { s.DoEmit(ec) }

func (s *ForStatement) String() string { return fmt.Sprintf("for(...) %v", s.Body) }

// ── GotoStatement ────────────────────────────────────────────────────────────

type GotoStatement struct {
	StatementBase
	Label string
}

func NewGotoStatement(label string, location Location) *GotoStatement {
	return &GotoStatement{
		StatementBase: StatementBase{Location: location},
		Label:         label,
	}
}

func (s *GotoStatement) AlwaysReturns() bool { return false }

//goland:noinspection GoUnusedParameter
func (s *GotoStatement) AddDeclarationToBlock(ctx *BlockContext) {}

func (s *GotoStatement) DoEmit(ec *EmitContext) {
	f := ec.GetFunctionDecl()
	if f == nil {
		ec.GetReport().Error(9999, "goto statement used outside of function body")
		return
	}
	
	lbl := ec.self.ResolveGotoLabel(s.Label)
	if lbl == nil {
		ec.GetReport().Error(107, fmt.Sprintf("undefined label '%s'", s.Label))
		return
	}
	ec.EmitBranchP(OpCodeJump, lbl)
}

func (s *GotoStatement) Emit(ec *EmitContext) { s.DoEmit(ec) }

func (s *GotoStatement) String() string { return fmt.Sprintf("goto %s", s.Label) }

// ── ContinueStatement ────────────────────────────────────────────────────────

type ContinueStatement struct {
	StatementBase
}

func NewContinueStatement() *ContinueStatement { return &ContinueStatement{} }

func (s *ContinueStatement) AlwaysReturns() bool { return false }

//goland:noinspection GoUnusedParameter
func (s *ContinueStatement) AddDeclarationToBlock(ctx *BlockContext) {}

func (s *ContinueStatement) DoEmit(ec *EmitContext) {
	lbl := ec.self.ContinueLabel()
	if lbl != nil {
		ec.EmitBranchP(OpCodeJump, lbl)
		return
	}
	
	ec.GetReport().Error(139, "No enclosing statement out of which to continue")
}

func (s *ContinueStatement) Emit(ec *EmitContext) { s.DoEmit(ec) }

func (s *ContinueStatement) String() string { return "continue" }

// ── BreakStatement ───────────────────────────────────────────────────────────

type BreakStatement struct {
	StatementBase
}

func NewBreakStatement() *BreakStatement { return &BreakStatement{} }

func (s *BreakStatement) AlwaysReturns() bool { return false }

//goland:noinspection GoUnusedParameter
func (s *BreakStatement) AddDeclarationToBlock(ctx *BlockContext) {}

func (s *BreakStatement) DoEmit(ec *EmitContext) {
	lbl := ec.self.BreakLabel()
	if lbl != nil {
		ec.EmitBranchP(OpCodeJump, lbl)
		return
	}

	ec.GetReport().Error(139, "No enclosing statement out of which to break")
}

func (s *BreakStatement) Emit(ec *EmitContext) { s.DoEmit(ec) }

func (s *BreakStatement) String() string { return "break" }

// ── ReturnStatement ──────────────────────────────────────────────────────────

type ReturnStatement struct {
	StatementBase
	Value Expression
}

func NewReturnStatement() *ReturnStatement { return &ReturnStatement{} }

func NewReturnValueStatement(value Expression) *ReturnStatement {
	return &ReturnStatement{Value: value}
}

func (s *ReturnStatement) AlwaysReturns() bool { return true }

//goland:noinspection GoUnusedParameter
func (s *ReturnStatement) AddDeclarationToBlock(ctx *BlockContext) {}

func (s *ReturnStatement) DoEmit(ec *EmitContext) {
	f := ec.GetFunctionDecl()
	if f == nil {
		ec.GetReport().Error(1519, "Invalid return outside of function")
		return
	}

	if s.Value != nil {
		if f.FunctionType.ReturnType.IsVoid() {
			ec.GetReport().Error(127, "A return keyword must not be followed by any expression when the function returns void")
		} else {
			s.Value.Emit(ec)
			ec.EmitCast(s.Value.GetEvaluatedCType(ec), f.FunctionType.ReturnType)
			ec.Emit(OpCodeReturn, ValueOf(0))
		}
	} else {
		if f.FunctionType.ReturnType.IsVoid() {
			ec.Emit(OpCodeReturn, ValueOf(0))
		} else {
			ec.GetReport().Error(126, "A value is required for the return statement")
		}
	}
}

func (s *ReturnStatement) Emit(ec *EmitContext) { s.DoEmit(ec) }

func (s *ReturnStatement) String() string {
	if s.Value != nil {
		return fmt.Sprintf("return %v", s.Value)
	}
	return "return"
}

// ── LabeledStatement ─────────────────────────────────────────────────────────

type LabeledStatement struct {
	StatementBase
	Label     string
	Statement Statement
}

func NewLabeledStatement(label string, stmt Statement, location Location) *LabeledStatement {
	return &LabeledStatement{
		StatementBase: StatementBase{Location: location},
		Label:         label,
		Statement:     stmt,
	}
}

func (s *LabeledStatement) AlwaysReturns() bool { return s.Statement.AlwaysReturns() }

//goland:noinspection GoUnusedParameter
func (s *LabeledStatement) AddDeclarationToBlock(ctx *BlockContext) {}

func (s *LabeledStatement) DoEmit(ec *EmitContext) {
	label := ec.DefineLabel()
	ec.self.DefineGotoLabel(s.Label)
	_ = label
	s.Statement.Emit(ec)
}

func (s *LabeledStatement) Emit(ec *EmitContext) { s.DoEmit(ec) }

func (s *LabeledStatement) String() string {
	return fmt.Sprintf("%s: %v", s.Label, s.Statement)
}

// ── StructureExpression & StructureExpressionItem ────────────────────────────

type StructureExpressionItem struct {
	FieldName  string
	Expression Expression
}

func NewStructureExpressionItem(fieldName string, expr Expression) *StructureExpressionItem {
	return &StructureExpressionItem{FieldName: fieldName, Expression: expr}
}

type StructureExpression struct {
	ExpressionBase
	Items []*StructureExpressionItem
}

func NewStructureExpression() *StructureExpression {
	return &StructureExpression{}
}

func (e *StructureExpression) GetEvaluatedCType(ec *EmitContext) CType {
	if len(e.Items) > 0 && e.Items[0].Expression != nil {
		return e.Items[0].Expression.GetEvaluatedCType(ec)
	}
	return CBasicTypeSignedInt
}

func (e *StructureExpression) Emit(ec *EmitContext) {
	// Structured initialization is handled by AssignExpression
	ec.Emit(OpCodeLoadConstant, ValueOf(0))
}

func (e *StructureExpression) EmitPointer(ec *EmitContext) { defaultEmitPointer(ec) }

func (e *StructureExpression) CanEmitPointer() bool { return false }

func (e *StructureExpression) EvalConstant(ec *EmitContext) Value {
	return defaultEvalConstant(e, ec)
}

func (e *StructureExpression) String() string {
	return fmt.Sprintf("{... %d items}", len(e.Items))
}
