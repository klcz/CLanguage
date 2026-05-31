<?php declare(strict_types=1);

namespace CLanguage\Syntax;

readonly class Location
{
    public ?Document $Document;
    public int $Index;
    public int $Line;
    public int $Column;

    public function __construct(?Document $document = null, int $index = 0, int $line = 0, int $column = 0)
    {
        $this->Document = $document;
        $this->Index = $index;
        $this->Line = $line;
        $this->Column = $column;
    }

    public static function null(): self
    {
        return new self();
    }

    public function add(int $columnOffset): self
    {
        return new self($this->Document, $this->Index + $columnOffset, $this->Line, $this->Column + $columnOffset);
    }

    public function equals(mixed $other): bool
    {
        if (!$other instanceof self) return false;
        return $this->Line === $other->Line
            && $this->Column === $other->Column
            && $this->Document?->Path === $other->Document?->Path;
    }

    public function hashCode(): int
    {
        $hash = 1439312346;
        $hash = $hash * -1521134295 + ($this->Document !== null ? crc32($this->Document->Path) : 0);
        $hash = $hash * -1521134295 + $this->Line;
        return $hash * -1521134295 + $this->Column;
    }

    public function __toString(): string
    {
        if ($this->isNull()) {
            return '?(?,?)';
        }
        return $this->Document . '(' . $this->Line . ',' . $this->Column . ')';
    }

    public function isNull(): bool
    {
        return $this->Document === null;
    }
}
