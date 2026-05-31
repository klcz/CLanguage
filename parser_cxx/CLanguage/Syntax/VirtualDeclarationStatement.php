<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\BlockContext;
use CLanguage\Compiler\EmitContext;

class VirtualDeclarationStatement extends Statement
{
    public readonly Statement $InnerDeclaration;
    public bool $IsVirtual = false;
    public bool $IsOverride = false;
    public bool $IsPureVirtual = false;

    public function __construct(Statement $innerDeclaration)
    {
        $this->InnerDeclaration = $innerDeclaration;
    }

    public function AlwaysReturns(): bool
    {
        return false;
    }

    public function AddDeclarationToBlock(BlockContext $context): void
    {
    }

    protected function DoEmit(EmitContext $ec): void
    {
    }
}
