<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;
use CLanguage\Types\CBasicType;
use CLanguage\Types\CType;

class ScopeResolutionExpression extends Expression
{
    public string $TypeName;
    public string $MemberName;

    public function __construct(string $typeName, string $memberName)
    {
        $this->TypeName = $typeName;
        $this->MemberName = $memberName;
    }

    public function getEvaluatedCType(EmitContext $ec): CType
    {
        $r = $ec->tryResolveQualifiedFunction($this->TypeName, $this->MemberName, null);
        if ($r !== null) {
            return $r->VariableType;
        }
        return CBasicType::signedInt();
    }

    public function __toString(): string
    {
        return "{$this->TypeName}::{$this->MemberName}";
    }

    protected function doEmit(EmitContext $ec): void
    {
        $r = $ec->tryResolveQualifiedFunction($this->TypeName, $this->MemberName, null);
        if ($r !== null) {
            $r->emit($ec);
        } else {
            $ec->Report->error(103, error: "'{$this->TypeName}::{$this->MemberName}' not found");
            $ec->emit(OpCode::LoadConstant, 0);
        }
    }
}
