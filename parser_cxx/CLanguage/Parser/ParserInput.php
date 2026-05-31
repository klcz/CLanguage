<?php declare(strict_types=1);

namespace CLanguage\Parser;

use CLanguage\Syntax\Token;

class ParserInput
{
    public readonly array $Tokens;
    private int $index = -1;
    private array $typedefs = [];

    public function __construct(array $tokens)
    {
        $this->Tokens = $tokens;
    }

    public function advance(): bool
    {
        if ($this->index + 1 < count($this->Tokens)) {
            $this->index++;
            $this->tryRegisterStructName();
            return true;
        }
        return false;
    }

    private function tryRegisterStructName(): void
    {
        $tok = $this->Tokens[$this->index];
        if ($tok->kind === TokenKind::STRUCT || $tok->kind === TokenKind::CLASSTYPE || $tok->kind === TokenKind::UNION) {
            if ($this->index + 2 < count($this->Tokens)) {
                $nameTok = $this->Tokens[$this->index + 1];
                $afterName = $this->Tokens[$this->index + 2];
                if ($nameTok->kind === TokenKind::IDENTIFIER &&
                    ($afterName->kind === ord('{') || $afterName->kind === ord(':'))) {
                    $this->typedefs[$nameTok->stringValue()] = true;
                }
            }
        }
    }

    public function token(): int
    {
        return $this->getCurrentToken()->Kind;
    }

    public function getCurrentToken(): Token
    {
        $tok = $this->Tokens[$this->index];
        if ($tok->kind === TokenKind::IDENTIFIER && isset($this->typedefs[$tok->stringValue()])) {
            if ($this->index + 1 < count($this->Tokens) && $this->Tokens[$this->index + 1]->kind === TokenKind::COLONCOLON) {
                return $tok;
            }
            return $tok->asKind(TokenKind::TYPE_NAME);
        }
        return $tok;
    }

    public function value(): mixed
    {
        $tok = $this->getCurrentToken();
        return $tok->Value ?? '';
    }

    public function addTypedef(string $declaredIdentifier): void
    {
        $this->typedefs[$declaredIdentifier] = true;
    }
}
