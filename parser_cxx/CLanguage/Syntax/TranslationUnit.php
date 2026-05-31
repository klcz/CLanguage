<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\VariableScope;
use InvalidArgumentException;

class TranslationUnit extends Block
{
    public readonly string $Name;

    public function __construct(string $name)
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Translation unit name must be specified');
        }

        $this->Name = $name;
        parent::__construct(VariableScope::Global, []);
    }

    public function __toString(): string
    {
        return $this->Name;
    }
}
