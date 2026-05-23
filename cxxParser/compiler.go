package cxxParser

import (
	"fmt"
	"path/filepath"
)

// ============================================================================
// FunctionContext — function-level context for code generation
// (Corresponds to CLanguage.Compiler.FunctionContext)
// ============================================================================

type BlockLocals struct {
	StartIndex int
	Length     int
}

type FunctionContext struct {
	*BlockContext
	exe               *Executable
	fexe              *CompiledFunction
	blocks            []*Block
	blockLocals       map[*Block]*BlockLocals
	allLocals         []*CompiledVariable
	gotoLabels        map[string]*Label
	definedGotoLabels map[string]bool
}

func NewFunctionContext(exe *Executable, fexe *CompiledFunction, parent *EmitContext) *FunctionContext {
	body := fexe.Body
	if body == nil {
		body = NewBlock(VariableScopeLocal)
	}
	if parent == nil {
		parent = NewEmitContext(exe.MachineInfo, NewReport(nil), nil, nil)
	}
	fc := &FunctionContext{
		BlockContext:      NewBlockContextFull(body, parent.GetMachineInfo(), parent.GetReport(), fexe, parent),
		exe:               exe,
		fexe:              fexe,
		blocks:            make([]*Block, 0),
		blockLocals:       make(map[*Block]*BlockLocals),
		allLocals:         make([]*CompiledVariable, 0),
		gotoLabels:        make(map[string]*Label),
		definedGotoLabels: make(map[string]bool),
	}
	fc.Block = body
	fc.self = fc
	return fc
}

func (fc *FunctionContext) String() string {
	return fmt.Sprintf("%v function context", fc.fexe)
}

func (fc *FunctionContext) ResolveTypeNameString(typeName string) CType {
	// Look for local types
	for i := len(fc.blocks) - 1; i >= 0; i-- {
		b := fc.blocks[i]
		if t, ok := b.Typedefs[typeName]; ok {
			return t
		}
	}
	return fc.BlockContext.ResolveTypeNameString(typeName)
}

func (fc *FunctionContext) TryResolveVariable(name string, argTypes []CType) *ResolvedVariable {
	// Look for function parameters
	for _, p := range fc.fexe.FunctionType.Parameters() {
		if p.Name == name {
			return &ResolvedVariable{Scope: VariableScopeArg, Address: p.Offset, VariableType: p.ParameterType}
		}
	}

	// Look for locals
	for i := len(fc.blocks) - 1; i >= 0; i-- {
		b := fc.blocks[i]
		blocals := fc.blockLocals[b]
		for j := 0; j < blocals.Length; j++ {
			k := blocals.StartIndex + j
			if fc.allLocals[k].Name == name {
				return &ResolvedVariable{Scope: VariableScopeLocal, Address: fc.allLocals[k].StackOffset, VariableType: fc.allLocals[k].VariableType}
			}
		}
	}

	// Check 'this' for instance methods
	if name == "this" && fc.fexe.FunctionType.IsInstance() && fc.fexe.FunctionType.DeclaringType != nil {
		if dtype, ok := fc.fexe.FunctionType.DeclaringType.(*CStructType); ok {
			return &ResolvedVariable{Scope: VariableScopeArg, Address: -1, VariableType: dtype.Pointer()}
		}
	}

	return fc.BlockContext.TryResolveVariable(name, argTypes)
}

func (fc *FunctionContext) BeginBlock(b *Block) {
	fc.blocks = append(fc.blocks, b)
	locs := &BlockLocals{
		StartIndex: len(fc.allLocals),
		Length:     len(b.Variables),
	}
	fc.blockLocals[b] = locs
	fc.allLocals = append(fc.allLocals, b.Variables...)

	offset := 0
	for _, v := range fc.allLocals {
		v.StackOffset = offset
		offset += v.VariableType.NumValues()
	}
}

func (fc *FunctionContext) EndBlock() {
	fc.blocks = fc.blocks[:len(fc.blocks)-1]
}

