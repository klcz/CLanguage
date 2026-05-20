<?php
namespace parse;

class ParserInput
{
    public $Tokens;
    private $index = -1;
    private $typedefs = [];

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
        if ($tok->Kind === TokenKind::STRUCT || $tok->Kind === TokenKind::CLASS || $tok->Kind === TokenKind::UNION) {
            if ($this->index + 2 < count($this->Tokens)) {
                $nameTok = $this->Tokens[$this->index + 1];
                $afterName = $this->Tokens[$this->index + 2];
                if ($nameTok->Kind === TokenKind::IDENTIFIER &&
                    ($afterName->Kind === ord('{') || $afterName->Kind === ord(':'))) {
                    $this->typedefs[$nameTok->getStringValue()] = true;
                }
            }
        }
    }

    public function token(): int
    {
        return $this->getCurrentToken()->Kind;
    }

    public function value()
    {
        $tok = $this->getCurrentToken();
        return $tok->Value ?? '';
    }

    public function getCurrentToken(): Token
    {
        $tok = $this->Tokens[$this->index];
        if ($tok->Kind === TokenKind::IDENTIFIER && isset($this->typedefs[$tok->getStringValue()])) {
            if ($this->index + 1 < count($this->Tokens) && $this->Tokens[$this->index + 1]->Kind === TokenKind::COLONCOLON) {
                return $tok;
            }
            return $tok->asKind(TokenKind::TYPE_NAME);
        }
        return $tok;
    }

    public function addTypedef(string $declaredIdentifier): void
    {
        $this->typedefs[$declaredIdentifier] = true;
    }
}
