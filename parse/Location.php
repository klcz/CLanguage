<?php
namespace parse;

class Location
{
    public $Document;
    public $Index;
    public $Line;
    public $Column;

    public function __construct($document = null, int $index = 0, int $line = 0, int $column = 0)
    {
        $this->Document = $document;
        $this->Index = $index;
        $this->Line = $line;
        $this->Column = $column;
    }

    public function getIsNull(): bool
    {
        return $this->Document === null;
    }

    public static function getNull(): Location
    {
        return new Location(null, 0, 1, 1);
    }

    public function __toString(): string
    {
        if ($this->getIsNull()) {
            return '?(?,?)';
        }
        return $this->Document . '(' . $this->Line . ',' . $this->Column . ')';
    }

    public function addOffset(int $columnOffset): Location
    {
        return new Location(
            $this->Document,
            $this->Index + $columnOffset,
            $this->Line,
            $this->Column + $columnOffset
        );
    }

    public function equals(Location $y): bool
    {
        return $this->Line === $y->Line &&
            $this->Column === $y->Column &&
            ($this->Document !== null ? $this->Document->Path : null) === ($y->Document !== null ? $y->Document->Path : null);
    }
}
