<?php declare(strict_types=1);

namespace CLanguage\Parser;

use CLanguage\Report;
use CLanguage\Syntax\Document;
use CLanguage\Syntax\Location;
use CLanguage\Syntax\Token;
use Closure;
use RuntimeException;

class Lexer
{
    public static array $KeywordTokens;
    public static array $OperatorTokens = [
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
    private static array $_kwTokens = [
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
    public readonly Report $Report;
    public readonly Document $Document;
    public Closure $IsTypedef;
    private int $_token = -1;
    private mixed $_value = null;
    private int $_lastR = -2;
    private Location $location;
    private Location $endLocation;
    private int $line = 1;
    private int $column = 1;
    private int $nextPosition = 0;

    public function __construct(Document $document, ?Report $report = null)
    {
        $this->Report = $report ?? new Report();
        $this->Document = $document;
        $this->location = new Location($document, 0, 1, 1);
        $this->endLocation = $this->location;
        $this->IsTypedef = fn(string $s) => false;
    }

    static function __init(): void
    {
        self::$KeywordTokens = array_values(self::$_kwTokens);
    }

    public function getCurrentToken(): Token
    {
        return new Token($this->_token, $this->_value, $this->location, $this->endLocation);
    }

    /** @noinspection PhpUnusedLocalVariableInspection
     * @noinspection PhpIfWithCommonPartsInspection
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function Advance(): bool
    {
        $this->SkipWhiteSpace();

        $r = $this->_lastR;

        if ($r === -1) {
            return $this->Eof();
        }

        $this->location = new Location($this->location->Document, $this->nextPosition - 1, $this->line, $this->column);

        $ch = chr($r);
        $_chbuf = '';
        $_chbuflen = 0;
        if ($ch === "\n" || $r === 8232) {
            $this->_token = TokenKind::EOL;
            $this->_value = null;
            $this->_lastR = $this->Read();
            $this->line++;
            $this->column = 1;
        } elseif (ctype_digit($ch) || $r === 46) {
            $onlydigits = true;
            $islong = false;
            $isunsigned = false;
            $isfloat = false;
            $ishex = false;
            $_chbuf = $ch;
            $_chbuflen = 1;
            $ch = chr($r);
            while ($ch === '.' || ctype_digit($ch) || $ch === 'E' || $ch === 'e' || $ch === 'f' || $ch === 'F' || $ch === 'u' || $ch === 'U' || $ch === 'l' || $ch === 'L' || (!$ishex && $ch === 'x') || ($ishex && self::IsHex($ch))) {
                if ($ch === 'l' || $ch === 'L') {
                    $islong = true;
                } elseif ($ch === 'u' || $ch === 'U') {
                    $isunsigned = true;
                } elseif (!$ishex && ($ch === 'f' || $ch === 'F')) {
                    $isfloat = true;
                } elseif ($ch === 'x' && $_chbuflen === 1 && $_chbuf[0] === '0') {
                    $ishex = true;
                } else {
                    $onlydigits = $onlydigits && ctype_digit($ch);
                    $_chbuf .= $ch;
                    $_chbuflen++;
                }
                $r = $this->Read();
                $ch = chr($r);
            }
            $this->_lastR = $r;

            $vals = $_chbuf;
            /** @noinspection PhpFieldImmediatelyRewrittenInspection */
            $this->endLocation = new Location($this->location->Document, $this->_lastR >= 0 ? $this->nextPosition - 1 : strlen($this->Document->Content), $this->line, $this->column);
            if ($onlydigits || $ishex) {
                if ($ishex) {
                    $v = intval($vals, 16);
                } else {
                    $v = intval($vals);
                }
                if ($isunsigned && $v < 0) {
                    $v = $v & 0x7FFFFFFFFFFFFFFF;
                }
                $this->_value = $v;
            } else {
                $this->_value = (float)$vals;
            }

            $this->_token = TokenKind::CONSTANT;
        } elseif ($r === 61) {
            $r = $this->Read();
            if ($r === 61) {
                $this->_token = TokenKind::EQ_OP;
                $this->_value = null;
                $this->_lastR = $this->Read();
            } else {
                $this->_token = 61;
                $this->_value = null;
                $this->_lastR = $r;
            }
        } elseif ($r === 33) {
            $r = $this->Read();
            if ($r === 61) {
                $this->_token = TokenKind::NE_OP;
                $this->_value = null;
                $this->_lastR = $this->Read();
            } else {
                $this->_token = 33;
                $this->_value = null;
                $this->_lastR = $r;
            }
        } elseif ($r === 58) {
            $r = $this->Read();
            if ($r === 58) {
                $this->_token = TokenKind::COLONCOLON;
                $this->_value = null;
                $this->_lastR = $this->Read();
            } else {
                $this->_token = 58;
                $this->_value = null;
                $this->_lastR = $r;
            }
        } elseif (in_array($r, [44, 59, 63, 40, 41, 123, 125, 91, 93, 126, 37, 35, 92], true)) {
            $this->_token = $r;
            $this->_value = null;
            $this->_lastR = $this->Read();
        } elseif ($r === 46) {
            $nr = $this->Read();

            if ($nr === 46 && $this->Peek() === 46) {
                $r = $this->Read();
                if ($r === 46) {
                    $this->_token = TokenKind::ELLIPSIS;
                    $this->_value = null;
                    $this->_lastR = $this->Read();
                } else {
                    $this->_token = 46;
                    $this->_value = null;
                    $this->_lastR = $r;
                    $this->Report->Error(1001, $this->location->add(1), $this->location->add(2), "Identifier expected");
                }
            } else {
                $this->_token = $r;
                $this->_value = null;
                $this->_lastR = $nr;
            }
        } elseif ($r === 42 || $r === 47) {
            $nr = $this->Read();

            if ($nr === 61) {
                $nr = $this->Read();
                $this->_token = ($r === 42) ? TokenKind::MUL_ASSIGN : TokenKind::DIV_ASSIGN;
                $this->_value = null;
                $this->_lastR = $nr;
            } else {
                $this->_token = $r;
                $this->_value = null;
                $this->_lastR = $nr;
            }
        } elseif ($r === 94) {
            $nr = $this->Read();

            if ($nr === 61) {
                $nr = $this->Read();
                $this->_token = TokenKind::BINARY_XOR_ASSIGN;
                $this->_value = null;
                $this->_lastR = $nr;
            } else {
                $this->_token = $r;
                $this->_value = null;
                $this->_lastR = $nr;
            }
        } elseif ($r === 38) {
            $nr = $this->Read();

            if ($nr === 38) {
                $nr = $this->Read();

                if ($nr === 61) {
                    $nr = $this->Read();
                    $this->_token = TokenKind::AND_ASSIGN;
                    $this->_value = null;
                    $this->_lastR = $nr;
                } else {
                    $this->_token = TokenKind::AND_OP;
                    $this->_value = null;
                    $this->_lastR = $nr;
                }
            } elseif ($nr === 61) {
                $nr = $this->Read();
                $this->_token = TokenKind::BINARY_AND_ASSIGN;
                $this->_value = null;
                $this->_lastR = $nr;
            } else {
                $this->_token = $r;
                $this->_value = null;
                $this->_lastR = $nr;
            }
        } elseif ($r === 124) {
            $nr = $this->Read();

            if ($nr === 124) {
                $nr = $this->Read();

                if ($nr === 61) {
                    $nr = $this->Read();
                    $this->_token = TokenKind::OR_ASSIGN;
                    $this->_value = null;
                    $this->_lastR = $nr;
                } else {
                    $this->_token = TokenKind::OR_OP;
                    $this->_value = null;
                    $this->_lastR = $nr;
                }
            } elseif ($nr === 61) {
                $nr = $this->Read();
                $this->_token = TokenKind::BINARY_OR_ASSIGN;
                $this->_value = null;
                $this->_lastR = $nr;
            } else {
                $this->_token = $r;
                $this->_value = null;
                $this->_lastR = $nr;
            }
        } elseif ($r === 43) {
            $nr = $this->Read();

            if ($nr === 61) {
                $this->_token = TokenKind::ADD_ASSIGN;
                $this->_value = null;
                $this->_lastR = $this->Read();
            } elseif ($nr === 43) {
                $this->_token = TokenKind::INC_OP;
                $this->_value = null;
                $this->_lastR = $this->Read();
            } else {
                $this->_token = $r;
                $this->_value = null;
                $this->_lastR = $nr;
            }
        } elseif ($r === 45) {
            $nr = $this->Read();

            if ($nr === 61) {
                $this->_token = TokenKind::SUB_ASSIGN;
                $this->_value = null;
                $this->_lastR = $this->Read();
            } elseif ($nr === 45) {
                $this->_token = TokenKind::DEC_OP;
                $this->_value = null;
                $this->_lastR = $this->Read();
            } elseif ($nr === 62) {
                $this->_token = TokenKind::PTR_OP;
                $this->_value = null;
                $this->_lastR = $this->Read();
            } else {
                $this->_token = $r;
                $this->_value = null;
                $this->_lastR = $nr;
            }
        } elseif ($r === 60) {
            $nr = $this->Read();

            if ($nr === 61) {
                $this->_token = TokenKind::LE_OP;
                $this->_value = null;
                $this->_lastR = $this->Read();
            } elseif ($nr === 60) {
                $this->_token = TokenKind::LEFT_OP;
                $this->_value = null;
                $this->_lastR = $this->Read();
            } else {
                $this->_token = $r;
                $this->_value = null;
                $this->_lastR = $nr;
            }
        } elseif ($r === 62) {
            $nr = $this->Read();

            if ($nr === 61) {
                $this->_token = TokenKind::GE_OP;
                $this->_value = null;
                $this->_lastR = $this->Read();
            } elseif ($nr === 62) {
                $this->_token = TokenKind::RIGHT_OP;
                $this->_value = null;
                $this->_lastR = $this->Read();
            } else {
                $this->_token = $r;
                $this->_value = null;
                $this->_lastR = $nr;
            }
        } elseif ($r === 34) {
            $_chbuf = '';
            $_chbuflen = 0;
            $r = $this->Read();
            $ch = chr($r);
            $done = $r < 0 || $ch === '"';
            while (!$done) {
                if ($ch === '\\') {
                    $r = $this->Read();
                    $ch = chr($r);
                    if ($r >= 0) {
                        $advanceAfterEscape = true;
                        switch ($ch) {
                            case '\\':
                                $_chbuf .= '\\';
                                $_chbuflen++;
                                break;
                            case 'r':
                                $_chbuf .= "\r";
                                $_chbuflen++;
                                break;
                            case 'n':
                                $_chbuf .= "\n";
                                $_chbuflen++;
                                break;
                            case 't':
                                $_chbuf .= "\t";
                                $_chbuflen++;
                                break;
                            case "'":
                                $_chbuf .= "'";
                                $_chbuflen++;
                                break;
                            case '"':
                                $_chbuf .= '"';
                                $_chbuflen++;
                                break;
                            case '0':
                                $_chbuf .= "\0";
                                $_chbuflen++;
                                break;
                            case 'x':
                                {
                                    $hex = 0;
                                    $r = $this->Read();
                                    $ch = chr($r);
                                    while ($r >= 0 && (ctype_digit($ch) || self::IsHex($ch))) {
                                        $hex = $hex * 16 + self::HexVal($ch);
                                        $r = $this->Read();
                                        $ch = chr($r);
                                    }
                                    $_chbuf .= chr($hex);
                                    $_chbuflen++;
                                    $advanceAfterEscape = false;
                                }
                                break;
                            default:
                                {
                                    if (ctype_space(chr($r))) {
                                        while ($r > 0 && $r !== 10 && $r !== 8232) {
                                            $r = $this->Read();
                                        }
                                    } else {
                                        throw new RuntimeException("Unrecognized string escape sequence");
                                    }
                                }
                                break;
                        }
                        if ($advanceAfterEscape) {
                            $r = $this->Read();
                            $ch = chr($r);
                        }
                    }
                } elseif ($ch === "\n" || $r === 8232) {
                    $this->endLocation = new Location($this->location->Document, $this->_lastR >= 0 ? $this->nextPosition - 1 : strlen($this->Document->Content), $this->line, $this->column);
                    $this->Report->Error(1010, $this->location, $this->endLocation, "Newline in constant");
                    $done = true;
                } else {
                    $_chbuf .= $ch;
                    $_chbuflen++;
                    $r = $this->Read();
                    $ch = chr($r);
                }
                $done = $done || $r < 0 || $ch === '"';
            }

            $this->_lastR = $this->Read();

            $this->_token = TokenKind::STRING_LITERAL;
            $this->_value = $_chbuf;
        } elseif ($r === 39) {
            $_chbuf = '';
            $_chbuflen = 0;
            $r = $this->Read();
            $ch = chr($r);
            $done = $r < 0 || $ch === "'";
            while (!$done) {
                if ($ch === '\\') {
                    $r = $this->Read();
                    $ch = chr($r);
                    if ($r >= 0) {
                        $advanceAfterEscape = true;
                        switch ($ch) {
                            case '\\':
                                $_chbuf .= '\\';
                                $_chbuflen++;
                                break;
                            case 'r':
                                $_chbuf .= "\r";
                                $_chbuflen++;
                                break;
                            case 'n':
                                $_chbuf .= "\n";
                                $_chbuflen++;
                                break;
                            case 't':
                                $_chbuf .= "\t";
                                $_chbuflen++;
                                break;
                            case "'":
                                $_chbuf .= "'";
                                $_chbuflen++;
                                break;
                            case '"':
                                $_chbuf .= '"';
                                $_chbuflen++;
                                break;
                            case '0':
                                $_chbuf .= "\0";
                                $_chbuflen++;
                                break;
                            case 'x':
                                {
                                    $hex = 0;
                                    $r = $this->Read();
                                    $ch = chr($r);
                                    while ($r >= 0 && (ctype_digit($ch) || self::IsHex($ch))) {
                                        $hex = $hex * 16 + self::HexVal($ch);
                                        $r = $this->Read();
                                        $ch = chr($r);
                                    }
                                    $_chbuf .= chr($hex);
                                    $_chbuflen++;
                                    $advanceAfterEscape = false;
                                }
                                break;
                            default:
                                throw new RuntimeException("Unrecognized char escape sequence");
                        }
                        if ($advanceAfterEscape) {
                            $r = $this->Read();
                            $ch = chr($r);
                        }
                    }
                } else {
                    $_chbuf .= $ch;
                    $_chbuflen++;
                    $r = $this->Read();
                    $ch = chr($r);
                }
                $done = $r < 0 || $ch === "'";
            }

            $this->_lastR = $this->Read();
            $this->_token = TokenKind::CONSTANT;
            $this->_value = $_chbuf[0] ?? "\0";
        } else {
            $_chbuf = '';
            $_chbuflen = 0;
            while ($ch === '_' || ctype_alnum($ch) || $r > 127) {
                $_chbuf .= $ch;
                $_chbuflen++;
                $r = $this->Read();
                $ch = chr($r);
            }

            if ($_chbuflen === 0) {
                throw new RuntimeException("Character '" . chr($r) . "' is not parsable");
            }

            $this->_lastR = $r;

            $id = $_chbuf;
            $this->_value = $id;

            $tok = self::$_kwTokens[$id] ?? 0;
            if ($tok !== 0) {
                $this->_token = $tok;
            } else {
                if ($this->IsTypedef !== null && ($this->IsTypedef)($id)) {
                    $this->_token = TokenKind::TYPE_NAME;
                } else {
                    $this->_token = TokenKind::IDENTIFIER;
                }
            }
        }

        $this->endLocation = new Location($this->location->Document, $this->_lastR >= 0 ? $this->nextPosition - 1 : strlen($this->Document->Content), $this->line, $this->column);
        return true;
    }

