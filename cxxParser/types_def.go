package cxxParser

import (
	"fmt"
	"unsafe"
)

// Signedness represents C integer signedness (Unsigned=0, Signed=1).
type Signedness int

const (
	Unsigned Signedness = 0
	Signed   Signedness = 1
)

// CType is the interface for all C type representations.
type CType interface {
	GetByteSize(c *EmitContext) int
	NumValues() int
	ScoreCastTo(other CType) int
	Pointer() *CPointerType
	GetClrValue(values []Value, machineInfo *MachineInfo) any
	GetTypeQualifiers() TypeQualifiers
	SetTypeQualifiers(tq TypeQualifiers)
	IsIntegral() bool
	IsVoid() bool
	IsVoidPointer() bool
	IsPointer() bool
	EqualType(other CType) bool
	HashCode() int
	String() string
	GetBasicType() *CBasicType
}

// ---------------------------------------------------------------------------
// CTypeBase — embedded in all concrete CType structs; provides defaults.
// ---------------------------------------------------------------------------

type CTypeBase struct {
	self           CType
	TypeQualifiers TypeQualifiers
	ptrOnce        bool
	ptrVal         *CPointerType
}

func (b *CTypeBase) initBase(self CType) { b.self = self }

func (b *CTypeBase) GetTypeQualifiers() TypeQualifiers { return b.TypeQualifiers }

func (b *CTypeBase) SetTypeQualifiers(tq TypeQualifiers) { b.TypeQualifiers = tq }

func (b *CTypeBase) IsIntegral() bool { return false }

func (b *CTypeBase) IsVoid() bool { return false }

func (b *CTypeBase) IsVoidPointer() bool {
	if pt, ok := b.self.(*CPointerType); ok {
		return pt.InnerType.IsVoid() || pt.InnerType.IsVoidPointer()
	}
	return false
}

func (b *CTypeBase) IsPointer() bool {
	_, ok := b.self.(*CPointerType)
	return ok
}

func (b *CTypeBase) Pointer() *CPointerType {
	if !b.ptrOnce {
		b.ptrOnce = true
		b.ptrVal = NewCPointerType(b.self)
	}
	return b.ptrVal
}

func (b *CTypeBase) ScoreCastTo(other CType) int {
	if b.self.EqualType(other) {
		return 1000
	}
	return 0
}

//goland:noinspection GoUnusedParameter
func (b *CTypeBase) GetClrValue(values []Value, machineInfo *MachineInfo) any {
	panic(fmt.Sprintf("Cannot get CLR type from %v", b.self))
}

func (b *CTypeBase) EqualType(other CType) bool { return b.self == other }

func (b *CTypeBase) HashCode() int { return 17 }

func (b *CTypeBase) GetBasicType() *CBasicType { return nil }

// ---------------------------------------------------------------------------
// CVoidType
// ---------------------------------------------------------------------------

type CVoidType struct{ CTypeBase }

func NewCVoidType() *CVoidType {
	t := &CVoidType{}
	t.CTypeBase.initBase(t)
	return t
}

func (t *CVoidType) IsVoid() bool { return true }

func (t *CVoidType) NumValues() int { return 0 }

func (t *CVoidType) GetByteSize(c *EmitContext) int {
	c.GetReport().Error(2070, "'void': illegal sizeof operand")
	return 0
}

func (t *CVoidType) EqualType(other CType) bool {
	_, ok := other.(*CVoidType)
	return ok
}

func (t *CVoidType) HashCode() int { return 17 }

func (t *CVoidType) String() string { return "void" }

// ---------------------------------------------------------------------------
// CBasicType
// ---------------------------------------------------------------------------

type CBasicType struct {
	CTypeBase
	Name       string
	Signedness Signedness
	Size       string
}

func (b *CBasicType) EqualType(other CType) bool {
	ob := other.GetBasicType()
	return ob != nil && b.Name == ob.Name && b.Signedness == ob.Signedness && b.Size == ob.Size
}

func (b *CBasicType) HashCode() int {
	hash := 17
	for _, c := range b.Name {
		hash = hash*37 + int(c)
	}
	for _, c := range b.Size {
		hash = hash*37 + int(c)
	}
	hash = hash*37 + int(b.Signedness)
	return hash
}

