<?php
namespace parse;

class Lexer
{
    private $token = -1;
    private $value = null;
    private $lastR = -2;
    private $chbuf = '';
    private $chbuflen = 0;
    private $location;
    private $endLocation;
    private $line = 1;
    private $column = 1;
    private $nextPosition = 0;

    public $Report;
    public $Document;
    public $CurrentToken;
    public $IsTypedef;

    private static $kwTokens = [];
    private static $keywordTokens = null;
    private static $operatorTokens = null;

    public function __construct($document, $report = null)
    {
        $this->Report = $report ?? new Report();
        if ($document instanceof Document) {
            $this->Document = $document;
        } else {
            $this->Document = new Document('', $document ?? '');
        }
        $this->location = new Location($this->Document, 0, 1, 1);
        $this->endLocation = $this->location;
        $this->IsTypedef = function ($name) {
            return false;
        };
        $this->initStatics();
    }

    private static function initStatics(): void
    {
        if (empty(self::$kwTokens)) {
            self::$kwTokens = [
                'auto' => TokenKind::AUTO,
                'bool' => TokenKind::BOOL,
                'break' => TokenKind::BREAK,
                'case' => TokenKind::CASE,
                'char' => TokenKind::CHAR,
                'class' => TokenKind::CLASS,
                'const' => TokenKind::CONST,
                'continue' => TokenKind::CONTINUE,
                'default' => TokenKind::DEFAULT,
                'do' => TokenKind::DO,
                'double' => TokenKind::DOUBLE,
                'else' => TokenKind::ELSE,
                'enum' => TokenKind::ENUM,
                'extern' => TokenKind::EXTERN,
                'false' => TokenKind::FALSE,
                'float' => TokenKind::FLOAT,
                'for' => TokenKind::FOR,
                'goto' => TokenKind::GOTO,
                'if' => TokenKind::IF,
                'inline' => TokenKind::INLINE,
                'int' => TokenKind::INT,
                'long' => TokenKind::LONG,
                'public' => TokenKind::PUBLIC,
                'private' => TokenKind::PRIVATE,
                'protected' => TokenKind::PROTECTED,
                'register' => TokenKind::REGISTER,
                'restrict' => TokenKind::RESTRICT,
                'return' => TokenKind::RETURN,
                'short' => TokenKind::SHORT,
                'signed' => TokenKind::SIGNED,
                'sizeof' => TokenKind::SIZEOF,
                'static' => TokenKind::STATIC,
                'struct' => TokenKind::STRUCT,
                'switch' => TokenKind::SWITCH,
                'true' => TokenKind::TRUE,
                'typedef' => TokenKind::TYPEDEF,
                'union' => TokenKind::UNION,
                'unsigned' => TokenKind::UNSIGNED,
                'virtual' => TokenKind::VIRTUAL,
                'void' => TokenKind::VOID,
                'volatile' => TokenKind::VOLATILE,
                'while' => TokenKind::WHILE,
                'override' => TokenKind::OVERRIDE,
                'operator' => TokenKind::OPERATOR,
            ];
        }
    }

    public static function getKeywordTokens(): array
    {
        if (self::$keywordTokens === null) {
            self::$keywordTokens = array_values(self::$kwTokens);
        }
        return self::$keywordTokens;
    }

    public static function getOperatorTokens(): array
    {
        if (self::$operatorTokens === null) {
            self::$operatorTokens = [
                TokenKind::EQ_OP,
                TokenKind::GE_OP,
                TokenKind::LE_OP,
                TokenKind::NE_OP,
                TokenKind::OR_OP,
                TokenKind::AND_OP,
                TokenKind::DEC_OP,
                TokenKind::INC_OP,
                TokenKind::PTR_OP,
                TokenKind::LEFT_OP,
                TokenKind::RIGHT_OP,
            ];
        }
        return self::$operatorTokens;
    }

    private function read(): int
    {
        if ($this->nextPosition < strlen($this->Document->Content)) {
            $r = ord($this->Document->Content[$this->nextPosition]);
            $this->nextPosition++;
            $this->column++;
            return $r;
        }
        return -1;
    }

    private function peek(): int
    {
        if ($this->nextPosition < strlen($this->Document->Content)) {
            return ord($this->Document->Content[$this->nextPosition]);
        }
        return -1;
    }

