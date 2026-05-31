<?php declare(strict_types=1);

namespace CLanguage\Syntax;

abstract class Initializer
{
    public ?InitializerDesignation $Designation = null;
}

class ExpressionInitializer extends Initializer
{
    public Expression $Expression;

    public function __construct(Expression $expr)
    {
        $this->Expression = $expr;
    }

    public function __toString(): string
    {
        return (string)$this->Expression;
    }
}

class StructuredInitializer extends Initializer
{
    /** @--var Initializer[] */
    public array $Initializers;

    public function __construct()
    {
        $this->Initializers = [];
    }

    public function add(Initializer $init): void
    {
        $this->Initializers[] = $init;
    }
}

class InitializerDesignation
{
    /** @--var InitializerDesignator[] */
    public array $Designators;

    /** @param InitializerDesignator[] $des */
    public function __construct(array $des)
    {
        $this->Designators = $des;
    }
}

class InitializerDesignator
{
}