func (b *CBasicType) GetBasicType() *CBasicType { return b }

func (b *CBasicType) IsIntegral() bool {
	if b.self != nil {
		switch b.self.(type) {
		case *CIntType, *CBoolType, *CEnumType:
			return true
		}
	}
	return b.Name == "int" || b.Name == "char" || b.Name == "bool"
}

func (b *CBasicType) GetByteSize(c *EmitContext) int {
	if b.self != nil {
		return b.self.GetByteSize(c)
	}
	return c.GetMachineInfo().IntSize
}

func (b *CBasicType) NumValues() int { return 1 }

func (b *CBasicType) String() string {
	if b.self.IsIntegral() {
		sign := "signed"
		if b.Signedness == Unsigned {
			sign = "unsigned"
		}
		if b.Size == "" {
			return sign + " " + b.Name
		}
		return sign + " " + b.Size + " " + b.Name
	}
	if b.Size == "" {
		return b.Name
	}
	return b.Size + " " + b.Name
}

// IntegerPromote — Section 6.3.1.1 (page 51) of N1570.
func (b *CBasicType) IntegerPromote(ctx *EmitContext) CType {
	if b.self.IsIntegral() {
		size := b.self.GetByteSize(ctx)
		intSize := ctx.GetMachineInfo().IntSize
		if size < intSize {
			return SignedInt
		} else if size == intSize {
			if b.Signedness == Unsigned {
				return UnsignedInt
			}
			return SignedInt
		}
	}
	return b
}

// ArithmeticConvert — Section 6.3.1.8 (page 53) of N1570.
func (b *CBasicType) ArithmeticConvert(otherType CType, ctx *EmitContext) CType {
	otherBasicType := otherType.GetBasicType()
	if otherBasicType == nil {
		ctx.GetReport().Error(19, "Cannot perform arithmetic with "+fmt.Sprint(otherType))
		return SignedInt
	}
	if b.Name == "double" || otherBasicType.Name == "double" {
		return Double
	}
	if b.Name == "single" || otherBasicType.Name == "single" {
		return Float
	}

	p1 := b.IntegerPromote(ctx)
	p1b := p1.GetBasicType()
	size1 := p1b.GetByteSize(ctx)

	p2 := otherBasicType.IntegerPromote(ctx)
	p2b := p2.GetBasicType()
	size2 := p2b.GetByteSize(ctx)

	if p1b.Signedness == p2b.Signedness {
		if size1 >= size2 {
			return p1
		}
		return p2
	}

	if p1b.Signedness == Unsigned {
		if size1 > size2 {
			return p1
		}
		if size2 > size1 {
			return p2
		}
		return NewCIntType(p2b.Name, Unsigned, p2b.Size)
	}

	if size2 > size1 {
		return p2
	}
	if size1 > size2 {
		return p1
	}
	return NewCIntType(p1b.Name, Unsigned, p1b.Size)
}

// ---------------------------------------------------------------------------
// CIntType
// ---------------------------------------------------------------------------

type CIntType struct {
	CBasicType
}

func (t *CIntType) IsIntegral() bool { return true }

func NewCIntType(name string, signedness Signedness, size string) *CIntType {
	t := &CIntType{}
	t.Name = name
	t.Signedness = signedness
	t.Size = size
	t.CTypeBase.initBase(t)
	return t
}

func (t *CIntType) NumValues() int { return 1 }

func (t *CIntType) GetByteSizeFromMachine(m *MachineInfo) int {
	if t.Name == "char" {
		return m.CharSize
	}
	if t.Name == "int" {
		switch t.Size {
		case "short":
			return m.ShortIntSize
		case "long":
			return m.LongIntSize
		case "long long":
			return m.LongLongIntSize
		default:
			return m.IntSize
		}
	}
	panic(t.String())
}

func (t *CIntType) GetByteSize(c *EmitContext) int {
	return t.GetByteSizeFromMachine(c.GetMachineInfo())
}

func (t *CIntType) ScoreCastTo(other CType) int {
	if t.EqualType(other) {
		return 1000
	}
	if it, ok := other.(*CIntType); ok {
		if t.Name == it.Name && t.Size == it.Size {
			return 950
		}
		if t.Size == it.Size {
			return 900
		}
		return 800
	}
	if ft, ok := other.(*CFloatType); ok {
		if ft.Bits == 64 {
			return 400
		}
		return 300
	}
	if _, ok := other.(*CBoolType); ok {
		return 200
	}
	return 0
}