    private function eof(): bool
    {
        $this->value = null;
        $this->token = -1;
        return false;
    }

    public function skipWhiteSpace(): void
    {
        $r = $this->lastR;
        if ($r === -2) {
            $r = $this->read();
        }

        $skippedComment = true;
        while ($skippedComment) {
            while ($r >= 0 && $r <= ord(' ')) {
                if ($r === ord("\n") && $r !== 8232) {
                    break;
                }
                $r = $this->read();
            }

            $skippedComment = false;

            if ($r === ord('/') && $this->peek() === ord('/')) {
                $nr = $this->read();
                while ($nr > 0 && $nr !== ord("\n") && $nr !== 8232) {
                    $nr = $this->read();
                }
                $r = $nr;
            } elseif ($r === ord('/') && $this->peek() === ord('*')) {
                $nr = $this->read();
                while ($nr > 0 && !($nr === ord('*') && $this->peek() === ord('/'))) {
                    if ($nr === ord("\n") || $nr === 8232) {
                        $this->line++;
                        $this->column = 1;
                    }
                    $nr = $this->read();
                }
                $this->read();
                $r = $this->read();
                $skippedComment = true;
            }
        }

        $this->lastR = $r;
    }

    public function advance(): bool
    {
        $this->skipWhiteSpace();

        $r = $this->lastR;

        if ($r === -1) {
            return $this->eof();
        }

        $this->location = new Location($this->location->Document, $this->nextPosition - 1, $this->line, $this->column);

        $ch = chr($r);
        if ($ch === "\n" || $r === 8232) {
            $this->token = TokenKind::EOL;
            $this->value = null;
            $this->lastR = $this->read();
            $this->line++;
            $this->column = 1;
        } elseif (ctype_digit($ch)) {
            $onlydigits = true;
            $islong = false;
            $isunsigned = false;
            $isfloat = false;
            $ishex = false;
            $this->chbuf = '';
            $this->chbuflen = 0;
            $this->chbuf .= $ch;
            $this->chbuflen++;
            while ($ch === '.' || ctype_digit($ch) || $ch === 'E' || $ch === 'e' || $ch === 'f' || $ch === 'F' || $ch === 'u' || $ch === 'U' || $ch === 'l' || $ch === 'L' || (!$ishex && $ch === 'x') || ($ishex && self::isHex($ch))) {
                if ($ch === 'l' || $ch === 'L') {
                    $islong = true;
                } elseif ($ch === 'u' || $ch === 'U') {
                    $isunsigned = true;
                } elseif (!$ishex && ($ch === 'f' || $ch === 'F')) {
                    $isfloat = true;
                } elseif ($ch === 'x' && $this->chbuflen === 1 && $this->chbuf[0] === '0') {
                    $ishex = true;
                } else {
                    $onlydigits = $onlydigits && ctype_digit($ch);
                    $this->chbuf .= $ch;
                    $this->chbuflen++;
                }
                $r = $this->read();
                $ch = chr($r);
            }
            $this->lastR = $r;

            $vals = substr($this->chbuf, 0, $this->chbuflen);
            $this->endLocation = new Location($this->location->Document, $this->lastR >= 0 ? $this->nextPosition - 1 : strlen($this->location->Document->Content), $this->line, $this->column);
            if ($onlydigits || $ishex) {
                if ($islong) {
                    if ($isunsigned) {
                        $this->value = (int)$vals;
                    } else {
                        $this->value = (int)$vals;
                    }
                } else {
                    if ($isunsigned) {
                        $this->value = (int)$vals;
                    } else {
                        $this->value = (int)$vals;
                    }
                }
            } else {
                if ($isfloat) {
                    $this->value = (float)$vals;
                } else {
                    $this->value = (float)$vals;
                }
            }

            $this->token = TokenKind::CONSTANT;
        } elseif ($r === ord('=')) {
            $r = $this->read();
            if ($r === ord('=')) {
                $this->token = TokenKind::EQ_OP;
                $this->value = null;
                $this->lastR = $this->read();
            } else {
                $this->token = ord('=');
                $this->value = null;
                $this->lastR = $r;
            }
        } elseif ($r === ord('!')) {
            $r = $this->read();
            if ($r === ord('=')) {
                $this->token = TokenKind::NE_OP;
                $this->value = null;
                $this->lastR = $this->read();
            } else {
                $this->token = ord('!');
                $this->value = null;
                $this->lastR = $r;
            }
        } elseif ($r === ord(':')) {
            $r = $this->read();
            if ($r === ord(':')) {
                $this->token = TokenKind::COLONCOLON;
                $this->value = null;
                $this->lastR = $this->read();
            } else {
                $this->token = ord(':');
                $this->value = null;
                $this->lastR = $r;
            }
        } elseif (in_array($r, [ord(','), ord(';'), ord('?'), ord('('), ord(')'), ord('{'), ord('}'), ord('['), ord(']'), ord('~'), ord('%'), ord('#'), ord('\\')], true)) {
            $this->token = $r;
            $this->value = null;
            $this->lastR = $this->read();
        } elseif ($r === ord('.')) {
            $nr = $this->read();
            if ($nr === ord('.') && $this->peek() === ord('.')) {
                $r = $this->read();
                if ($r === ord('.')) {
                    $this->token = TokenKind::ELLIPSIS;
                    $this->value = null;
                    $this->lastR = $this->read();
                } else {
                    $this->token = ord('.');
                    $this->value = null;
                    $this->lastR = $r;
                    $this->Report->error(1001, $this->location->addOffset(1), $this->location->addOffset(2), 'Identifier expected');
                }
            } else {
                $this->token = $r;
                $this->value = null;
                $this->lastR = $nr;
            }
        } elseif ($r === ord('*') || $r === ord('/')) {
            $nr = $this->read();
            if ($nr === ord('=')) {
                $nr = $this->read();
                $this->token = ($r === ord('*')) ? TokenKind::MUL_ASSIGN : TokenKind::DIV_ASSIGN;
                $this->value = null;
                $this->lastR = $nr;
            } else {
                $this->token = $r;
                $this->value = null;
                $this->lastR = $nr;
            }
        } elseif ($r === ord('^')) {
            $nr = $this->read();
            if ($nr === ord('=')) {
                $nr = $this->read();
                $this->token = TokenKind::BINARY_XOR_ASSIGN;
                $this->value = null;
                $this->lastR = $nr;
            } else {
                $this->token = $r;
                $this->value = null;
                $this->lastR = $nr;
            }
        } elseif ($r === ord('&')) {
            $nr = $this->read();
            if ($nr === ord('&')) {
                $nr = $this->read();
                if ($nr === ord('=')) {
                    $nr = $this->read();
                    $this->token = TokenKind::AND_ASSIGN;
                    $this->value = null;
                    $this->lastR = $nr;
                } else {
                    $this->token = TokenKind::AND_OP;
                    $this->value = null;
                    $this->lastR = $nr;
                }
            } elseif ($nr === ord('=')) {
                $nr = $this->read();
                $this->token = TokenKind::BINARY_AND_ASSIGN;
                $this->value = null;
                $this->lastR = $nr;
            } else {
                $this->token = $r;
                $this->value = null;
                $this->lastR = $nr;
            }
        } elseif ($r === ord('|')) {
            $nr = $this->read();
            if ($nr === ord('|')) {
                $nr = $this->read();
                if ($nr === ord('=')) {
                    $nr = $this->read();
                    $this->token = TokenKind::OR_ASSIGN;
                    $this->value = null;
                    $this->lastR = $nr;
                } else {
                    $this->token = TokenKind::OR_OP;
                    $this->value = null;
                    $this->lastR = $nr;
                }
            } elseif ($nr === ord('=')) {
                $nr = $this->read();
                $this->token = TokenKind::BINARY_OR_ASSIGN;
                $this->value = null;
                $this->lastR = $nr;
            } else {
                $this->token = $r;
                $this->value = null;
                $this->lastR = $nr;
            }
        } elseif ($r === ord('+')) {
            $nr = $this->read();
            if ($nr === ord('=')) {
                $this->token = TokenKind::ADD_ASSIGN;
                $this->value = null;
                $this->lastR = $this->read();
            } elseif ($nr === ord('+')) {
                $this->token = TokenKind::INC_OP;
                $this->value = null;
                $this->lastR = $this->read();
            } else {
                $this->token = $r;
                $this->value = null;
                $this->lastR = $nr;
            }
        } elseif ($r === ord('-')) {
            $nr = $this->read();
            if ($nr === ord('=')) {
                $this->token = TokenKind::SUB_ASSIGN;
                $this->value = null;
                $this->lastR = $this->read();
            } elseif ($nr === ord('-')) {
                $this->token = TokenKind::DEC_OP;
                $this->value = null;
                $this->lastR = $this->read();
            } elseif ($nr === ord('>')) {
                $this->token = TokenKind::PTR_OP;
                $this->value = null;
                $this->lastR = $this->read();
            } else {
                $this->token = $r;
                $this->value = null;
                $this->lastR = $nr;
            }
        } elseif ($r === ord('<')) {
            $nr = $this->read();
            if ($nr === ord('=')) {
                $this->token = TokenKind::LE_OP;
                $this->value = null;
                $this->lastR = $this->read();
            } elseif ($nr === ord('<')) {
                $this->token = TokenKind::LEFT_OP;
                $this->value = null;
                $this->lastR = $this->read();
            } else {
                $this->token = $r;
                $this->value = null;
                $this->lastR = $nr;
            }
        } elseif ($r === ord('>')) {
            $nr = $this->read();
            if ($nr === ord('=')) {
                $this->token = TokenKind::GE_OP;
                $this->value = null;
                $this->lastR = $this->read();
            } elseif ($nr === ord('>')) {
                $this->token = TokenKind::RIGHT_OP;
                $this->value = null;
                $this->lastR = $this->read();
            } else {
                $this->token = $r;
                $this->value = null;
                $this->lastR = $nr;
            }
        } elseif ($r === ord('"')) {
            $this->chbuf = '';
            $this->chbuflen = 0;
            $r = $this->read();
            $ch = chr($r);
            $done = $r < 0 || $ch === '"';
            while (!$done && $this->chbuflen + 1 < 4096) {
                if ($ch === '\\') {
                    $r = $this->read();
                    $ch = chr($r);
                    if ($r >= 0) {
                        $advanceAfterEscape = true;
                        switch ($ch) {
                            case '\\':
                                $this->chbuf .= '\\';
                                $this->chbuflen++;
                                break;
                            case 'r':
                                $this->chbuf .= "\r";
                                $this->chbuflen++;
                                break;
                            case 'n':
                                $this->chbuf .= "\n";
                                $this->chbuflen++;
                                break;
                            case 't':
                                $this->chbuf .= "\t";
                                $this->chbuflen++;
                                break;
                            case "'":
                                $this->chbuf .= "'";
                                $this->chbuflen++;
                                break;
                            case '"':
                                $this->chbuf .= '"';
                                $this->chbuflen++;
                                break;
                            case '0':
                                $this->chbuf .= "\0";
                                $this->chbuflen++;
                                break;
                            case 'x':
                                $hex = 0;
                                $r = $this->read();
                                $ch = chr($r);
                                while ($r >= 0 && (ctype_digit($ch) || self::isHex($ch))) {
                                    $hex = $hex * 16 + self::hexVal($ch);
                                    $r = $this->read();
                                    $ch = chr($r);
                                }
                                $this->chbuf .= chr($hex);
                                $this->chbuflen++;
                                $advanceAfterEscape = false;
                                break;
                            default:
                                if (ctype_space(chr($r))) {
                                    while ($r > 0 && $r !== ord("\n") && $r !== 8232) {
                                        $r = $this->read();
                                    }
                                } else {
                                    throw new \RuntimeException('Unrecognized string escape sequence');
                                }
                                break;
                        }
                        if ($advanceAfterEscape) {
                            $r = $this->read();
                            $ch = chr($r);
                        }
                    }
                } elseif ($ch === "\n" || $r === 8232) {
                    $this->endLocation = new Location($this->location->Document, $this->lastR >= 0 ? $this->nextPosition - 1 : strlen($this->location->Document->Content), $this->line, $this->column);
                    $this->Report->error(1010, $this->location, $this->endLocation, 'Newline in constant');
                    $done = true;
                } else {
                    $this->chbuf .= $ch;
                    $this->chbuflen++;
                    $r = $this->read();
                    $ch = chr($r);
                }
                $done = $done || $r < 0 || $ch === '"';
            }

            $this->lastR = $this->read();

            $this->token = TokenKind::STRING_LITERAL;
            $this->value = substr($this->chbuf, 0, $this->chbuflen);
        } elseif ($r === ord("'")) {
            $this->chbuf = '';
            $this->chbuflen = 0;
            $r = $this->read();
            $ch = chr($r);
            $done = $r < 0 || $ch === "'";
            while (!$done && $this->chbuflen + 1 < 4096) {
                if ($ch === '\\') {
                    $r = $this->read();
                    $ch = chr($r);
                    if ($r >= 0) {
                        $advanceAfterEscape = true;
                        switch ($ch) {
                            case '\\':
                                $this->chbuf .= '\\';
                                $this->chbuflen++;
                                break;
                            case 'r':
                                $this->chbuf .= "\r";
                                $this->chbuflen++;
                                break;
                            case 'n':
                                $this->chbuf .= "\n";
                                $this->chbuflen++;
                                break;
                            case 't':
                                $this->chbuf .= "\t";
                                $this->chbuflen++;
                                break;
                            case "'":
                                $this->chbuf .= "'";
                                $this->chbuflen++;
                                break;
                            case '"':
                                $this->chbuf .= '"';
                                $this->chbuflen++;
                                break;
                            case '0':
                                $this->chbuf .= "\0";
                                $this->chbuflen++;
                                break;
                            case 'x':
                                $hex = 0;
                                $r = $this->read();
                                $ch = chr($r);
                                while ($r >= 0 && (ctype_digit($ch) || self::isHex($ch))) {
                                    $hex = $hex * 16 + self::hexVal($ch);
                                    $r = $this->read();
                                    $ch = chr($r);
                                }
                                $this->chbuf .= chr($hex);
                                $this->chbuflen++;
                                $advanceAfterEscape = false;
                                break;
                            default:
                                throw new \RuntimeException('Unrecognized char escape sequence');
                        }
                        if ($advanceAfterEscape) {
                            $r = $this->read();
                            $ch = chr($r);
                        }
                    }
                } else {
                    $this->chbuf .= $ch;
                    $this->chbuflen++;
                    $r = $this->read();
                    $ch = chr($r);
                }
                $done = $r < 0 || $ch === "'";
            }

            $this->lastR = $this->read();
            $this->token = TokenKind::CONSTANT;
            $this->value = $this->chbuflen > 0 ? $this->chbuf[0] : 0;
        } else {
            $this->chbuf = '';
            $this->chbuflen = 0;
            while ($ch === '_' || ctype_alnum($ch) || $r > 127) {
                $this->chbuf .= $ch;
                $this->chbuflen++;
                $r = $this->read();
                $ch = chr($r);
            }

            if ($this->chbuflen === 0) {
                throw new \RuntimeException("Character '" . chr($r) . "' is not parsable");
            }

            $this->lastR = $r;

            $id = substr($this->chbuf, 0, $this->chbuflen);
            $this->value = $id;

            $tok = 0;
            if (isset(self::$kwTokens[$id])) {
                $this->token = self::$kwTokens[$id];
            } else {
                $isTypedef = $this->IsTypedef;
                if ($isTypedef($id)) {
                    $this->token = TokenKind::TYPE_NAME;
                } else {
                    $this->token = TokenKind::IDENTIFIER;
                }
            }
        }

        $this->endLocation = new Location($this->location->Document, $this->lastR >= 0 ? $this->nextPosition - 1 : strlen($this->location->Document->Content), $this->line, $this->column);
        $this->CurrentToken = new Token($this->token, $this->value, $this->location, $this->endLocation);
        return true;
    }

    private static function isHex(string $c): bool
    {
        switch ($c) {
            case 'a':
            case 'b':
            case 'c':
            case 'd':
            case 'e':
            case 'f':
            case 'A':
            case 'B':
            case 'C':
            case 'D':
            case 'E':
            case 'F':
                return true;
        }
        return false;
    }

    private static function hexVal(string $c): int
    {
        if ($c >= '0' && $c <= '9') return ord($c) - ord('0');
        if ($c >= 'a' && $c <= 'f') return ord($c) - ord('a') + 10;
        if ($c >= 'A' && $c <= 'F') return ord($c) - ord('A') + 10;
        return 0;
    }
}
