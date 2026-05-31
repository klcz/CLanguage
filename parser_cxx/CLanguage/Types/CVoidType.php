<?php declare(strict_types=1);

namespace CLanguage\Types;

use CLanguage\Compiler\EmitContext;

class CVoidType extends CType
{
    public function isVoid(): bool
    {
        return true;
    }

    public function getNumValues(): int
    {
        return 0;
    }

    public function getByteSize(EmitContext $c): int
    {
        $c->Report->error(2070, error: "'void': illegal sizeof operand");
        return 0;
    }

    public function __toString(): string
    {
        return "void";
    }

    public function equals(?object $obj): bool
    {
        return $obj instanceof CVoidType;
    }

    public function getHashCode(): int
    {
        return 17;
    }
}
