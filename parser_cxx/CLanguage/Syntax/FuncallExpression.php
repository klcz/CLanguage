<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;
use CLanguage\Types\CBasicType;
use CLanguage\Types\CFunctionType;
use CLanguage\Types\CPointerType;
use CLanguage\Types\CReferenceType;
use CLanguage\Types\CStructMethod;
use CLanguage\Types\CStructType;
use CLanguage\Types\CType;
use CLanguage\Value;
use Closure;
use Override;

class ScoredMethod
{
    public CStructMethod $Method;
    public int $Score;

    public function __construct(CStructMethod $method, int $score)
    {
        $this->Method = $method;
        $this->Score = $score;
    }
}

class Overload
{
    public static Closure $noEmit;
    public static Overload $error;
    public readonly ?CType $CType;
    public readonly Closure $emit;
    public readonly ?int $vTableSlotIndex;

    public function __construct(?CType $type, Closure $emit, ?int $vTableSlotIndex = null)
    {
        $this->CType = $type;
        $this->emit = $emit;
        $this->vTableSlotIndex = $vTableSlotIndex;
    }
}

Overload::$noEmit = function (EmitContext $ec): void {
};
Overload::$error = new Overload(CBasicType::signedInt(), Overload::$noEmit);

class FuncallExpression extends Expression
{
    public readonly Expression $Function;
    /** @--var Expression[] */
    public readonly array $Arguments;

    public function __construct(Expression $fun, ?array $args = null)
    {
        $this->Function = $fun;
        $this->Arguments = $args ?? [];
    }

    #[Override]
    public function __toString(): string
    {
        $sb = (string)$this->Function;
        $sb .= "(";
        $head = "";
        foreach ($this->Arguments as $a) {
            $sb .= $head;
            $sb .= $a;
            $head = ", ";
        }
        $sb .= ")";
        return $sb;
    }

    #[Override]
    protected function doEmit(EmitContext $ec): void
    {
        $argTypes = [];
        foreach ($this->Arguments as $arg) {
            $argTypes[] = $arg->getEvaluatedCType($ec);
        }
        $function = $this->resolveOverload($this->Function, $argTypes, $ec);

        $type = ($function->CType instanceof CFunctionType) ? $function->CType : null;

        $numRequiredParameters = 0;
        if ($type !== null) {
            $params = $type->Parameters;
            foreach ($params as $p) {
                if ($p->DefaultValue !== null)
                    break;
                $numRequiredParameters++;
            }
            if (count($this->Arguments) < $numRequiredParameters) {
                $ec->Report->error(1501, error: "'{$this->Function}' takes {$numRequiredParameters} arguments, " . count($this->Arguments) . " provided");
                return;
            }
        } else {
            $ec->Report->error(2064, error: "'{$this->Function}' does not evaluate to a function taking " . count($this->Arguments) . " arguments");
            return;
        }

        $params = $type->Parameters;
        for ($i = 0; $i < count($this->Arguments); $i++) {
            $paramType = $params[$i]->ParameterType;
            if ($paramType instanceof CReferenceType) {
                if ($this->Arguments[$i]->canEmitPointer()) {
                    $this->Arguments[$i]->emitPointer($ec);
                } else {
                    $innerType = $paramType->InnerType;
                    $tempOffset = $ec->allocateTemp($innerType);
                    $this->Arguments[$i]->emit($ec);
                    $ec->emitCast($argTypes[$i], $innerType);
                    $ec->emit(OpCode::StoreLocal, Value::fromInt($tempOffset));
                    $ec->emit(OpCode::LoadConstant, Value::pointer($tempOffset));
                    $ec->emit(OpCode::LoadFramePointer);
                    $ec->emit(OpCode::OffsetPointer);
                }
            } else {
                $this->Arguments[$i]->emit($ec);
                $ec->emitCast($argTypes[$i], $paramType);
            }
        }

        for ($i = count($this->Arguments); $i < count($params); $i++) {
            $v = $params[$i]->DefaultValue ?? new Value(0);
            $ec->emit(OpCode::LoadConstant, $v);
        }

        ($function->emit)($ec);

        if ($function->vTableSlotIndex !== null) {
            $ec->emit(OpCode::CallVirtual, Value::fromInt($function->vTableSlotIndex));
        } else {
            $ec->emit(OpCode::Call, Value::fromInt(count($params)));
        }

        if ($type->ReturnType->isVoid()) {
            $ec->emit(OpCode::LoadConstant, Value::fromInt(0));
        }
    }

    #[Override]
    public function getEvaluatedCType(EmitContext $ec): CType
    {
        $argTypes = [];
        foreach ($this->Arguments as $arg) {
            $argTypes[] = $arg->getEvaluatedCType($ec);
        }
        $function = $this->resolveOverload($this->Function, $argTypes, $ec);
        $ft = ($function->CType instanceof CFunctionType) ? $function->CType : null;
        if ($ft !== null) {
            return $ft->ReturnType;
        } else {
            return CBasicType::signedInt();
        }
    }

