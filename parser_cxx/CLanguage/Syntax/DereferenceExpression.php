<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;
use CLanguage\Types\CBasicType;
use CLanguage\Types\CPointerType;
use CLanguage\Types\CType;
use Override;

class DereferenceExpression extends Expression
{
    public Expression $InnerExpression;

    public function __construct(Expression $innerExpression)
    {
        $this->InnerExpression = $innerExpression;
    }

    #[Override]
    public function getEvaluatedCType(EmitContext $ec): CType
    {
        $it = $this->InnerExpression->getEvaluatedCType($ec);
        if ($it instanceof CPointerType) {
            return $it->InnerType;
        } else {
            $ec->Report->error(0, error: "Cannot dereference values of type `{$it}`.");
            return CBasicType::signedInt();
        }
    }

    #[Override]
    public function canEmitPointer(): bool
    {
        return true;
    }

    #[Override]
    protected function doEmit(EmitContext $ec): void
    {
        $this->InnerExpression->emit($ec);
        $ec->emit(OpCode::LoadPointer);
    }

    #[Override]
    protected function doEmitPointer(EmitContext $ec): void
    {
        $this->InnerExpression->emit($ec);
    }
}
