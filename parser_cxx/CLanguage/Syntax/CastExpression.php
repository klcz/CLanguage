<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\EmitContext;
use CLanguage\Types\CBasicType;
use CLanguage\Types\CType;
use Override;

class CastExpression extends Expression
{
    public readonly TypeName $TypeName;
    public readonly Expression $InnerExpression;

    public function __construct(TypeName $typeName, Expression $innerExpression)
    {
        $this->TypeName = $typeName;
        $this->InnerExpression = $innerExpression;
    }

    #[Override]
    protected function doEmit(EmitContext $ec): void
    {
        $rtype = $this->getEvaluatedCType($ec);
        $itype = $this->InnerExpression->getEvaluatedCType($ec);
        $this->InnerExpression->emit($ec);
        $ec->emitCast($itype, $rtype);
    }

    #[Override]
    public function getEvaluatedCType(EmitContext $ec): CType
    {
        return $ec->resolveTypeName($this->TypeName) ?? CBasicType::signedInt();
    }
}
