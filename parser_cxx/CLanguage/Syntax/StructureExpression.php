<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\EmitContext;
use CLanguage\Types\CType;
use ReflectionClass;
use RuntimeException;

class StructureExpressionItem
{
    public int $Index;
    public ?string $Field;
    public Expression $Expression;

    public function __construct(?string $field, Expression $expression)
    {
        $this->Field = $field;
        $this->Expression = $expression;
    }
}

class StructureExpression extends Expression
{
    /** @--var StructureExpressionItem[] */
    public array $Items;

    public function __construct()
    {
        $this->Items = [];
    }

    public function __toString(): string
    {
        $parts = array_map(fn(StructureExpressionItem $x) => (string)$x->Expression, $this->Items);
        return "{ " . implode(", ", $parts) . " }";
    }

    public function getEvaluatedCType(EmitContext $ec): CType
    {
        return CType::voidType();
    }

    protected function doEmit(EmitContext $ec): void
    {
        throw new RuntimeException(new ReflectionClass($this)->getShortName() . ": Emit");
    }
}
