<?php declare(strict_types=1);

namespace CLanguage\Syntax;

abstract class Declarator
{
    public bool $StrongBinding = false;
    public ?Declarator $InnerDeclarator = null;
    public string $DeclaredIdentifier {
        get => $this->getDeclaredIdentifier();
    }

    public function __construct(?Declarator $innerDeclarator = null)
    {
        $this->InnerDeclarator = $innerDeclarator;
    }

    public function __toString(): string
    {
        return $this->getDeclaredIdentifier();
    }

    abstract public function getDeclaredIdentifier(): string;
}

class IdentifierDeclarator extends Declarator
{
    public string $Identifier;
    public array $Context = [];

    public function __construct(string $id)
    {
        parent::__construct();
        $this->Identifier = $id;
    }

    public function getDeclaredIdentifier(): string
    {
        return $this->Identifier;
    }

    public function push(string $id): static
    {
        $this->Context[] = $this->Identifier;
        $this->Identifier = $id;
        return $this;
    }

    public function __toString(): string
    {
        return $this->Identifier;
    }
}

class ArrayDeclarator extends Declarator
{
    public mixed $LengthExpression = null;
    public int $TypeQualifiers = TypeQualifiers::None;
    public bool $LengthIsStatic = false;

    public function __construct(?Declarator $innerDeclarator, mixed $length = null)
    {
        parent::__construct($innerDeclarator);
        $this->LengthExpression = $length;
    }

    public function getDeclaredIdentifier(): string
    {
        return $this->InnerDeclarator?->getDeclaredIdentifier() ?? '';
    }
}

class FunctionDeclarator extends Declarator
{
    public array $Parameters = [];
    public bool $CouldBeCtorCall {
        get => $this->getCouldBeCtorCall();
    }

    public function __construct(null|Declarator|array $innerDeclarator = null, ?array $parameters = null)
    {
        if ($innerDeclarator instanceof Declarator) {
            parent::__construct($innerDeclarator);
            $this->Parameters = $parameters ?? [];
        } else {
            parent::__construct();
            $this->Parameters = $innerDeclarator;
        }
    }

    public function getCouldBeCtorCall(): bool
    {
        if (count($this->Parameters) === 0) return true;
        foreach ($this->Parameters as $p) {
            if ($p->CtorArgumentValue === null) return false;
        }
        return true;
    }

    public function __toString(): string
    {
        return $this->getDeclaredIdentifier() . '(' . implode(', ', array_map(fn($p) => (string)$p, $this->Parameters)) . ')';
    }

    public function getDeclaredIdentifier(): string
    {
        return $this->InnerDeclarator?->getDeclaredIdentifier() ?? '';
    }
}

class Pointer
{
    public int $TypeQualifiers = TypeQualifiers::None;
    public ?Pointer $NextPointer = null;

    public function __construct(int $qual, ?Pointer $p = null)
    {
        $this->TypeQualifiers = $qual;
        $this->NextPointer = $p;
    }
}

class PointerDeclarator extends Declarator
{
    public readonly Pointer $Pointer;

    public function __construct(Pointer $pointer, ?Declarator $decl)
    {
        parent::__construct($decl);
        $this->Pointer = $pointer;
    }

    public function getDeclaredIdentifier(): string
    {
        return $this->InnerDeclarator?->getDeclaredIdentifier() ?? '';
    }
}

class ReferenceDeclarator extends Declarator
{
    public readonly int $Qualifiers;

    public function __construct(?Declarator $inner, int $qualifiers = TypeQualifiers::None)
    {
        parent::__construct($inner);
        $this->Qualifiers = $qualifiers;
    }

    public function getDeclaredIdentifier(): string
    {
        return $this->InnerDeclarator?->getDeclaredIdentifier() ?? '';
    }
}
