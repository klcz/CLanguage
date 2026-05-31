<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\BlockContext;
use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\CompiledFunction;
use CLanguage\Types\CArrayType;
use CLanguage\Types\CBasicType;
use CLanguage\Types\CEnumType;
use CLanguage\Types\CFunctionType;
use CLanguage\Types\CStructType;
use InvalidArgumentException;

class MultiDeclaratorStatement extends Statement
{
    public DeclarationSpecifiers $Specifiers;
    public ?array $InitDeclarators;

    public function __construct(DeclarationSpecifiers $specifiers, ?array $initDeclarators = null)
    {
        $this->Specifiers = $specifiers;
        $this->InitDeclarators = $initDeclarators;
    }

    public function AlwaysReturns(): bool
    {
        return false;
    }

    public function __toString(): string
    {
        $s = implode(' ', $this->Specifiers->TypeSpecifiers);
        if ($this->InitDeclarators !== null) {
            $s .= ' ' . implode(', ', $this->InitDeclarators);
        }
        return $s;
    }

    /** @noinspection PhpStatementHasEmptyBodyInspection */

    public function AddDeclarationToBlock(BlockContext $context): void
    {
        $multi = $this;
        $block = $context->Block;
        if ($multi->InitDeclarators !== null) {
            foreach ($multi->InitDeclarators as $idecl) {
                if (($multi->Specifiers->StorageClassSpecifier & StorageClassSpecifier::Typedef) !== 0) {
                    $name = $idecl->Declarator->DeclaredIdentifier;
                    $ttype = $context->MakeCType($multi->Specifiers, $idecl->Declarator, $idecl->Initializer, $block);
                    $block->Typedefs[$name] = $ttype;
                } else {
                    $ctype = $context->MakeCType($multi->Specifiers, $idecl->Declarator, $idecl->Initializer, $block);
                    $name = $idecl->Declarator->DeclaredIdentifier;

                    if ($ctype instanceof CFunctionType && !self::HasStronglyBoundPointer($idecl->Declarator)) {
                        if ($ctype->ReturnType instanceof CStructType
                            && $idecl->Initializer === null
                            && $idecl->Declarator instanceof FunctionDeclarator
                            && $idecl->Declarator->CouldBeCtorCall
                        ) {
                            $found = false;
                            foreach ($block->Variables as $v) {
                                if ($v->Name === $name) {
                                    $found = true;
                                    break;
                                }
                            }
                            if ($found) {
                                $context->Report->Error(2086, error: "Redefinition of '{$name}'");
                            } else {
                                $block->AddVariable($name, $ctype->ReturnType);
                                $callStmt = self::GetCtorInitializerStatement($name, $ctype->ReturnType, $idecl->Declarator);
                                $block->InitStatements[] = $callStmt;
                            }
                        } else {
                            $nameContext = '';
                            if ($idecl->Declarator->InnerDeclarator instanceof IdentifierDeclarator) {
                                $ndecl = $idecl->Declarator->InnerDeclarator;
                                if (count($ndecl->Context) > 0) {
                                    $nameContext = implode('::', $ndecl->Context);
                                }
                            }
                            $f = new CompiledFunction($name, $nameContext, $ctype, null);
                            $block->Functions[] = $f;
                        }
                    } else {
                        if ($ctype instanceof CArrayType && $ctype->Length === null && $idecl->Initializer !== null) {
                            if ($idecl->Initializer instanceof StructuredInitializer) {
                                $structInit = $idecl->Initializer;
                                $len = 0;
                                foreach ($structInit->Initializers as $i) {
                                    if ($i->Designation === null) {
                                        $len++;
                                    } else {
                                        foreach ($i->Designation->Designators as $de) {
                                            $len++;
                                        }
                                    }
                                }
                                $atype = new CArrayType($ctype->ElementType, $len);
                            }
                        }
                        $found = false;
                        foreach ($block->Variables as $v) {
                            if ($v->Name === $name) {
                                $found = true;
                                break;
                            }
                        }
                        if ($found) {
                            $context->Report->Error(2086, error: "Redefinition of '{$name}'");
                        } else {
                            $block->AddVariable($name, $ctype ?? CBasicType::signedInt());
                        }
                    }

                    if ($idecl->Initializer !== null) {
                        $varExpr = new VariableExpression($name, Location::null(), Location::null());
                        $initExpr = self::GetInitializerExpression($idecl->Initializer);
                        $block->InitStatements[] = new ExpressionStatement(new AssignExpression($varExpr, $initExpr));
                    }
                }
            }
        } else {
            $ctype = $context->MakeCType($multi->Specifiers, null, null, $block);
            if ($ctype instanceof CStructType) {
                $n = $ctype->Name;
                if ($n !== null && $n !== '') {
                    $block->Structures[$n] = $ctype;
                }
            } elseif ($ctype instanceof CEnumType) {
                $n = $ctype->Name;
                if ($n === null || $n === '') {
                    $n = 'e' . spl_object_id($ctype);
                }
                $block->Enums[$n] = $ctype;
            }
        }
    }

