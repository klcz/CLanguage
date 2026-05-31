<?php declare(strict_types=1);

namespace CLanguage\Types;

use CLanguage\Compiler\EmitContext;

class CBoolType extends CBasicType
{
    public function __construct()
    {
        parent::__construct("bool", Signedness::Unsigned, "");
    }

    public function isIntegral(): bool
    {
        return true;
    }

    public function getNumValues(): int
    {
        return 1;
    }

    public function getByteSize(EmitContext $c): int
    {
        return $c->MachineInfo->CharSize;
    }

    public function __toString(): string
    {
        return "bool";
    }
}
