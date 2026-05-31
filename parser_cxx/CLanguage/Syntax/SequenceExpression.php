<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;
use CLanguage\Types\CType;

class SequenceExpression extends Expression
{
    public Expression $First;
    public Expression $Second;

    public function __construct(Expression $first, Expression $second)
    {
        $this->First = $first;
        $this->Second = $second;
    }

    public function getEvaluatedCType(EmitContext $ec): CType
    {
        return $this->Second->getEvaluatedCType($ec);
    }

    public function __toString(): string
    {
        return "(" . $this->First . ", " . $this->Second . ")";
    }

    protected function doEmit(EmitContext $ec): void
    {
        $this->First->emit($ec);
        $ec->emit(OpCode::Pop);
        $this->Second->emit($ec);
    }
}
