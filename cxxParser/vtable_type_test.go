package cxxParser

import (
	"strings"
	"testing"

	"github.com/stretchr/testify/assert"
)

func vtableMakeField(name string, memberType CType) *CStructField {
	return NewCStructField(name, memberType)
}

func vtableMakeVirtualMethod(name string, sig *CFunctionType) *CStructMethod {
	m := NewCStructMethod(name, sig)
	m.IsVirtual = true
	return m
}

func vtableMakeOverrideMethod(name string, sig *CFunctionType) *CStructMethod {
	m := NewCStructMethod(name, sig)
	m.IsOverride = true
	return m
}

func vtableMakeMethodSig(declaringType *CStructType) *CFunctionType {
	return NewCFunctionType(SignedInt, true, declaringType)
}

func Test_NonPolymorphicStructIsNotPolymorphic(t *testing.T) {
	s := NewCStructType("Plain")
	s.Members = append(s.Members, vtableMakeField("x", SignedInt))
	s.Members = append(s.Members, vtableMakeField("y", SignedInt))
	assert.False(t, s.IsPolymorphic())
	assert.False(t, s.HasVTable())
}

func Test_NonPolymorphicNumValuesUnchanged(t *testing.T) {
	s := NewCStructType("Plain")
	s.Members = append(s.Members, vtableMakeField("x", SignedInt))
	s.Members = append(s.Members, vtableMakeField("y", SignedInt))
	assert.Equal(t, 2, s.NumValues())
}

func Test_NonPolymorphicFieldOffsetUnchanged(t *testing.T) {
	s := NewCStructType("Plain")
	fx := vtableMakeField("x", SignedInt)
	fy := vtableMakeField("y", SignedInt)
	s.Members = append(s.Members, fx)
	s.Members = append(s.Members, fy)
	ec := NewEmitContext(NewMachineInfo(), NewReport(nil), nil, nil)
	assert.Equal(t, 0, s.GetFieldValueOffset(fx, ec))
	assert.Equal(t, 1, s.GetFieldValueOffset(fy, ec))
}

func Test_TypeWithVTableIsPolymorphic(t *testing.T) {
	s := NewCStructType("Base")
	method := vtableMakeVirtualMethod("foo", vtableMakeMethodSig(s))
	s.Members = append(s.Members, method)
	s.BuildVTable()
	assert.True(t, s.HasVTable())
	assert.True(t, s.IsPolymorphic())
}

func Test_DerivedFromPolymorphicIsPolymorphic(t *testing.T) {
	baseType := NewCStructType("Base")
	method := vtableMakeVirtualMethod("foo", vtableMakeMethodSig(baseType))
	baseType.Members = append(baseType.Members, method)
	baseType.BuildVTable()

	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	derived.Members = append(derived.Members, vtableMakeField("z", SignedInt))
	derived.BuildVTable()

	assert.True(t, derived.IsPolymorphic())
}

func Test_NonVirtualDerivedIsNotPolymorphic(t *testing.T) {
	baseType := NewCStructType("Base")
	baseType.Members = append(baseType.Members, vtableMakeField("x", SignedInt))

	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	derived.Members = append(derived.Members, vtableMakeField("y", SignedInt))

	assert.False(t, derived.IsPolymorphic())
	assert.Nil(t, baseType.VTable_)
}

func Test_PolymorphicNumValuesIncludesVptr(t *testing.T) {
	s := NewCStructType("Base")
	s.Members = append(s.Members, vtableMakeField("x", SignedInt))
	method := vtableMakeVirtualMethod("foo", vtableMakeMethodSig(s))
	s.Members = append(s.Members, method)
	s.BuildVTable()

	assert.Equal(t, 2, s.NumValues())
}

func Test_DerivedNumValuesIncludesBaseFields(t *testing.T) {
	baseType := NewCStructType("Base")
	baseType.Members = append(baseType.Members, vtableMakeField("x", SignedInt))
	method := vtableMakeVirtualMethod("foo", vtableMakeMethodSig(baseType))
	baseType.Members = append(baseType.Members, method)
	baseType.BuildVTable()

	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	derived.Members = append(derived.Members, vtableMakeField("y", SignedInt))
	derived.BuildVTable()

	assert.Equal(t, 3, derived.NumValues())
}

