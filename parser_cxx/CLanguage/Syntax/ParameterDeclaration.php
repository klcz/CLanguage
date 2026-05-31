<?php declare(strict_types=1);

namespace CLanguage\Syntax;

class ParameterDeclaration
{
    public string $Name = '';
    public ?DeclarationSpecifiers $DeclarationSpecifiers = null;
    public ?Declarator $Declarator = null;
    public ?Expression $DefaultValue = null;
    public ?Expression $CtorArgumentValue = null;

    public function __construct(
        string|DeclarationSpecifiers|Expression $nameOrSpecs = '',
        ?Declarator                             $declarator = null,
        ?Expression                             $defaultValue = null,
    )
    {
        if ($nameOrSpecs instanceof DeclarationSpecifiers) {
            $this->DeclarationSpecifiers = $nameOrSpecs;
            if ($declarator !== null) {
                $this->Name = $declarator->getDeclaredIdentifier();
                $this->Declarator = $declarator;
            }
            if ($defaultValue !== null) {
                $this->DefaultValue = $defaultValue;
            }
        } elseif ($nameOrSpecs instanceof Expression) {
            $this->CtorArgumentValue = $nameOrSpecs;
        } else {
            $this->Name = $nameOrSpecs;
            $this->DeclarationSpecifiers = $declarator instanceof DeclarationSpecifiers ? $declarator : null;
            $this->Declarator = $defaultValue instanceof Declarator ? $defaultValue : $declarator;
        }
    }

    public static function fromName(string $name): self
    {
        return new self($name);
    }

    /** C++ constructor arguments look like function declarations; we reuse this type */
    public static function fromCtorArgument(Expression $expr): self
    {
        return new self($expr);
    }

    public static function fromSpecs(DeclarationSpecifiers $specs): self
    {
        return new self($specs);
    }

    public static function fromSpecsDeclarator(DeclarationSpecifiers $specs, Declarator $dec): self
    {
        return new self($specs, $dec);
    }

    public static function fromSpecsDeclaratorDefault(DeclarationSpecifiers $specs, Declarator $dec, Expression $defaultValue): self
    {
        return new self($specs, $dec, $defaultValue);
    }

    public function __toString(): string
    {
        return ($this->DeclarationSpecifiers ?? '') . ' ' . ($this->Declarator ?? '');
    }
}

class VarParameter extends ParameterDeclaration
{
    public function __construct()
    {
        parent::__construct('...');
    }
}