func (fc *FunctionContext) AllocateTemp(ctype CType) int {
	offset := 0
	if len(fc.allLocals) > 0 {
		last := fc.allLocals[len(fc.allLocals)-1]
		offset = last.StackOffset + last.VariableType.NumValues()
	}
	temp := &CompiledVariable{Name: fmt.Sprintf("__ref_temp_%d", len(fc.allLocals)), StackOffset: offset, VariableType: ctype}
	fc.allLocals = append(fc.allLocals, temp)
	return offset
}

func (fc *FunctionContext) LocalVariables() []*CompiledVariable {
	return fc.allLocals
}

func (fc *FunctionContext) DefineLabel() Label {
	return Label{}
}

func (fc *FunctionContext) EmitLabel(l *Label) {
	l.Index = len(fc.fexe.Instructions)
}

func (fc *FunctionContext) ResolveGotoLabel(name string) *Label {
	if label, ok := fc.gotoLabels[name]; ok {
		return label
	}
	label := &Label{}
	fc.gotoLabels[name] = label
	return label
}

func (fc *FunctionContext) DefineGotoLabel(name string) *Label {
	if fc.definedGotoLabels[name] {
		fc.report.ErrorSimple(140, fmt.Sprintf("Label '%s' is already defined", name))
		return nil
	}
	fc.definedGotoLabels[name] = true
	if label, ok := fc.gotoLabels[name]; ok {
		return label
	}
	label := &Label{}
	fc.gotoLabels[name] = label
	return label
}

func (fc *FunctionContext) CheckLabels() {
	for k := range fc.gotoLabels {
		if !fc.definedGotoLabels[k] {
			fc.report.ErrorSimple(9999, fmt.Sprintf("Label '%s' is not defined", k))
		}
	}
}

func (fc *FunctionContext) EmitInstruction(inst Instruction) {
	fc.fexe.Instructions = append(fc.fexe.Instructions, inst)
}

func (fc *FunctionContext) GetConstantMemory(stringConstant string) Value {
	return fc.exe.GetConstantMemory(stringConstant)
}

// ============================================================================
// LoopContext — EmitContext for loop break/continue labels
// (Corresponds to CLanguage.Compiler.LoopContext)
// ============================================================================

type LoopContext struct {
	*EmitContext
	LoopBreakLabel    *Label
	LoopContinueLabel *Label
}

func NewLoopContext(breakLabel *Label, continueLabel *Label, parent *EmitContext) *LoopContext {
	lc := &LoopContext{
		EmitContext:       NewEmitContextFromParent(parent),
		LoopBreakLabel:    breakLabel,
		LoopContinueLabel: continueLabel,
	}
	lc.self = lc
	return lc
}

func (lc *LoopContext) BreakLabel() *Label {
	return lc.LoopBreakLabel
}

func (lc *LoopContext) ContinueLabel() *Label {
	if lc.LoopContinueLabel != nil {
		return lc.LoopContinueLabel
	}
	if lc.parentCtx != nil {
		return lc.parentCtx.self.ContinueLabel()
	}
	return nil
}

// ============================================================================
// ExecutableContext — top-level context for resolving globals and functions
// (Corresponds to CLanguage.Compiler.ExecutableContext)
// ============================================================================

type ExecutableContext struct {
	*EmitContext
	Executable *Executable
}

func NewExecutableContext(exe *Executable, report *Report) *ExecutableContext {
	ec := &ExecutableContext{
		EmitContext: NewEmitContext(exe.MachineInfo, report, nil, nil),
		Executable:  exe,
	}
	ec.self = ec
	return ec
}

func (ec *ExecutableContext) ResolveMethodFunction(structType *CStructType, method *CStructMethod) *ResolvedVariable {
	if ftype, ok := method.GetMemberType().(*CFunctionType); ok {
		nameContext := structType.Name
		for i, f := range ec.Executable.Functions {
			if f.GetNameContext() == nameContext && f.GetName() == method.GetName() && f.GetFunctionType().ParameterTypesEqual(ftype) {
				return &ResolvedVariable{Function: f, Address: i, VariableType: f.GetFunctionType()}
			}
		}
	}
	ec.report.ErrorSimple(9000, fmt.Sprintf("No definition for '%s::%s' found", structType.Name, method.GetName()))
	return &ResolvedVariable{Scope: VariableScopeFunction, Address: 0, VariableType: ec.UnresolvedMethod(structType.Name, method.GetName()).GetFunctionType()}
}

