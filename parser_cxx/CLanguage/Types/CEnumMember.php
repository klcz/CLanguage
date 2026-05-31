<?php declare(strict_types=1);

namespace CLanguage\Types;

use InvalidArgumentException;

readonly class CEnumMember
{
    public string $Name;
    public int $Value;

    public function __construct(string $name, int $value)
    {
        if ($name === '') {
            throw new InvalidArgumentException('Name cannot be empty');
        }
        $this->Name = $name;
        $this->Value = $value;
    }

    public function __toString(): string
    {
        return "{$this->Name} = {$this->Value}";
    }
}
