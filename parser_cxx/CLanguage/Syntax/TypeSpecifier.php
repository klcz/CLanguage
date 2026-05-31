<?php declare(strict_types=1);

namespace CLanguage\Syntax;

enum TypeSpecifierKind: int
{
    case Builtin = 0;
    case Typename = 1;
    case Struct = 2;
    case ClassType = 3;
    case Union = 4;
    case Enum = 5;
}

class TypeSpecifier
{
    public readonly TypeSpecifierKind $Kind;
    public readonly string $Name;
    public readonly ?Block $Body;
    public ?array $BaseSpecifiers = null;

    public function __construct(TypeSpecifierKind $kind, string $name, ?Block $body = null)
    {
        $this->Kind = $kind;
        $this->Name = $name;
        $this->Body = $body;
        $this->BaseSpecifiers = null;
    }

    public function __toString(): string
    {
        return $this->Name;
    }
}
