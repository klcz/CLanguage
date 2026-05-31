<?php declare(strict_types=1);

namespace CLanguage\Syntax;

readonly class TypeName
{
    public DeclarationSpecifiers $Specifiers;
    public ?Declarator $Declarator;

    public function __construct(DeclarationSpecifiers $specifiers, ?Declarator $declarator = null)
    {
        $this->Specifiers = $specifiers;
        $this->Declarator = $declarator;
    }

    public function __toString(): string
    {
        return implode(', ', (array)$this->Specifiers);
    }
}