    private static function HasStronglyBoundPointer(?Declarator $d): bool
    {
        if ($d === null) {
            return false;
        } elseif ($d instanceof PointerDeclarator && $d->StrongBinding) {
            return true;
        } else {
            return self::HasStronglyBoundPointer($d->InnerDeclarator);
        }
    }

    private static function GetCtorInitializerStatement(string $name, CStructType $ctorDeclType, FunctionDeclarator $ctorDecl): ExpressionStatement
    {
        $varExpr = new VariableExpression($name, Location::null(), Location::null());
        $pointerExpr = new AddressOfExpression($varExpr);
        $memExpr = new MemberFromReferenceExpression($varExpr, $ctorDeclType->Name);

        $args = [];
        foreach ($ctorDecl->Parameters as $p) {
            $args[] = $p->CtorArgumentValue;
        }
        $callExpr = new FuncallExpression($memExpr, $args);
        return new ExpressionStatement($callExpr);
    }

    private static function GetInitializerExpression(Initializer $init): Expression
    {
        if ($init instanceof ExpressionInitializer) {
            return $init->Expression;
        } elseif ($init instanceof StructuredInitializer) {
            $sinit = $init;
            $sexpr = new StructureExpression();

            foreach ($sinit->Initializers as $i) {
                $e = self::GetInitializerExpression($i);

                if ($i->Designation === null || count($i->Designation->Designators) === 0) {
                    $sexpr->Items[] = new StructureExpressionItem(null, self::GetInitializerExpression($i));
                } else {
                    foreach ($i->Designation->Designators as $d) {
                        $sexpr->Items[] = new StructureExpressionItem((string)$d, $e);
                    }
                }
            }

            return $sexpr;
        } else {
            throw new InvalidArgumentException('Not supported: ' . $init::class);
        }
    }

    protected function DoEmit(EmitContext $ec): void
    {
        $multi = $this;
        if ($multi->InitDeclarators !== null) {
            foreach ($multi->InitDeclarators as $idecl) {
                /** @noinspection PhpStatementHasEmptyBodyInspection */
                if (($multi->Specifiers->StorageClassSpecifier & StorageClassSpecifier::Typedef) !== 0) {
                } else {
                    $ctype = $ec->MakeCType($multi->Specifiers, $idecl->Declarator, $idecl->Initializer, null);
                    $name = $idecl->Declarator->DeclaredIdentifier;

                    if ($ctype instanceof CFunctionType && !self::HasStronglyBoundPointer($idecl->Declarator)) {
                        if ($ctype->ReturnType instanceof CStructType
                            && $idecl->Initializer === null
                            && $idecl->Declarator instanceof FunctionDeclarator
                            && $idecl->Declarator->CouldBeCtorCall
                        ) {
                            self::GetCtorInitializerStatement($name, $ctype->ReturnType, $idecl->Declarator)->Emit($ec);
                        }
                    } elseif ($idecl->Initializer !== null) {
                        $varExpr = new VariableExpression($name, Location::null(), Location::null());
                        $initExpr = self::GetInitializerExpression($idecl->Initializer);
                        new ExpressionStatement(new AssignExpression($varExpr, $initExpr))->Emit($ec);
                    }
                }
            }
        }
    }
}

class InitDeclarator
{
    public Declarator $Declarator;
    public ?Initializer $Initializer;

    public function __construct(Declarator $declarator, ?Initializer $initializer = null)
    {
        $this->Declarator = $declarator;
        $this->Initializer = $initializer;
    }

    public function __toString(): string
    {
        return (string)$this->Declarator;
    }
}