    private function resolveOverload(Expression $function, array $argTypes, EmitContext $ec): Overload
    {
        if ($function instanceof MemberFromReferenceExpression) {
            $memr = $function;
            $targetType = $memr->Left->getEvaluatedCType($ec);

            if ($targetType instanceof CStructType) {
                $structType = $targetType;
                $methods = $structType->findMethods($memr->MemberName);

                if (count($methods) === 0) {
                    $ec->Report->error(1061, error: "'{$memr->MemberName}' not found in '{$structType->Name}'");
                    return Overload::$error;
                } else {
                    $scoredMethods = [];
                    foreach ($methods as $m) {
                        if ($m->MemberType instanceof CFunctionType) {
                            $mt = $m->MemberType;
                            $score = $mt->scoreParameterTypeMatches($argTypes);
                            if ($score > 0) {
                                $scoredMethods[] = new ScoredMethod($m, $score);
                            }
                        }
                    }
                    usort($scoredMethods, fn(ScoredMethod $a, ScoredMethod $b) => $b->Score - $a->Score);
                    $bestMatch = (count($scoredMethods) > 0) ? $scoredMethods[0] : null;

                    if ($bestMatch === null) {
                        $ec->Report->error(1503, error: "'{$function}' argument type mismatch");
                        return Overload::$error;
                    } else {
                        $method = $bestMatch->Method;
                        if ($method->VTableSlotIndex !== null && $structType->VTableGlobalAddress !== null) {
                            $functionType = ($method->MemberType instanceof CFunctionType) ? $method->MemberType : null;
                            return new Overload(
                                $functionType,
                                function (EmitContext $nec) use ($memr): void {
                                    $memr->Left->emitPointer($nec);
                                },
                                $method->VTableSlotIndex
                            );
                        } else {
                            $res = $ec->resolveMethodFunction($structType, $method);
                            if ($res !== null) {
                                $funcType = ($res->Function !== null) ? $res->Function->FunctionType : null;
                                return new Overload(
                                    $funcType,
                                    function (EmitContext $nec) use ($memr, $res): void {
                                        $memr->Left->emitPointer($nec);
                                        $nec->emit(OpCode::LoadConstant, Value::pointer($res->Address));
                                    }
                                );
                            } else {
                                return Overload::$error;
                            }
                        }
                    }
                }
            } else {
                $ec->Report->error(119, error: "'{$memr->Left}' is not valid in the given context");
                return Overload::$error;
            }
        } elseif ($function instanceof MemberFromPointerExpression) {
            $memp = $function;
            $targetType = $memp->Left->getEvaluatedCType($ec);

            if ($targetType instanceof CPointerType && $targetType->InnerType instanceof CStructType) {
                $structType = $targetType->InnerType;
                $methods = $structType->findMethods($memp->MemberName);

                if (count($methods) === 0) {
                    $ec->Report->error(1061, error: "'{$memp->MemberName}' not found in '{$structType->Name}'");
                    return Overload::$error;
                } else {
                    $scoredMethods = [];
                    foreach ($methods as $m) {
                        if ($m->MemberType instanceof CFunctionType) {
                            $mt = $m->MemberType;
                            $score = $mt->scoreParameterTypeMatches($argTypes);
                            if ($score > 0) {
                                $scoredMethods[] = new ScoredMethod($m, $score);
                            }
                        }
                    }
                    usort($scoredMethods, fn(ScoredMethod $a, ScoredMethod $b) => $b->Score - $a->Score);
                    $bestMatch = (count($scoredMethods) > 0) ? $scoredMethods[0] : null;

                    if ($bestMatch === null) {
                        $ec->Report->error(1503, error: "'{$function}' argument type mismatch");
                        return Overload::$error;
                    } else {
                        $method = $bestMatch->Method;
                        if ($method->VTableSlotIndex !== null && $structType->VTableGlobalAddress !== null) {
                            $functionType = ($method->MemberType instanceof CFunctionType) ? $method->MemberType : null;
                            return new Overload(
                                $functionType,
                                function (EmitContext $nec) use ($memp): void {
                                    $memp->Left->emit($nec);
                                },
                                $method->VTableSlotIndex
                            );
                        } else {
                            $res = $ec->resolveMethodFunction($structType, $method);
                            if ($res !== null) {
                                $funcType = ($res->Function !== null) ? $res->Function->FunctionType : null;
                                return new Overload(
                                    $funcType,
                                    function (EmitContext $nec) use ($memp, $res): void {
                                        $memp->Left->emit($nec);
                                        $nec->emit(OpCode::LoadConstant, Value::pointer($res->Address));
                                    }
                                );
                            } else {
                                return Overload::$error;
                            }
                        }
                    }
                }
            } else {
                $ec->Report->error(119, error: "'{$memp->Left}' is not valid for -> operator");
                return Overload::$error;
            }
        } elseif ($function instanceof ScopeResolutionExpression) {
            $scopeExpr = $function;
            $res = $ec->tryResolveQualifiedFunction($scopeExpr->TypeName, $scopeExpr->MemberName, $argTypes);
            if ($res !== null) {
                $varType = $res->VariableType;
                $emitFunc = ($varType instanceof CFunctionType)
                    ? $res->emit(...)
                    : $res->emitPointer(...);
                return new Overload($varType, $emitFunc);
            } else {
                $ec->Report->error(103, error: "'{$scopeExpr}' not found");
                return Overload::$error;
            }
        } elseif ($function instanceof VariableExpression) {
            $v = $function;
            $res = $ec->resolveVariable($v, $argTypes);
            if ($res !== null) {
                $varType = $res->VariableType;
                $emitFunc = ($varType instanceof CFunctionType)
                    ? $res->emit(...)
                    : $res->emitPointer(...);
                return new Overload($varType, $emitFunc);
            } else {
                return Overload::$error;
            }
        } else {
            $ft = ($function !== null) ? $function->getEvaluatedCType($ec) : null;
            $fn = $function;
            return new Overload(
                $ft,
                ($fn !== null)
                    ? function (EmitContext $nec) use ($fn): void {
                    $fn->emit($nec);
                }
                    : Overload::$noEmit
            );
        }
    }
}
