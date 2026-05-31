<?php /** @noinspection PhpStatementHasEmptyBodyInspection */
declare(strict_types=1);

namespace CLanguage\Compiler;

use BadMethodCallException;
use CLanguage\Interpreter\CompiledFunction;
use CLanguage\Interpreter\Instruction;
use CLanguage\Interpreter\Label;
use CLanguage\Interpreter\OpCode;
use CLanguage\MachineInfo;
use CLanguage\Report;
use CLanguage\Syntax\ArrayDeclarator;
use CLanguage\Syntax\Block;
use CLanguage\Syntax\ConstantExpression;
use CLanguage\Syntax\DeclarationSpecifiers;
use CLanguage\Syntax\Declarator;
use CLanguage\Syntax\EnumeratorStatement;
use CLanguage\Syntax\ExpressionInitializer;
use CLanguage\Syntax\FunctionDeclarator;
use CLanguage\Syntax\FunctionDefinition;
use CLanguage\Syntax\IdentifierDeclarator;
use CLanguage\Syntax\Initializer;
use CLanguage\Syntax\MultiDeclaratorStatement;
use CLanguage\Syntax\PointerDeclarator;
use CLanguage\Syntax\ReferenceDeclarator;
use CLanguage\Syntax\Statement;
use CLanguage\Syntax\StorageClassSpecifier;
use CLanguage\Syntax\StructuredInitializer;
use CLanguage\Syntax\TypeName;
use CLanguage\Syntax\TypeSpecifierKind;
use CLanguage\Syntax\VariableExpression;
use CLanguage\Syntax\VirtualDeclarationStatement;
use CLanguage\Syntax\VisibilityStatement;
use CLanguage\Types\CArrayType;
use CLanguage\Types\CBasicType;
use CLanguage\Types\CEnumMember;
use CLanguage\Types\CEnumType;
use CLanguage\Types\CFunctionType;
use CLanguage\Types\CIntType;
use CLanguage\Types\CPointerType;
use CLanguage\Types\CReferenceType;
use CLanguage\Types\CStructField;
use CLanguage\Types\CStructMethod;
use CLanguage\Types\CStructType;
use CLanguage\Types\CType;
use CLanguage\Types\Signedness;
use CLanguage\Value;
use Exception;
use InvalidArgumentException;

abstract class EmitContext
{
    public readonly ?EmitContext $parentContext;
    public readonly ?CompiledFunction $FunctionDecl;
    public readonly Report $Report;
    public readonly MachineInfo $MachineInfo;
    public ?Label $BreakLabel {
        get => $this->getBreakLabel();
    }
    public ?Label $ContinueLabel {
        get => $this->getContinueLabel();
    }

    public function __construct(
        ?MachineInfo      $machineInfo = null,
        ?Report           $report = null,
        ?CompiledFunction $fdecl = null,
        ?EmitContext      $parentContext = null,
    )
    {
        if ($parentContext !== null) {
            $this->MachineInfo = $parentContext->MachineInfo;
            $this->Report = $parentContext->Report;
            $this->FunctionDecl = $parentContext->FunctionDecl;
        } else {
            if ($machineInfo === null) {
                throw new InvalidArgumentException('machineInfo must be provided when no parentContext');
            }
            if ($report === null) {
                throw new InvalidArgumentException('report must be provided when no parentContext');
            }
            $this->MachineInfo = $machineInfo;
            $this->Report = $report;
            $this->FunctionDecl = $fdecl;
        }
        $this->parentContext = $parentContext;
    }

    public function getBreakLabel(): ?Label
    {
        return $this->parentContext?->getBreakLabel();
    }

    public function getContinueLabel(): ?Label
    {
        return $this->parentContext?->getContinueLabel();
    }

    public function resolveTypeName(TypeName|string $typeName): CType
    {
        if ($typeName instanceof TypeName) {
            return $this->makeCTypeFromSpecsOnly($typeName->Specifiers, $typeName->Declarator, new Block(VariableScope::Global));
        }
        $r = $this->parentContext?->resolveTypeName($typeName);
        if ($r !== null) {
            return $r;
        }
        $this->Report->errorCode(103, 'Type', $typeName);
        return CBasicType::signedInt();
    }

