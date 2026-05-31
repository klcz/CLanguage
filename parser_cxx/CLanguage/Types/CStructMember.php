<?php declare(strict_types=1);

namespace CLanguage\Types;

abstract class CStructMember
{
    public string $Name = '';
    public CType $MemberType;

    public function __construct(string $name, CType $memberType)
    {
        $this->Name = $name;
        $this->MemberType = $memberType;
    }

    public function __toString(): string
    {
        return "{$this->MemberType} {$this->Name}";
    }
}

class CStructField extends CStructMember
{
}

class CStructMethod extends CStructMember
{
    public bool $IsVirtual = false;
    public bool $IsOverride = false;
    public bool $IsPureVirtual = false;
    public ?int $VTableSlotIndex = null;

    public function __construct(
        string $name,
        CType  $memberType,
        bool   $isVirtual,
        bool   $isOverride,
        bool   $isPureVirtual,
    )
    {
        parent::__construct($name, $memberType);
        $this->IsVirtual = $isVirtual;
        $this->IsOverride = $isOverride;
        $this->IsPureVirtual = $isPureVirtual;
    }

}
