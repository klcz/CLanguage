<?php declare(strict_types=1);

namespace CLanguage\Types;

use CLanguage\Compiler\EmitContext;

class CEnumType extends CType
{
    public string $Name;
    /** @--var CEnumMember[] */
    public array $Members = [];
    public int $NextValue {
        get => $this->getNextValue();
    }

    public function __construct(string $name)
    {
        $this->Name = $name;
    }

    public function getNextValue(): int
    {
        if (count($this->Members) > 0) {
            return $this->Members[count($this->Members) - 1]->value + 1;
        }
        return 0;
    }

    public function getNumValues(): int
    {
        return 1;
    }

    public function isIntegral(): bool
    {
        return true;
    }

    public function getByteSize(EmitContext $c): int
    {
        return CBasicType::signedInt()->getByteSize($c);
    }
}
