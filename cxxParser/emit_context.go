package cxxParser

import "fmt"

// Type aliases matching the C# CBasicType.{SignedInt, Bool, Float, Double} naming.
// These are initialized in init() to avoid capturing nil values before types_def.go's init() runs.
//
//goland:noinspection GoUnusedGlobalVariable
var (
	CBasicTypeSignedInt            CType
	CBasicTypeBool                 CType
	CBasicTypeFloat                CType
	CBasicTypeDouble               CType
	CBasicTypeUnsignedLongInt      CType
	CBasicTypeSignedLongInt        CType
	CBasicTypeSignedLongLongInt    CType
	CBasicTypeUnsignedLongLongInt  CType
	CBasicTypeUnsignedInt          CType
	CBasicTypeUnsignedChar         CType
	CBasicTypeSignedChar           CType
	CBasicTypeUnsignedShortInt     CType
	CBasicTypeSignedShortInt       CType
	CPointerTypePointerToConstChar CType
)

func init() {
	CBasicTypeSignedInt = SignedInt
	CBasicTypeBool = Bool
	CBasicTypeFloat = Float
	CBasicTypeDouble = Double
	CBasicTypeUnsignedLongInt = UnsignedLongInt
	CBasicTypeSignedLongInt = SignedLongInt
	CBasicTypeSignedLongLongInt = SignedLongLongInt
	CBasicTypeUnsignedLongLongInt = UnsignedLongLongInt
	CBasicTypeUnsignedInt = UnsignedInt
	CBasicTypeUnsignedChar = UnsignedChar
	CBasicTypeSignedChar = SignedChar
	CBasicTypeUnsignedShortInt = UnsignedShortInt
	CBasicTypeSignedShortInt = SignedShortInt
	CPointerTypePointerToConstChar = PointerToConstChar
}

// ============================================================================
// EmitContextSelf — self-referencing interface for virtual dispatch
// ============================================================================

type EmitContextSelf interface {
	Parent() *EmitContext
	GetReport() *Report
	GetMachineInfo() *MachineInfo
	GetFunctionDecl() *CompiledFunction

	TryResolveVariable(name string, argTypes []CType) *ResolvedVariable
	ResolveMethodFunction(structType *CStructType, method *CStructMethod) *ResolvedVariable
	TryResolveOperatorFunction(structName string, operatorName string, argTypes []CType) *ResolvedVariable
	TryResolveQualifiedFunction(nameContext string, name string, argTypes []CType) *ResolvedVariable
	BeginBlock(b *Block)
	EndBlock()
	AllocateTemp(ctype CType) int
	DefineLabel() Label
	EmitLabel(l *Label)
	ResolveGotoLabel(name string) *Label
	DefineGotoLabel(name string) *Label
	EmitInstruction(inst Instruction)
	GetConstantMemory(stringConstant string) Value
	ResolveTypeNameString(typeName string) CType

	BreakLabel() *Label
	ContinueLabel() *Label
}

// ============================================================================
// EmitContext — abstract base for all compiler contexts
// (Corresponds to CLanguage.Compiler.EmitContext)
// ============================================================================

type EmitContext struct {
	self         EmitContextSelf
	parentCtx    *EmitContext
	report       *Report
	machineInfo  *MachineInfo
	functionDecl *CompiledFunction
}

func NewEmitContext(mi *MachineInfo, report *Report, fdecl *CompiledFunction, parent *EmitContext) *EmitContext {
	if mi == nil && parent != nil {
		mi = parent.machineInfo
	}
	if report == nil && parent != nil {
		report = parent.report
	}
	if fdecl == nil && parent != nil {
		fdecl = parent.functionDecl
	}
	ec := &EmitContext{
		parentCtx:    parent,
		report:       report,
		machineInfo:  mi,
		functionDecl: fdecl,
	}
	ec.self = ec
	return ec
}

func NewEmitContextFromParent(parent *EmitContext) *EmitContext {
	return NewEmitContext(parent.machineInfo, parent.report, parent.functionDecl, parent)
}

func (ec *EmitContext) Parent() *EmitContext               { return ec.parentCtx }
func (ec *EmitContext) GetReport() *Report                 { return ec.report }
func (ec *EmitContext) GetMachineInfo() *MachineInfo       { return ec.machineInfo }
func (ec *EmitContext) GetFunctionDecl() *CompiledFunction { return ec.functionDecl }

