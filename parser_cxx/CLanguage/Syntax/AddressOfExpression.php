<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\EmitContext;
use CLanguage\Types\CType;
use Override;

class AddressOfExpression extends Expression
{
    public Expression $InnerExpression;

    public function __construct(Expression $innerExpression)
    {
        $this->InnerExpression = $innerExpression;
    }

    #[Override]
    public function getEvaluatedCType(EmitContext $ec): CType
    {
        return $this->InnerExpression->getEvaluatedCType($ec)->pointer();
    }

    #[Override]
    protected function doEmit(EmitContext $ec): void
    {
        $this->InnerExpression->emitPointer($ec);
    }
}
