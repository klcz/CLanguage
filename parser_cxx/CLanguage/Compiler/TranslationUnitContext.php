<?php declare(strict_types=1);

namespace CLanguage\Compiler;

use CLanguage\Syntax\TranslationUnit;
use CLanguage\Syntax\TypeName;
use CLanguage\Types\CType;

class TranslationUnitContext extends BlockContext
{
    public readonly TranslationUnit $translationUnit;

    public function __construct(TranslationUnit $translationUnit, ExecutableContext $exeContext)
    {
        parent::__construct($translationUnit, $exeContext);
        $this->translationUnit = $translationUnit;
    }

    public function resolveTypeName(TypeName|string $typeName): CType
    {
        if (isset($this->translationUnit->Typedefs[$typeName])) {
            return $this->translationUnit->Typedefs[$typeName];
        }
        if (isset($this->translationUnit->Structures[$typeName])) {
            return $this->translationUnit->Structures[$typeName];
        }
        return parent::resolveTypeName($typeName);
    }

    public function tryResolveVariable(string $name, ?array $argTypes): ?ResolvedVariable
    {
        // HACK: There is some confusion about when and why we're looking up variables
        // Sometimes, we just need the type when we're building types
        // Other times, we need its real memory address
        // This function can provide the type, but not the address
        // So we ask our parent (which is probably a ExeContext) for the variable first
        $v = $this->parentContext?->tryResolveVariable($name, $argTypes);
        if ($v !== null) {
            return $v;
        }

        foreach ($this->translationUnit->Enums as $e) {
            foreach ($e->members as $em) {
                if ($em->Name === $name) {
                    return ResolvedVariable::fromConstant($em->value, $e);
                }
            }
        }

        return parent::tryResolveVariable($name, $argTypes);
    }
}