    public function SkipWhiteSpace(): void
    {
        $r = $this->_lastR;
        if ($r === -2) {
            $r = $this->Read();
        }

        $skippedComment = true;
        while ($skippedComment) {
            while ($r >= 0 && $r <= 32) {
                /** @noinspection PhpConditionAlreadyCheckedInspection */
                if ($r === 10 && $r !== 8232) {
                    break;
                }
                $r = $this->Read();
            }

            $skippedComment = false;

            if ($r === 47 && $this->Peek() === 47) {
                $nr = $this->Read();
                while ($nr > 0 && $nr !== 10 && $nr !== 8232) {
                    $nr = $this->Read();
                }
                $r = $nr;
            } elseif ($r === 47 && $this->Peek() === 42) {
                $nr = $this->Read();
                while ($nr > 0 && !($nr === 42 && $this->Peek() === 47)) {
                    if ($nr === 10 || $nr === 8232) {
                        $this->line++;
                        $this->column = 1;
                    }
                    $nr = $this->Read();
                }
                $this->Read();
                $r = $this->Read();
                $skippedComment = true;
            }
        }

        $this->_lastR = $r;
    }

    private function Read(): int
    {
        if ($this->nextPosition < strlen($this->Document->Content)) {
            $r = ord($this->Document->Content[$this->nextPosition]);
            $this->nextPosition++;
            $this->column++;
            return $r;
        }
        return -1;
    }

    private function Peek(): int
    {
        if ($this->nextPosition < strlen($this->Document->Content)) {
            return ord($this->Document->Content[$this->nextPosition]);
        }
        return -1;
    }

    private function Eof(): bool
    {
        $this->_value = null;
        $this->_token = -1;
        return false;
    }

    private static function IsHex(string $c): bool
    {
        return in_array($c, ['a', 'b', 'c', 'd', 'e', 'f', 'A', 'B', 'C', 'D', 'E', 'F'], true);
    }

    private static function HexVal(string $c): int
    {
        $o = ord($c);
        if ($o >= 48 && $o <= 57) return $o - 48;
        if ($o >= 97 && $o <= 102) return $o - 97 + 10;
        if ($o >= 65 && $o <= 70) return $o - 65 + 10;
        return 0;
    }
}
