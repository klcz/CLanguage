<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\BlockContext;
use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;

class SwitchStatement extends Statement
{
    public readonly Expression $value;
    /** @--var SwitchCase[] */
    public readonly array $cases;

    public function __construct(Expression $value, array $cases, ?Location $loc = null)
    {
        $this->value = $value;
        $this->cases = $cases;
        if ($loc !== null) {
            $this->Location = $loc;
        }
    }

    /** @noinspection PhpParameterNameChangedDuringInheritanceInspection */

    public function __toString(): string
    {
        return "switch ({$this->value}) " . implode('', $this->cases) . ";";
    }

    public function addDeclarationToBlock(BlockContext $context): void
    {
        foreach ($this->cases as $c) {
            foreach ($c->statements as $s) {
                $s->addDeclarationToBlock($context);
            }
        }
    }

    public function alwaysReturns(): bool
    {
        return false;
    }

    protected function doEmit(EmitContext $initialContext): void
    {
        $valueType = $this->value->getEvaluatedCType($initialContext);

        $this->value->emit($initialContext);

        if (count($this->cases) === 0) {
            $initialContext->emit(OpCode::Pop);
            return;
        }

        $caseLabels = [];
        $defaultLabel = null;
        foreach ($this->cases as $c) {
            $caseLabel = $initialContext->defineLabel();
            $caseLabels[] = $caseLabel;
            if ($c->value === null) {
                if ($defaultLabel !== null) {
                    $initialContext->Report->error(139, error: "Duplicate default labels in switch");
                }
                $defaultLabel = $caseLabel;
            }
        }
        $endLabel = $initialContext->defineLabel();

        $ec = $initialContext->pushLoop(breakLabel: $endLabel, continueLabel: null);

        // Emit case tests
        $ioff = $ec->getInstructionOffset($valueType);
        $eqOp = (int)OpCode::EqualToInt8 + $ioff;
        for ($ci = 0; $ci < count($this->cases); $ci++) {
            $c = $this->cases[$ci];
            $caseLabel = $caseLabels[$ci];
            if ($c->value === null) continue;
            $ec->emit(OpCode::Dup);
            $c->value->emit($ec);
            $ec->emitCast($c->value->getEvaluatedCType($ec), $valueType);
            $ec->emit(Opcode::from($eqOp));
            $ec->emit(OpCode::BranchIfTrue, $caseLabel);
        }
        if ($defaultLabel !== null) {
            $ec->emit(OpCode::Pop);
            $ec->emit(OpCode::Jump, $defaultLabel);
        } else {
            $ec->emit(OpCode::Pop);
            $ec->emit(OpCode::Jump, $endLabel);
        }

        // Emit case statements
        for ($ci = 0; $ci < count($this->cases); $ci++) {
            $c = $this->cases[$ci];
            $caseLabel = $caseLabels[$ci];
            $ec->emitLabel($caseLabel);
            foreach ($c->statements as $s) {
                $s->emit($ec);
            }
        }

        $ec->emitLabel($endLabel);
    }
}

readonly class SwitchCase
{
    public ?Expression $value;
    /** @--var Statement[] */
    public array $statements;

    public function __construct(?Expression $value, array $statements)
    {
        $this->value = $value;
        $this->statements = $statements;
    }
}
