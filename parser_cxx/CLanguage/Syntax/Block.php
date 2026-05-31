<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\BlockContext;
use CLanguage\Compiler\EmitContext;
use CLanguage\Compiler\VariableScope;
use CLanguage\Interpreter\CompiledVariable;
use CLanguage\Interpreter\OpCode;
use CLanguage\Types\CStructType;
use CLanguage\Types\CType;
use CLanguage\Value;

class Block extends Statement
{
    public readonly VariableScope $VariableScope;
    /** @--var Statement[] */
    public array $Statements = [];

    public ?Block $parent = null;
    /** @--var CompiledVariable[] */
    public array $Variables = [];
    /** @--var CompiledFunction[] */
    public array $Functions = [];
    /** @--var array<string, CType> */
    public array $Typedefs = [];
    /** @--var Statement[] */
    public array $InitStatements = [];
    /** @--var array<string, CStructType> */
    public array $Structures = [];
    /** @--var array<string, CEnumType> */
    public array $Enums = [];

    public function __construct(VariableScope $variableScope, ?array $statements = null)
    {
        $this->VariableScope = $variableScope;
        if ($statements !== null) {
            $this->addStatements($statements);
        }
    }

    public function addStatements(array $stmts): void
    {
        foreach ($stmts as $s) {
            $this->addStatement($s);
        }
    }

    public function addStatement(?Statement $stmt): void
    {
        if ($stmt !== null) {
            $this->Statements[] = $stmt;
        }

        if ($stmt instanceof Block) {
            $stmt->parent = $this;
        }
    }

    public function alwaysReturns(): bool
    {
        return array_any($this->Statements, fn($s) => $s->alwaysReturns());
    }

    public function addVariable(string $name, CType $ctype): void
    {
        $this->Variables[] = new CompiledVariable($name, 0, $ctype);
    }

    public function addDeclarationToBlock(BlockContext $context): void
    {
        $subContext = new BlockContext($this, $context);
        foreach ($this->Statements as $s) {
            $s->addDeclarationToBlock($subContext);
        }
    }

    public function __toString(): string
    {
        if (count($this->InitStatements) > 0) {
            return '{[' . implode('; ', $this->InitStatements) . '] ' . implode('; ', $this->Statements) . '}';
        }
        return '{' . implode('; ', $this->Statements) . '}';
    }

    protected function doEmit(EmitContext $ec): void
    {
        $ec->beginBlock($this);

        foreach ($this->Variables as $v) {
            $st = $v->variableType;
            if ($st instanceof CStructType && $st->IsPolymorphic && $st->VTableGlobalAddress !== null) {
                $ec->emit(OpCode::LoadConstant, Value::pointer($st->VTableGlobalAddress));
                $ec->emit(OpCode::LoadFramePointer);
                $ec->emit(OpCode::LoadConstant, Value::pointer($v->stackOffset));
                $ec->emit(OpCode::OffsetPointer);
                $ec->emit(OpCode::StorePointer);
            }
        }

        foreach ($this->Statements as $s) {
            $s->emit($ec);
        }

        $ec->endBlock();
    }
}
