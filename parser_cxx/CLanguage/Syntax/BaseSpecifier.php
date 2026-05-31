<?php declare(strict_types=1);

namespace CLanguage\Syntax;

enum DeclarationsVisibility: string
{
    case Public = 'public';
    case Private = 'private';
    case Protected = 'protected';
}

class BaseSpecifier
{
    public string $Name;
    public ?DeclarationsVisibility $Visibility;

    public function __construct(string $name, ?DeclarationsVisibility $visibility = null)
    {
        $this->Name = $name;
        $this->Visibility = $visibility;
    }

    public function __toString(): string
    {
        if ($this->Visibility !== null) {
            return strtolower($this->Visibility->name) . ' ' . $this->Name;
        }
        return $this->Name;
    }
}
