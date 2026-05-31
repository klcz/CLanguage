<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Parser\CParser;

readonly class Token
{
    public int $Kind;
    public Location $Location;
    public Location $EndLocation;
    public mixed $Value;

    public function __construct(int $kind, mixed $value = null, ?Location $location = null, ?Location $endLocation = null)
    {
        $this->Kind = $kind;
        $this->Value = $value ?? '';
        $this->Location = $location ?? Location::null();
        $this->EndLocation = $endLocation ?? Location::null();
    }

    public static function fromChar(int $kind): self
    {
        return new self($kind, null, Location::null(), Location::null());
    }

    public function stringValue(): string
    {
        if (is_string($this->Value)) return $this->Value;
        if ($this->Value !== null) return (string)$this->Value;
        return '';
    }

    public function asKind(int $kind): self
    {
        return new self($kind, $this->Value, $this->Location, $this->EndLocation);
    }

    public function equals(mixed $other): bool
    {
        if (!$other instanceof self) return false;

        return $this->Kind === $other->Kind
            && $this->Location->equals($other->Location)
            && (($this->Value === null && $other->Value === null)
                || ($this->Value !== null && $this->Value === $other->Value));
    }

    public function hashCode(): int
    {
        $hash = 666775603;
        $hash = $hash * -1521134295 + $this->Kind;
        $hash = $hash * -1521134295 + crc32(serialize($this->Location));
        return $hash * -1521134295 + ($this->Value !== null ? crc32(serialize($this->Value)) : 0);
    }

    public function __toString(): string
    {
        if ($this->Location->isNull()) {
            $text = $this->Value !== null ? (string)$this->Value : ($this->Kind < 127 ? chr($this->Kind) : '');
        } else {
            $text = $this->text();
        }

        if ($this->Kind < 127) {
            return '"' . $text . '"';
        }
        return '"' . $text . '": ' . CParser::yyname($this->Kind);
    }

    public function text(): string
    {
        if ($this->Location->isNull() || $this->EndLocation->isNull()) {
            return '';
        }
        if ($this->Location->Document?->Path !== $this->EndLocation->Document?->Path) {
            return '';
        }
        return substr(
            $this->Location->Document->Content,
            $this->Location->Index,
            $this->EndLocation->Index - $this->Location->Index
        );
    }
}