func (ec *EmitContext) BreakLabel() *Label {
	if ec.parentCtx != nil {
		return ec.parentCtx.self.BreakLabel()
	}
	return nil
}

func (ec *EmitContext) ContinueLabel() *Label {
	if ec.parentCtx != nil {
		return ec.parentCtx.self.ContinueLabel()
	}
	return nil
}

func (ec *EmitContext) ResolveTypeNameString(typeName string) CType {
	if ec.parentCtx != nil {
		return ec.parentCtx.self.ResolveTypeNameString(typeName)
	}
	ec.report.Errorf(103, "%v '%v' not found", "Type", typeName)
	return CBasicTypeSignedInt
}

func (ec *EmitContext) ResolveTypeNameFromTypeName(typeName *TypeName) CType {
	return ec.MakeCType(typeName.Specifiers, typeName.Declarator, nil, NewBlock(VariableScopeGlobal))
}

func (ec *EmitContext) ResolveVariable(variable *VariableExpression, argTypes []CType) *ResolvedVariable {
	name := variable.VariableName

	r := ec.self.TryResolveVariable(name, argTypes)
	if r != nil {
		return r
	}

	if ec.machineInfo != nil {
		r = ec.machineInfo.GetUnresolvedVariable(name, argTypes, ec)
		if r != nil {
			return r
		}
	}

	ec.report.ErrorAtf(103, variable.Location, variable.EndLocation, "%v '%v' not found", "Variable", name)
	return &ResolvedVariable{Scope: VariableScopeGlobal, Address: 0, VariableType: CBasicTypeSignedInt}
}

func (ec *EmitContext) PushLoop(breakLabel *Label, continueLabel *Label) *EmitContext {
	return NewLoopContext(breakLabel, continueLabel, ec).EmitContext
}

// --- Virtual methods with default implementations ---

func (ec *EmitContext) TryResolveVariable(name string, argTypes []CType) *ResolvedVariable {
	if ec.parentCtx != nil {
		return ec.parentCtx.self.TryResolveVariable(name, argTypes)
	}
	return nil
}

func (ec *EmitContext) ResolveMethodFunction(structType *CStructType, method *CStructMethod) *ResolvedVariable {
	if ec.parentCtx != nil {
		return ec.parentCtx.self.ResolveMethodFunction(structType, method)
	}
	panic("Cannot resolve method function")
}

func (ec *EmitContext) TryResolveOperatorFunction(structName string, operatorName string, argTypes []CType) *ResolvedVariable {
	if ec.parentCtx != nil {
		return ec.parentCtx.self.TryResolveOperatorFunction(structName, operatorName, argTypes)
	}
	return nil
}

func (ec *EmitContext) TryResolveQualifiedFunction(nameContext string, name string, argTypes []CType) *ResolvedVariable {
	if ec.parentCtx != nil {
		return ec.parentCtx.self.TryResolveQualifiedFunction(nameContext, name, argTypes)
	}
	return nil
}

func (ec *EmitContext) BeginBlock(b *Block) {
	if ec.parentCtx != nil {
		ec.parentCtx.self.BeginBlock(b)
	}
}

func (ec *EmitContext) EndBlock() {
	if ec.parentCtx != nil {
		ec.parentCtx.self.EndBlock()
	}
}

func (ec *EmitContext) AllocateTemp(ctype CType) int {
	// Go doesn't have virtual dispatch, so if self is a FunctionContext,
	// call its AllocateTemp directly. The FunctionContext is the only
	// context that can actually allocate temporaries.
	if fc, ok := ec.self.(*FunctionContext); ok {
		return fc.AllocateTemp(ctype)
	}
	if ec.parentCtx != nil {
		return ec.parentCtx.self.AllocateTemp(ctype)
	}
	panic("Cannot allocate temp outside of a function")
}

func (ec *EmitContext) DefineLabel() Label {
	if ec.parentCtx != nil {
		return ec.parentCtx.self.DefineLabel()
	}
	return Label{}
}

func (ec *EmitContext) EmitLabel(l *Label) {
	if ec.parentCtx != nil {
		ec.parentCtx.self.EmitLabel(l)
	}
}