func Test_PolymorphicFieldOffsetSkipsVptr(t *testing.T) {
	s := NewCStructType("Base")
	fx := vtableMakeField("x", SignedInt)
	s.Members = append(s.Members, fx)
	method := vtableMakeVirtualMethod("foo", vtableMakeMethodSig(s))
	s.Members = append(s.Members, method)
	s.BuildVTable()

	ec := NewEmitContext(NewMachineInfo(), NewReport(nil), nil, nil)
	assert.Equal(t, 1, s.GetFieldValueOffset(fx, ec))
}

func Test_DerivedFieldOffsetIncludesBaseFields(t *testing.T) {
	baseType := NewCStructType("Base")
	fx := vtableMakeField("x", SignedInt)
	baseType.Members = append(baseType.Members, fx)
	method := vtableMakeVirtualMethod("foo", vtableMakeMethodSig(baseType))
	baseType.Members = append(baseType.Members, method)
	baseType.BuildVTable()

	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	fy := vtableMakeField("y", SignedInt)
	derived.Members = append(derived.Members, fy)
	derived.BuildVTable()

	ec := NewEmitContext(NewMachineInfo(), NewReport(nil), nil, nil)
	assert.Equal(t, 1, derived.GetFieldValueOffset(fx, ec))
	assert.Equal(t, 2, derived.GetFieldValueOffset(fy, ec))
}

func Test_BuildVTableCreatesSlots(t *testing.T) {
	s := NewCStructType("Base")
	sig := vtableMakeMethodSig(s)
	m1 := vtableMakeVirtualMethod("foo", sig)
	m2 := vtableMakeVirtualMethod("bar", sig)
	s.Members = append(s.Members, m1)
	s.Members = append(s.Members, m2)
	s.BuildVTable()

	assert.NotNil(t, s.VTable_)
	assert.Equal(t, 2, s.VTable_.Count())
	assert.Equal(t, "foo", s.VTable_.Entries[0].MethodName)
	assert.Equal(t, "bar", s.VTable_.Entries[1].MethodName)
	assert.Equal(t, 0, m1.VTableSlotIndex)
	assert.Equal(t, 1, m2.VTableSlotIndex)
}

func Test_BuildVTableInheritsBaseSlots(t *testing.T) {
	baseType := NewCStructType("Base")
	baseSig := vtableMakeMethodSig(baseType)
	baseFoo := vtableMakeVirtualMethod("foo", baseSig)
	baseType.Members = append(baseType.Members, baseFoo)
	baseType.BuildVTable()

	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	derivedSig := vtableMakeMethodSig(derived)
	derivedBar := vtableMakeVirtualMethod("bar", derivedSig)
	derived.Members = append(derived.Members, derivedBar)
	derived.BuildVTable()

	assert.NotNil(t, derived.VTable_)
	assert.Equal(t, 2, derived.VTable_.Count())
	assert.Equal(t, "foo", derived.VTable_.Entries[0].MethodName)
	assert.Equal(t, "bar", derived.VTable_.Entries[1].MethodName)
	assert.Equal(t, 0, derived.VTable_.Entries[0].SlotIndex)
	assert.Equal(t, 1, derived.VTable_.Entries[1].SlotIndex)
}

func Test_BuildVTableOverridesBaseSlot(t *testing.T) {
	baseType := NewCStructType("Base")
	baseSig := vtableMakeMethodSig(baseType)
	baseFoo := vtableMakeVirtualMethod("foo", baseSig)
	baseType.Members = append(baseType.Members, baseFoo)
	baseType.BuildVTable()

	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	derivedSig := vtableMakeMethodSig(derived)
	derivedFoo := vtableMakeVirtualMethod("foo", derivedSig)
	derived.Members = append(derived.Members, derivedFoo)
	derived.BuildVTable()

	assert.NotNil(t, derived.VTable_)
	assert.Equal(t, 1, derived.VTable_.Count())
	assert.Equal(t, "foo", derived.VTable_.Entries[0].MethodName)
	assert.Equal(t, derived, derived.VTable_.Entries[0].DeclaringType)
	assert.Equal(t, 0, derivedFoo.VTableSlotIndex)
}

