<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\BlockContext;
use CLanguage\Compiler\EmitContext;
use CLanguage\Compiler\VariableScope;

abstract class Statement
{
    public Location $Location;

    public function emit(EmitContext $ec): void
    {
        $this->doEmit($ec);
    }

    abstract protected function doEmit(EmitContext $ec): void;

    abstract public function alwaysReturns(): bool;

    public function toBlock(): Block
    {
        if ($this instanceof Block) {
            return $this;
        }
        $b = new Block(VariableScope::Local);
        $b->addStatement($this);
        return $b;
    }

    abstract public function addDeclarationToBlock(BlockContext $context): void;
}