func (t *CIntType) GetClrValue(values []Value, machineInfo *MachineInfo) any {
	byteSize := t.GetByteSizeFromMachine(machineInfo)
	if t.Signedness == Signed {
		switch byteSize {
		case 1:
			return values[0].Int8Value()
		case 2:
			return values[0].Int16Value()
		case 4:
			return values[0].Int32Value()
		default:
			return values[0].Int64Value
		}
	}
	switch byteSize {
	case 1:
		return values[0].UInt8Value()
	case 2:
		return values[0].UInt16Value()
	case 4:
		return values[0].UInt32Value()
	default:
		return values[0].UInt64Value()
	}
}

// ---------------------------------------------------------------------------
// CFloatType
// ---------------------------------------------------------------------------

type CFloatType struct {
	CBasicType
	Bits int
}

func NewCFloatType(name string, bits int) *CFloatType {
	t := &CFloatType{Bits: bits}
	t.Name = name
	t.Signedness = Signed
	t.Size = ""
	t.CTypeBase.initBase(t)
	return t
}

func (t *CFloatType) NumValues() int { return 1 }

//goland:noinspection GoUnusedParameter
func (t *CFloatType) GetByteSize(c *EmitContext) int { return t.Bits / 8 }

func (t *CFloatType) ScoreCastTo(other CType) int {
	if t.EqualType(other) {
		return 1000
	}
	if _, ok := other.(*CFloatType); ok {
		return 900
	}
	return 0
}

// ---------------------------------------------------------------------------
// CBoolType
// ---------------------------------------------------------------------------

type CBoolType struct {
	CBasicType
}

func NewCBoolType() *CBoolType {
	t := &CBoolType{}
	t.Name = "bool"
	t.Signedness = Unsigned
	t.Size = ""
	t.CTypeBase.initBase(t)
	return t
}

func (t *CBoolType) IsIntegral() bool { return true }

func (t *CBoolType) NumValues() int { return 1 }

func (t *CBoolType) GetByteSize(c *EmitContext) int { return c.GetMachineInfo().CharSize }

func (t *CBoolType) String() string { return "bool" }

// ---------------------------------------------------------------------------
// CPointerType
// ---------------------------------------------------------------------------

type CPointerType struct {
	CTypeBase
	InnerType CType
}

func NewCPointerType(innerType CType) *CPointerType {
	t := &CPointerType{InnerType: innerType}
	t.CTypeBase.initBase(t)
	return t
}

func (t *CPointerType) NumValues() int { return 1 }

func (t *CPointerType) ScoreCastTo(other CType) int {
	if t.EqualType(other) {
		return 1000
	}
	// Derived* -> Base* (implicit pointer upcast)
	if opt, ok := other.(*CPointerType); ok {
		if derivedStruct, ok1 := t.InnerType.(*CStructType); ok1 {
			if baseStruct, ok2 := opt.InnerType.(*CStructType); ok2 {
				if derivedStruct.IsDerivedFrom(baseStruct) {
					return 900
				}
			}
		}
	}
	return 0
}

func (t *CPointerType) GetByteSize(c *EmitContext) int { return c.GetMachineInfo().PointerSize }

func (t *CPointerType) EqualType(other CType) bool {
	if ot, ok := other.(*CPointerType); ok {
		return t.InnerType.EqualType(ot.InnerType)
	}
	return false
}

func (t *CPointerType) HashCode() int {
	hash := 17
	hash = hash*37 + t.InnerType.HashCode()
	hash = hash*37 + 1
	return hash
}

func (t *CPointerType) String() string { return fmt.Sprint(t.InnerType) + "*" }

// ---------------------------------------------------------------------------
// CArrayType
// ---------------------------------------------------------------------------

type CArrayType struct {
	CTypeBase
	ElementType CType
	Length      *int
}

func NewCArrayType(elementType CType, length *int) *CArrayType {
	t := &CArrayType{ElementType: elementType, Length: length}
	t.CTypeBase.initBase(t)
	return t
}

func (t *CArrayType) NumValues() int {
	if t.Length == nil {
		return 1
	}
	return *t.Length * t.ElementType.NumValues()
}

