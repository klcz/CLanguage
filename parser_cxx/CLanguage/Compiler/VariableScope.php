<?php declare(strict_types=1);

namespace CLanguage\Compiler;

enum VariableScope: int
{
    case Global = 0;
    case Arg = 1;
    case Local = 2;
    case Function = 3;
    case Constant = 4;
}