func (ec *ExecutableContext) UnresolvedMethod(typeName string, methodName string) *InternalFunction {
	return NewInternalFunction(ec.machineInfo, "void "+typeName+"::"+methodName+"()", nil)
}

func (ec *ExecutableContext) GetConstantMemory(stringConstant string) Value {
	return ec.Executable.GetConstantMemory(stringConstant)
}

func (ec *ExecutableContext) TryResolveVariable(name string, argTypes []CType) *ResolvedVariable {
	// Look for global variables
	for _, g := range ec.Executable.Globals {
		if g.Name == name {
			return &ResolvedVariable{Scope: VariableScopeGlobal, Address: g.StackOffset, VariableType: g.VariableType}
		}
	}

	// Look for global functions
	var bestFunction BaseFunction
	bestIndex := -1
	bestScore := 0

	for i, f := range ec.Executable.Functions {
		if f.GetName() == name && f.GetNameContext() == "" {
			score := f.GetFunctionType().ScoreParameterTypeMatches(argTypes)
			if score > bestScore {
				bestFunction = f
				bestIndex = i
				bestScore = score
			}
		}
	}
	if bestFunction != nil {
		return &ResolvedVariable{Function: bestFunction, Address: bestIndex, VariableType: bestFunction.GetFunctionType(), Scope: VariableScopeFunction}
	}

	return ec.EmitContext.TryResolveVariable(name, argTypes)
}

func (ec *ExecutableContext) TryResolveOperatorFunction(structName string, operatorName string, argTypes []CType) *ResolvedVariable {
	var bestFunction BaseFunction
	bestIndex := -1
	bestScore := 0

	for i, f := range ec.Executable.Functions {
		if f.GetName() == operatorName && f.GetNameContext() == structName {
			score := f.GetFunctionType().ScoreParameterTypeMatches(argTypes)
			if score > bestScore {
				bestFunction = f
				bestIndex = i
				bestScore = score
			}
		}
	}
	if bestFunction != nil {
		return &ResolvedVariable{Function: bestFunction, Address: bestIndex, VariableType: bestFunction.GetFunctionType()}
	}
	return ec.EmitContext.TryResolveOperatorFunction(structName, operatorName, argTypes)
}

func (ec *ExecutableContext) TryResolveQualifiedFunction(nameContext string, name string, argTypes []CType) *ResolvedVariable {
	var bestFunction BaseFunction
	bestIndex := -1
	bestScore := 0

	for i, f := range ec.Executable.Functions {
		if f.GetName() == name && f.GetNameContext() == nameContext {
			score := f.GetFunctionType().ScoreParameterTypeMatches(argTypes)
			if score > bestScore {
				bestFunction = f
				bestIndex = i
				bestScore = score
			}
		}
	}
	if bestFunction != nil {
		return &ResolvedVariable{Function: bestFunction, Address: bestIndex, VariableType: bestFunction.GetFunctionType()}
	}
	return ec.EmitContext.TryResolveQualifiedFunction(nameContext, name, argTypes)
}

// ============================================================================
// TranslationUnitContext — file-level context for type/variable resolution
// (Corresponds to CLanguage.Compiler.TranslationUnitContext)
// ============================================================================

type TranslationUnitContext struct {
	*BlockContext
	TranslationUnit *TranslationUnit
}

func NewTranslationUnitContext(tu *TranslationUnit, exeContext *ExecutableContext) *TranslationUnitContext {
	tuc := &TranslationUnitContext{
		BlockContext:    NewBlockContext(&tu.Block, exeContext.EmitContext),
		TranslationUnit: tu,
	}
	tuc.Block = &tu.Block
	tuc.self = tuc
	return tuc
}

func (tuc *TranslationUnitContext) ResolveTypeNameString(typeName string) CType {
	if tt, ok := tuc.TranslationUnit.Typedefs[typeName]; ok {
		return tt
	}
	if st, ok := tuc.TranslationUnit.Structures[typeName]; ok {
		return st
	}
	return tuc.BlockContext.ResolveTypeNameString(typeName)
}

