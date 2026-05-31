<?php declare(strict_types=1);

namespace CLanguage\Compiler;

use CLanguage\Interpreter\BaseFunction;
use CLanguage\Interpreter\Executable;
use CLanguage\Interpreter\InternalFunction;
use CLanguage\Report;
use CLanguage\Types\CFunctionType;
use CLanguage\Types\CStructMethod;
use CLanguage\Types\CStructType;

class ExecutableContext extends EmitContext
{
    public readonly Executable $executable;

    public function __construct(Executable $executable, Report $report)
    {
        parent::__construct(
            machineInfo: $executable->MachineInfo,
            report: $report,
        );
        $this->executable = $executable;
    }

    public function resolveMethodFunction(CStructType $structType, CStructMethod $method): ResolvedVariable
    {
        if ($method->MemberType instanceof CFunctionType) {
            $nameContext = $structType->Name;

            $funcs = $this->executable->Functions;
            for ($i = 0; $i < count($funcs); $i++) {
                $f = $funcs[$i];
                if ($f->NameContext === $nameContext && $f->Name === $method->Name && $f->functionType->parameterTypesEqual($method->MemberType)) {
                    return ResolvedVariable::fromFunction($f, $i);
                }
            }
        }

        $this->Report->error(9000, error: "No definition for '{$structType->Name}::{$method->Name}' found");
        return ResolvedVariable::fromFunction($this->unresolvedMethod($structType->Name, $method->Name), 0);
    }

    private function unresolvedMethod(string $typeName, string $methodName): BaseFunction
    {
        return new InternalFunction($this->MachineInfo, "void {$typeName}::{$methodName}()");
    }

    public function tryResolveVariable(string $name, ?array $argTypes): ?ResolvedVariable
    {
        //
        // Look for global variables
        //
        foreach ($this->executable->Globals as $g) {
            if ($g->Name === $name) {
                return ResolvedVariable::fromScope(VariableScope::Global, $g->stackOffset, $g->variableType);
            }
        }

        //
        // Look for global functions
        //
        $ff = null;
        $fi = -1;
        $fs = 0;
        $funcs = $this->executable->Functions;
        for ($i = 0; $i < count($funcs); $i++) {
            $f = $funcs[$i];
            if ($f->Name === $name && $f->NameContext === '') {
                $score = $f->functionType->scoreParameterTypeMatches($argTypes);
                if ($score > $fs) {
                    $ff = $f;
                    $fi = $i;
                    $fs = $score;
                }
            }
        }
        if ($ff !== null) {
            return ResolvedVariable::fromFunction($ff, $fi);
        }

        return parent::tryResolveVariable($name, $argTypes);
    }

    public function tryResolveOperatorFunction(string $structName, string $operatorName, ?array $argTypes): ?ResolvedVariable
    {
        $best = null;
        $bestIndex = -1;
        $bestScore = 0;
        $funcs = $this->executable->Functions;
        for ($i = 0; $i < count($funcs); $i++) {
            $f = $funcs[$i];
            if ($f->Name === $operatorName && $f->NameContext === $structName) {
                $score = $f->functionType->scoreParameterTypeMatches($argTypes);
                if ($score > $bestScore) {
                    $best = $f;
                    $bestIndex = $i;
                    $bestScore = $score;
                }
            }
        }
        if ($best !== null) {
            return ResolvedVariable::fromFunction($best, $bestIndex);
        }
        return parent::tryResolveOperatorFunction($structName, $operatorName, $argTypes);
    }

    public function tryResolveQualifiedFunction(string $nameContext, string $name, ?array $argTypes): ?ResolvedVariable
    {
        $best = null;
        $bestIndex = -1;
        $bestScore = 0;
        $funcs = $this->executable->Functions;
        for ($i = 0; $i < count($funcs); $i++) {
            $f = $funcs[$i];
            if ($f->Name === $name && $f->NameContext === $nameContext) {
                $score = $f->functionType->scoreParameterTypeMatches($argTypes);
                if ($score > $bestScore) {
                    $best = $f;
                    $bestIndex = $i;
                    $bestScore = $score;
                }
            }
        }
        if ($best !== null) {
            return ResolvedVariable::fromFunction($best, $bestIndex);
        }
        return parent::tryResolveQualifiedFunction($nameContext, $name, $argTypes);
    }
}