func (t *CArrayType) Pointer() *CPointerType { return t.ElementType.Pointer() }

func (t *CArrayType) GetByteSize(c *EmitContext) int {
	if t.Length == nil {
		return c.GetMachineInfo().PointerSize
	}
	return *t.Length * t.ElementType.GetByteSize(c)
}

func (t *CArrayType) ScoreCastTo(other CType) int {
	if t.EqualType(other) {
		return 1000
	}
	if pt, ok := other.(*CPointerType); ok {
		if t.ElementType.EqualType(pt.InnerType) {
			return 900
		}
		return t.ElementType.ScoreCastTo(pt.InnerType) / 2
	}
	return 0
}

func (t *CArrayType) EqualType(other CType) bool {
	if ot, ok := other.(*CArrayType); ok {
		if (t.Length == nil) != (ot.Length == nil) {
			return false
		}
		if t.Length != nil && *t.Length != *ot.Length {
			return false
		}
		return t.ElementType.EqualType(ot.ElementType)
	}
	return false
}

func (t *CArrayType) HashCode() int {
	hash := 17
	hash = hash*37 + t.ElementType.HashCode()
	if t.Length != nil {
		hash = hash*37 + *t.Length
	} else {
		hash = hash*37 + 0
	}
	return hash
}

func (t *CArrayType) String() string {
	return fmt.Sprintf("%v[%v]", t.ElementType, t.Length)
}

// ---------------------------------------------------------------------------
// CStructMember interface + implementations
// ---------------------------------------------------------------------------

type CStructMember interface {
	GetName() string
	SetName(string)
	GetMemberType() CType
	SetMemberType(CType)
	String() string
}

type CStructMemberBase struct {
	name       string
	memberType CType
}

func (b *CStructMemberBase) GetName() string       { return b.name }
func (b *CStructMemberBase) SetName(n string)      { b.name = n }
func (b *CStructMemberBase) GetMemberType() CType  { return b.memberType }
func (b *CStructMemberBase) SetMemberType(t CType) { b.memberType = t }
func (b *CStructMemberBase) String() string        { return fmt.Sprintf("%v %s", b.memberType, b.name) }

type CStructField struct {
	CStructMemberBase
}

func NewCStructField(name string, memberType CType) *CStructField {
	t := &CStructField{}
	t.name = name
	t.memberType = memberType
	return t
}

type CStructMethod struct {
	CStructMemberBase
	IsVirtual       bool
	IsOverride      bool
	IsPureVirtual   bool
	VTableSlotIndex int // -1 = nil/unset
}

func NewCStructMethod(name string, memberType CType) *CStructMethod {
	t := &CStructMethod{VTableSlotIndex: -1}
	t.name = name
	t.memberType = memberType
	return t
}

// ---------------------------------------------------------------------------
// CStructType
// ---------------------------------------------------------------------------

type CStructType struct {
	CTypeBase
	Name                string
	Members             []CStructMember
	BaseType            *CStructType
	VTable_             *VTable
	VTableGlobalAddress *int
}

func NewCStructType(name string) *CStructType {
	t := &CStructType{Name: name}
	t.CTypeBase.initBase(t)
	return t
}

func (t *CStructType) HasVTable() bool { return t.VTable_ != nil && len(t.VTable_.Entries) > 0 }
func (t *CStructType) VTable() *VTable { return t.VTable_ }

func (t *CStructType) IsPolymorphic() bool {
	if t.HasVTable() {
		return true
	}
	if t.BaseType != nil {
		return t.BaseType.IsPolymorphic()
	}
	return false
}

func (t *CStructType) EqualType(other CType) bool {
	if t == other {
		return true
	}
	ot, ok := other.(*CStructType)
	return ok && t.Name != "" && t.Name == ot.Name
}

func (t *CStructType) HashCode() int {
	if t.Name == "" {
		return int(uintptr(unsafe.Pointer(t)))
	}
	hash := 17
	for _, c := range t.Name {
		hash = hash*37 + int(c)
	}
	return hash
}

// IsDerivedFrom returns true if this type derives from the given type.
func (t *CStructType) IsDerivedFrom(other *CStructType) bool {
	for b := t.BaseType; b != nil; b = b.BaseType {
		if b == other {
			return true
		}
	}
	return false
}

