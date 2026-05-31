<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use BadMethodCallException;
use CLanguage\Compiler\BlockContext;
use CLanguage\Compiler\EmitContext;

class VisibilityStatement extends Statement
{
    public readonly DeclarationsVisibility $Visibility;

    public function __construct(DeclarationsVisibility $visibility)
    {
        $this->Visibility = $visibility;
    }

    public function AlwaysReturns(): bool
    {
        throw new BadMethodCallException('Not implemented');
    }

    public function AddDeclarationToBlock(BlockContext $context): void
    {
    }

    protected function DoEmit(EmitContext $ec): void
    {
    }
}
