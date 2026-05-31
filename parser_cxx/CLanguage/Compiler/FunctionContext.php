<?php declare(strict_types=1);

namespace CLanguage\Compiler;

use CLanguage\Interpreter\CompiledFunction;
use CLanguage\Interpreter\CompiledVariable;
use CLanguage\Interpreter\Executable;
use CLanguage\Interpreter\Instruction;
use CLanguage\Interpreter\Label;
use CLanguage\Interpreter\OpCode;
use CLanguage\Syntax\Block;
use CLanguage\Syntax\TypeName;
use CLanguage\Types\CStructType;
use CLanguage\Types\CType;
use CLanguage\Value;

class FunctionContext extends BlockContext
{
    public array $LocalVariables {
        get => $this->getLocalVariables();
    }
    private Executable $exe;
    private CompiledFunction $fexe;

    /** @--var array<int, array{startIndex: int, length: int}> blockLocals keyed by spl_id */
    private array $blockLocals = [];

    /** @--var Block[] */
    private array $blocks = [];

    /** @--var CompiledVariable[] */
    private array $allLocals = [];

    /** @--var array<string, Label> */
    private array $gotoLabels = [];

    /** @--var array<string, bool> */
    private array $definedGotoLabels = [];

    public function __construct(Executable $exe, CompiledFunction $fexe, EmitContext $parentContext)
    {
        parent::__construct(
            $fexe->Body ?? new Block(VariableScope::Local),
            machineInfoOrParent: $parentContext->MachineInfo,
            report: $parentContext->Report,
            fdecl: $fexe,
            parentContext: $parentContext,
        );
        $this->exe = $exe;
        $this->fexe = $fexe;
    }

    public function __toString(): string
    {
        return "{$this->fexe} function context";
    }

    /** @return CompiledVariable[] */
    public function getLocalVariables(): array
    {
        return $this->allLocals;
    }

    public function resolveTypeName(TypeName|string $typeName): CType
    {
        //
        // Look for local types
        //
        foreach (array_reverse($this->blocks) as $b) {
            if (isset($b->typedefs[$typeName])) {
                return $b->typedefs[$typeName];
            }
        }

        return parent::resolveTypeName($typeName);
    }

    public function tryResolveVariable(string $name, ?array $argTypes): ?ResolvedVariable
    {
        //
        // Look for function parameters
        //
        $params = $this->fexe->FunctionType->Parameters;
        for ($i = 0; $i < count($params); $i++) {
            $p = $params[$i];
            if ($p->Name === $name) {
                return ResolvedVariable::fromScope(VariableScope::Arg, $p->offset, $p->parameterType);
            }
        }

        //
        // Look for locals
        //
        foreach (array_reverse($this->blocks) as $b) {
            $blocals = $this->blockLocals[spl_object_id($b)];
            for ($i = 0; $i < $blocals['length']; $i++) {
                $j = $blocals['startIndex'] + $i;
                if ($this->allLocals[$j]->Name === $name) {
                    return ResolvedVariable::fromScope(
                        VariableScope::Local,
                        $this->allLocals[$j]->stackOffset,
                        $this->allLocals[$j]->variableType,
                    );
                }
            }
        }

        //
        // This?
        //
        if ($name === 'this' && $this->fexe->FunctionType->IsInstance && $this->fexe->FunctionType->DeclaringType instanceof CStructType) {
            /** @--var CStructType $dtype */
            $dtype = $this->fexe->FunctionType->DeclaringType;
            return ResolvedVariable::fromScope(VariableScope::Arg, -1, $dtype->pointer());
        }

        return parent::tryResolveVariable($name, $argTypes);
    }

    public function beginBlock(Block $b): void
    {
        $this->blocks[] = $b;
        $this->blockLocals[spl_object_id($b)] = [
            'startIndex' => count($this->allLocals),
            'length' => count($b->Variables),
        ];
        array_push($this->allLocals, ...$b->Variables);

        $offset = 0;
        foreach ($this->allLocals as $v) {
            $v->stackOffset = $offset;
            $offset += $v->variableType->getNumValues();
        }
    }

    public function endBlock(): void
    {
        array_pop($this->blocks);
    }

    public function allocateTemp(CType $type): int
    {
        $offset = 0;
        if (count($this->allLocals) > 0) {
            $last = $this->allLocals[count($this->allLocals) - 1];
            $offset = $last->stackOffset + $last->variableType->getNumValues();
        }
        $temp = new CompiledVariable("__ref_temp_" . count($this->allLocals), $offset, $type);
        $this->allLocals[] = $temp;
        return $offset;
    }

    public function defineLabel(): Label
    {
        return new Label();
    }

    public function emitLabel(Label $l): void
    {
        $l->index = count($this->fexe->Instructions);
    }

    public function resolveGotoLabel(string $name): ?Label
    {
        if (!isset($this->gotoLabels[$name])) {
            $this->gotoLabels[$name] = new Label();
        }
        return $this->gotoLabels[$name];
    }

    public function defineGotoLabel(string $name): ?Label
    {
        if (isset($this->definedGotoLabels[$name])) {
            $this->Report->error(140, error: "Label '{$name}' is already defined");
            return null;
        }
        $this->definedGotoLabels[$name] = true;
        if (!isset($this->gotoLabels[$name])) {
            $this->gotoLabels[$name] = new Label();
        }
        return $this->gotoLabels[$name];
    }

    public function checkLabels(): void
    {
        foreach ($this->gotoLabels as $name => $label) {
            if (!isset($this->definedGotoLabels[$name])) {
                $this->Report->error(9999, error: "Label '{$name}' is not defined");
            }
        }
    }

    public function emit(Instruction|OpCode $opOrInst, null|Value|Label|int $x = null): void
    {
        if ($opOrInst instanceof Instruction) {
            $this->fexe->Instructions[] = $opOrInst;
            return;
        }

        if ($x instanceof Label) {
            $this->fexe->Instructions[] = new Instruction($opOrInst, $x);
        } elseif ($x !== null) {
            $this->fexe->Instructions[] = new Instruction($opOrInst, $x);
        } else {
            $this->fexe->Instructions[] = new Instruction($opOrInst, Value::fromInt(0));
        }
    }

    public function getConstantMemory(string $stringConstant): Value
    {
        return $this->exe->getConstantMemory($stringConstant);
    }
}
