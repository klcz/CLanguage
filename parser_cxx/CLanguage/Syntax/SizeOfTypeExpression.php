<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;
use CLanguage\Types\CBasicType;
use CLanguage\Types\CType;

class SizeOfTypeExpression extends Expression
{
    public TypeName $TypeName;

    public function __construct(TypeName $typeName)
    {
        $this->TypeName = $typeName;
    }

    public function getEvaluatedCType(EmitContext $ec): CType
    {
        return CBasicType::unsignedLongInt();
    }

    protected function doEmit(EmitContext $ec): void
    {
        $type = $ec->resolveTypeName($this->TypeName);
        $cval = $type->getNumValues();
        $ec->emit(OpCode::LoadConstant, $cval);
    }
}
