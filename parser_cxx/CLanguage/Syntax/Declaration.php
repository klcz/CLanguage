<?php declare(strict_types=1);

namespace CLanguage\Syntax;

abstract class Declaration extends Statement
{
    public DeclarationSpecifiers $Specifiers;
    public ?Declarator $Declarator = null;
    public ?Initializer $Initializer = null;

    public function __construct(DeclarationSpecifiers $specs, ?Declarator $decl = null, ?Initializer $init = null)
    {
        $this->Specifiers = $specs;
        $this->Declarator = $decl;
        $this->Initializer = $init;
    }

    public function getAlwaysReturns(): bool
    {
        return false;
    }
}
