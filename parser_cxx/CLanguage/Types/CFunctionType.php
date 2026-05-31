<?php declare(strict_types=1);

namespace CLanguage\Types;

use CLanguage\Compiler\EmitContext;
use CLanguage\Value;
use InvalidArgumentException;

class Parameter
{
    public function __construct(
        public string $Name = '',
        public CType  $ParameterType,
        public int    $Offset = 0,
        public ?Value $DefaultValue = null,
    )
    {
    }

    public function __toString(): string
    {
        return $this->ParameterType . ' ' . $this->Name;
    }
}

class CFunctionType extends CType
{
    public static CFunctionType $VoidProcedure;

    public CType $ReturnType;
    public bool $IsInstance;
    public ?CType $DeclaringType;
    /** @--var Parameter[] */
    public array $Parameters = [];

    public function __construct(CType $returnType, bool $isInstance, ?CType $declaringType)
    {
        $this->ReturnType = $returnType;
        $this->IsInstance = $isInstance;
        $this->DeclaringType = $declaringType;
        if ($isInstance && $declaringType === null) {
            throw new InvalidArgumentException('declaringType cannot be null when isInstance is true');
        }
    }

    public function addParameter(string $name, CType $type, ?Value $defaultValue): void
    {
        $this->Parameters[] = new Parameter($name, $type, 0, $defaultValue);
        $this->calculateParameterOffsets();
    }

    private function calculateParameterOffsets(): void
    {
        $offset = $this->IsInstance ? -1 : 0;
        for ($i = count($this->Parameters) - 1; $i >= 0; --$i) {
            $p = $this->Parameters[$i];
            $n = $p->ParameterType->getNumValues();
            $offset -= $n;
            $p->Offset = $offset;
        }
    }

    public function getNumValues(): int
    {
        return 1;
    }

    public function equals(?object $obj): bool
    {
        return $obj instanceof CFunctionType
            && $this->ReturnType->equals($obj->ReturnType)
            && $this->parameterTypesEqual($obj);
    }

    public function parameterTypesEqual(CFunctionType $otherType): bool
    {
        if (count($this->Parameters) !== count($otherType->Parameters)) {
            return false;
        }

        for ($i = 0; $i < count($this->Parameters); $i++) {
            $ft = $otherType->Parameters[$i]->ParameterType;
            $tt = $this->Parameters[$i]->ParameterType;

            if (!$ft->equals($tt)) {
                return false;
            }
        }

        return true;
    }

    public function getHashCode(): int
    {
        $pcode = 17;
        foreach ($this->Parameters as $p) {
            $pcode += $p->ParameterType->getHashCode();
        }
        return parent::getHashCode() * 13 + $this->ReturnType->getHashCode() * 11 + $pcode * 7;
    }

    public function getByteSize(EmitContext $c): int
    {
        return $c->MachineInfo->PointerSize;
    }

    public function __toString(): string
    {
        $s = '(Function ' . $this->ReturnType . ' (';
        $head = '';
        foreach ($this->Parameters as $p) {
            $s .= $head;
            $s .= $p;
            $head = ', ';
        }
        $s .= '))';
        return $s;
    }

    /**
     * @param CType[]|null $argTypes
     */
    public function scoreParameterTypeMatches(?array $argTypes): int
    {
        if ($argTypes === null) {
            return 1;
        }

        $pc = count($this->Parameters);
        $requiredParamCount = 0;

        for ($i = 0; $i < $pc; $i++) {
            if ($this->Parameters[$i]->DefaultValue !== null) {
                break;
            }
            $requiredParamCount++;
        }

        if (count($argTypes) < $requiredParamCount || count($argTypes) > $pc) {
            return 0;
        }

        $score = count($argTypes) === $pc ? 3 : 2;

        for ($i = 0; $i < count($argTypes); $i++) {
            $ft = $argTypes[$i];
            $tt = $this->Parameters[$i]->ParameterType;
            if ($tt instanceof CReferenceType) {
                $score += $ft->scoreCastTo($tt->InnerType);
            } else {
                $score += $ft->scoreCastTo($tt);
            }
        }

        return $score;
    }
}

CFunctionType::$VoidProcedure = new CFunctionType(CType::voidType(), false, null);
