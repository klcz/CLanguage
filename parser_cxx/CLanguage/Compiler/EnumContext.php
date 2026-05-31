<?php declare(strict_types=1);

namespace CLanguage\Compiler;

use CLanguage\Syntax\TypeSpecifier;
use CLanguage\Types\CEnumMember;
use CLanguage\Types\CEnumType;
use CLanguage\Value;

class EnumContext extends EmitContext
{
    protected TypeSpecifier $enumTs;
    protected CEnumType $et;
    protected EmitContext $emitContext;

    public function __construct(TypeSpecifier $enumTs, CEnumType $et, EmitContext $parentContext)
    {
        parent::__construct(parentContext: $parentContext);
        $this->enumTs = $enumTs;
        $this->et = $et;
        $this->emitContext = $parentContext;
    }

    public function tryResolveVariable(string $name, ?array $argTypes): ?ResolvedVariable
    {
        foreach ($this->et->Members as $member) {
            if ($member instanceof CEnumMember && $member->Name === $name) {
                return ResolvedVariable::fromConstant(Value::fromInt($member->Value), $this->et);
            }
        }
        return parent::tryResolveVariable($name, $argTypes);
    }
}