func (tuc *TranslationUnitContext) TryResolveVariable(name string, argTypes []CType) *ResolvedVariable {
	// Ask parent first (ExecutableContext)
	v := tuc.EmitContext.TryResolveVariable(name, argTypes)
	if v != nil {
		return v
	}

	// Look in translation unit enums
	for _, e := range tuc.TranslationUnit.Enums {
		for _, em := range e.Members {
			if em.Name == name {
				return &ResolvedVariable{Constant: ValueOf(int64(em.Value)), VariableType: e}
			}
		}
	}

	return tuc.BlockContext.TryResolveVariable(name, argTypes)
}

// ============================================================================
// EnumContext — context for enum value resolution
// (Corresponds to CLanguage.Compiler.EnumContext)
// ============================================================================

type EnumContext struct {
	*EmitContext
	enumTs *TypeSpecifier
	et     *CEnumType
}

func NewEnumContext(enumTs *TypeSpecifier, et *CEnumType, parent *EmitContext) *EnumContext {
	ec := &EnumContext{
		EmitContext: NewEmitContextFromParent(parent),
		enumTs:      enumTs,
		et:          et,
	}
	ec.self = ec
	return ec
}

func (ec *EnumContext) TryResolveVariable(name string, argTypes []CType) *ResolvedVariable {
	for _, m := range ec.et.Members {
		if m.Name == name {
			return &ResolvedVariable{Constant: ValueOf(int64(m.Value)), VariableType: ec.et}
		}
	}
	return ec.EmitContext.TryResolveVariable(name, argTypes)
}

// ============================================================================
// ResolvedVariable — resolved variable/function reference with code emission
// (Corresponds to CLanguage.Compiler.ResolvedVariable)
// ============================================================================

type ResolvedVariable struct {
	Scope        VariableScope
	Address      int
	VariableType CType
	Function     BaseFunction
	Constant     Value
}

func (rv *ResolvedVariable) Emit(ec *EmitContext) {
	switch rv.Scope {
	case VariableScopeFunction:
		ec.Emit(OpCodeLoadConstant, ValuePointer(rv.Address))
	case VariableScopeGlobal:
		ec.Emit(OpCodeLoadGlobal, ValuePointer(rv.Address))
	case VariableScopeArg:
		ec.Emit(OpCodeLoadArg, ValuePointer(rv.Address))
	case VariableScopeLocal:
		ec.Emit(OpCodeLoadLocal, ValuePointer(rv.Address))
	case VariableScopeConstant:
		ec.Emit(OpCodeLoadConstant, ValueOf(0))
	default:
		panic(fmt.Sprintf("Cannot get value of variable scope '%v'", rv.Scope))
	}
}

func (rv *ResolvedVariable) EmitPointer(ec *EmitContext) {
	switch rv.Scope {
	case VariableScopeFunction:
		ec.Emit(OpCodeLoadConstant, ValuePointer(rv.Address))
	case VariableScopeGlobal:
		ec.Emit(OpCodeLoadConstant, ValuePointer(rv.Address))
	case VariableScopeArg:
		ec.Emit(OpCodeLoadConstant, ValuePointer(rv.Address))
		ec.Emit(OpCodeLoadFramePointer, ValueOf(0))
		ec.Emit(OpCodeOffsetPointer, ValueOf(0))
	case VariableScopeLocal:
		ec.Emit(OpCodeLoadConstant, ValuePointer(rv.Address))
		ec.Emit(OpCodeLoadFramePointer, ValueOf(0))
		ec.Emit(OpCodeOffsetPointer, ValueOf(0))
	case VariableScopeConstant:
		ec.Emit(OpCodeLoadConstant, ValueOf(0))
	default:
		panic(fmt.Sprintf("Cannot get address of variable scope '%v'", rv.Scope))
	}
}

// ============================================================================
// CompilerOptions — configuration for the compiler
// (Corresponds to CLanguage.Compiler.CompilerOptions)
// ============================================================================