func (ec *EmitContext) ResolveGotoLabel(name string) *Label {
	if ec.parentCtx != nil {
		return ec.parentCtx.self.ResolveGotoLabel(name)
	}
	return nil
}

func (ec *EmitContext) DefineGotoLabel(name string) *Label {
	if ec.parentCtx != nil {
		return ec.parentCtx.self.DefineGotoLabel(name)
	}
	return nil
}

func (ec *EmitContext) EmitInstruction(inst Instruction) {
	if ec.parentCtx != nil {
		ec.parentCtx.self.EmitInstruction(inst)
	}
}

func (ec *EmitContext) Emit(op OpCode, x Value) {
	ec.self.EmitInstruction(Instruction{Op: op, X: x})
}

func (ec *EmitContext) EmitBranch(op OpCode, label *Label) {
	ec.self.EmitInstruction(Instruction{Op: op, Label: label})
}

func (ec *EmitContext) EmitBranchP(op OpCode, label *Label) {
	if label != nil {
		ec.self.EmitInstruction(Instruction{Op: op, Label: label})
	}
}

func (ec *EmitContext) GetConstantMemory(stringConstant string) Value {
	if ec.parentCtx != nil {
		return ec.parentCtx.self.GetConstantMemory(stringConstant)
	}
	panic("Cannot get constant memory from this context")
}

// --- EmitCast ---

func (ec *EmitContext) EmitCast(fromType CType, toType CType) {
	if fromType.EqualType(toType) {
		return
	}

	fromBasic := fromType.GetBasicType()
	toBasic := toType.GetBasicType()

	if fromBasic != nil && toBasic != nil {
		fromOffset := ec.GetInstructionOffset(fromType)
		toOffset := ec.GetInstructionOffset(toType)
		op := OpCode(int(OpCodeConvertInt8Int8) + (fromOffset*10 + toOffset))
		ec.Emit(op, ValueOf(0))
	} else if fromBasic != nil && fromBasic.IsIntegral() && IsCPointerType(toType) {
		// Support const char *p = 0;
	} else if fromArray, ok := fromType.(*CArrayType); ok {
		if toPtr, ok := toType.(*CPointerType); ok && fromArray.ElementType.NumValues() == toPtr.InnerType.NumValues() {
			// Demote arrays to pointers
		} else if toType.IsVoidPointer() {
			// Demote arrays to void pointers
		} else {
			ec.report.ErrorSimple(30, fmt.Sprintf("Cannot convert type '%v' to '%v'", fromType, toType))
		}
	} else if fromType.IsPointer() && toType.IsVoidPointer() {
		// Demote pointers to void pointers
	} else if _, ok := fromType.(*CEnumType); ok {
		if _, ok := toType.(*CIntType); ok {
			// Enums act like ints
		} else {
			ec.report.ErrorSimple(30, fmt.Sprintf("Cannot convert type '%v' to '%v'", fromType, toType))
		}
	} else if _, ok := fromType.(*CFunctionType); ok {
		if _, ok := toType.(*CFunctionType); ok {
			// Function to function is OK
		} else {
			ec.report.ErrorSimple(30, fmt.Sprintf("Cannot convert type '%v' to '%v'", fromType, toType))
		}
	} else if fromPtr, ok := fromType.(*CPointerType); ok {
		if toPtr, ok := toType.(*CPointerType); ok {
			if derivedStruct, ok1 := fromPtr.InnerType.(*CStructType); ok1 {
				if baseStruct, ok2 := toPtr.InnerType.(*CStructType); ok2 {
					if derivedStruct.IsDerivedFrom(baseStruct) {
						return // Derived pointer to base pointer (implicit upcast)
					}
				}
			}
		}
		ec.report.ErrorSimple(30, fmt.Sprintf("Cannot convert type '%v' to '%v'", fromType, toType))
	} else if fromRef, ok := fromType.(*CReferenceType); ok {
		if fromRef.InnerType.EqualType(toType) {
			ec.Emit(OpCodeLoadPointer, ValueOf(0))
		} else {
			ec.Emit(OpCodeLoadPointer, ValueOf(0))
			ec.EmitCast(fromRef.InnerType, toType)
		}
	} else if toRef, ok := toType.(*CReferenceType); ok && fromType.EqualType(toRef.InnerType) {
		// Value to reference: no-op
	} else if _, ok := fromType.(*CStructType); ok {
		if _, ok := toType.(*CStructType); ok {
			// Struct to same struct type: no conversion
		} else {
			ec.report.ErrorSimple(30, fmt.Sprintf("Cannot convert type '%v' to '%v'", fromType, toType))
		}
	} else {
		ec.report.ErrorSimple(30, fmt.Sprintf("Cannot convert type '%v' to '%v'", fromType, toType))
	}
}