// FindMember searches this type and its base types for a member with the given name.
func (t *CStructType) FindMember(name string) CStructMember {
	for ct := t; ct != nil; ct = ct.BaseType {
		for _, m := range ct.Members {
			if m.GetName() == name {
				return m
			}
		}
	}
	return nil
}

// FindMethods searches this type and its base types for methods with the given name.
func (t *CStructType) FindMethods(name string) []*CStructMethod {
	var methods []*CStructMethod
	for ct := t; ct != nil; ct = ct.BaseType {
		for _, mem := range ct.Members {
			if meth, ok := mem.(*CStructMethod); ok && meth.GetName() == name {
				methods = append(methods, meth)
			}
		}
		if len(methods) > 0 {
			break
		}
	}
	return methods
}

func (t *CStructType) String() string {
	if t.Name == "" {
		return "struct"
	}
	return t.Name
}

func (t *CStructType) GetOwnFieldsNumValues() int {
	s := 0
	for _, m := range t.Members {
		if _, ok := m.(*CStructField); ok {
			s += m.GetMemberType().NumValues()
		}
	}
	return s
}

func (t *CStructType) GetOwnFieldsByteSize(c *EmitContext) int {
	s := 0
	for _, m := range t.Members {
		if _, ok := m.(*CStructField); ok {
			s += m.GetMemberType().GetByteSize(c)
		}
	}
	return s
}

func (t *CStructType) NumValues() int {
	if !t.IsPolymorphic() && t.BaseType == nil {
		return t.GetOwnFieldsNumValues()
	}
	total := 0
	if t.IsPolymorphic() {
		total = 1 // vptr
	}
	if t.BaseType != nil {
		total += t.BaseType.GetOwnFieldsNumValues()
	}
	total += t.GetOwnFieldsNumValues()
	return total
}

func (t *CStructType) GetByteSize(c *EmitContext) int {
	if !t.IsPolymorphic() && t.BaseType == nil {
		return t.GetOwnFieldsByteSize(c)
	}
	total := 0
	if t.IsPolymorphic() {
		total = c.GetMachineInfo().PointerSize // vptr
	}
	if t.BaseType != nil {
		total += t.BaseType.GetOwnFieldsByteSize(c)
	}
	total += t.GetOwnFieldsByteSize(c)
	return total
}

func (t *CStructType) GetFieldValueOffset(member CStructMember, c *EmitContext) int {
	if !t.IsPolymorphic() && t.BaseType == nil {
		offset := 0
		for _, m := range t.Members {
			if _, ok := m.(*CStructField); ok {
				if m == member {
					return offset
				}
				offset += m.GetMemberType().NumValues()
			}
		}
		panic(fmt.Sprintf("Member '%s' not found", member.GetName()))
	}
	return t.getFieldValueOffsetPolymorphic(member, c)
}

func (t *CStructType) getFieldValueOffsetPolymorphic(member CStructMember, c *EmitContext) int {
	offset := 0
	if t.IsPolymorphic() {
		offset = 1 // skip vptr
	}

	// Base class fields first
	if t.BaseType != nil {
		baseOffset := t.BaseType.findFieldValueOffset(member, c)
		if baseOffset >= 0 {
			return offset + baseOffset
		}
		offset += t.BaseType.GetOwnFieldsNumValues()
	}

	// Own fields
	for _, m := range t.Members {
		if _, ok := m.(*CStructField); ok {
			if m == member {
				return offset
			}
			offset += m.GetMemberType().NumValues()
		}
	}
	panic(fmt.Sprintf("Member '%s' not found", member.GetName()))
}

func (t *CStructType) findFieldValueOffset(member CStructMember, c *EmitContext) int {
	offset := 0
	if t.BaseType != nil {
		baseOffset := t.BaseType.findFieldValueOffset(member, c)
		if baseOffset >= 0 {
			return offset + baseOffset
		}
		offset += t.BaseType.GetOwnFieldsNumValues()
	}
	for _, m := range t.Members {
		if _, ok := m.(*CStructField); ok {
			if m == member {
				return offset
			}
			offset += m.GetMemberType().NumValues()
		}
	}
	return -1
}