    public function makeCTypeFromSpecsOnly(DeclarationSpecifiers $specs, ?Initializer $init, ?Block $block): CType
    {
        //
        // Infer types
        //
        if (($specs->StorageClassSpecifier & StorageClassSpecifier::Auto) === StorageClassSpecifier::Auto) {
            if (!($init instanceof ExpressionInitializer)) {
                $this->Report->error(818, error: 'Implicitly-typed variabled must be initialized');
                return CBasicType::signedInt();
            }
            return $init->Expression->getEvaluatedCType($this);
        }

        //
        // Try for Basic. The TypeSpecifiers are recorded in reverse from what is actually declared in code.
        //
        $basicTs = null;
        foreach ($specs->TypeSpecifiers as $ts) {
            if ($ts->kind === TypeSpecifierKind::Builtin) {
                $basicTs = $ts;
                break;
            }
        }

        if ($basicTs !== null) {
            if ($basicTs->Name === 'void') {
                return CType::voidType();
            }

            $sign = Signedness::Signed;
            $size = '';
            $trueTs = null;

            foreach ($specs->TypeSpecifiers as $ts) {
                if ($ts->Name === 'unsigned') {
                    $sign = Signedness::Unsigned;
                } elseif ($ts->Name === 'signed') {
                    $sign = Signedness::Signed;
                } elseif ($ts->Name === 'short' || $ts->Name === 'long') {
                    if (strlen($size) === 0) {
                        $size = $ts->Name;
                    } else {
                        $size = $size . ' ' . $ts->Name;
                    }
                } else {
                    $trueTs = $ts;
                }
            }

            // Validate size specifier combinations
            if ($size !== '' && $size !== 'short' && $size !== 'long' && $size !== 'long long') {
                $this->Report->error(2078, error: 'Invalid combination of type specifiers');
                $size = '';
            }

            $typeName = $trueTs === null ? 'int' : $trueTs->Name;

            // Validate size modifiers with noninteger types
            if ($size !== '' && $typeName === 'float') {
                $this->Report->error(2078, error: 'Invalid combination of type specifiers');
                $size = '';
            }
            if ($size !== '' && $typeName === 'double' && $size !== 'long') {
                $this->Report->error(2078, error: 'Invalid combination of type specifiers');
                $size = '';
            }
            if ($size !== '' && $typeName === 'bool') {
                $this->Report->error(2078, error: 'Invalid combination of type specifiers');
                $size = '';
            }

            $type = match ($typeName) {
                'float' => CBasicType::float(),
                'double' => CBasicType::double(),
                'bool' => CBasicType::bool(),
                default => new CIntType($typeName, $sign, $size),
            };
            $type->TypeQualifiers = $specs->TypeQualifiers;
            return $type;
        }

        //
        // Structs, Classes, Unions, and Enums
        //
        $structTs = null;
        foreach ($specs->TypeSpecifiers as $ts) {
            if ($ts->kind === TypeSpecifierKind::Struct || $ts->kind === TypeSpecifierKind::Class) {
                $structTs = $ts;
                break;
            }
        }
        if ($structTs !== null) {
            if ($structTs->body !== null) {
                // Reuse an existing forward-declared struct type if one exists,
                // otherwise create a new one.
                $st = null;
                if ($structTs->Name !== '' && $structTs->Name !== null && $block !== null
                    && isset($block->Structures[$structTs->Name])
                    && count($block->Structures[$structTs->Name]->Members) === 0) {
                    $st = $block->Structures[$structTs->Name];
                } else {
                    $st = new CStructType($structTs->Name);
                }
                // Register the struct type early so members can reference it
                if ($structTs->Name !== '' && $structTs->Name !== null && $block !== null) {
                    $block->Structures[$structTs->Name] = $st;
                }
                if ($structTs->baseSpecifiers !== null) {
                    foreach ($structTs->baseSpecifiers as $baseSpec) {
                        if ($st->baseType !== null) {
                            $this->Report->error(1500, error: 'Multiple inheritance is not supported');
                            continue;
                        }
                        $baseType = $this->resolveTypeName($baseSpec->Name);
                        if ($baseType instanceof CStructType) {
                            $st->baseType = $baseType;
                        } else {
                            $this->Report->error(246, error: "Base type '{$baseSpec->Name}' is not a class or struct");
                        }
                    }
                }
                foreach ($structTs->body->statements as $s) {
                    $this->addStructMember($st, $s, $block);
                }
                $st->buildVTable();
                return $st;
            } else {
                // Lookup (also handles forward declarations like `struct V;`)
                $name = $structTs->Name;
                if ($block !== null && isset($block->Structures[$name])) {
                    return $block->Structures[$name];
                } elseif ($name !== '' && $name !== null) {
                    // Forward declaration
                    $fwdStruct = new CStructType($name);
                    if ($block !== null) {
                        $block->Structures[$name] = $fwdStruct;
                    }
                    return $fwdStruct;
                } else {
                    $this->Report->error(246, error: "'{$name}' not found");
                    return CBasicType::signedInt();
                }
            }
        }

        //
        // Enums
        //
        $enumTs = null;
        foreach ($specs->TypeSpecifiers as $ts) {
            if ($ts->kind === TypeSpecifierKind::Enum) {
                $enumTs = $ts;
                break;
            }
        }
        if ($enumTs !== null) {
            $enumName = $specs->TypeSpecifiers[0]->Name;
            if ($enumTs->body !== null) {
                $et = new CEnumType($enumTs->Name);
                $enumContext = new EnumContext($enumTs, $et, $this);
                foreach ($enumTs->body->statements as $s) {
                    $this->addEnumMember($et, $s, $block, $enumContext);
                }
                return $et;
            } else {
                $name = $enumTs->Name;
                if ($block !== null && isset($block->Enums[$name])) {
                    return $block->Enums[$name];
                } else {
                    $this->Report->error(246, error: "'{$name}' not found");
                    return CBasicType::signedInt();
                }
            }
        }

        //
        // Typedefs
        //
        $typenameTs = null;
        foreach ($specs->TypeSpecifiers as $ts) {
            if ($ts->kind === TypeSpecifierKind::Typename) {
                $typenameTs = $ts;
                break;
            }
        }
        if ($typenameTs !== null) {
            $typedefName = $typenameTs->Name;
            return $this->resolveTypeName($typedefName);
        }

        //
        // Rest
        //
        return CBasicType::voidType();
    }