func (ec *EmitContext) EmitCastToBoolean(fromType CType) {
	ec.EmitCast(fromType, CBasicTypeBool)
}

// --- GetInstructionOffset ---

func (ec *EmitContext) GetInstructionOffset(ctype CType) int {
	size := ctype.GetByteSize(ec)

	if ctype.IsIntegral() {
		basic := ctype.GetBasicType()
		signed := true
		if basic != nil {
			signed = basic.Signedness == Signed
		}
		if signed {
			switch size {
			case 1:
				return 0
			case 2:
				return 2
			case 4:
				return 4
			case 8:
				return 6
			}
		} else {
			switch size {
			case 1:
				return 1
			case 2:
				return 3
			case 4:
				return 5
			case 8:
				return 7
			}
		}
	} else if _, ok := ctype.(*CPointerType); ok {
		switch ec.machineInfo.PointerSize {
		case 1:
			return 1
		case 2:
			return 3
		case 4:
			return 5
		case 8:
			return 7
		}
	} else {
		switch size {
		case 4:
			return 8
		case 8:
			return 9
		}
	}

	panic(fmt.Sprintf("Arithmetic on type '%v'", ctype))
}

// --- MakeCType ---

func (ec *EmitContext) MakeCType(specs *DeclarationSpecifiers, decl Declarator, init Initializer, block *Block) CType {
	type_ := ec.MakeCTypeFromSpecs(specs, init, block)
	return ec.MakeCTypeFromDecl(type_, decl, init, block)
}

func (ec *EmitContext) MakeCTypeFromDecl(type_ CType, decl Declarator, init Initializer, block *Block) CType {
	if decl == nil {
		return type_
	}

	switch d := decl.(type) {
	case *IdentifierDeclarator:
		// This is the name

	case *PointerDeclarator:
		isPointerToFunc := false

		if d.StrongBinding {
			type_ = ec.MakeCTypeFromDecl(type_, d.InnerDeclarator, nil, block)
			isPointerToFunc = IsCFunctionType(type_)
		}

		p := d.Pointer
		for p != nil {
			type_ = NewCPointerType(type_)
			type_.SetTypeQualifiers(p.TypeQualifiers)
			p = p.NextPointer
		}

		if !d.StrongBinding {
			type_ = ec.MakeCTypeFromDecl(type_, d.InnerDeclarator, nil, block)
		}

		if isPointerToFunc {
			type_ = type_.(*CPointerType).InnerType
		}

	case *ArrayDeclarator:
		adecl := d
		for adecl != nil {
			var length *int
			if adecl.LengthExpression != nil {
				if clen, ok := adecl.LengthExpression.(*ConstantExpression); ok {
					length = new(int(clen.EvalConstant(ec).Int64Value))
				} else {
					ec.report.ErrorSimple(2057, "Expected constant expression")
					length = new(0)
				}
			} else {
				if sinit, ok := init.(*StructuredInitializer); ok {
					length = new(len(sinit.Initializers))
				} else {
					ec.report.ErrorSimple(2057, "Expected constant expression")
					length = new(0)
				}
			}

			arrType := NewCArrayType(type_, length)
			if adecl.InnerDeclarator != nil {
				nextArray, nextOk := adecl.InnerDeclarator.(*ArrayDeclarator)
				if nextOk && nextArray != nil {
					type_ = arrType
					adecl = nextArray
					continue
				}
				if _, isId := adecl.InnerDeclarator.(*IdentifierDeclarator); isId {
					type_ = arrType
				} else {
					type_ = ec.MakeCTypeFromDecl(arrType, adecl.InnerDeclarator, nil, block)
				}
			} else {
				type_ = arrType
			}
			adecl = nil
		}

	case *FunctionDeclarator:
		type_ = ec.MakeCFunctionType(type_, decl, block)

	case *ReferenceDeclarator:
		type_ = NewCReferenceType(type_)
		type_.SetTypeQualifiers(d.Qualifiers)
		type_ = ec.MakeCTypeFromDecl(type_, d.InnerDeclarator, nil, block)
	}

	return type_
}

