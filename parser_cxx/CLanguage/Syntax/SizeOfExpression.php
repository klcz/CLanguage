<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;
use CLanguage\Types\CBasicType;
use CLanguage\Types\CType;
use CLanguage\Value;
use Override;

class SizeOfExpression extends Expression
{
    public Expression $query;

    public function __construct(Expression $query)
    {
        $this->query = $query;
    }

    #[Override]
    protected function doEmit(EmitContext $ec): void
    {
        $type = $this->query->getEvaluatedCType($ec);
        $cval = Value::fromInt($type->getNumValues());
        $ec->emit(OpCode::LoadConstant, $cval);
    }

    #[Override]
    public function getEvaluatedCType(EmitContext $ec): CType
    {
        return CBasicType::unsignedLongInt();
    }
}