type CompilerOptions struct {
	MachineInfo *MachineInfo
	Report      *Report
	Documents   []*Document
}

func NewCompilerOptions(machineInfo *MachineInfo, report *Report, documents []*Document) *CompilerOptions {
	return &CompilerOptions{
		MachineInfo: machineInfo,
		Report:      report,
		Documents:   documents,
	}
}

func NewCompilerOptionsFromMi(machineInfo *MachineInfo) *CompilerOptions {
	return NewCompilerOptions(machineInfo, NewReport(nil), []*Document{})
}

func NewCompilerOptionsDefault() *CompilerOptions {
	return NewCompilerOptionsFromMi(NewMachineInfo())
}

// ============================================================================
// CCompiler — the main compiler orchestrator
// (Corresponds to CLanguage.Compiler.CCompiler)
// ============================================================================

const FirstTypeId = 1

type FunctionToCompile struct {
	Function *CompiledFunction
	Context  EmitContextSelf
}

type CCompiler struct {
	options        *CompilerOptions
	lexedDocuments map[string]*LexedDocument
	tus            []*TranslationUnit
}

func NewCCompiler(options *CompilerOptions) *CCompiler {
	cc := &CCompiler{
		options:        options,
		lexedDocuments: make(map[string]*LexedDocument),
		tus:            make([]*TranslationUnit, 0),
	}
	cc.ProcessDocument(NewDocument("_machine.h", options.MachineInfo.GeneratedHeaderCode()))
	for path, code := range options.MachineInfo.SystemHeadersCode {
		cc.ProcessDocument(NewDocument(path, code))
	}
	for _, d := range options.Documents {
		cc.ProcessDocument(d)
	}
	return cc
}

func NewCCompilerWithMi(mi *MachineInfo, report *Report) *CCompiler {
	return NewCCompiler(NewCompilerOptions(mi, report, []*Document{}))
}

func (cc *CCompiler) Options() *CompilerOptions {
	return cc.options
}

func (cc *CCompiler) Add(tu *TranslationUnit) {
	cc.tus = append(cc.tus, tu)
}

func (cc *CCompiler) ProcessDocument(document *Document) {
	lexed := NewLexedDocument(document, cc.options.Report)
	cc.lexedDocuments[document.Path] = lexed

	if document.IsCompilable() {
		parser := NewCParser()
		name := filepath.Base(document.Path)
		if ext := filepath.Ext(name); ext != "" {
			name = name[:len(name)-len(ext)]
		}

		machineTokens := cc.lexedDocuments["_machine.h"].Tokens
		tu := parser.ParseTranslationUnit(cc.options.Report, name,
			func(path string, relative bool) []Token {
				if doc, ok := cc.lexedDocuments[path]; ok {
					return doc.Tokens
				}
				return nil
			},
			machineTokens, lexed.Tokens)
		cc.Add(tu)
	}
}

//goland:noinspection GoUnusedParameter
func (cc *CCompiler) Include(path string, relative bool) []Token {
	if doc, ok := cc.lexedDocuments[path]; ok {
		return doc.Tokens
	}
	return nil
}

func (cc *CCompiler) AddCode(name string, code string) {
	cc.AddDocument(NewDocument(name, code))
}

func (cc *CCompiler) AddDocument(document *Document) {
	cc.ProcessDocument(document)
}

func (cc *CCompiler) Compile() *Executable {
	defer func() {
		if r := recover(); r != nil {
			cc.options.Report.ErrorSimple(9000, fmt.Sprintf("Compiler error: %v", r))
		}
	}()
	return cc.CompileExecutable()
}

func CompileStatic(code string) *Executable {
	compiler := NewCCompiler(NewCompilerOptionsDefault())
	compiler.AddCode("main.c", code)
	exe := compiler.Compile()
	for _, e := range compiler.options.Report.Errors() {
		if !e.IsWarning {
			msg := ""
			for _, e2 := range compiler.options.Report.Errors() {
				if !e2.IsWarning {
					if msg != "" {
						msg += "\n"
					}
					msg += e2.Text
				}
			}
			panic(fmt.Errorf("compile error: %s", msg))
		}
	}
	return exe
}

