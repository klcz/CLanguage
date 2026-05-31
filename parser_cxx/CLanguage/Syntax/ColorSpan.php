<?php declare(strict_types=1);

namespace CLanguage\Syntax;

enum SyntaxColor: int
{
    case Comment = 0;
    case Identifier = 1;
    case Number = 2;
    case String = 3;
    case Keyword = 4;
    case Operator = 5;
    case Function = 6;
    case Type = 7;
}

class ColorSpan
{
    public int $Index;
    public int $Length;
    public SyntaxColor $Color;

    public function __construct(int $Index, int $Length, SyntaxColor $Color)
    {
        $this->Index = $Index;
        $this->Length = $Length;
        $this->Color = $Color;
    }

    public function __toString(): string
    {
        return $this->Color->name;
    }
}
