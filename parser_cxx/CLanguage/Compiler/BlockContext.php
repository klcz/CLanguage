<?php declare(strict_types=1);

namespace CLanguage\Compiler;

use CLanguage\Interpreter\CompiledFunction;
use CLanguage\MachineInfo;
use CLanguage\Report;
use CLanguage\Syntax\Block;
use CLanguage\Syntax\MultiDeclaratorStatement;

class BlockContext extends EmitContext
{
    public readonly Block $Block;

    public function __construct(
        Block                        $block,
        MachineInfo|EmitContext|null $machineInfoOrParent = null,
        ?Report                      $report = null,
        ?CompiledFunction            $fdecl = null,
        ?EmitContext                 $parentContext = null,
    )
    {
        if ($machineInfoOrParent instanceof EmitContext || ($machineInfoOrParent === null && $parentContext !== null)) {
            // BlockContext(Block, EmitContext parentContext) overload
            parent::__construct(parentContext: $machineInfoOrParent ?? $parentContext);
        } else {
            // BlockContext(Block, MachineInfo, Report, CompiledFunction, EmitContext) overload
            parent::__construct(
                machineInfo: $machineInfoOrParent instanceof MachineInfo ? $machineInfoOrParent : null,
                report: $report,
                fdecl: $fdecl,
                parentContext: $parentContext,
            );
        }
        $this->Block = $block;
    }

    public function tryResolveVariable(string $name, ?array $argTypes): ?ResolvedVariable
    {
        foreach ($this->Block->Statements as $s) {
            if ($s instanceof MultiDeclaratorStatement && $s->InitDeclarators !== null) {
                foreach ($s->InitDeclarators as $i) {
                    if ($i->declarator->declaredIdentifier === $name) {
                        $type = $this->makeCType($s->Specifiers, $i->declarator, $i->initializer, $this->Block);
                        return ResolvedVariable::fromScope($this->Block->VariableScope, 0, $type);
                    }
                }
            }
        }
        return parent::tryResolveVariable($name, $argTypes);
    }
}