func (cc *CCompiler) CompileExecutable() *Executable {
	exe := &Executable{MachineInfo: cc.options.MachineInfo}
	for _, f := range cc.options.MachineInfo.InternalFunctions {
		exe.Functions = append(exe.Functions, f)
	}
	exeContext := NewExecutableContext(exe, cc.options.Report)

	// Put something at the zero address so we don't get 0 addresses of globals
	exe.AddGlobal("__zero__", CBasicTypeSignedInt)

	// Find variables, functions, types
	exeInitBody := NewBlock(VariableScopeLocal)
	var tucs []*TranslationUnitContext
	for _, tu := range cc.tus {
		tucs = append(tucs, NewTranslationUnitContext(tu, exeContext))
	}

	var tuInits []*FunctionToCompile
	for _, tuc := range tucs {
		cc.AddStatementDeclarations(tuc)
		if len(tuc.TranslationUnit.InitStatements) > 0 {
			tuInitBody := NewBlock(VariableScopeLocal)
			for _, s := range tuc.TranslationUnit.InitStatements {
				tuInitBody.AddStatement(s)
			}
			tuInit := &CompiledFunction{
				Name:         fmt.Sprintf("__%s__cinit", tuc.TranslationUnit.Name),
				NameContext:  "",
				FunctionType: VoidProcedure,
				Body:         tuInitBody,
			}
			exeInitBody.AddStatement(NewExpressionStatement(
				NewFuncallExpression(NewVariableExpression(tuInit.Name, NullLocation, NullLocation))))
			tuInits = append(tuInits, &FunctionToCompile{Function: tuInit, Context: tuc})
			exe.Functions = append(exe.Functions, tuInit)
		}
	}

	// Generate a function to init globals
	exeInit := &CompiledFunction{
		Name:         "__cinit",
		NameContext:  "",
		FunctionType: VoidProcedure,
		Body:         exeInitBody,
	}
	exe.Functions = append(exe.Functions, exeInit)

	// Allocate vtable globals for polymorphic types
	var polymorphicTypes []*CStructType
	vtableVars := make(map[*CStructType]*CompiledVariable)
	for _, tuc := range tucs {
		cc.CollectPolymorphicTypes(&tuc.TranslationUnit.Block, &polymorphicTypes)
	}
	var pureVirtualTrap BaseFunction
	if len(polymorphicTypes) > 0 {
		trapFunc := NewInternalFunction(cc.options.MachineInfo, "__pure_virtual_called", func(state *CInterpreter) {
			panic("Pure virtual function called")
		})
		pureVirtualTrap = trapFunc
		exe.Functions = append(exe.Functions, trapFunc)
	}

	nextTypeId := FirstTypeId
	for _, st := range polymorphicTypes {
		st.VTable().TypeId = nextTypeId
		nextTypeId++
		vtableType := NewCArrayType(CBasicTypeSignedInt, new(st.VTable().RuntimeSlotCount()))
		vtableVar := exe.AddGlobal(fmt.Sprintf("__vtable_%s", st.Name), vtableType)
		st.VTableGlobalAddress = &vtableVar.StackOffset
		vtableVars[st] = vtableVar
	}

	// Link everything together
	functionsToCompile := []*FunctionToCompile{
		{Function: exeInit, Context: exeContext},
	}
	functionsToCompile = append(functionsToCompile, tuInits...)

	for _, tuc := range tucs {
		tu := tuc.TranslationUnit
		for _, g := range tu.Variables {
			v := exe.AddGlobal(g.Name, g.VariableType)
			v.InitialValue = g.InitialValue
			if gst, ok := g.VariableType.(*CStructType); ok && gst.IsPolymorphic() && gst.VTableGlobalAddress != nil {
				numValues := gst.NumValues()
				if v.InitialValue == nil || len(v.InitialValue) < numValues {
					iv := make([]Value, numValues)
					if v.InitialValue != nil {
						copy(iv, v.InitialValue)
					}
					v.InitialValue = iv
				}
				if v.InitialValue == nil {
					v.InitialValue = make([]Value, numValues)
				}
				v.InitialValue[0] = ValuePointer(*gst.VTableGlobalAddress)
			}
		}

		var funcsToAdd []*CompiledFunction
		for _, f := range tu.Functions {
			if f.Body != nil {
				funcsToAdd = append(funcsToAdd, f)
			}
		}
		for _, f := range funcsToAdd {
			exe.Functions = append(exe.Functions, f)
		}
		for _, f := range funcsToAdd {
			functionsToCompile = append(functionsToCompile, &FunctionToCompile{Function: f, Context: tuc})
		}
	}

	// Populate vtable initial values with function pointers
	funcIndex := cc.BuildFunctionIndex(exe)
	for _, st := range polymorphicTypes {
		if vtableVar, ok := vtableVars[st]; ok {
			cc.PopulateVTable(exe, st, vtableVar, funcIndex, pureVirtualTrap)
		}
	}

	// Build compile-time type hierarchy table for RTTI
	for _, st := range polymorphicTypes {
		baseTypeId := -1
		if st.BaseType != nil && st.BaseType.VTable() != nil {
			baseTypeId = st.BaseType.VTable().TypeId
		}
		exe.AddTypeHierarchyEntry(NewTypeHierarchyEntry(st.VTable().TypeId, baseTypeId, st.Name))
	}

	// Compile functions
	for _, ftc := range functionsToCompile {
		f := ftc.Function
		body := f.Body
		if body == nil {
			continue
		}
		var parentCtx *EmitContext
		switch c := ftc.Context.(type) {
		case *ExecutableContext:
			parentCtx = c.EmitContext
		case *TranslationUnitContext:
			parentCtx = c.EmitContext
		case *FunctionContext:
			parentCtx = c.EmitContext
		case *BlockContext:
			parentCtx = c.EmitContext
		default:
			parentCtx = ftc.Context.(EmitContextSelf).Parent()
		}
		fc := NewFunctionContext(exe, f, parentCtx)
		// The FunctionContext's Block is already set to f.Body
		cc.AddStatementDeclarations(fc)
		f.Body.Emit(fc.EmitContext)
		fc.CheckLabels()
		f.LocalVariables = append(f.LocalVariables, fc.LocalVariables()...)

		if len(body.Statements) == 0 || !body.AlwaysReturns() {
			if f.FunctionType.ReturnType.IsVoid() {
				fc.Emit(OpCodeReturn, ValueOf(0))
			} else {
				cc.options.Report.ErrorSimple(161, "'"+f.Name+"' not all code paths return a value")
			}
		}
	}

	return exe
}