// BuildVTable builds the vtable for this type by inheriting base vtable entries,
// replacing overridden slots, and appending new virtual methods.
func (t *CStructType) BuildVTable() {
	var entries []*VTableEntry

	// Copy base vtable entries
	if t.BaseType != nil && t.BaseType.VTable_ != nil {
		for _, baseEntry := range t.BaseType.VTable_.Entries {
			entries = append(entries, &VTableEntry{
				SlotIndex:     baseEntry.SlotIndex,
				MethodName:    baseEntry.MethodName,
				Signature:     baseEntry.Signature,
				DeclaringType: baseEntry.DeclaringType,
			})
		}
	}

	// Process virtual and override methods
	for _, m := range t.Members {
		if method, ok := m.(*CStructMethod); ok {
			sig, okSig := method.GetMemberType().(*CFunctionType)
			if !okSig {
				continue
			}
			if method.IsOverride {
				slot := -1
				for i, e := range entries {
					if e.MethodName == method.GetName() && e.Signature.ParameterTypesEqual(sig) {
						slot = i
						break
					}
				}
				if slot < 0 {
					panic(fmt.Sprintf("Override method '%s' does not match any base virtual method in '%s'", method.GetName(), t.Name))
				}
				entries[slot] = &VTableEntry{
					SlotIndex:     slot,
					MethodName:    method.GetName(),
					Signature:     sig,
					DeclaringType: t,
				}
				method.VTableSlotIndex = slot
			} else if method.IsVirtual {
				slot := -1
				for i, e := range entries {
					if e.MethodName == method.GetName() && e.Signature.ParameterTypesEqual(sig) {
						slot = i
						break
					}
				}
				if slot >= 0 {
					entries[slot] = &VTableEntry{
						SlotIndex:     slot,
						MethodName:    method.GetName(),
						Signature:     sig,
						DeclaringType: t,
					}
					method.VTableSlotIndex = slot
				} else {
					newSlot := len(entries)
					entries = append(entries, &VTableEntry{
						SlotIndex:     newSlot,
						MethodName:    method.GetName(),
						Signature:     sig,
						DeclaringType: t,
					})
					method.VTableSlotIndex = newSlot
				}
			}
		}
	}

	if len(entries) > 0 {
		t.VTable_ = &VTable{Entries: entries}
	} else {
		t.VTable_ = nil
	}
}

// ---------------------------------------------------------------------------
// CEnumMember
// ---------------------------------------------------------------------------

type CEnumMember struct {
	Name  string
	Value int
}

func NewCEnumMember(name string, value int) *CEnumMember {
	return &CEnumMember{Name: name, Value: value}
}

func (m *CEnumMember) String() string { return fmt.Sprintf("%s = %d", m.Name, m.Value) }

// ---------------------------------------------------------------------------
// CEnumType
// ---------------------------------------------------------------------------

type CEnumType struct {
	CTypeBase
	Name    string
	Members []*CEnumMember
}

func NewCEnumType(name string) *CEnumType {
	t := &CEnumType{Name: name}
	t.CTypeBase.initBase(t)
	return t
}

func (t *CEnumType) NextValue() int {
	if len(t.Members) > 0 {
		return t.Members[len(t.Members)-1].Value + 1
	}
	return 0
}

func (t *CEnumType) NumValues() int   { return 1 }
func (t *CEnumType) IsIntegral() bool { return true }

func (t *CEnumType) GetByteSize(c *EmitContext) int { return SignedInt.GetByteSize(c) }

func (t *CEnumType) String() string { return t.Name }

// ---------------------------------------------------------------------------
// CFunctionType + Parameter
// ---------------------------------------------------------------------------

type CFunctionTypeParameter struct {
	Name          string
	ParameterType CType
	Offset        int
	DefaultValue  *Value
}

func NewCFunctionTypeParameter(name string, parameterType CType, defaultValue *Value) *CFunctionTypeParameter {
	return &CFunctionTypeParameter{
		Name:          name,
		ParameterType: parameterType,
		DefaultValue:  defaultValue,
	}
}

func (p *CFunctionTypeParameter) String() string {
	return fmt.Sprintf("%v %s", p.ParameterType, p.Name)
}

type CFunctionType struct {
	CTypeBase
	ReturnType    CType
	parameters    []*CFunctionTypeParameter
	IsInstance_   bool
	DeclaringType CType // *CStructType, nil for non-member functions
}

