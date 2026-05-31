<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\BlockContext;
use CLanguage\Compiler\EmitContext;

class LabeledStatement extends Statement
{
    public readonly string $label;
    public readonly Statement $statement;

    public function __construct(string $label, Statement $statement, Location $location)
    {
        $this->label = $label;
        $this->statement = $statement;
        $this->Location = $location;
    }

    public function alwaysReturns(): bool
    {
        return $this->statement->alwaysReturns();
    }

    public function addDeclarationToBlock(BlockContext $context): void
    {
        $this->statement->addDeclarationToBlock($context);
    }

    protected function doEmit(EmitContext $ec): void
    {
        $label = $ec->defineGotoLabel($this->label);
        if ($label !== null) {
            $ec->emitLabel($label);
        }
        $this->statement->emit($ec);
    }
}