func (cc *CCompiler) CollectPolymorphicTypes(block *Block, result *[]*CStructType) {
	for _, st := range block.Structures {
		if st.IsPolymorphic() && st.VTable() != nil {
			found := false
			for _, r := range *result {
				if r == st {
					found = true
					break
				}
			}
			if !found {
				*result = append(*result, st)
			}
		}
	}
}

func (cc *CCompiler) PopulateVTable(exe *Executable, st *CStructType, vtableVar *CompiledVariable, funcIndex map[FuncIndexKey][]FuncIndexEntry, pureVirtualTrap BaseFunction) {
	if st.VTable() == nil {
		return
	}

	initialValues := make([]Value, st.VTable().RuntimeSlotCount())
	initialValues[0] = ValueOf(int64(st.VTable().TypeId))

	trapIndex := -1
	for i, f := range exe.Functions {
		if f == pureVirtualTrap {
			trapIndex = i
			break
		}
	}

	for i := 0; i < st.VTable().Count(); i++ {
		entry := st.VTable().Entry(i)
		idx := cc.FindFunctionInIndex(funcIndex, entry.DeclaringType.Name, entry.MethodName, entry.Signature)
		if idx >= 0 {
			initialValues[i+1] = ValuePointer(idx)
		} else {
			if trapIndex >= 0 {
				initialValues[i+1] = ValuePointer(trapIndex)
			} else {
				initialValues[i+1] = ValuePointer(0)
			}
		}
	}
	vtableVar.InitialValue = initialValues
}

type FuncIndexKey struct {
	NameContext string
	Name        string
}

type FuncIndexEntry struct {
	Index int
	Func  BaseFunction
}