    private function addStructMember(CStructType $st, Statement $s, ?Block $block, bool $isVirtual = false, bool $isOverride = false, bool $isPureVirtual = false): void
    {
        if ($s instanceof VirtualDeclarationStatement) {
            $this->addStructMember($st, $s->InnerDeclaration, $block, $s->IsVirtual, $s->IsOverride, $s->IsPureVirtual);
            return;
        }

        if ($s instanceof MultiDeclaratorStatement) {
            if ($s->InitDeclarators !== null) {
                foreach ($s->InitDeclarators as $i) {
                    $type = $this->makeCType($s->Specifiers, $i->Declarator, $i->initializer, $block);
                    $name = $i->Declarator->declaredIdentifier;
                    if ($type instanceof CFunctionType) {
                        $st->Members[] = new CStructMethod(
                            Name: $name,
                            memberType: $type,
                            isVirtual: $isVirtual || $isPureVirtual,
                            isOverride: $isOverride,
                            isPureVirtual: $isPureVirtual,
                        );
                    } else {
                        $st->Members[] = new CStructField(
                            name: $name,
                            memberType: $type,
                        );
                    }
                }
            }
        } elseif ($s instanceof FunctionDefinition) {
            $methodType = $this->makeCType($s->Specifiers, $s->Declarator, null, $block);
            $name = $s->Declarator->declaredIdentifier;
            if ($methodType instanceof CFunctionType) {
                $ftype = $methodType;
                $isStatic = ($s->Specifiers->storageClassSpecifier & StorageClassSpecifier::Static) === StorageClassSpecifier::Static;
                // For inline method definitions, create an instance function type unless the method is static.
                if (!$isStatic && !$ftype->IsInstance) {
                    $instanceFtype = new CFunctionType($ftype->ReturnType, isInstance: true, declaringType: $st);
                    foreach ($ftype->Parameters as $p) {
                        $instanceFtype->addParameter($p->Name, $p->parameterType, $p->defaultValue);
                    }
                    $ftype = $instanceFtype;
                }
                $st->Members[] = new CStructMethod(
                    name: $name,
                    memberType: $ftype,
                    isVirtual: $isVirtual || $isPureVirtual,
                    isOverride: $isOverride,
                    isPureVirtual: $isPureVirtual,
                );
                // Register the compiled function on the enclosing block
                $f = new CompiledFunction($name, $st->Name, $ftype, $s->Body);
                if ($block !== null) {
                    $block->Functions[] = $f;
                }
            }
        } elseif ($s instanceof VisibilityStatement) {
            // Ignoring visibility at the moment
        } else {
            throw new BadMethodCallException("Cannot add statement `{$s}` to struct");
        }
    }

