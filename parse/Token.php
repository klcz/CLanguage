<?php
namespace parse;

class Token
{
    public $Kind;
    public $Location;
    public $EndLocation;
    public $Value;

    public function __construct(int $kind, $value = null, $location = null, $endLocation = null)
    {
        $this->Kind = $kind;
        $this->Value = $value ?? '';
        $this->Location = $location ?? Location::getNull();
        $this->EndLocation = $endLocation ?? Location::getNull();
    }

    public static function fromChar(int $kind): Token
    {
        $t = new Token($kind, null);
        $t->Location = Location::getNull();
        $t->EndLocation = Location::getNull();
        return $t;
    }

    public function getStringValue(): string
    {
        if (is_string($this->Value)) {
            return $this->Value;
        }
        if ($this->Value !== null) {
            return (string)$this->Value;
        }
        return '';
    }

    public function getText(): string
    {
        if ($this->Location->getIsNull() || $this->EndLocation->getIsNull()) {
            return '';
        }
        return substr($this->Location->Document->Content, $this->Location->Index, $this->EndLocation->Index - $this->Location->Index);
    }

    public function asKind(int $kind): Token
    {
        return new Token($kind, $this->Value, $this->Location, $this->EndLocation);
    }
}
