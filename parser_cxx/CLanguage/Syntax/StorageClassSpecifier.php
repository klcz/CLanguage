<?php declare(strict_types=1);

namespace CLanguage\Syntax;

class StorageClassSpecifier
{
    const int None = 0;
    const int Typedef = 1;
    const int Extern = 2;
    const int Static = 4;
    const int Auto = 8;
    const int Register = 16;
}