    public function makeCType(DeclarationSpecifiers $specs, ?Declarator $decl, ?Initializer $init, ?Block $block): CType
    {
        $type = $this->makeCTypeFromSpecsOnly($specs, $init, $block);
        return $this->makeCTypeFromType($type, $decl, $init, $block);
    }

    private function makeCTypeFromType(CType $type, ?Declarator $decl, ?Initializer $init, ?Block $block): CType
    {
        if ($decl instanceof IdentifierDeclarator) {
            // This is the name
        } elseif ($decl instanceof PointerDeclarator) {
            $pdecl = $decl;
            $isPointerToFunc = false;

            if ($pdecl->StrongBinding) {
                $type = $this->makeCTypeFromType($type, $pdecl->InnerDeclarator, null, $block);
                $isPointerToFunc = $type instanceof CFunctionType;
            }

            $p = $pdecl->Pointer;
            while ($p !== null) {
                $type = new CPointerType($type);
                $type->TypeQualifiers = $p->TypeQualifiers;
                $p = $p->nextPointer;
            }

            if (!$pdecl->StrongBinding) {
                $type = $this->makeCTypeFromType($type, $pdecl->InnerDeclarator, null, $block);
            }

            // Remove 1 level of pointer indirection if this is
            // a pointer to a function since functions are themselves pointers
            if ($isPointerToFunc) {
                $type = $type->InnerType;
            }
        } elseif ($decl instanceof ArrayDeclarator) {
            $adecl = $decl;

            while ($adecl !== null) {
                $len = null;
                if ($adecl->lengthExpression instanceof ConstantExpression) {
                    $len = (int)$adecl->lengthExpression->evalConstant($this);
                } else {
                    if ($init instanceof StructuredInitializer) {
                        $len = count($init->Initializers);
                    } else {
                        $len = 0;
                        $this->Report->error(2057, error: 'Expected constant expression');
                    }
                }
                $type = new CArrayType($type, $len);
                $adeclInner = $adecl->InnerDeclarator;
                $adecl = $adeclInner instanceof ArrayDeclarator ? $adeclInner : null;
                if ($adecl !== null && $adecl->InnerDeclarator !== null) {
                    if ($adecl->InnerDeclarator instanceof IdentifierDeclarator) {
                        // skip
                    } elseif (!($adecl->InnerDeclarator instanceof ArrayDeclarator)) {
                        $type = $this->makeCTypeFromType($type, $adecl->InnerDeclarator, null, $block);
                    }
                }
            }
        } elseif ($decl instanceof FunctionDeclarator) {
            $type = $this->makeCFunctionType($type, $decl, $block);
        } elseif ($decl instanceof ReferenceDeclarator) {
            $rdecl = $decl;
            $type = new CReferenceType($type);
            $type->TypeQualifiers = $rdecl->Qualifiers;
            $type = $this->makeCTypeFromType($type, $rdecl->InnerDeclarator, null, $block);
        }

        return $type;
    }

    private function makeCFunctionType(CType $returnType, Declarator $decl, ?Block $block): CType
    {
        $fdecl = $decl;

        $declaringTypeName = null;
        if ($decl->InnerDeclarator instanceof IdentifierDeclarator && count($decl->InnerDeclarator->Context) > 0) {
            $declaringTypeName = $decl->InnerDeclarator->Context[0];
        }
        $declaringType = $declaringTypeName !== null ? $this->resolveTypeName($declaringTypeName) : null;
        $isInstance = $declaringType !== null;

        $name = $decl->getDeclaredIdentifier();
        $ftype = new CFunctionType($returnType, $isInstance, $declaringType);
        foreach ($fdecl->Parameters as $pdecl) {
            if ($pdecl->declarationSpecifiers !== null) {
                $pt = $this->makeCType($pdecl->declarationSpecifiers, $pdecl->Declarator, null, $block);
            } else {
                $pt = CBasicType::signedInt();
            }
            if (!$pt->IsVoid) {
                $defaultVal = $pdecl->defaultValue?->evalConstant($this);
                $ftype->addParameter($pdecl->Name, $pt, $defaultVal);
            }
        }

        return $this->makeCTypeFromType($ftype, $fdecl->InnerDeclarator, null, $block);
    }

