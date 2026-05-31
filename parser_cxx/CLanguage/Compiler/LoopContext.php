<?php declare(strict_types=1);

namespace CLanguage\Compiler;

use CLanguage\Interpreter\Label;

class LoopContext extends EmitContext
{
    public readonly Label $loopBreakLabel;
    public readonly ?Label $loopContinueLabel;

    public function __construct(Label $breakLabel, ?Label $continueLabel, EmitContext $parentContext)
    {
        parent::__construct(parentContext: $parentContext);
        $this->loopBreakLabel = $breakLabel;
        $this->loopContinueLabel = $continueLabel;
    }

    public function getBreakLabel(): ?Label
    {
        return $this->loopBreakLabel;
    }

    public function getContinueLabel(): ?Label
    {
        return $this->loopContinueLabel ?? $this->parentContext?->getContinueLabel();
    }
}
