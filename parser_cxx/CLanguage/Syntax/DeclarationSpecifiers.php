<?php declare(strict_types=1);

namespace CLanguage\Syntax;

class DeclarationSpecifiers
{
    public int $StorageClassSpecifier = StorageClassSpecifier::None;
    public array $TypeSpecifiers;
    public int $FunctionSpecifier = FunctionSpecifier::None;
    public int $TypeQualifiers = TypeQualifiers::None;

    public function __construct()
    {
        $this->TypeSpecifiers = [];
    }

    public function __toString(): string
    {
        if ($this->StorageClassSpecifier === StorageClassSpecifier::Auto) {
            return 'auto';
        }
        return implode(' ', $this->TypeSpecifiers);
    }
}