    private function addEnumMember(CEnumType $st, Statement $s, ?Block $block, EnumContext $context): void
    {
        if ($s instanceof EnumeratorStatement) {
            $value = $s->LiteralValue !== null ? (int)$s->LiteralValue->evalConstant($context) : $st->NextValue;
            $st->Members[] = new CEnumMember($s->Name, $value);
        } else {
            throw new BadMethodCallException("Cannot add statement `{$s}` to enum");
        }
    }

    public function resolveVariable(VariableExpression $variable, ?array $argTypes): ResolvedVariable
    {
        $name = $variable->VariableName;

        $r = $this->tryResolveVariable($name, $argTypes);
        if ($r !== null) {
            return $r;
        }

        $r = $this->MachineInfo->getUnresolvedVariable($name, $argTypes, $this);
        if ($r !== null) {
            return $r;
        }

        $this->Report->errorCode(103, $variable->Location, $variable->EndLocation, 'Variable', $name);
        return ResolvedVariable::fromScope(VariableScope::Global, 0, CBasicType::signedInt());
    }

    public function tryResolveVariable(string $name, ?array $argTypes): ?ResolvedVariable
    {
        $r = $this->parentContext?->tryResolveVariable($name, $argTypes);
        if ($r !== null) {
            return $r;
        }
        return null;
    }

    public function pushLoop(Label $breakLabel, ?Label $continueLabel): EmitContext
    {
        return new LoopContext($breakLabel, $continueLabel, $this);
    }

    public function resolveMethodFunction(CStructType $structType, CStructMethod $method): ResolvedVariable
    {
        $r = $this->parentContext?->resolveMethodFunction($structType, $method);
        if ($r !== null) {
            return $r;
        }
        throw new Exception('Cannot resolve method function');
    }

    public function tryResolveOperatorFunction(string $structName, string $operatorName, ?array $argTypes): ?ResolvedVariable
    {
        return $this->parentContext?->tryResolveOperatorFunction($structName, $operatorName, $argTypes);
    }

    public function tryResolveQualifiedFunction(string $nameContext, string $name, ?array $argTypes): ?ResolvedVariable
    {
        return $this->parentContext?->tryResolveQualifiedFunction($nameContext, $name, $argTypes);
    }

    public function beginBlock(Block $b): void
    {
        $this->parentContext?->beginBlock($b);
    }

    /** @noinspection PhpStatementHasEmptyBodyInspection */

    public function endBlock(): void
    {
        $this->parentContext?->endBlock();
    }

    public function allocateTemp(CType $type): int
    {
        if ($this->parentContext !== null) {
            return $this->parentContext->allocateTemp($type);
        }
        throw new BadMethodCallException('Cannot allocate temp outside of a function');
    }

    public function defineLabel(): Label
    {
        if ($this->parentContext !== null) {
            return $this->parentContext->defineLabel();
        }
        return new Label();
    }

    public function emitLabel(Label $l): void
    {
        $this->parentContext?->emitLabel($l);
    }

    public function resolveGotoLabel(string $name): ?Label
    {
        return $this->parentContext?->resolveGotoLabel($name);
    }

    public function defineGotoLabel(string $name): ?Label
    {
        return $this->parentContext?->defineGotoLabel($name);
    }

    /** @noinspection PhpStatementHasEmptyBodyInspection */

    public function emitCastToBoolean(CType $fromType): void
    {
        $this->emitCast($fromType, CBasicType::bool());
    }