func NewCFunctionType(returnType CType, isInstance bool, declaringType CType) *CFunctionType {
	if isInstance && declaringType == nil {
		panic("declaringType must not be nil for instance methods")
	}
	t := &CFunctionType{
		ReturnType:    returnType,
		IsInstance_:   isInstance,
		DeclaringType: declaringType,
	}
	t.CTypeBase.initBase(t)
	return t
}

func (t *CFunctionType) NumValues() int { return 1 }

func (t *CFunctionType) IsInstance() bool { return t.IsInstance_ }

func (t *CFunctionType) Parameters() []*CFunctionTypeParameter {
	return t.parameters
}

func (t *CFunctionType) AddParameter(name string, ctype CType, defaultValue *Value) {
	t.parameters = append(t.parameters, &CFunctionTypeParameter{
		Name:          name,
		ParameterType: ctype,
		DefaultValue:  defaultValue,
	})
	t.calculateParameterOffsets()
}

func (t *CFunctionType) calculateParameterOffsets() {
	offset := 0
	if t.IsInstance_ {
		offset = -1
	}
	for i := len(t.parameters) - 1; i >= 0; i-- {
		p := t.parameters[i]
		n := p.ParameterType.NumValues()
		offset -= n
		p.Offset = offset
	}
}

func (t *CFunctionType) GetByteSize(c *EmitContext) int { return c.GetMachineInfo().PointerSize }

func (t *CFunctionType) EqualType(other CType) bool {
	if ot, ok := other.(*CFunctionType); ok {
		return t.ReturnType.EqualType(ot.ReturnType) && t.ParameterTypesEqual(ot)
	}
	return false
}

func (t *CFunctionType) HashCode() int {
	pcode := 17
	for _, p := range t.parameters {
		pcode += p.ParameterType.HashCode()
	}
	baseHash := int(uintptr(unsafe.Pointer(t)))
	return baseHash*13 + t.ReturnType.HashCode()*11 + pcode*7
}

func (t *CFunctionType) String() string {
	s := "(Function " + fmt.Sprint(t.ReturnType) + " ("
	head := ""
	for _, p := range t.parameters {
		s += head
		s += fmt.Sprint(p)
		head = ", "
	}
	s += "))"
	return s
}

func (t *CFunctionType) ScoreParameterTypeMatches(argTypes []CType) int {
	if argTypes == nil {
		return 1
	}
	pc := len(t.parameters)
	requiredParamCount := 0
	for i := 0; i < pc; i++ {
		if t.parameters[i].DefaultValue != nil {
			break
		}
		requiredParamCount++
	}

	if len(argTypes) < requiredParamCount || len(argTypes) > pc {
		return 0
	}

	score := 2
	if len(argTypes) == pc {
		score = 3
	}

	for i := 0; i < len(argTypes); i++ {
		ft := argTypes[i]
		tt := t.parameters[i].ParameterType
		if refTt, ok := tt.(*CReferenceType); ok {
			score += ft.ScoreCastTo(refTt.InnerType)
		} else {
			score += ft.ScoreCastTo(tt)
		}
	}
	return score
}

func (t *CFunctionType) ParameterTypesEqual(other *CFunctionType) bool {
	if len(t.parameters) != len(other.parameters) {
		return false
	}
	for i := 0; i < len(t.parameters); i++ {
		if !t.parameters[i].ParameterType.EqualType(other.parameters[i].ParameterType) {
			return false
		}
	}
	return true
}

// ---------------------------------------------------------------------------
// CReferenceType
// ---------------------------------------------------------------------------

type CReferenceType struct {
	CTypeBase
	InnerType CType
}

func NewCReferenceType(innerType CType) *CReferenceType {
	t := &CReferenceType{InnerType: innerType}
	t.CTypeBase.initBase(t)
	return t
}

func (t *CReferenceType) NumValues() int { return 1 }

func (t *CReferenceType) GetByteSize(c *EmitContext) int { return c.GetMachineInfo().PointerSize }