func (ec *EmitContext) MakeCFunctionType(returnType CType, decl Declarator, block *Block) CType {
	fdecl := decl.(*FunctionDeclarator)

	var declaringType CType
	if id, ok := fdecl.InnerDeclarator.(*IdentifierDeclarator); ok && len(id.Context) > 0 {
		declaringTypeName := id.Context[0]
		declaringType = ec.self.ResolveTypeNameString(declaringTypeName)
	}
	isInstance := declaringType != nil

	ftype := NewCFunctionType(returnType, isInstance, declaringType)
	for _, pdecl := range fdecl.Parameters {
		var pt CType
		if pdecl.DeclarationSpecifiers != nil {
			pt = ec.MakeCType(pdecl.DeclarationSpecifiers, pdecl.Declarator, nil, block)
		} else {
			pt = CBasicTypeSignedInt
		}
		if !pt.IsVoid() {
			var defaultValue *Value
			if pdecl.DefaultValue != nil {
				defaultValue = new(pdecl.DefaultValue.EvalConstant(ec))
			}
			ftype.AddParameter(pdecl.Name, pt, defaultValue)
		}
	}

	type_ := ec.MakeCTypeFromDecl(ftype, fdecl.InnerDeclarator, nil, block)
	return type_
}

func (ec *EmitContext) MakeCTypeFromSpecs(specs *DeclarationSpecifiers, init Initializer, block *Block) CType {
	// Infer types for auto
	if specs.StorageClassSpecifier == StorageClassSpecifierAuto {
		if einit, ok := init.(*ExpressionInitializer); ok {
			return einit.Expression.GetEvaluatedCType(ec)
		}
		ec.report.ErrorSimple(818, "Implicitly-typed variables must be initialized")
		return CBasicTypeSignedInt
	}

	// Try for basic types
	basicTs := findFirstBuiltinTypeSpecifier(specs.TypeSpecifiers)
	if basicTs != nil {
		if basicTs.Name == "void" {
			return VoidCType
		}
		sign := Signed
		size := ""
		var trueTs *TypeSpecifier

		for _, ts := range specs.TypeSpecifiers {
			switch ts.Name {
			case "unsigned":
				sign = Unsigned
			case "signed":
				sign = Signed
			case "short", "long":
				if size == "" {
					size = ts.Name
				} else {
					size = size + " " + ts.Name
				}
			default:
				if ts.Name == "int" {
					// int is implicit; skip
				} else {
					trueTs = ts
				}
			}
		}

		if size != "" && size != "short" && size != "long" && size != "long long" {
			ec.report.ErrorSimple(2078, "Invalid combination of type specifiers")
			size = ""
		}

		typeName := "int"
		if trueTs != nil {
			typeName = trueTs.Name
		}

		if size != "" && typeName == "float" {
			ec.report.ErrorSimple(2078, "Invalid combination of type specifiers")
			size = ""
		}
		if size != "" && typeName == "double" && size != "long" {
			ec.report.ErrorSimple(2078, "Invalid combination of type specifiers")
			size = ""
		}
		if size != "" && typeName == "bool" {
			ec.report.ErrorSimple(2078, "Invalid combination of type specifiers")
			size = ""
		}

		var type_ CType
		switch typeName {
		case "float":
			type_ = Float
		case "double":
			type_ = Double
		case "bool":
			type_ = Bool
		default:
			type_ = NewCIntType(typeName, sign, size)
		}
		type_.SetTypeQualifiers(specs.TypeQualifiers)
		return type_
	}

	// Structs, Classes, Unions
	structTs := findFirstStructOrClassTypeSpecifier(specs.TypeSpecifiers)
	if structTs != nil {
		if structTs.Body != nil {
			var st *CStructType
			if structTs.Name != "" && block != nil {
				if existing, ok := block.Structures[structTs.Name]; ok && len(existing.Members) == 0 {
					st = existing
				} else {
					st = NewCStructType(structTs.Name)
				}
			} else {
				st = NewCStructType(structTs.Name)
			}

			if structTs.Name != "" && block != nil {
				block.Structures[structTs.Name] = st
			}

			if structTs.BaseSpecifiers != nil {
				for _, baseSpec := range structTs.BaseSpecifiers {
					if st.BaseType != nil {
						ec.report.ErrorSimple(1500, "Multiple inheritance is not supported")
						continue
					}
					baseType := ec.self.ResolveTypeNameString(baseSpec.Name)
					if baseStructType, ok := baseType.(*CStructType); ok {
						st.BaseType = baseStructType
					} else {
						ec.report.ErrorSimpleFormat(246, "Base type '%s' is not a class or struct", baseSpec.Name)
					}
				}
			}

			for _, s := range structTs.Body.Statements {
				ec.AddStructMember(st, s, block)
			}
			st.BuildVTable()
			return st
		}

		name := structTs.Name
		if name != "" && block != nil {
			if structType, ok := block.Structures[name]; ok {
				return structType
			}
			fwdStruct := NewCStructType(name)
			block.Structures[name] = fwdStruct
			return fwdStruct
		}
		ec.report.ErrorSimple(246, fmt.Sprintf("'%s' not found", name))
		return CBasicTypeSignedInt
	}

	// Enums
	enumTs := findFirstEnumTypeSpecifier(specs.TypeSpecifiers)
	if enumTs != nil {
		if enumTs.Body != nil {
			et := NewCEnumType(enumTs.Name)
			enumContext := NewEnumContext(enumTs, et, ec)
			for _, s := range enumTs.Body.Statements {
				ec.AddEnumMember(et, s, block, enumContext)
			}
			return et
		}
		name := enumTs.Name
		if name != "" {
			if block != nil {
				if et, ok := block.Enums[name]; ok {
					return et
				}
			}
			// Fallback: try type name resolution through context chain
			if resolved := ec.self.ResolveTypeNameString(name); resolved != nil {
				if et, ok := resolved.(*CEnumType); ok {
					return et
				}
			}
		}
		ec.report.ErrorSimple(246, fmt.Sprintf("'%s' not found", name))
		return CBasicTypeSignedInt
	}

	// Typedefs
	typenameTs := findFirstTypenameTypeSpecifier(specs.TypeSpecifiers)
	if typenameTs != nil {
		return ec.self.ResolveTypeNameString(typenameTs.Name)
	}

	return VoidCType
}