    public function emitCast(CType $fromType, CType $toType): void
    {
        if ($fromType->equals($toType)) {
            return;
        }

        $fromBasicType = null;
        if ($fromType instanceof CBasicType) {
            $fromBasicType = $fromType;
        }
        $toBasicType = null;
        if ($toType instanceof CBasicType) {
            $toBasicType = $toType;
        }

        if ($fromBasicType !== null && $toBasicType !== null) {
            $fromOffset = $this->getInstructionOffset($fromBasicType);
            $toOffset = $this->getInstructionOffset($toBasicType);
            $op = (int)(OpCode::ConvertInt8Int8) + ($fromOffset * 10 + $toOffset);
            $this->emit(OpCode::from($op));
        } elseif ($fromBasicType !== null && $fromBasicType->IsIntegral && $toType instanceof CPointerType) {
            // Support `const char *p = 0;`
        } elseif ($fromType instanceof CArrayType && $toType instanceof CPointerType && $fromType->ElementType->numValues === $toType->InnerType->numValues) {
            // Demote arrays to pointers
        } elseif ($fromType instanceof CArrayType && $toType->IsVoidPointer) {
            // Demote arrays to void pointers without size check
        } elseif ($fromType->IsPointer && $toType->IsVoidPointer) {
            // Demote pointers to void pointers
        } elseif ($fromType instanceof CEnumType && $toType instanceof CIntType) {
            // Enums act like ints
        } elseif ($fromType instanceof CFunctionType && $toType instanceof CFunctionType) {
            // Function to function is OK
        } elseif ($fromType instanceof CPointerType && $toType instanceof CPointerType
            && $fromType->InnerType instanceof CStructType
            && $toType->InnerType instanceof CStructType
            && $fromType->InnerType->IsDerivedFrom($toType->InnerType)
        ) {
            // Derived pointer to base pointer (implicit upcast)
        } elseif ($fromType instanceof CReferenceType) {
            // Reference to inner type: dereference
            /** @noinspection PhpIfWithCommonPartsInspection */
            if ($fromType->InnerType->equals($toType)) {
                $this->emit(OpCode::LoadPointer);
            } else {
                $this->emit(OpCode::LoadPointer);
                $this->emitCast($fromType->InnerType, $toType);
            }
        } elseif ($toType instanceof CReferenceType && $fromType->equals($toType->InnerType)) {
            // Value to reference: no-op at cast level
        } elseif ($fromType instanceof CStructType && $toType instanceof CStructType) {
            // Struct to same struct type: no conversion needed
        } else {
            $this->Report->error(30, error: "Cannot convert type '" . $fromType . "' to '" . $toType . "'");
        }
    }

    public function getInstructionOffset(CType $cType): int
    {
        $size = $cType->getByteSize($this);

        if ($cType instanceof CBasicType) {
            if ($cType->IsIntegral) {
                return match ($size) {
                    1 => $cType->Signedness === Signedness::Signed ? 0 : 1,
                    2 => $cType->Signedness === Signedness::Signed ? 2 : 3,
                    4 => $cType->Signedness === Signedness::Signed ? 4 : 5,
                    8 => $cType->Signedness === Signedness::Signed ? 6 : 7,
                    default => throw new BadMethodCallException("Arithmetic on type '" . $cType . "'"),
                };
            } else {
                return match ($size) {
                    4 => 8,
                    8 => 9,
                    default => throw new BadMethodCallException("Arithmetic on type '" . $cType . "'"),
                };
            }
        } elseif ($cType instanceof CPointerType) {
            return match ($this->MachineInfo->pointerSize) {
                1 => 1,
                2 => 3,
                4 => 5,
                8 => 7,
                default => throw new BadMethodCallException("Arithmetic on type '" . $cType . "'"),
            };
        }

        throw new BadMethodCallException("Arithmetic on type '" . $cType . "'");
    }

    /** @noinspection PhpStatementHasEmptyBodyInspection */

    public function emit(Instruction|OpCode $opOrInst, null|Value|Label|int $x = null): void
    {
        if ($opOrInst instanceof Instruction) {
            $this->parentContext?->emit($opOrInst);
            return;
        }

        $op = $opOrInst;

        if ($x instanceof Label) {
            $this->emit(new Instruction($op, $x));
        } elseif ($x !== null) {
            $this->emit(new Instruction($op, $x));
        } else {
            $this->emit(new Instruction($op, Value::fromInt(0)));
        }
    }

    public function getConstantMemory(string $stringConstant): Value
    {
        if ($this->parentContext !== null) {
            return $this->parentContext->getConstantMemory($stringConstant);
        }
        throw new BadMethodCallException('Cannot get constant memory from this context');
    }
}
