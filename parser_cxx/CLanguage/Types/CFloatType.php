<?php declare(strict_types=1);

namespace CLanguage\Types;

use CLanguage\Compiler\EmitContext;
use Override;

class CFloatType extends CBasicType
{
    public readonly int $Bits;

    public function __construct(string $name, int $bits)
    {
        parent::__construct($name, Signedness::Signed, "");
        $this->Bits = $bits;
    }

    #[Override]
    public function getNumValues(): int
    {
        return 1;
    }

    #[Override]
    public function getByteSize(EmitContext $c): int
    {
        return intdiv($this->Bits, 8);
    }

    #[Override]
    public function scoreCastTo(CType $otherType): int
    {
        if ($this->equals($otherType)) return 1000;
        if ($otherType instanceof CFloatType) {
            return 900;
        } else {
            return 0;
        }
    }
}