func (ec *EmitContext) AddStructMember(st *CStructType, s Statement, block *Block) {
	if vds, ok := s.(*VirtualDeclarationStatement); ok {
		ec.AddStructMemberWithFlags(st, vds.InnerDeclaration, block, vds.IsVirtual, vds.IsOverride, vds.IsPureVirtual)
	} else {
		ec.AddStructMemberWithFlags(st, s, block, false, false, false)
	}
}

func (ec *EmitContext) AddStructMemberWithFlags(st *CStructType, s Statement, block *Block, isVirtual bool, isOverride bool, isPureVirtual bool) {
	switch stmt := s.(type) {
	case *MultiDeclaratorStatement:
		if stmt.InitDeclarators != nil {
			for _, i := range stmt.InitDeclarators {
				type_ := ec.MakeCType(stmt.Specifiers, i.Declarator, i.Initializer, block)
				name := i.Declarator.DeclaredIdentifier()
				if ftype, ok := type_.(*CFunctionType); ok {
					m := NewCStructMethod(name, type_)
					m.IsVirtual = isVirtual || isPureVirtual
					m.IsOverride = isOverride
					m.IsPureVirtual = isPureVirtual
					st.Members = append(st.Members, m)
					_ = ftype
				} else {
					st.Members = append(st.Members, NewCStructField(name, type_))
				}
			}
		}

	case *FunctionDefinition:
		methodType := ec.MakeCType(stmt.Specifiers, stmt.Declarator, nil, block)
		name := stmt.Declarator.DeclaredIdentifier()
		if ftype, ok := methodType.(*CFunctionType); ok {
			isStatic := stmt.Specifiers.StorageClassSpecifier == StorageClassSpecifierStatic
			if !isStatic && !ftype.IsInstance() {
				instanceFtype := NewCFunctionType(ftype.ReturnType, true, st)
				for _, p := range ftype.Parameters() {
					instanceFtype.AddParameter(p.Name, p.ParameterType, p.DefaultValue)
				}
				ftype = instanceFtype
			}
			st.Members = append(st.Members, &CStructMethod{
				CStructMemberBase: CStructMemberBase{name: name, memberType: ftype},
				IsVirtual:         isVirtual || isPureVirtual,
				IsOverride:        isOverride,
				IsPureVirtual:     isPureVirtual,
			})
			f := &CompiledFunction{Name: name, NameContext: st.Name, FunctionType: ftype, Body: stmt.Body}
			if block != nil {
				block.Functions = append(block.Functions, f)
			}
		}

	case *VisibilityStatement:
		// Ignoring visibility

	default:
		panic(fmt.Sprintf("Cannot add statement `%v` to struct", s))
	}
}