func (cc *CCompiler) BuildFunctionIndex(exe *Executable) map[FuncIndexKey][]FuncIndexEntry {
	index := make(map[FuncIndexKey][]FuncIndexEntry)
	for i, f := range exe.Functions {
		key := FuncIndexKey{NameContext: f.GetNameContext(), Name: f.GetName()}
		index[key] = append(index[key], FuncIndexEntry{Index: i, Func: f})
	}
	return index
}

func (cc *CCompiler) FindFunctionInIndex(funcIndex map[FuncIndexKey][]FuncIndexEntry, nameContext string, methodName string, signature *CFunctionType) int {
	key := FuncIndexKey{NameContext: nameContext, Name: methodName}
	if candidates, ok := funcIndex[key]; ok {
		for _, entry := range candidates {
			if entry.Func.GetFunctionType().ParameterTypesEqual(signature) {
				return entry.Index
			}
		}
	}
	return -1
}

func (cc *CCompiler) AddStatementDeclarations(context EmitContextSelf) {
	var block *Block
	var bc *BlockContext
	switch ctx := context.(type) {
	case *BlockContext:
		block = ctx.Block
		bc = ctx
	case *FunctionContext:
		block = ctx.Block
		bc = ctx.BlockContext
	case *TranslationUnitContext:
		block = &ctx.TranslationUnit.Block
		bc = ctx.BlockContext
	case *ExecutableContext:
		return
	default:
		return
	}
	if bc == nil {
		return
	}
	for _, s := range block.Statements {
		s.AddDeclarationToBlock(bc)
	}
}

func (cc *CCompiler) GetFunctionDeclarator(d Declarator) *FunctionDeclarator {
	if d == nil {
		return nil
	}
	if fd, ok := d.(*FunctionDeclarator); ok {
		return fd
	}
	return cc.GetFunctionDeclarator(d.GetInnerDeclarator())
}

// ============================================================================
// Helper: type-checking functions for types that might be interfaces
// ============================================================================

func IsCPointerType(t CType) bool {
	_, ok := t.(*CPointerType)
	return ok
}

func IsCFunctionType(t CType) bool {
	_, ok := t.(*CFunctionType)
	return ok
}

// ============================================================================
// CompiledFunction helpers
// ============================================================================

// ParameterListWrapper provides convenience methods for CFunctionType parameters
// since the original C# has a List<Parameter> with indexer.

type ParameterListWrapper []*CFunctionTypeParameter

func (pl ParameterListWrapper) Len() int                          { return len(pl) }
func (pl ParameterListWrapper) Get(i int) *CFunctionTypeParameter { return pl[i] }

// GetIndex Label convenience accessors
func (l *Label) GetIndex() int { return l.Index }

// ============================================================================
// Executable methods needed by the compiler
// ============================================================================

func (e *Executable) AddGlobal(name string, ctype CType) *CompiledVariable {
	offset := e.NextGlobalOffset()
	e.Globals = append(e.Globals, CompiledGlobal{
		Name:         name,
		VariableType: ctype,
		StackOffset:  offset,
	})
	return &CompiledVariable{Name: name, StackOffset: offset, VariableType: ctype}
}

func (e *Executable) NextGlobalOffset() int {
	offset := 0
	for _, g := range e.Globals {
		offset += g.VariableType.NumValues()
	}
	return offset
}

func (e *Executable) GetConstantMemory(stringConstant string) Value {
	index := len(e.Globals)
	bytes := []byte(stringConstant)
	length := len(bytes) + 1
	type_ := NewCArrayType(SignedChar, &length)
	v := e.AddGlobal(fmt.Sprintf("__c%d", index), type_)
	initialValues := make([]Value, length)
	for i, b := range bytes {
		initialValues[i] = ValueOf(int8(b))
	}
	initialValues[length-1] = ValueOf(int8(0))
	v.InitialValue = initialValues
	return ValuePointer(v.StackOffset)
}

func (e *Executable) AddTypeHierarchyEntry(entry *TypeHierarchyEntry) {
	e.typeHierarchy = append(e.typeHierarchy, entry)
}