func Test_BuildVTableExplicitOverride(t *testing.T) {
	baseType := NewCStructType("Base")
	baseSig := vtableMakeMethodSig(baseType)
	baseFoo := vtableMakeVirtualMethod("foo", baseSig)
	baseType.Members = append(baseType.Members, baseFoo)
	baseType.BuildVTable()

	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	derivedSig := vtableMakeMethodSig(derived)
	derivedFoo := vtableMakeOverrideMethod("foo", derivedSig)
	derived.Members = append(derived.Members, derivedFoo)
	derived.BuildVTable()

	assert.Equal(t, 1, derived.VTable_.Count())
	assert.Equal(t, derived, derived.VTable_.Entries[0].DeclaringType)
	assert.Equal(t, 0, derivedFoo.VTableSlotIndex)
}

func Test_BuildVTableWithNoVirtualMethodsProducesNull(t *testing.T) {
	s := NewCStructType("Plain")
	s.Members = append(s.Members, vtableMakeField("x", SignedInt))
	s.BuildVTable()
	assert.Nil(t, s.VTable_)
	assert.False(t, s.HasVTable())
	assert.False(t, s.IsPolymorphic())
}

func Test_GetOwnFieldsNumValuesExcludesMethods(t *testing.T) {
	s := NewCStructType("S")
	s.Members = append(s.Members, vtableMakeField("x", SignedInt))
	s.Members = append(s.Members, NewCStructMethod("foo", vtableMakeMethodSig(s)))
	s.Members = append(s.Members, vtableMakeField("y", SignedInt))
	assert.Equal(t, 2, s.GetOwnFieldsNumValues())
}

func Test_CStructMethodDefaultFlags(t *testing.T) {
	method := NewCStructMethod("foo", nil)
	assert.False(t, method.IsVirtual)
	assert.False(t, method.IsOverride)
	assert.False(t, method.IsPureVirtual)
	assert.Equal(t, -1, method.VTableSlotIndex)
}

func Test_BaseTypeDefaultsToNull(t *testing.T) {
	s := NewCStructType("S")
	assert.Nil(t, s.BaseType)
}

func Test_BaseTypeCanBeSet(t *testing.T) {
	baseType := NewCStructType("Base")
	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	assert.Same(t, baseType, derived.BaseType)
}

func Test_NonPolymorphicBaseNumValues(t *testing.T) {
	baseType := NewCStructType("Base")
	baseType.Members = append(baseType.Members, vtableMakeField("x", SignedInt))

	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	derived.Members = append(derived.Members, vtableMakeField("y", SignedInt))

	assert.Equal(t, 2, derived.NumValues())
}

func Test_NonPolymorphicBaseFieldOffset(t *testing.T) {
	baseType := NewCStructType("Base")
	fx := vtableMakeField("x", SignedInt)
	baseType.Members = append(baseType.Members, fx)

	derived := NewCStructType("Derived")
	derived.BaseType = baseType
	fy := vtableMakeField("y", SignedInt)
	derived.Members = append(derived.Members, fy)

	ec := NewEmitContext(NewMachineInfo(), NewReport(nil), nil, nil)
	assert.Equal(t, 0, derived.GetFieldValueOffset(fx, ec))
	assert.Equal(t, 1, derived.GetFieldValueOffset(fy, ec))
}

func Test_VTableEntryToString(t *testing.T) {
	s := NewCStructType("Base")
	sig := vtableMakeMethodSig(s)
	entry := NewVTableEntry(0, "foo", sig, s)
	str := entry.String()
	assert.True(t, strings.Contains(str, "foo"))
	assert.True(t, strings.Contains(str, "0"))
}