func (t *CReferenceType) ScoreCastTo(other CType) int {
	if otherRef, ok := other.(*CReferenceType); ok && t.InnerType.EqualType(otherRef.InnerType) {
		return 1000
	}
	if t.InnerType.EqualType(other) {
		return 900
	}
	if pt, ok := other.(*CPointerType); ok && t.InnerType.EqualType(pt.InnerType) {
		return 800
	}
	return t.InnerType.ScoreCastTo(other)
}

func (t *CReferenceType) EqualType(other CType) bool {
	if ot, ok := other.(*CReferenceType); ok {
		return t.InnerType.EqualType(ot.InnerType)
	}
	return false
}

func (t *CReferenceType) HashCode() int { return t.InnerType.HashCode() ^ 0x5A5A }

func (t *CReferenceType) String() string { return fmt.Sprintf("%v&", t.InnerType) }

// ---------------------------------------------------------------------------
// TypeHierarchyEntry
// ---------------------------------------------------------------------------

type TypeHierarchyEntry struct {
	TypeId     int
	BaseTypeId int
	TypeName   string
}

func NewTypeHierarchyEntry(typeId, baseTypeId int, typeName string) *TypeHierarchyEntry {
	return &TypeHierarchyEntry{
		TypeId:     typeId,
		BaseTypeId: baseTypeId,
		TypeName:   typeName,
	}
}

func (e *TypeHierarchyEntry) String() string {
	return fmt.Sprintf("TypeId=%d Base=%d Name=%s", e.TypeId, e.BaseTypeId, e.TypeName)
}

// ---------------------------------------------------------------------------
// VTableEntry
// ---------------------------------------------------------------------------

type VTableEntry struct {
	SlotIndex     int
	MethodName    string
	Signature     *CFunctionType
	DeclaringType *CStructType
}

func NewVTableEntry(slotIndex int, methodName string, signature *CFunctionType, declaringType *CStructType) *VTableEntry {
	return &VTableEntry{
		SlotIndex:     slotIndex,
		MethodName:    methodName,
		Signature:     signature,
		DeclaringType: declaringType,
	}
}

func (e *VTableEntry) String() string {
	return fmt.Sprintf("vtable[%d] %s (from %s)", e.SlotIndex, e.MethodName, e.DeclaringType)
}

// ---------------------------------------------------------------------------
// VTable
// Runtime vtable layout: [0]=type_id, [1..]=method pointers
// ---------------------------------------------------------------------------

type VTable struct {
	TypeId  int
	Entries []*VTableEntry
}

func (t *VTable) Count() int                   { return len(t.Entries) }
func (t *VTable) RuntimeSlotCount() int        { return 1 + len(t.Entries) }
func (t *VTable) Entry(index int) *VTableEntry { return t.Entries[index] }

// ---------------------------------------------------------------------------
// Package-level static CType instances  (analogous to C# static readonly fields)
// ---------------------------------------------------------------------------

//goland:noinspection GoUnusedGlobalVariable
var (
	VoidCType CType = NewCVoidType()

	ConstChar *CIntType = func() *CIntType {
		c := NewCIntType("char", Signed, "")
		c.TypeQualifiers = TypeQualifiersConst
		return c
	}()
	UnsignedChar        *CIntType = NewCIntType("char", Unsigned, "")
	SignedChar          *CIntType = NewCIntType("char", Signed, "")
	UnsignedShortInt    *CIntType = NewCIntType("int", Unsigned, "short")
	SignedShortInt      *CIntType = NewCIntType("int", Signed, "short")
	UnsignedInt         *CIntType = NewCIntType("int", Unsigned, "")
	SignedInt           *CIntType = NewCIntType("int", Signed, "")
	UnsignedLongInt     *CIntType = NewCIntType("int", Unsigned, "long")
	SignedLongInt       *CIntType = NewCIntType("int", Signed, "long")
	UnsignedLongLongInt *CIntType = NewCIntType("int", Unsigned, "long long")
	SignedLongLongInt   *CIntType = NewCIntType("int", Signed, "long long")

	Float  *CFloatType = NewCFloatType("float", 32)
	Double *CFloatType = NewCFloatType("double", 64)
	Bool   *CBoolType  = NewCBoolType()
)

var (
	PointerToConstChar *CPointerType  = NewCPointerType(ConstChar)
	PointerToVoid      *CPointerType  = NewCPointerType(VoidCType)
	VoidProcedure      *CFunctionType = NewCFunctionType(VoidCType, false, nil)
)