//goland:noinspection GoUnusedParameter
func (ec *EmitContext) AddEnumMember(et *CEnumType, s Statement, block *Block, enumContext *EnumContext) {
	if es, ok := s.(*EnumeratorStatement); ok {
		value := et.NextValue()
		if es.LiteralValue != nil {
			value = int(es.LiteralValue.EvalConstant(enumContext.EmitContext).Int64Value)
		}
		et.Members = append(et.Members, NewCEnumMember(es.Name, value))
	} else {
		panic(fmt.Sprintf("Cannot add statement `%v` to enum", s))
	}
}

// ============================================================================
// Helper functions for MakeCType
// ============================================================================

func findFirstBuiltinTypeSpecifier(specs []*TypeSpecifier) *TypeSpecifier {
	for _, ts := range specs {
		if ts.Kind == TypeSpecifierKindBuiltin {
			return ts
		}
	}
	return nil
}

func findFirstStructOrClassTypeSpecifier(specs []*TypeSpecifier) *TypeSpecifier {
	for _, ts := range specs {
		if ts.Kind == TypeSpecifierKindStruct || ts.Kind == TypeSpecifierKindClass || ts.Kind == TypeSpecifierKindUnion {
			return ts
		}
	}
	return nil
}

func findFirstEnumTypeSpecifier(specs []*TypeSpecifier) *TypeSpecifier {
	for _, ts := range specs {
		if ts.Kind == TypeSpecifierKindEnum {
			return ts
		}
	}
	return nil
}

func findFirstTypenameTypeSpecifier(specs []*TypeSpecifier) *TypeSpecifier {
	for _, ts := range specs {
		if ts.Kind == TypeSpecifierKindTypename {
			return ts
		}
	}
	return nil
}

// ============================================================================
// BlockContext — EmitContext with a Block for local variable resolution
// (Corresponds to CLanguage.Compiler.BlockContext)
// ============================================================================

type BlockContext struct {
	*EmitContext
	Block *Block
}

func NewBlockContext(block *Block, parent *EmitContext) *BlockContext {
	bc := &BlockContext{
		EmitContext: NewEmitContextFromParent(parent),
		Block:       block,
	}
	bc.self = bc
	return bc
}

func NewBlockContextFull(block *Block, mi *MachineInfo, report *Report, fdecl *CompiledFunction, parent *EmitContext) *BlockContext {
	bc := &BlockContext{
		EmitContext: NewEmitContext(mi, report, fdecl, parent),
		Block:       block,
	}
	bc.self = bc
	return bc
}

func (bc *BlockContext) ResolveTypeNameString(typeName string) CType {
	if t, ok := bc.Block.Typedefs[typeName]; ok {
		return t
	}
	if t, ok := bc.Block.Enums[typeName]; ok {
		return t
	}
	if t, ok := bc.Block.Structures[typeName]; ok {
		return t
	}
	return bc.EmitContext.ResolveTypeNameString(typeName)
}

func (bc *BlockContext) TryResolveVariable(name string, argTypes []CType) *ResolvedVariable {
	// Check enum members in this block scope
	for _, et := range bc.Block.Enums {
		for _, em := range et.Members {
			if em.Name == name {
				return &ResolvedVariable{Scope: VariableScopeConstant, Constant: ValueOf(int64(em.Value)), VariableType: et}
			}
		}
	}
	return bc.EmitContext.TryResolveVariable(name, argTypes)
}
