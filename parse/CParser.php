<?php
namespace parse;

class yyException extends \Exception
{
}

class yyUnexpectedEof extends yyException
{
}

interface yyInput
{
    public function advance(): bool;
    public function token(): int;
    public function value();
}

class CParser
{
    public $ErrorOutput;
    public $eof_token = 0;
    protected static $yyMax = 256;
    protected $use_global_stacks = false;
    protected static $global_yyStates;
    protected static $global_yyVals;
    protected $yyVals;
    protected $yyVal;
    protected $yyToken;
    protected $yyTop;
    protected $yyExpectingState;
    protected $lexer;
    protected $_tu;

    public function __construct()
    {
        $this->ErrorOutput = fopen("php://memory", "w+");
    }

    public function yyerror(string $message, ?array $expected = null): void
    {
        if ($expected !== null && count($expected) > 0) {
            fwrite($this->ErrorOutput, $message . ", expecting");
            foreach ($expected as $n) {
                fwrite($this->ErrorOutput, " " . $n);
            }
            fwrite($this->ErrorOutput, "\n");
        } else {
            fwrite($this->ErrorOutput, $message . "\n");
        }
    }

    protected function yyDefault($first)
    {
        return $first;
    }

    public function yyparse(yyInput $yyLex, $yyd = null)
    {
        return $this->yyparseInternal($yyLex);
    }
    protected static $yyNames = [
        "end-of-file",
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        "'!'",
        null,
        "'#'",
        null,
        "'%'",
        "'&'",
        null,
        "'('",
        "')'",
        "'*'",
        "'+'",
        "',
        '",
        "'-'",
        "'.'",
        "'/'",
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        "':'",
        "';'",
        "'<'",
        "'='",
        "'>'",
        "'?'",
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        "'['",
        "'\\\\'",
        "']'",
        "'^'",
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        "'{'",
        "'|'",
        "'}'",
        "'~'",
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        "IDENTIFIER",
        "CONSTANT",
        "STRING_LITERAL",
        "SIZEOF",
        "PTR_OP",
        "INC_OP",
        "DEC_OP",
        "LEFT_OP",
        "RIGHT_OP",
        "LE_OP",
        "GE_OP",
        "EQ_OP",
        "NE_OP",
        "COLONCOLON",
        "AND_OP",
        "OR_OP",
        "MUL_ASSIGN",
        "DIV_ASSIGN",
        "MOD_ASSIGN",
        "ADD_ASSIGN",
        "SUB_ASSIGN",
        "LEFT_ASSIGN",
        "RIGHT_ASSIGN",
        "BINARY_AND_ASSIGN",
        "BINARY_XOR_ASSIGN",
        "BINARY_OR_ASSIGN",
        "AND_ASSIGN",
        "OR_ASSIGN",
        "TYPE_NAME",
        "PUBLIC",
        "PRIVATE",
        "PROTECTED",
        "VIRTUAL",
        "OVERRIDE",
        "OPERATOR",
        "TYPEDEF",
        "EXTERN",
        "STATIC",
        "AUTO",
        "REGISTER",
        "INLINE",
        "RESTRICT",
        "CHAR",
        "SHORT",
        "INT",
        "LONG",
        "SIGNED",
        "UNSIGNED",
        "FLOAT",
        "DOUBLE",
        "CONST",
        "VOLATILE",
        "VOID",
        "BOOL",
        "COMPLEX",
        "IMAGINARY",
        "TRUE",
        "FALSE",
        "STRUCT",
        "CLASS",
        "UNION",
        "ENUM",
        "ELLIPSIS",
        "CASE",
        "DEFAULT",
        "IF",
        "ELSE",
        "SWITCH",
        "WHILE",
        "DO",
        "FOR",
        "GOTO",
        "CONTINUE",
        "BREAK",
        "RETURN",
        "EOL",
    ];

    public static function yyname(int $token): string
    {
        if ($token < 0 || $token >= count(self::$yyNames)) return "[illegal]";
        $name = self::$yyNames[$token];
        return $name !== null ? $name : "[unknown]";
    }

    protected function yyExpectingTokens(int $state): array
    {
        $len = 0;
        $ok = array_fill(0, count(self::$yyNames), false);
        $n = self::$yySindex[$state];
        if ($n !== 0) {
            $start = $n < 0 ? -$n : 0;
            $end = count(self::$yyTable);
            for ($token = $start; $token < count(self::$yyNames) && ($n + $token) < $end; $token++) {
                if (self::$yyCheck[$n + $token] === $token && !$ok[$token] && self::$yyNames[$token] !== null) {
                    $len++;
                    $ok[$token] = true;
                }
            }
        }
        $n = self::$yyRindex[$state];
        if ($n !== 0) {
            $start = $n < 0 ? -$n : 0;
            $end = count(self::$yyTable);
            for ($token = $start; $token < count(self::$yyNames) && ($n + $token) < $end; $token++) {
                if (self::$yyCheck[$n + $token] === $token && !$ok[$token] && self::$yyNames[$token] !== null) {
                    $len++;
                    $ok[$token] = true;
                }
            }
        }
        $result = [];
        for ($n = 0, $token = 0; $n < $len; $token++) {
            if ($ok[$token]) {
                $result[] = $token;
                $n++;
            }
        }
        return $result;
    }

    protected function yyExpecting(int $state): array
    {
        $tokens = $this->yyExpectingTokens($state);
        $result = [];
        foreach ($tokens as $t) {
            $result[] = self::$yyNames[$t];
        }
        return $result;
    }
    protected function yyparseInternal(yyInput $yyLex)
    {
        if (self::$yyMax <= 0) self::$yyMax = 256;
        $yyState = 0;
        $yyVal = null;
        $yyToken = -1;
        $yyErrorFlag = 0;

        if ($this->use_global_stacks && self::$global_yyStates !== null) {
            $yyVals = self::$global_yyVals;
            $yyStates = self::$global_yyStates;
        } else {
            $yyVals = array_fill(0, self::$yyMax, null);
            $yyStates = array_fill(0, self::$yyMax, 0);
            if ($this->use_global_stacks) {
                self::$global_yyVals = $yyVals;
                self::$global_yyStates = $yyStates;
            }
        }

        for ($yyTop = 0; ; $yyTop++) {
            if ($yyTop >= count($yyStates)) {
                $yyStates = array_pad($yyStates, count($yyStates) + self::$yyMax, 0);
                $yyVals = array_pad($yyVals, count($yyVals) + self::$yyMax, null);
            }
            $yyStates[$yyTop] = $yyState;
            $yyVals[$yyTop] = $yyVal;

            while (true) {
                $yyN = self::$yyDefRed[$yyState];
                if ($yyN === 0) {
                    if ($yyToken < 0) {
                        $yyToken = $yyLex->advance() ? $yyLex->token() : 0;
                    }
                    $yyN = self::$yySindex[$yyState];
                    if ($yyN !== 0 && ($yyN += $yyToken) >= 0
                        && $yyN < count(self::$yyTable)
                        && self::$yyCheck[$yyN] === $yyToken)
                    {
                        $yyState = self::$yyTable[$yyN];
                        $yyVal = $yyLex->value();
                        $yyToken = -1;
                        if ($yyErrorFlag > 0) $yyErrorFlag--;
                        goto continue_yyLoop;
                    }
                    $yyN = self::$yyRindex[$yyState];
                    if ($yyN !== 0 && ($yyN += $yyToken) >= 0
                        && $yyN < count(self::$yyTable)
                        && self::$yyCheck[$yyN] === $yyToken)
                    {
                        $yyN = self::$yyTable[$yyN];
                    } else {
                        switch ($yyErrorFlag) {
                            case 0:
                                $this->yyExpectingState = $yyState;
                                if ($yyToken === 0 || $yyToken === $this->eof_token) {
                                    throw new yyUnexpectedEof();
                                }
                                $yyErrorFlag = 1;
                            case 1:
                            case 2:
                                $yyErrorFlag = 3;
                                do {
                                    $yyN = self::$yySindex[$yyStates[$yyTop]];
                                    if ($yyN !== 0 && ($yyN += \parse\TokenKind::yyErrorCode) >= 0
                                        && $yyN < count(self::$yyTable)
                                        && self::$yyCheck[$yyN] === \parse\TokenKind::yyErrorCode)
                                    {
                                        $yyState = self::$yyTable[$yyN];
                                        $yyVal = $yyLex->value();
                                        goto continue_yyLoop;
                                    }
                                } while (--$yyTop >= 0);
                                throw new yyException('irrecoverable syntax error');
                            case 3:
                                if ($yyToken === 0) {
                                    throw new yyException('irrecoverable syntax error at end-of-file');
                                }
                                $yyToken = -1;
                                goto continue_yyDiscarded;
                        }
                    }
                }

                $yyV = $yyTop + 1 - self::$yyLen[$yyN];
                $yyVal = $yyV > $yyTop ? null : $yyVals[$yyV];
                switch ($yyN) {
                    case 1:
                        $t = $this->lexer->CurrentToken; $yyVal = new \parse\VariableExpression(($yyVals[$yyTop+0]), $t->Location, $t->EndLocation);
                        break;
                    case 2:
                        $yyVal = new \parse\ConstantExpression($yyVals[$yyTop+0]);
                        break;
                    case 3:
                        $yyVal = new \parse\ConstantExpression($yyVals[$yyTop+0]);
                        break;
                    case 4:
                        $yyVal = \parse\ConstantExpression::getTrue();
                        break;
                    case 5:
                        $yyVal = \parse\ConstantExpression::getFalse();
                        break;
                    case 6:
                        $yyVal = $yyVals[$yyTop+-1];
                        break;
                    case 7:
                        $yyVal = new \parse\ScopeResolutionExpression(($yyVals[$yyTop+-2]), ($yyVals[$yyTop+0]));
                        break;
                    case 8:
                        $yyVal = $yyVals[$yyTop+0];
                        break;
                    case 9:
                        $yyVal = new \parse\ArrayElementExpression($yyVals[$yyTop+-3], $yyVals[$yyTop+-1]);
                        break;
                    case 10:
                        $yyVal = new \parse\FuncallExpression($yyVals[$yyTop+-2]);
                        break;
                    case 11:
                        $yyVal = new \parse\FuncallExpression($yyVals[$yyTop+-3], $yyVals[$yyTop+-1]);
                        break;
                    case 12:
                        $yyVal = new \parse\MemberFromReferenceExpression($yyVals[$yyTop+-2], ($yyVals[$yyTop+0]));
                        break;
                    case 13:
                        $yyVal = new \parse\MemberFromPointerExpression($yyVals[$yyTop+-2], ($yyVals[$yyTop+0]));
                        break;
                    case 14:
                        $yyVal = new \parse\UnaryExpression(\parse\Unop::PostIncrement(), $yyVals[$yyTop+-1]);
                        break;
                    case 15:
                        $yyVal = new \parse\UnaryExpression(\parse\Unop::PostDecrement(), $yyVals[$yyTop+-1]);
                        break;
                    case 16:
                        throw new \RuntimeException("Syntax: '(' type_name ')' '{' initializer_list '}'");
                        break;
                    case 17:
                        throw new \RuntimeException("Syntax: '(' type_name ')' '{' initializer_list ',' '}'");
                        break;
                    case 18:
                        $this->case_18();
                        break;
                    case 19:
                        $this->case_19();
                        break;
                    case 20:
                        $yyVal = $yyVals[$yyTop+0];
                        break;
                    case 21:
                        $yyVal = new \parse\UnaryExpression(\parse\Unop::PreIncrement(), $yyVals[$yyTop+0]);
                        break;
                    case 22:
                        $yyVal = new \parse\UnaryExpression(\parse\Unop::PreDecrement(), $yyVals[$yyTop+0]);
                        break;
                    case 23:
                        $yyVal = new \parse\AddressOfExpression($yyVals[$yyTop+0]);
                        break;
                    case 24:
                        $yyVal = new \parse\DereferenceExpression($yyVals[$yyTop+0]);
                        break;
                    case 25:
                        $yyVal = new \parse\UnaryExpression($yyVals[$yyTop+-1], $yyVals[$yyTop+0]);
                        break;
                    case 26:
                        $yyVal = new \parse\SizeOfExpression($yyVals[$yyTop+0]);
                        break;
                    case 27:
                        $yyVal = new \parse\SizeOfTypeExpression($yyVals[$yyTop+-1]);
                        break;
                    case 28:
                        $yyVal = \parse\Unop::None();
                        break;
                    case 29:
                        $yyVal = \parse\Unop::Negate();
                        break;
                    case 30:
                        $yyVal = \parse\Unop::BinaryComplement();
                        break;
                    case 31:
                        $yyVal = \parse\Unop::Not();
                        break;
                    case 32:
                        $yyVal = $yyVals[$yyTop+0];
                        break;
                    case 33:
                        $yyVal = new \parse\CastExpression($yyVals[$yyTop+-2], $yyVals[$yyTop+0]);
                        break;
                    case 34:
                        $yyVal = $yyVals[$yyTop+0];
                        break;
                    case 35:
                        $yyVal = new \parse\BinaryExpression($yyVals[$yyTop+-2], \parse\Binop::Multiply(), $yyVals[$yyTop+0]);
                        break;
                    case 36:
                        $yyVal = new \parse\BinaryExpression($yyVals[$yyTop+-2], \parse\Binop::Divide(), $yyVals[$yyTop+0]);
                        break;
                    case 37:
                        $yyVal = new \parse\BinaryExpression($yyVals[$yyTop+-2], \parse\Binop::Mod(), $yyVals[$yyTop+0]);
                        break;
                    case 39:
                        $yyVal = new \parse\BinaryExpression($yyVals[$yyTop+-2], \parse\Binop::Add(), $yyVals[$yyTop+0]);
                        break;
                    case 40:
                        $yyVal = new \parse\BinaryExpression($yyVals[$yyTop+-2], \parse\Binop::Subtract(), $yyVals[$yyTop+0]);
                        break;
                    case 42:
                        $yyVal = new \parse\BinaryExpression($yyVals[$yyTop+-2], \parse\Binop::ShiftLeft(), $yyVals[$yyTop+0]);
                        break;
                    case 43:
                        $yyVal = new \parse\BinaryExpression($yyVals[$yyTop+-2], \parse\Binop::ShiftRight(), $yyVals[$yyTop+0]);
                        break;
                    case 45:
                        $yyVal = new \parse\RelationalExpression($yyVals[$yyTop+-2], \parse\RelationalOp::LessThan(), $yyVals[$yyTop+0]);
                        break;
                    case 46:
                        $yyVal = new \parse\RelationalExpression($yyVals[$yyTop+-2], \parse\RelationalOp::GreaterThan(), $yyVals[$yyTop+0]);
                        break;
                    case 47:
                        $yyVal = new \parse\RelationalExpression($yyVals[$yyTop+-2], \parse\RelationalOp::LessThanOrEqual(), $yyVals[$yyTop+0]);
                        break;
                    case 48:
                        $yyVal = new \parse\RelationalExpression($yyVals[$yyTop+-2], \parse\RelationalOp::GreaterThanOrEqual(), $yyVals[$yyTop+0]);
                        break;
                    case 50:
                        $yyVal = new \parse\RelationalExpression($yyVals[$yyTop+-2], \parse\RelationalOp::Equals(), $yyVals[$yyTop+0]);
                        break;
                    case 51:
                        $yyVal = new \parse\RelationalExpression($yyVals[$yyTop+-2], \parse\RelationalOp::NotEquals(), $yyVals[$yyTop+0]);
                        break;
                    case 53:
                        $yyVal = new \parse\BinaryExpression($yyVals[$yyTop+-2], \parse\Binop::BinaryAnd(), $yyVals[$yyTop+0]);
                        break;
                    case 55:
                        $yyVal = new \parse\BinaryExpression($yyVals[$yyTop+-2], \parse\Binop::BinaryXor(), $yyVals[$yyTop+0]);
                        break;
                    case 57:
                        $yyVal = new \parse\BinaryExpression($yyVals[$yyTop+-2], \parse\Binop::BinaryOr(), $yyVals[$yyTop+0]);
                        break;
                    case 59:
                        $yyVal = new \parse\LogicExpression($yyVals[$yyTop+-2], \parse\LogicOp::And(), $yyVals[$yyTop+0]);
                        break;
                    case 61:
                        $yyVal = new \parse\LogicExpression($yyVals[$yyTop+-2], \parse\LogicOp::Or(), $yyVals[$yyTop+0]);
                        break;
                    case 62:
                        $yyVal = $yyVals[$yyTop+0];
                        break;
                    case 63:
                        $yyVal = new \parse\ConditionalExpression($yyVals[$yyTop+-4], $yyVals[$yyTop+-2], $yyVals[$yyTop+0]);
                        break;
                    case 65:
                        $this->case_65();
                        break;
                    case 66:
                        $yyVal = \parse\RelationalOp::Equals();
                        break;
                    case 67:
                        $yyVal = \parse\Binop::Multiply();
                        break;
                    case 68:
                        $yyVal = \parse\Binop::Divide();
                        break;
                    case 69:
                        $yyVal = \parse\Binop::Mod();
                        break;
                    case 70:
                        $yyVal = \parse\Binop::Add();
                        break;
                    case 71:
                        $yyVal = \parse\Binop::Subtract();
                        break;
                    case 72:
                        $yyVal = \parse\Binop::ShiftLeft();
                        break;
                    case 73:
                        $yyVal = \parse\Binop::ShiftRight();
                        break;
                    case 74:
                        $yyVal = \parse\Binop::BinaryAnd();
                        break;
                    case 75:
                        $yyVal = \parse\Binop::BinaryXor();
                        break;
                    case 76:
                        $yyVal = \parse\Binop::BinaryOr();
                        break;
                    case 77:
                        $yyVal = \parse\LogicOp::And();
                        break;
                    case 78:
                        $yyVal = \parse\LogicOp::Or();
                        break;
                    case 79:
                        $yyVal = $yyVals[$yyTop+0];
                        break;
                    case 80:
                        $yyVal = new \parse\SequenceExpression($yyVals[$yyTop+-2], $yyVals[$yyTop+0]);
                        break;
                    case 82:
                        $yyVal = new \parse\MultiDeclaratorStatement($yyVals[$yyTop+-1], null);
                        break;
                    case 83:
                        $this->case_83();
                        break;
                    case 84:
                        $this->case_84();
                        break;
                    case 85:
                        $this->case_85();
                        break;
                    case 86:
                        $this->case_86();
                        break;
                    case 87:
                        $this->case_87();
                        break;
                    case 88:
                        $this->case_88();
                        break;
                    case 89:
                        $this->case_89();
                        break;
                    case 90:
                        $this->case_90();
                        break;
                    case 91:
                        $this->case_91();
                        break;
                    case 92:
                        $this->case_92();
                        break;
                    case 93:
                        $this->case_93();
                        break;
                    case 94:
                        $yyVal = new \parse\InitDeclarator($yyVals[$yyTop+0], null);
                        break;
                    case 95:
                        $yyVal = new \parse\InitDeclarator($yyVals[$yyTop+-2], $yyVals[$yyTop+0]);
                        break;
                    case 96:
                        $yyVal = \parse\StorageClassSpecifier::Typedef();
                        break;
                    case 97:
                        $yyVal = \parse\StorageClassSpecifier::Extern();
                        break;
                    case 98:
                        $yyVal = \parse\StorageClassSpecifier::Static();
                        break;
                    case 99:
                        $yyVal = \parse\StorageClassSpecifier::Auto();
                        break;
                    case 100:
                        $yyVal = \parse\StorageClassSpecifier::Register();
                        break;
                    case 101:
                        $yyVal = new \parse\TypeSpecifier(\parse\TypeSpecifierKind::Builtin(), "void");
                        break;
                    case 102:
                        $yyVal = new \parse\TypeSpecifier(\parse\TypeSpecifierKind::Builtin(), "char");
                        break;
                    case 103:
                        $yyVal = new \parse\TypeSpecifier(\parse\TypeSpecifierKind::Builtin(), "short");
                        break;
                    case 104:
                        $yyVal = new \parse\TypeSpecifier(\parse\TypeSpecifierKind::Builtin(), "int");
                        break;
                    case 105:
                        $yyVal = new \parse\TypeSpecifier(\parse\TypeSpecifierKind::Builtin(), "long");
                        break;
                    case 106:
                        $yyVal = new \parse\TypeSpecifier(\parse\TypeSpecifierKind::Builtin(), "float");
                        break;
                    case 107:
                        $yyVal = new \parse\TypeSpecifier(\parse\TypeSpecifierKind::Builtin(), "double");
                        break;
                    case 108:
                        $yyVal = new \parse\TypeSpecifier(\parse\TypeSpecifierKind::Builtin(), "signed");
                        break;
                    case 109:
                        $yyVal = new \parse\TypeSpecifier(\parse\TypeSpecifierKind::Builtin(), "unsigned");
                        break;
                    case 110:
                        $yyVal = new \parse\TypeSpecifier(\parse\TypeSpecifierKind::Builtin(), "bool");
                        break;
                    case 111:
                        $yyVal = new \parse\TypeSpecifier(\parse\TypeSpecifierKind::Builtin(), "complex");
                        break;
                    case 112:
                        $yyVal = new \parse\TypeSpecifier(\parse\TypeSpecifierKind::Builtin(), "imaginary");
                        break;
                    case 115:
                        $yyVal = new \parse\TypeSpecifier(\parse\TypeSpecifierKind::Typename(), ($yyVals[$yyTop+0]));
                        break;
                    case 118:
                        $yyVal = new \parse\TypeSpecifier($yyVals[$yyTop+-2], ($yyVals[$yyTop+-1]), $yyVals[$yyTop+0]);
                        break;
                    case 119:
                        $this->case_119();
                        break;
                    case 120:
                        $yyVal = new \parse\TypeSpecifier($yyVals[$yyTop+-1], "", $yyVals[$yyTop+0]);
                        break;
                    case 121:
                        $yyVal = new \parse\TypeSpecifier($yyVals[$yyTop+-1], ($yyVals[$yyTop+0]));
                        break;
                    case 122:
                        $yyVal = [ $yyVals[$yyTop+0] ];
                        break;
                    case 123:
                        $this->case_123();
                        break;
                    case 124:
                        $yyVal = new \parse\BaseSpecifier(($yyVals[$yyTop+0]));
                        break;
                    case 125:
                        $yyVal = new \parse\BaseSpecifier(($yyVals[$yyTop+0]), \parse\DeclarationsVisibility::Public());
                        break;
                    case 126:
                        $yyVal = new \parse\BaseSpecifier(($yyVals[$yyTop+0]), \parse\DeclarationsVisibility::Private());
                        break;
                    case 127:
                        $yyVal = new \parse\BaseSpecifier(($yyVals[$yyTop+0]), \parse\DeclarationsVisibility::Protected());
                        break;
                    case 128:
                        $yyVal = \parse\TypeSpecifierKind::Struct();
                        break;
                    case 129:
                        $yyVal = \parse\TypeSpecifierKind::Class();
                        break;
                    case 130:
                        $yyVal = \parse\TypeSpecifierKind::Union();
                        break;
                    case 131:
                        $this->case_131();
                        break;
                    case 132:
                        $this->case_132();
                        break;
                    case 133:
                        $this->case_133();
                        break;
                    case 134:
                        $this->case_134();
                        break;
                    case 135:
                        $yyVal = new \parse\TypeSpecifier(\parse\TypeSpecifierKind::Enum(), "", $yyVals[$yyTop+-1]);
                        break;
                    case 136:
                        $yyVal = new \parse\TypeSpecifier(\parse\TypeSpecifierKind::Enum(), ($yyVals[$yyTop+-3]), $yyVals[$yyTop+-1]);
                        break;
                    case 137:
                        $yyVal = new \parse\TypeSpecifier(\parse\TypeSpecifierKind::Enum(), "", $yyVals[$yyTop+-2]);
                        break;
                    case 138:
                        $yyVal = new \parse\TypeSpecifier(\parse\TypeSpecifierKind::Enum(), ($yyVals[$yyTop+-4]), $yyVals[$yyTop+-2]);
                        break;
                    case 139:
                        $yyVal = new \parse\TypeSpecifier(\parse\TypeSpecifierKind::Enum(), ($yyVals[$yyTop+0]));
                        break;
                    case 140:
                        $this->case_140();
                        break;
                    case 141:
                        $this->case_141();
                        break;
                    case 142:
                        $yyVal = new \parse\EnumeratorStatement($yyVals[$yyTop+0]);
                        break;
                    case 143:
                        $yyVal = new \parse\EnumeratorStatement($yyVals[$yyTop+-2], $yyVals[$yyTop+0]);
                        break;
                    case 144:
                        $yyVal = \parse\FunctionSpecifier::Inline();
                        break;
                    case 145:
                        $yyVal = new \parse\PointerDeclarator($yyVals[$yyTop+-1], $yyVals[$yyTop+0]);
                        break;
                    case 146:
                        $yyVal = new \parse\ReferenceDeclarator($yyVals[$yyTop+0]);
                        break;
                    case 147:
                        $yyVal = new \parse\ReferenceDeclarator($yyVals[$yyTop+0], $yyVals[$yyTop+-1]);
                        break;
                    case 148:
                        $yyVal = $yyVals[$yyTop+0];
                        break;
                    case 149:
                        $yyVal = "+";
                        break;
                    case 150:
                        $yyVal = "-";
                        break;
                    case 151:
                        $yyVal = "*";
                        break;
                    case 152:
                        $yyVal = "/";
                        break;
                    case 153:
                        $yyVal = "%";
                        break;
                    case 154:
                        $yyVal = "==";
                        break;
                    case 155:
                        $yyVal = "!=";
                        break;
                    case 156:
                        $yyVal = "<";
                        break;
                    case 157:
                        $yyVal = ">";
                        break;
                    case 158:
                        $yyVal = "<=";
                        break;
                    case 159:
                        $yyVal = ">=";
                        break;
                    case 160:
                        $yyVal = "<<";
                        break;
                    case 161:
                        $yyVal = ">>";
                        break;
                    case 162:
                        $yyVal = "&";
                        break;
                    case 163:
                        $yyVal = "|";
                        break;
                    case 164:
                        $yyVal = "^";
                        break;
                    case 165:
                        $yyVal = "!";
                        break;
                    case 166:
                        $yyVal = "~";
                        break;
                    case 167:
                        $yyVal = "&&";
                        break;
                    case 168:
                        $yyVal = "||";
                        break;
                    case 169:
                        $yyVal = "++";
                        break;
                    case 170:
                        $yyVal = "--";
                        break;
                    case 171:
                        $yyVal = "=";
                        break;
                    case 172:
                        $yyVal = "+=";
                        break;
                    case 173:
                        $yyVal = "-=";
                        break;
                    case 174:
                        $yyVal = "*=";
                        break;
                    case 175:
                        $yyVal = "/=";
                        break;
                    case 176:
                        $yyVal = "%=";
                        break;
                    case 177:
                        $yyVal = "()";
                        break;
                    case 178:
                        $yyVal = "[]";
                        break;
                    case 179:
                        $yyVal = new \parse\IdentifierDeclarator(($yyVals[$yyTop+0]));
                        break;
                    case 180:
                        $yyVal = new \parse\IdentifierDeclarator("~" + ($yyVals[$yyTop+-1]));
                        break;
                    case 181:
                        $yyVal = new \parse\IdentifierDeclarator("operator" + $yyVals[$yyTop+0]);
                        break;
                    case 182:
                        $yyVal = ((IdentifierDeclarator)($yyVals[$yyTop+-2]))->Push (($yyVals[$yyTop+0]));
                        break;
                    case 183:
                        $yyVal = ((IdentifierDeclarator)($yyVals[$yyTop+-3]))->Push ("~" + ($yyVals[$yyTop+-1]));
                        break;
                    case 184:
                        $yyVal = ((IdentifierDeclarator)($yyVals[$yyTop+-3]))->Push ("operator" + $yyVals[$yyTop+0]);
                        break;
                    case 186:
                        $this->case_186();
                        break;
                    case 187:
                        $yyVal = $this->makeArrayDeclarator($yyVals[$yyTop+-4], $yyVals[$yyTop+-2], $yyVals[$yyTop+-1], false);
                        break;
                    case 188:
                        $yyVal = $this->makeArrayDeclarator($yyVals[$yyTop+-3], \parse\TypeQualifiers::None(), null, false);
                        break;
                    case 189:
                        $yyVal = $this->makeArrayDeclarator($yyVals[$yyTop+-3], \parse\TypeQualifiers::None(), $yyVals[$yyTop+-1], false);
                        break;
                    case 190:
                        $yyVal = $this->makeArrayDeclarator($yyVals[$yyTop+-5], $yyVals[$yyTop+-2], $yyVals[$yyTop+-1], true);
                        break;
                    case 191:
                        $yyVal = $this->makeArrayDeclarator($yyVals[$yyTop+-5], $yyVals[$yyTop+-3], $yyVals[$yyTop+-1], true);
                        break;
                    case 192:
                        $yyVal = $this->makeArrayDeclarator($yyVals[$yyTop+-4], $yyVals[$yyTop+-2], null, false);
                        break;
                    case 193:
                        $yyVal = $this->makeArrayDeclarator($yyVals[$yyTop+-3], \parse\TypeQualifiers::None(), null, false);
                        break;
                    case 194:
                        $yyVal = $this->makeArrayDeclarator($yyVals[$yyTop+-2], \parse\TypeQualifiers::None(), null, false);
                        break;
                    case 195:
                        $yyVal = new \parse\FunctionDeclarator( $yyVals[$yyTop+-3], $yyVals[$yyTop+-1]);
                        break;
                    case 196:
                        $this->case_196();
                        break;
                    case 197:
                        $yyVal = new \parse\FunctionDeclarator( $yyVals[$yyTop+-2], []);
                        break;
                    case 198:
                        $yyVal = new Pointer(\parse\TypeQualifiers::None());
                        break;
                    case 199:
                        $yyVal = new Pointer($yyVals[$yyTop+0]);
                        break;
                    case 200:
                        $yyVal = new Pointer(\parse\TypeQualifiers::None(), $yyVals[$yyTop+0]);
                        break;
                    case 201:
                        $yyVal = new Pointer($yyVals[$yyTop+-1], $yyVals[$yyTop+0]);
                        break;
                    case 202:
                        $yyVal = $yyVals[$yyTop+0];
                        break;
                    case 203:
                        $yyVal = ($yyVals[$yyTop+-1]) | ($yyVals[$yyTop+0]);
                        break;
                    case 204:
                        $yyVal = \parse\TypeQualifiers::Const();
                        break;
                    case 205:
                        $yyVal = \parse\TypeQualifiers::Restrict();
                        break;
                    case 206:
                        $yyVal = \parse\TypeQualifiers::Volatile();
                        break;
                    case 207:
                        $yyVal = $yyVals[$yyTop+0];
                        break;
                    case 208:
                        $this->case_208();
                        break;
                    case 209:
                        $this->case_209();
                        break;
                    case 210:
                        $this->case_210();
                        break;
                    case 211:
                        $yyVal = new \parse\ParameterDeclaration($yyVals[$yyTop+-1], $yyVals[$yyTop+0]);
                        break;
                    case 212:
                        $yyVal = new \parse\ParameterDeclaration($yyVals[$yyTop+-3], $yyVals[$yyTop+-2], $yyVals[$yyTop+0]);
                        break;
                    case 213:
                        $yyVal = new \parse\ParameterDeclaration($yyVals[$yyTop+-1], $yyVals[$yyTop+0]);
                        break;
                    case 214:
                        $yyVal = new \parse\ParameterDeclaration($yyVals[$yyTop+0]);
                        break;
                    case 215:
                        $yyVal = new \parse\TypeName($yyVals[$yyTop+0], null);
                        break;
                    case 216:
                        $yyVal = new \parse\TypeName($yyVals[$yyTop+-1], $yyVals[$yyTop+0]);
                        break;
                    case 217:
                        $yyVal = new \parse\PointerDeclarator($yyVals[$yyTop+0], null);
                        break;
                    case 218:
                        $yyVal = new \parse\ReferenceDeclarator(null);
                        break;
                    case 220:
                        $yyVal = new \parse\PointerDeclarator($yyVals[$yyTop+-1], $yyVals[$yyTop+0]);
                        break;
                    case 221:
                        $this->case_221();
                        break;
                    case 222:
                        $yyVal = $this->makeArrayDeclarator(null, \parse\TypeQualifiers::None(), null, false);
                        break;
                    case 223:
                        $yyVal = $this->makeArrayDeclarator(null, \parse\TypeQualifiers::None(), $yyVals[$yyTop+-1], false);
                        break;
                    case 224:
                        $yyVal = $this->makeArrayDeclarator($yyVals[$yyTop+-2], \parse\TypeQualifiers::None(), null, false);
                        break;
                    case 225:
                        $yyVal = $this->makeArrayDeclarator($yyVals[$yyTop+-3], \parse\TypeQualifiers::None(), $yyVals[$yyTop+-1], false);
                        break;
                    case 226:
                        $yyVal = $this->makeArrayDeclarator(null, \parse\TypeQualifiers::None(), null, false);
                        break;
                    case 227:
                        $yyVal = $this->makeArrayDeclarator($yyVals[$yyTop+-3], \parse\TypeQualifiers::None(), null, false);
                        break;
                    case 228:
                        $yyVal = new \parse\FunctionDeclarator( []);
                        break;
                    case 229:
                        $yyVal = new \parse\FunctionDeclarator( $yyVals[$yyTop+-1]);
                        break;
                    case 230:
                        $yyVal = new \parse\FunctionDeclarator( $yyVals[$yyTop+-2], []);
                        break;
                    case 231:
                        $yyVal = new \parse\FunctionDeclarator( $yyVals[$yyTop+-3], $yyVals[$yyTop+-1]);
                        break;
                    case 232:
                        $yyVal = new \parse\ExpressionInitializer($yyVals[$yyTop+0]);
                        break;
                    case 233:
                        $yyVal = $yyVals[$yyTop+-1];
                        break;
                    case 234:
                        $yyVal = $yyVals[$yyTop+-2];
                        break;
                    case 235:
                        $this->case_235();
                        break;
                    case 236:
                        $this->case_236();
                        break;
                    case 237:
                        $this->case_237();
                        break;
                    case 238:
                        $this->case_238();
                        break;
                    case 239:
                        $yyVal = new \parse\InitializerDesignation($yyVals[$yyTop+-1]);
                        break;
                    case 250:
                        $yyVal = new \parse\LabeledStatement($yyVals[$yyTop+-2], $yyVals[$yyTop+0], $this->getLocation($yyVals[$yyTop+-2]));
                        break;
                    case 251:
                        $yyVal = new \parse\Block(\parse\VariableScope::Local());
                        break;
                    case 252:
                        $yyVal = new \parse\Block(\parse\VariableScope::Local(), $yyVals[$yyTop+-1]);
                        break;
                    case 253:
                        $yyVal = [ $yyVals[$yyTop+0] ];
                        break;
                    case 254:
                        ($yyVals[$yyTop+-1])->Add ($yyVals[$yyTop+0]); $yyVal = $yyVals[$yyTop+-1];
                        break;
                    case 257:
                        $yyVal = new \parse\Block(\parse\VariableScope::Local());
                        break;
                    case 258:
                        $yyVal = new \parse\Block(\parse\VariableScope::Local(), $yyVals[$yyTop+-1]);
                        break;
                    case 259:
                        $yyVal = [ $yyVals[$yyTop+0] ];
                        break;
                    case 260:
                        ($yyVals[$yyTop+-1])->Add ($yyVals[$yyTop+0]); $yyVal = $yyVals[$yyTop+-1];
                        break;
                    case 265:
                        $this->case_265();
                        break;
                    case 266:
                        $yyVal = new \parse\VirtualDeclarationStatement($yyVals[$yyTop+0]) ;
                        break;
                    case 267:
                        $this->case_267();
                        break;
                    case 268:
                        $this->case_268();
                        break;
                    case 269:
                        $yyVal = $yyVals[$yyTop+0];
                        break;
                    case 270:
                        $this->case_270();
                        break;
                    case 271:
                        $yyVal = new \parse\VisibilityStatement(\parse\DeclarationsVisibility::Public());
                        break;
                    case 272:
                        $yyVal = new \parse\VisibilityStatement(\parse\DeclarationsVisibility::Private());
                        break;
                    case 273:
                        $yyVal = new \parse\VisibilityStatement(\parse\DeclarationsVisibility::Protected());
                        break;
                    case 274:
                        $yyVal = new \parse\FunctionDeclarator( $yyVals[$yyTop+-3], $yyVals[$yyTop+-1]);
                        break;
                    case 275:
                        $yyVal = new \parse\FunctionDeclarator( $yyVals[$yyTop+-2], []);
                        break;
                    case 276:
                        $yyVal = new \parse\FunctionDeclarator( new \parse\IdentifierDeclarator(($yyVals[$yyTop+-3])), $yyVals[$yyTop+-1]);
                        break;
                    case 277:
                        $yyVal = new \parse\FunctionDeclarator( new \parse\IdentifierDeclarator(($yyVals[$yyTop+-2])), []);
                        break;
                    case 278:
                        $yyVal = new \parse\FunctionDeclarator( new \parse\IdentifierDeclarator("~" + ($yyVals[$yyTop+-2])), []);
                        break;
                    case 279:
                        $this->case_279();
                        break;
                    case 280:
                        $yyVal = null;
                        break;
                    case 281:
                        $yyVal = new \parse\ExpressionStatement($yyVals[$yyTop+-1]);
                        break;
                    case 282:
                        $yyVal = new \parse\IfStatement($yyVals[$yyTop+-2], $yyVals[$yyTop+0], $this->getLocation($yyVals[$yyTop+-4]));
                        break;
                    case 283:
                        $yyVal = new \parse\IfStatement($yyVals[$yyTop+-4], $yyVals[$yyTop+-2], $yyVals[$yyTop+0], $this->getLocation($yyVals[$yyTop+-6]));
                        break;
                    case 284:
                        $yyVal = new \parse\SwitchStatement($yyVals[$yyTop+-4], $yyVals[$yyTop+-1], $this->getLocation($yyVals[$yyTop+-6]));
                        break;
                    case 285:
                        $yyVal = new \parse\SwitchStatement($yyVals[$yyTop+-3], [], $this->getLocation($yyVals[$yyTop+-5]));
                        break;
                    case 286:
                        $yyVal = [ $yyVals[$yyTop+0] ];
                        break;
                    case 287:
                        $this->case_287();
                        break;
                    case 288:
                        $yyVal = new \parse\SwitchCase($yyVals[$yyTop+-2], $yyVals[$yyTop+0]);
                        break;
                    case 289:
                        $yyVal = new \parse\SwitchCase(null, $yyVals[$yyTop+0]);
                        break;
                    case 290:
                        $yyVal = new \parse\WhileStatement(false, $yyVals[$yyTop+-2], ($yyVals[$yyTop+0])->ToBlock ());
                        break;
                    case 291:
                        $yyVal = new \parse\WhileStatement(true, $yyVals[$yyTop+-2], ($yyVals[$yyTop+-5])->ToBlock ());
                        break;
                    case 292:
                        $yyVal = new \parse\ForStatement($yyVals[$yyTop+-3], ($yyVals[$yyTop+-2])->Expression, ($yyVals[$yyTop+0])->ToBlock ());
                        break;
                    case 293:
                        $yyVal = new \parse\ForStatement($yyVals[$yyTop+-4], ($yyVals[$yyTop+-3])->Expression, $yyVals[$yyTop+-2], ($yyVals[$yyTop+0])->ToBlock ());
                        break;
                    case 294:
                        $yyVal = new \parse\ForStatement($yyVals[$yyTop+-3], ($yyVals[$yyTop+-2])->Expression, ($yyVals[$yyTop+0])->ToBlock ());
                        break;
                    case 295:
                        $yyVal = new \parse\ForStatement($yyVals[$yyTop+-4], ($yyVals[$yyTop+-3])->Expression, $yyVals[$yyTop+-2], ($yyVals[$yyTop+0])->ToBlock ());
                        break;
                    case 296:
                        $yyVal = new \parse\GotoStatement($yyVals[$yyTop+-1], $this->getLocation($yyVals[$yyTop+-2]));
                        break;
                    case 297:
                        $yyVal = new \parse\ContinueStatement();
                        break;
                    case 298:
                        $yyVal = new \parse\BreakStatement();
                        break;
                    case 299:
                        $yyVal = new \parse\ReturnStatement();
                        break;
                    case 300:
                        $yyVal = new \parse\ReturnStatement($yyVals[$yyTop+-1]);
                        break;
                    case 301:
                        $this->case_301();
                        break;
                    case 302:
                        $this->case_302();
                        break;
                    case 307:
                        $this->case_307();
                        break;
                    case 308:
                        $this->case_308();
                        break;
                    case 309:
                        $yyVal = new \parse\IdentifierDeclarator(($yyVals[$yyTop+0]));
                        break;
                    case 310:
                        $yyVal = (new \parse\IdentifierDeclarator(($yyVals[$yyTop+-2])))->Push(($yyVals[$yyTop+0]));
                        break;
                    case 311:
                        $yyVal = (new \parse\IdentifierDeclarator(($yyVals[$yyTop+-3])))->Push("~" + ($yyVals[$yyTop+0]));
                        break;
                    case 312:
                        $yyVal = ((IdentifierDeclarator)($yyVals[$yyTop+-2]))->Push (($yyVals[$yyTop+0]));
                        break;
                    case 313:
                        $yyVal = ((IdentifierDeclarator)($yyVals[$yyTop+-3]))->Push ("~" + ($yyVals[$yyTop+0]));
                        break;
                    case 314:
                        $yyVal = (new \parse\IdentifierDeclarator(($yyVals[$yyTop+-3])))->Push("operator" + $yyVals[$yyTop+0]);
                        break;
                    case 315:
                        $yyVal = ((IdentifierDeclarator)($yyVals[$yyTop+-3]))->Push ("operator" + $yyVals[$yyTop+0]);
                        break;
                    case 316:
                        $this->case_316();
                        break;
                    case 317:
                        $this->case_317();
                        break;
                    case 318:
                        $this->case_318();
                        break;
                    case 319:
                        $this->case_319();
                        break;
                    case 320:
                        $this->case_320();
                        break;
                }
                $yyTop -= self::$yyLen[$yyN];
                $yyState = $yyStates[$yyTop];
                $yyM = self::$yyLhs[$yyN];
                if ($yyState === 0 && $yyM === 0) {
                    $yyState = 29;
                    if ($yyToken < 0) {
                        $yyToken = $yyLex->advance() ? $yyLex->token() : 0;
                    }
                    if ($yyToken === 0) {
                        return $yyVal;
                    }
                    goto continue_yyLoop;
                }
                $yyN = self::$yyGindex[$yyM];
                if ($yyN !== 0 && ($yyN += $yyState) >= 0
                    && $yyN < count(self::$yyTable)
                    && self::$yyCheck[$yyN] === $yyState)
                {
                    $yyState = self::$yyTable[$yyN];
                } else {
                    $yyState = self::$yyDgoto[$yyM];
                }
                goto continue_yyLoop;
                continue_yyDiscarded:
            }
            continue_yyLoop:
        }
    }
    protected static $yyLhs = [
                      -1,
            1,    1,    1,    1,    1,    1,    1,    3,    3,    3,
            3,    3,    3,    3,    3,    3,    3,    4,    4,    8,
            8,    8,    8,    8,    8,    8,    8,   10,   10,   10,
           10,    9,    9,   11,   11,   11,   11,   12,   12,   12,
           13,   13,   13,   14,   14,   14,   14,   14,   15,   15,
           15,   16,   16,   17,   17,   18,   18,   19,   19,   20,
           20,   21,   21,    7,    7,   22,   22,   22,   22,   22,
           22,   22,   22,   22,   22,   22,   22,   22,    2,    2,
           23,   24,   24,   25,   25,   25,   25,   25,   25,   25,
           25,   26,   26,   31,   31,   27,   27,   27,   27,   27,
           28,   28,   28,   28,   28,   28,   28,   28,   28,   28,
           28,   28,   28,   28,   28,   36,   36,   34,   34,   34,
           34,   39,   39,   40,   40,   40,   40,   37,   37,   37,
           41,   41,   41,   41,   35,   35,   35,   35,   35,   42,
           42,   43,   43,   30,   32,   32,   32,   32,   47,   47,
           47,   47,   47,   47,   47,   47,   47,   47,   47,   47,
           47,   47,   47,   47,   47,   47,   47,   47,   47,   47,
           47,   47,   47,   47,   47,   47,   47,   47,   48,   48,
           48,   48,   48,   48,   45,   45,   45,   45,   45,   45,
           45,   45,   45,   45,   45,   45,   45,   44,   44,   44,
           44,   46,   46,   29,   29,   29,   49,   49,   50,   50,
           51,   51,   51,   51,    5,    5,   52,   52,   52,   52,
           53,   53,   53,   53,   53,   53,   53,   53,   53,   53,
           53,   33,   33,   33,    6,    6,    6,    6,   54,   55,
           55,   56,   56,   57,   57,   57,   57,   57,   57,   58,
           59,   59,   64,   64,   65,   65,   38,   38,   66,   66,
           67,   67,   67,   67,   67,   67,   67,   67,   67,   72,
           68,   68,   68,   71,   71,   71,   71,   71,   69,   60,
           60,   61,   61,   61,   61,   73,   73,   74,   74,   62,
           62,   62,   62,   62,   62,   63,   63,   63,   63,   63,
            0,    0,   75,   75,   75,   75,   70,   70,   78,   78,
           78,   78,   78,   78,   78,   76,   76,   77,   77,   77,
           79,   79,   79,
    ];

    protected static $yyLen = [
                   2,
            1,    1,    1,    1,    1,    3,    3,    1,    4,    3,
            4,    3,    3,    2,    2,    6,    7,    1,    3,    1,
            2,    2,    2,    2,    2,    2,    4,    1,    1,    1,
            1,    1,    4,    1,    3,    3,    3,    1,    3,    3,
            1,    3,    3,    1,    3,    3,    3,    3,    1,    3,
            3,    1,    3,    1,    3,    1,    3,    1,    3,    1,
            3,    1,    5,    1,    3,    1,    1,    1,    1,    1,
            1,    1,    1,    1,    1,    1,    1,    1,    1,    3,
            1,    2,    3,    1,    2,    1,    2,    1,    2,    1,
            2,    1,    3,    1,    3,    1,    1,    1,    1,    1,
            1,    1,    1,    1,    1,    1,    1,    1,    1,    1,
            1,    1,    1,    1,    1,    1,    1,    3,    5,    2,
            2,    1,    3,    1,    2,    2,    2,    1,    1,    1,
            2,    1,    2,    1,    4,    5,    5,    6,    2,    1,
            3,    1,    3,    1,    2,    2,    3,    1,    1,    1,
            1,    1,    1,    1,    1,    1,    1,    1,    1,    1,
            1,    1,    1,    1,    1,    1,    1,    1,    1,    1,
            1,    1,    1,    1,    1,    1,    2,    2,    1,    2,
            2,    3,    4,    4,    1,    3,    5,    4,    4,    6,
            6,    5,    4,    3,    4,    4,    3,    1,    2,    2,
            3,    1,    2,    1,    1,    1,    1,    3,    1,    3,
            2,    4,    2,    1,    1,    2,    1,    1,    1,    2,
            3,    2,    3,    3,    4,    3,    4,    2,    3,    3,
            4,    1,    3,    4,    1,    2,    3,    4,    2,    1,
            2,    3,    2,    1,    1,    1,    1,    1,    1,    3,
            2,    3,    1,    2,    1,    1,    2,    3,    1,    2,
            1,    1,    1,    1,    2,    2,    4,    5,    2,    5,
            2,    2,    2,    4,    3,    4,    3,    4,    2,    1,
            2,    5,    7,    7,    6,    1,    2,    4,    3,    5,
            7,    6,    7,    6,    7,    3,    2,    2,    2,    3,
            1,    2,    1,    1,    1,    1,    4,    3,    1,    3,
            4,    3,    4,    4,    4,    4,    5,    1,    2,    2,
            1,    1,    1,
    ];

    protected static $yyDefRed = [
                    0,
            0,    0,   96,   97,   98,   99,  100,  144,  205,  102,
          103,  104,  105,  108,  109,  106,  107,  204,  206,  101,
          110,  111,  112,  128,  129,  130,    0,  305,    0,  304,
            0,    0,    0,    0,    0,  113,  114,    0,  303,  301,
          306,    0,    0,  116,  117,    0,    0,  302,  179,    0,
            0,    0,    0,    0,   82,    0,   92,    0,    0,    0,
            0,  115,   85,   87,   89,   91,    0,    0,  120,    0,
            0,  310,    0,    0,    0,    0,  140,    0,  169,  170,
          160,  161,  158,  159,  154,  155,  167,  168,  174,  175,
          176,  172,  173,    0,    0,  162,  151,  149,  150,  166,
          165,  152,  153,  156,  157,  164,  163,  171,  181,    0,
          202,    0,    0,  200,    0,  180,    0,   83,  321,    0,
            0,  322,  323,  318,    0,  308,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,  257,    0,  261,
            0,    0,    0,  259,  262,  263,  264,    0,    0,  118,
          312,    0,    0,    0,    0,    0,    0,  209,  314,  311,
            0,  135,    0,    0,  177,  178,  186,  203,    0,  201,
           93,    0,    0,    2,    3,    0,    0,    0,    4,    5,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
          251,    0,    0,   28,   29,   30,   31,  280,    8,    0,
            0,   79,    0,   34,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,   64,  255,  256,  244,  245,
          246,  247,  248,  249,    0,  253,    0,    0,  232,   95,
          320,  307,  319,  197,    0,   18,    0,    0,  194,    0,
            0,    0,  182,    0,    0,    0,  271,  272,  273,  266,
            0,  269,    0,    0,    0,  258,  260,  279,  265,    0,
            0,    0,  124,    0,  122,  315,  313,  316,    0,    0,
            0,    0,    0,  213,    0,    0,    0,   32,   81,  143,
          137,  141,  136,    0,    0,    0,    0,   26,    0,   21,
           22,    0,    0,    0,    0,    0,    0,  297,  298,  299,
            0,    0,    0,    0,    0,    0,   23,   24,    0,  281,
            0,   14,   15,    0,    0,    0,   67,   68,   69,   70,
           71,   72,   73,   74,   75,   76,   77,   78,   66,    0,
           25,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
          252,  254,    0,    0,    0,  235,    0,    0,  240,  196,
            0,  195,    0,  193,  189,    0,  188,    0,    0,  184,
          183,  277,    0,    0,    0,    0,    0,  275,    0,  125,
          126,  127,    0,  119,  228,    0,    0,  222,    0,    0,
            0,    0,    0,    0,  317,  208,  210,  138,    7,  250,
            0,    0,    0,    0,    0,    0,    0,    0,  296,  300,
            6,    0,  131,  133,    0,  218,    0,  216,   80,   13,
           10,    0,    0,   12,   65,   35,   36,   37,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,  243,  233,    0,  236,  239,
          241,   19,    0,    0,  192,  187,  276,    0,    0,  278,
          267,  274,  123,  229,  221,  226,  223,  212,  230,    0,
          224,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,   33,   11,    9,    0,  242,  234,  237,    0,
          190,  191,  268,    0,  231,  227,  225,    0,    0,  290,
            0,    0,    0,    0,    0,    0,   63,  238,  270,    0,
            0,    0,  285,    0,  286,    0,  294,    0,  292,    0,
           16,    0,  283,    0,    0,  284,  287,  291,  295,  293,
           17,    0,    0,    0,
    ];

    protected static $yyDgoto = [
                    29,
          199,  200,  201,  235,  303,  355,  202,  203,  204,  205,
          206,  207,  208,  209,  210,  211,  212,  213,  214,  215,
          216,  330,  280,  217,  125,   56,   32,   33,   34,   35,
           57,   58,  230,   36,   37,  263,   38,   69,  264,  265,
          306,   76,   77,   59,   60,  113,  109,   61,  386,  157,
          158,  387,  275,  357,  358,  359,  218,  219,  220,  221,
          222,  223,  224,  225,  226,  143,  144,  145,  146,   39,
          148,  252,  514,  515,   40,   41,  127,   42,  128,
    ];

    protected static $yySindex = [
                 1086,
         -197,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,  -83,    0, 1086,    0,
           83, 3827, 3827, 3827, 3827,    0,    0,   85,    0,    0,
            0,  -29,  110,    0,    0, -172,  -19,    0,    0, 3778,
           88,  -33,  -26,  -64,    0,   30,    0, 1243,  124,   46,
           -1,    0,    0,    0,    0,    0, 3666,  -16,    0,  112,
         2267,    0, 3778, -187,  166,   -5,    0, -172,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,  230,  228,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,  298,
            0,   46,  -33,    0,  -26,    0,   88,    0,    0,  316,
          722,    0,    0,    0,   83,    0, 2324, 3827,   46, 1153,
          291,  -80,  310,  299,  305,  313, 3827,    0, -203,    0,
           83,  -21, 3771,    0,    0,    0,    0,  -18,  254,    0,
            0, 3778,   93,  257,   75,  346,  356,    0,    0,    0,
         1939,    0,  -68,   11,    0,    0,    0,    0,   46,    0,
            0,  344,  -11,    0,    0, 1979, 2056, 2056,    0,    0,
          379,  395,  408,  617,  416,  174,  398,  402, 1694, 1381,
            0, 1939, 1939,    0,    0,    0,    0,    0,    0,   99,
            5,    0,  251,    0, 1939,  391,   32, -200,   21, -160,
          442,  421,  412,  266,  -49,    0,    0,    0,    0,    0,
            0,    0,    0,    0,  420,    0,  268, 1466,    0,    0,
            0,    0,    0,    0,  278,    0,  521,  104,    0, 1543,
          473,  771,    0, 3778,  314, 2352,    0,    0,    0,    0,
           83,    0,  537,  -38, 2454,    0,    0,    0,    0, -186,
         -186, -186,    0,    9,    0,    0,    0,    0, 2176, 1702,
          -33,  519,  116,    0,   63,  257, 3799,    0,    0,    0,
            0,    0,    0,  -65,  325,  617, 1381,    0, 1381,    0,
            0, 1939, 1939, 1939,  256, 1309,  524,    0,    0,    0,
          102,  303,  545, 3855, 3855,  392,    0,    0, 1939,    0,
          330,    0,    0, 1622, 1939,  331,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0, 1939,
            0, 1939, 1939, 1939, 1939, 1939, 1939, 1939, 1939, 1939,
         1939, 1939, 1939, 1939, 1939, 1939, 1939, 1939, 1939, 1939,
            0,    0, 1939,  333,   14,    0,  722,    6,    0,    0,
         1939,    0, 1453,    0,    0, 1939,    0, 1732,  498,    0,
            0,    0,  551,  -36,  532,  553,  536,    0,  555,    0,
            0,    0,  254,    0,    0,  556,  559,    0, 1743,  510,
         1939,   63, 2504, 1771,    0,    0,    0,    0,    0,    0,
          565,  566,  324,  352,  396,  595, 1779, 1779,    0,    0,
            0, 1801,    0,    0, 2399,    0,   87,    0,    0,    0,
            0,  426,   36,    0,    0,    0,    0,    0,  391,  391,
           32,   32, -200, -200, -200, -200,   21,   21, -160,  442,
          421,  412,  266,  171,  544,    0,    0,  981,    0,    0,
            0,    0,  546,  560,    0,    0,    0,  590, 1850,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,  611,
            0, 1870,  561,  533,  533,  617,  535,  617, 1939, 1878,
         1899, 1466,    0,    0,    0, 1939,    0,    0,    0,  722,
            0,    0,    0,  602,    0,    0,    0,  340, -100,    0,
          428,  617,  432,  617,  434,   15,    0,    0,    0,  617,
         1939,  606,    0,  -13,    0,  607,    0,  617,    0,  617,
            0, 1394,    0,  609,  525,    0,    0,    0,    0,    0,
            0,  525,  525,  525,
    ];

    protected static $yyRindex = [
                    0,
            0, 2232,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,  131,  896, 1014, 2205,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0, 2080,    0,    0,    0,
            0,    0,  345,    0,    0,    0,    0,  -35,    0,  867,
          826,    0,    0,    0,    0,    0,    0, 2109,    0,    0,
            0,    0,    0,    0,   26,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,  916,    0,    0,  663,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,  957,    0,
            0,    0, 2296,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,  503,    0,  624,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0, 1016,    0,
            0,  -35, 3743,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
         2858,    0, 2949,    0,    0, 3143, 3221, 3296, 2921, 3587,
         2163, 2732, 1244, 2009,  912,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0, 2786,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
          511,  515,  516,    0,  520,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,  368,  383,  627,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,  -35,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,  528,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,  628,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0, 3184, 3211,
         3258, 3269, 3306, 3343, 3456, 3541, 3564, 3580, 3638, 3627,
         3650, 1431, 3403,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0, 2895,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0, 2821,    0,    0,    0,  192,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,   56,   62,
    ];

    protected static $yyGindex = [
                    0,
            0,  -89,    0,  357,  -56,  188, -118,  -43, -149,    0,
         -168,   61,  105,   70,  327,  328,  326,  336,  332,    0,
         -159,    0, -335,   20,    1, -110,    0,  177,  -25,    0,
          558,  -41, -162,    0,    0,   23,    0,  -46,    0,  302,
          122,  608, -131,  -53,  147,   39,  -58,    2,  -67,    0,
          410, -126, -235, -411,    0,  334, -167,    0,  -32, -248,
            0,    0,    0, -177, -220,    0,  547,    0,    0,   12,
            0,    0,    0,  175,  659,    0,    0,    0,    0,
    ];

    protected static $yyTable = [
                   114,
           31,  279,  229,  156,  352,  117,   51,  117,   94,  110,
           71,  236,  241,  350,  159,   53,  295,  445,  255,   30,
          118,  150,  118,   94,  513,  126,  111,  111,  274,   31,
          254,  282,   63,   64,   65,   66,  490,  392,  163,   46,
          258,  149,  307,  308,  314,  245,  286,  408,   30,   47,
          316,  354,  383,  116,  284,  331,  281,  448,  522,  398,
           68,  170,  237,  337,  338,  356,  450,  141,  142,  142,
           44,  155,   43,  117,  335,  172,  336,  124,  147,  309,
          341,  253,  342,  172,   75,  130,  140,  168,  118,  168,
          308,  115,   54,  266,  232,  315,  353,  160,   45,  301,
          302,  273,  393,   78,  120,  111,   67,  343,  344,  229,
          490,  526,  271,  272,  269,  259,   53,  278,  400,  162,
           52,  268,   51,  369,   53,   52,  415,   51,  485,   53,
          155,   67,  288,  290,  291,  283,  131,  251,  447,  521,
          374,   55,  309,  141,  142,  309,  231,  233,  278,  278,
          142,  390,  282,  394,  147,  269,  250,  310,  480,  481,
          410,  278,  140,   51,  305,  270,  429,  430,   84,  242,
           84,   84,   84,   44,   84,  524,  243,  270,  373,  418,
          289,  392,  426,  427,  428,  370,  288,  379,   75,   84,
          419,   75,  116,  279,  449,  236,  278,  302,  112,  302,
           54,   45,  403,  404,  405,  129,  270,   67,   54,  375,
          244,  425,  111,   54,  309,  273,  168,  384,  308,  511,
          512,   84,  349,   49,  282,  423,  161,  110,  486,  282,
          401,  282,  402,  282,  282,   74,  282,  153,  229,  308,
           70,   54,  452,  395,  453,  111,  155,  454,  132,   54,
          282,  377,  417,  458,   94,  155,   84,   50,  285,  169,
          444,  305,  483,  305,    9,  311,  312,  313,  132,  155,
          165,    9,  468,   18,   19,  473,  363,  155,  305,  305,
           18,   19,  380,  381,  382,  489,  339,  340,  278,  278,
          278,  278,  278,  278,  278,  278,  278,  278,  278,  278,
          278,  278,  278,  278,  278,  278,  511,  512,  498,  278,
          500,  329,  352,  352,  282,  407,  282,  282,  360,  356,
          166,  361,  308,  197,  278,  470,  507,  508,  192,  229,
          190,   49,  240,  194,  517,  195,  519,  168,  167,   49,
          229,   44,  523,  411,   49,  278,  309,  533,  197,  246,
          529,  279,  530,  192,  534,  190,  247,  193,  194,  489,
          195,  417,  248,  229,  476,   50,  304,  309,  278,   45,
          249,  229,   49,   50,  198,  289,  289,  267,   50,  120,
           49,  288,  288,  239,  198,  198,  276,   84,  198,  501,
          503,  505,  477,  155,   72,  309,  151,  431,  432,  277,
           73,    9,  152,  229,  121,  132,   50,  132,  132,  132,
           18,   19,  437,  438,   50,  155,  196,  112,  292,  129,
          134,   84,  134,  134,  134,  413,  414,  334,  278,  416,
          297,  415,  332,   53,  293,  198,  478,  333,  120,  309,
          191,  196,  278,  433,  434,  435,  436,  294,  282,  282,
          282,  282,  197,  282,  282,  296,  298,  192,  132,  190,
          299,  193,  194,  304,  195,  304,  484,  278,  516,  361,
          198,  309,  518,  134,  520,  309,  282,  309,  198,  345,
          304,  304,  270,  282,  282,  282,  282,  282,  282,  282,
          282,  282,  282,  282,  282,  282,  282,  282,  282,  282,
          282,  282,  282,  282,  282,  282,  282,  282,  282,  282,
           44,  282,  282,  282,  346,  282,  282,  282,  282,  282,
          282,  282,  282,  317,  318,  319,  320,  321,  322,  323,
          324,  325,  326,  327,  328,  347,  348,  285,   45,  260,
          261,  262,  120,  214,  351,  196,  214,  227,  174,  175,
          176,  218,  177,  178,  218,  211,  217,  197,  211,  217,
          219,  362,  192,  219,  190,  365,  193,  194,  220,  195,
          371,  220,  173,  174,  175,  176,  376,  177,  178,  391,
          406,  399,  409,  198,  238,  412,  420,  424,    9,  446,
          456,  457,  459,  460,  461,  462,  464,   18,   19,  465,
           62,  198,  467,  179,  180,  474,  475,    3,    4,    5,
            6,    7,    8,    9,   10,   11,   12,   13,   14,   15,
           16,   17,   18,   19,   20,   21,   22,   23,  179,  180,
           24,   25,   26,   27,  479,  198,  487,  181,  491,  182,
          183,  184,  185,  186,  187,  188,  189,  120,  493,  197,
          196,  495,  492,  497,  192,  482,  190,  499,  193,  194,
          509,  195,  510,  525,  207,  528,  532,  215,  217,  506,
          422,  439,  441,  440,  171,  198,  173,  174,  175,  176,
          443,  177,  178,  442,  463,  164,  397,   48,  527,  257,
            0,  451,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,  199,  199,   62,    0,  199,    0,    0,    0,
            0,    3,    4,    5,    6,    7,    8,    9,   10,   11,
           12,   13,   14,   15,   16,   17,   18,   19,   20,   21,
           22,   23,  179,  180,   24,   25,   26,   27,    0,  120,
            0,  181,  196,  182,  183,  184,  185,  186,  187,  188,
          189,    0,    0,  199,  197,    0,    0,    0,    0,  192,
            0,  190,    0,  193,  194,    0,  195,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,  173,  174,  175,  176,    0,  177,  178,  199,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,  197,    0,    0,    0,    0,  192,   62,
          190,    0,  368,  194,    0,  195,    3,    4,    5,    6,
            7,    8,    9,   10,   11,   12,   13,   14,   15,   16,
           17,   18,   19,   20,   21,   22,   23,  179,  180,   24,
           25,   26,   27,    0,  228,    0,  181,  196,  182,  183,
          184,  185,  186,  187,  188,  189,    0,    0,    0,    0,
          185,    0,    0,  367,    0,  185,  185,    0,    0,  185,
            0,    0,    0,  173,  174,  175,  176,    0,  177,  178,
            0,    0,    0,    0,  185,    0,  185,    0,    0,    0,
            0,    0,    0,    0,    0,    0,  196,    0,    0,    0,
            0,  148,    0,    0,    0,    0,    0,  148,    0,    0,
          148,    0,    0,    0,    0,    0,  185,  185,    0,  199,
            0,    0,    0,    0,    0,  148,    0,  148,    0,  179,
          180,    0,    0,   86,    0,   86,   86,   86,  181,   86,
          182,  183,  184,  185,  186,  187,  188,  189,  185,    0,
          146,    0,   62,  199,   86,   62,  146,    0,  148,  146,
            0,    0,    0,    0,    0,    0,    0,    0,    0,   62,
           62,    0,    0,    0,  146,    0,  146,    0,  227,  174,
          175,  176,    0,  177,  178,    0,   86,    0,    0,  148,
            0,  145,    0,    0,    0,    0,    0,  145,    0,    0,
          145,    0,    0,    0,   62,    0,    0,  146,    0,    0,
            0,    0,    0,  197,    0,  145,    0,  145,  192,    0,
          190,   86,  193,  194,    0,  195,  354,  227,  174,  175,
          176,    0,  177,  178,  179,  180,   62,    0,  146,    0,
            0,    0,    0,    0,    0,    0,    0,    0,  145,    0,
          147,   88,    0,   88,   88,   88,  147,   88,    0,  147,
            0,    0,    0,    0,  366,    0,    0,    0,    9,    0,
            0,  353,   88,    0,  147,    0,  147,   18,   19,  145,
            0,    0,    0,  179,  180,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,  228,   88,  488,  196,  147,    0,    0,
          185,    0,    0,    0,    0,  185,    0,  185,  185,  185,
          185,  185,  185,  185,  185,  185,  185,  185,  185,  185,
          185,  185,  185,  185,  185,  185,  185,  185,  147,   88,
          185,  185,  185,  185,   28,    0,    0,    0,    0,    0,
            0,  148,   86,    0,    0,    0,  148,  185,  148,  148,
          148,  148,  148,  148,  148,  148,  148,  148,  148,  148,
          148,  148,  148,  148,  148,  148,  148,  148,  148,    0,
            0,  148,  148,  148,  148,  197,   86,    0,    0,    0,
          192,    0,  190,  234,  193,  194,    0,  195,  148,    0,
          146,   62,    0,    0,    0,  146,    0,  146,  146,  146,
          146,  146,  146,  146,  146,  146,  146,  146,  146,  146,
          146,  146,  146,  146,  146,  146,  146,  146,    0,    0,
          146,  146,  146,  146,    0,    0,    0,  227,  174,  175,
          176,  145,  177,  178,    0,    0,  145,  146,  145,  145,
          145,  145,  145,  145,  145,  145,  145,  145,  145,  145,
          145,  145,  145,  145,  145,  145,  145,  145,  145,    0,
           88,  145,  145,  145,  145,    0,    0,  122,  196,    0,
            0,    0,    0,    0,   58,    0,    0,   58,  145,    0,
            0,    0,    0,  179,  180,    0,    0,    0,    0,    0,
          147,   58,   58,  121,   88,  147,   58,  147,  147,  147,
          147,  147,  147,  147,  147,  147,  147,  147,  147,  147,
          147,  147,  147,  147,  147,  147,  147,  147,    0,    0,
          147,  147,  147,  147,  123,    0,   58,    0,    0,    0,
            0,  197,    1,    0,    0,    0,  192,  147,  190,    0,
          193,  194,    0,  195,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,  120,    0,  198,   58,    0,
            2,    0,    0,    0,    0,    0,    0,    3,    4,    5,
            6,    7,    8,    9,   10,   11,   12,   13,   14,   15,
           16,   17,   18,   19,   20,   21,   22,   23,    0,    0,
           24,   25,   26,   27,    0,    0,    0,    0,    0,  227,
          174,  175,  176,  197,  177,  178,    0,    0,  192,    0,
          190,    0,  193,  194,    0,  195,  197,    0,    0,    0,
            0,  192,    0,  190,  196,  193,  194,   62,  195,  354,
            0,    0,    0,    0,    3,    4,    5,    6,    7,    8,
            9,   10,   11,   12,   13,   14,   15,   16,   17,   18,
           19,   20,   21,   22,   23,  179,  180,   24,   25,   26,
           27,   59,    0,    0,   59,    0,    0,    0,    0,    0,
            0,    0,    0,    0,  353,  197,    0,    0,   59,   59,
          192,    0,  190,   59,  193,  194,    0,  195,  197,    0,
            0,    0,    0,  192,    0,  190,  196,  193,  194,    0,
          195,  354,    0,    0,   58,   58,  228,    0,  531,  196,
            0,    0,    0,   59,    0,    0,    0,   62,    0,    0,
            0,    0,    0,   58,    3,    4,    5,    6,    7,    8,
            9,   10,   11,   12,   13,   14,   15,   16,   17,   18,
           19,   20,   21,   22,   23,   59,  353,   24,   25,   26,
           27,    0,    0,    0,    0,  227,  174,  175,  176,    0,
          177,  178,    0,    0,  119,  197,    0,    0,  196,    0,
          192,    0,  190,    0,  193,  194,    0,  195,  228,    0,
            0,  196,    0,   62,    0,    0,    0,    0,    0,    0,
            3,    4,    5,    6,    7,    8,    9,   10,   11,   12,
           13,   14,   15,   16,   17,   18,   19,   20,   21,   22,
           23,  179,  180,   24,   25,   26,   27,    0,    0,    0,
            0,    0,    0,    0,    0,  364,    0,  227,  174,  175,
          176,    0,  177,  178,    0,    0,    0,    0,    0,    0,
          227,  174,  175,  176,  197,  177,  178,    0,    0,  192,
            0,  190,  421,  193,  194,   62,  195,    0,  196,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    9,   10,
           11,   12,   13,   14,   15,   16,   17,   18,   19,   20,
           21,   22,   23,  179,  180,   24,   25,   26,   27,    0,
            0,   59,   59,    0,    0,    0,  179,  180,    0,  227,
          174,  175,  176,    0,  177,  178,    0,    0,    0,    0,
           59,    0,  227,  174,  175,  176,  197,  177,  178,    0,
            0,  192,    0,  190,  197,  193,  194,    0,  195,  192,
            0,  190,    0,  389,  194,    0,  195,  196,    0,    0,
            9,    0,  300,    0,    0,    0,    0,    0,    0,   18,
           19,    0,    0,    0,  197,  179,  180,    0,    0,  192,
            0,  190,    0,  193,  194,  197,  195,    0,  179,  180,
          192,    0,  190,    0,  193,  194,    0,  195,    0,    0,
            0,    0,    0,    0,  388,    0,    0,    0,    0,  227,
          174,  175,  176,  197,  177,  178,    0,    0,  192,    0,
          190,  197,  472,  194,    0,  195,  192,    0,  190,  196,
          193,  194,    0,  195,  455,    0,    0,  196,    0,    0,
            0,    0,    0,  197,    0,  466,    0,  198,  192,    0,
          190,    0,  193,  194,    0,  195,    0,    0,    0,    0,
            0,    0,    0,    0,    0,  179,  180,  196,    0,    0,
            0,    0,    0,  471,    0,    0,    0,    0,  196,    0,
            0,    0,    0,    0,    0,    0,    0,    0,  227,  174,
          175,  176,  197,  177,  178,    0,    0,  192,    0,  190,
            0,  193,  194,    0,  195,    0,  196,    0,    0,    0,
            0,    0,  197,    0,  196,    0,    0,  192,    0,  190,
          197,  193,  194,    0,  195,  192,    0,  190,  502,  193,
          194,    0,  195,  482,    0,    0,  196,    0,    0,    0,
            0,  197,    0,    0,  179,  180,  192,    0,  190,  504,
          193,  194,    0,  195,    0,    0,    0,    0,    0,    0,
          227,  174,  175,  176,    0,  177,  178,    0,  227,  174,
          175,  176,  496,  177,  178,    0,    0,    0,    0,    0,
            0,  197,  228,    0,    0,  196,  192,    0,  190,    0,
          193,  194,    0,  195,    0,    0,    0,    0,  227,  174,
          175,  176,    0,  177,  178,  196,    0,    0,    0,  227,
          174,  175,  176,  196,  177,  178,  179,  180,    0,    0,
            0,  197,    0,    0,  179,  180,  192,    0,  287,    0,
          193,  194,    0,  195,  196,    0,    0,  227,  174,  175,
          176,    0,  177,  178,    0,  227,  174,  175,  176,    0,
          177,  178,    0,    0,  179,  180,    0,    0,    0,   60,
            0,    0,   60,    0,    0,  179,  180,  227,  174,  175,
          176,    0,  177,  178,  196,    0,   60,   60,    0,    0,
            0,   60,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,  179,  180,    0,    0,    0,  197,    0,
            0,  179,  180,  192,    0,  289,    0,  193,  194,    0,
          195,   60,    0,    0,  196,    0,  227,  494,  175,  176,
            0,  177,  178,  179,  180,    0,    0,  139,    0,  139,
          139,  139,    0,  139,    0,    0,  227,  174,  175,  176,
            0,  177,  178,   60,  227,  174,  175,  176,  139,  177,
          178,    0,    0,    0,    0,    0,  121,    0,  121,  121,
          121,    0,  121,    0,    0,  227,  174,  175,  176,    0,
          177,  178,  179,  180,    0,    0,    0,  121,    0,    0,
          139,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,  196,  179,  180,    0,    0,    0,    0,    0,    0,
          179,  180,    0,    0,    0,  227,  174,  175,  176,  121,
          177,  178,    0,   54,    0,  139,   54,    0,    0,    0,
            0,  179,  180,  271,    0,  269,  385,   53,    0,    0,
           54,   54,    0,    0,    0,   54,    0,    0,    0,    0,
            0,    0,    0,    0,  121,  227,  174,  175,  176,    0,
          177,  178,   90,    0,   90,   90,   90,    0,   90,    0,
            0,  179,  180,    0,    0,   54,   54,    0,    0,    0,
            0,    0,    0,   90,    0,    0,  270,    0,    0,  115,
            0,  115,    0,  115,    0,    0,    0,    0,    0,    0,
           60,    0,    0,    0,    0,    0,   54,   54,    0,    0,
          115,  179,  180,    0,    0,   90,    0,    0,   60,    0,
            0,   54,    0,    0,    0,    0,    0,  154,    0,    0,
            0,    0,  227,  174,  175,  176,    0,  177,  178,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
           90,    0,    0,  115,    0,    0,  139,  115,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,  115,    0,    0,  115,    0,    0,
            0,    0,    0,    0,  139,  121,    0,    0,  179,  180,
          139,  139,  139,  139,  139,  139,  139,  139,  139,  139,
          139,  139,  139,  139,  139,  139,  139,  139,  139,  139,
          139,  139,  372,  121,  139,  139,  139,  139,    0,  121,
          121,  121,  121,  121,  121,  121,  121,  121,  121,  121,
          121,  121,  121,  121,  121,  121,  121,  121,  121,  121,
          121,  115,    0,  121,  121,  121,  121,    0,    0,    0,
            0,    0,   49,   54,   54,    0,  416,    0,  415,  385,
           53,    0,    0,    0,    0,    0,  120,    0,    0,    0,
            0,    0,   54,    0,    0,    0,    0,    0,    0,    0,
           62,   90,    0,    0,    0,    0,   50,    3,    4,    5,
            6,    7,    8,    9,   10,   11,   12,   13,   14,   15,
           16,   17,   18,   19,   20,   21,   22,   23,  115,  270,
           24,   25,   26,   27,  378,   90,    0,    0,    0,    0,
            0,  309,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,  115,    0,    0,    0,
            0,    0,  115,  115,  115,  115,  115,  115,  115,  115,
          115,  115,  115,  115,  115,  115,  115,  115,  115,  115,
          115,  115,  115,  115,  469,    0,  115,  115,  115,  115,
            0,   62,  115,    0,    0,    0,    0,    0,    3,    4,
            5,    6,    7,    8,    9,   10,   11,   12,   13,   14,
           15,   16,   17,   18,   19,   20,   21,   22,   23,    0,
          115,   24,   25,   26,   27,    0,  115,  115,  115,  115,
          115,  115,  115,  115,  115,  115,  115,  115,  115,  115,
          115,  115,  115,  115,  115,  115,  115,  115,   62,    0,
          115,  115,  115,  115,    0,    3,    4,    5,    6,    7,
            8,    9,   10,   11,   12,   13,   14,   15,   16,   17,
           18,   19,   20,   21,   22,   23,   62,    0,   24,   25,
           26,   27,    0,    3,    4,    5,    6,    7,    8,    9,
           10,   11,   12,   13,   14,   15,   16,   17,   18,   19,
           20,   21,   22,   23,    0,    0,   24,   25,   26,   27,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,   62,    0,    0,    0,    0,    0,    0,
            3,    4,    5,    6,    7,    8,    9,   10,   11,   12,
           13,   14,   15,   16,   17,   18,   19,   20,   21,   22,
           23,    0,    0,   24,   25,   26,   27,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,   62,    0,
            0,    0,    0,    0,    0,    3,    4,    5,    6,    7,
            8,    9,   10,   11,   12,   13,   14,   15,   16,   17,
           18,   19,   20,   21,   22,   23,    0,    0,   24,   25,
           26,   27,   56,    0,    0,   56,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,   62,   56,
           56,    0,    0,    0,   56,    3,    4,    5,    6,    7,
            8,    9,   10,   11,   12,   13,   14,   15,   16,   17,
           18,   19,   20,   21,   22,   23,    0,    0,   24,   25,
           26,   27,    1,    1,   56,    1,    1,    1,    1,    1,
            1,    1,    1,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    1,    1,    1,    1,    1,    1,    0,
            0,    0,    0,    0,    0,   56,   56,    2,    2,    0,
            2,    0,    2,    2,    2,    2,    2,    2,    0,    0,
            0,    0,    0,    0,    0,    0,    1,    0,    1,    1,
            2,    2,    2,    2,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,   20,   20,    0,    0,   20,   20,
           20,   20,   20,    0,   20,    0,    0,    0,    0,    1,
            1,    2,    0,    0,    2,   20,   20,   20,   20,   20,
           20,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,   27,   27,    0,    0,   27,   27,   27,   27,   27,
            0,   27,    0,    0,    2,    0,    0,    0,    0,    0,
           20,   20,   27,   27,   27,   27,   27,   27,   49,    0,
            0,   49,    0,    0,   49,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,   49,   49,
            0,   20,   20,   49,    0,   32,   32,   27,   27,   32,
           32,   32,   32,   32,    0,   32,    0,    0,    0,    0,
            0,    0,   56,   56,    0,    0,   32,   32,   32,    0,
           32,   32,    0,   49,   49,    0,    0,    0,   27,   27,
            0,   56,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,   32,   32,    0,   49,   49,    1,    1,    1,    1,
            1,    1,    1,    1,    1,    0,    1,    1,    1,    1,
            1,    1,    1,    1,    1,    1,    1,    1,    1,    1,
            0,    0,   32,   32,    0,    1,    0,    0,    0,    0,
            0,    2,    2,    2,    2,    2,    2,    2,    2,    2,
            0,    2,    2,    2,    2,    2,    2,    2,    2,    2,
            2,    2,    2,    2,    2,    0,    0,    0,    0,    0,
            2,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,   20,   20,   20,   20,   20,   20,    0,   20,   20,
           20,   20,   20,   20,   20,   20,   20,   20,   20,   20,
           20,   20,    0,    0,    0,    0,    0,   20,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,   27,   27,
           27,   27,   27,   27,    0,   27,   27,   27,   27,   27,
           27,   27,   27,   27,   27,   27,   27,   27,   27,    0,
           38,    0,    0,   38,   27,   38,   38,   38,   49,   49,
            0,   49,   49,    0,    0,    0,    0,    0,    0,    0,
           38,   38,   38,    0,   38,   38,    0,    0,    0,    0,
           49,    0,   32,   32,   32,   32,   32,   32,    0,   32,
           32,   39,    0,    0,   39,    0,   39,   39,   39,    0,
            0,    0,    0,    0,    0,   38,   38,    0,   32,    0,
            0,   39,   39,   39,    0,   39,   39,    0,   40,    0,
            0,   40,    0,   40,   40,   40,    0,    0,   41,    0,
            0,   41,    0,    0,   41,    0,   38,   38,   40,   40,
           40,    0,   40,   40,    0,    0,   39,   39,   41,   41,
           41,    0,   41,   41,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,   42,    0,    0,   42,    0,
            0,   42,    0,   40,   40,    0,   43,   39,   39,   43,
            0,    0,   43,   41,   41,   42,   42,   42,    0,   42,
           42,    0,    0,    0,    0,    0,   43,   43,   43,    0,
           43,   43,    0,   44,   40,   40,   44,    0,    0,   44,
            0,    0,    0,   47,   41,   41,   47,    0,    0,   47,
           42,   42,    0,   44,   44,   44,    0,   44,   44,    0,
            0,   43,   43,   47,   47,   47,    0,   47,   47,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
           48,   42,   42,   48,    0,    0,   48,    0,   44,   44,
            0,    0,   43,   43,    0,    0,    0,    0,   47,   47,
           48,   48,   48,    0,   48,   48,   38,   38,   38,   38,
           38,   38,    0,   38,   38,    0,    0,    0,    0,   44,
           44,    0,    0,    0,    0,    0,    0,    0,    0,   47,
           47,    0,   38,    0,    0,   48,   48,    0,    0,    0,
            0,    0,    0,   61,    0,    0,   61,   39,   39,   39,
           39,   39,   39,    0,   39,   39,    0,    0,    0,    0,
           61,   61,    0,    0,    0,   61,   48,   48,    0,    0,
            0,    0,    0,   39,   40,   40,   40,   40,   40,   40,
            0,   40,   40,    0,   41,   41,   41,   41,   41,   41,
            0,   41,   41,   45,    0,   61,   45,    0,    0,   45,
           40,    0,    0,    0,    0,    0,    0,    0,    0,    0,
           41,    0,    0,   45,   45,   45,    0,   45,   45,    0,
            0,   42,   42,   42,   42,   42,   42,   61,   42,   42,
            0,    0,   43,   43,   43,   43,   43,   43,    0,   43,
           43,    0,    0,    0,    0,    0,    0,   42,   45,   45,
            0,    0,    0,    0,    0,    0,    0,    0,   43,    0,
            0,   44,   44,   44,   44,    0,   44,   44,    0,    0,
            0,   47,   47,   47,   47,    0,   47,   47,   46,   45,
           45,   46,    0,    0,   46,   44,    0,    0,    0,    0,
            0,    0,    0,    0,    0,   47,    0,    0,   46,   46,
           46,   50,   46,   46,   50,    0,    0,   50,   48,   48,
           48,   48,    0,   48,   48,    0,    0,   51,    0,    0,
           51,   50,   50,   51,   52,    0,   50,   52,    0,    0,
           52,    0,   48,   46,   46,    0,    0,   51,   51,    0,
            0,    0,   51,    0,   52,   52,    0,    0,    0,   52,
            0,    0,    0,    0,    0,    0,   50,   50,    0,    0,
            0,    0,    0,    0,   46,   46,    0,   55,    0,    0,
           55,    0,   51,   51,   61,   53,    0,    0,   53,   52,
           52,   53,    0,    0,   55,   55,    0,   50,   50,   55,
           57,    0,   61,   57,    0,   53,   53,    0,    0,    0,
           53,    0,    0,   51,   51,    0,    0,   57,   57,    0,
           52,   52,   57,    0,    0,    0,    0,    0,    0,   55,
           55,   45,   45,   45,   45,    0,   45,   45,    0,    0,
           53,   53,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,   57,    0,    0,   45,    0,    0,    0,    0,
           55,   55,    0,    0,    0,    0,    0,    0,    0,    0,
            0,   53,   53,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,   57,   57,    0,    0,    0,    0,    1,
            1,    0,    1,    0,    1,    1,    1,    1,    1,    1,
          138,  139,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    1,    1,    1,    1,    1,   46,   46,   46,   46,
          101,   46,   46,    0,  103,   96,    0,   94,    0,   97,
           98,    0,   99,    0,  102,    0,    0,    0,    0,    0,
           46,   50,   50,    1,   50,   50,    1,  104,  108,  105,
            0,    0,    0,    0,    0,    0,    0,   51,   51,    0,
           51,   51,    0,   50,    0,    0,    0,   52,   52,    0,
            0,    0,    0,    0,    0,    0,    1,    0,   95,   51,
            0,  106,    0,    0,    0,    0,   52,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,  256,  139,   55,   55,    0,
            0,  107,    0,  100,    0,    0,    0,    0,   53,   53,
            0,    0,    0,    0,    0,    0,   55,    0,    0,    0,
           57,   57,   49,    0,    0,    0,    0,   53,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,   57,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
          133,  134,  135,  136,  137,    0,   50,    3,    4,    5,
            6,    7,    8,    9,   10,   11,   12,   13,   14,   15,
           16,   17,   18,   19,   20,   21,   22,   23,    0,    0,
           24,   25,   26,   27,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,    0,
            0,    0,    0,    1,    1,    1,    1,    1,    1,    1,
            1,    1,    0,    1,    1,    1,    1,    1,    1,    1,
            1,    1,    1,    1,    1,    1,    1,   49,    0,    0,
            0,    0,    0,    0,    0,    0,    0,    0,    0,   79,
           80,   81,   82,   83,   84,   85,   86,    0,   87,   88,
           89,   90,   91,   92,   93,  133,  134,  135,  136,  137,
            0,   50,    3,    4,    5,    6,    7,    8,    9,   10,
           11,   12,   13,   14,   15,   16,   17,   18,   19,   20,
           21,   22,   23,   62,    0,   24,   25,   26,   27,    0,
            3,    4,    5,    6,    7,    8,    9,   10,   11,   12,
           13,   14,   15,   16,   17,   18,   19,   20,   21,   22,
           23,   62,    0,   24,   25,   26,   27,  396,    3,    4,
            5,    6,    7,    8,    9,   10,   11,   12,   13,   14,
           15,   16,   17,   18,   19,   20,   21,   22,   23,   62,
            0,   24,   25,   26,   27,    0,    0,    0,    0,    0,
            0,    0,    9,   10,   11,   12,   13,   14,   15,   16,
           17,   18,   19,   20,   21,   22,   23,    0,    0,   24,
           25,   26,   27,
    ];

    protected static $yyCheck = [
                    53,
            0,  161,  121,   71,  225,   44,   40,   44,   44,   51,
           40,  130,  131,   63,   73,   42,  184,  353,   40,    0,
           59,   68,   59,   59,  125,   58,   52,   53,  155,   29,
          141,  163,   32,   33,   34,   35,  448,  273,   44,  123,
           59,   58,  192,  193,   40,  126,   58,  296,   29,   27,
           46,   46,   44,  257,   44,  205,  125,   44,   44,  125,
           38,  115,  130,  264,  265,  228,   61,   67,   67,   44,
          257,   71,  270,   44,   43,  117,   45,   58,   67,   44,
           60,  285,   62,  125,  257,   40,   67,  113,   59,  115,
          240,   53,  126,  152,  127,   91,   91,  285,  285,  189,
          190,  155,   40,  123,  123,  131,  123,  268,  269,  228,
          522,  125,   38,  155,   40,  148,   42,  161,  286,  125,
           38,  154,   40,  242,   42,   38,   40,   40,   93,   42,
          130,  123,  176,  177,  178,  125,   91,  137,  125,  125,
          251,   59,   44,  143,  143,   44,  127,  128,  192,  193,
          125,  270,  284,   91,  143,   40,  137,   59,  407,  408,
           59,  205,  143,   40,  190,   91,  335,  336,   38,  131,
           40,   41,   42,  257,   44,  511,  257,   91,  246,  306,
          125,  417,  332,  333,  334,  244,  125,  255,  257,   59,
          309,  257,  257,  353,  357,  314,  240,  287,   52,  289,
          126,  285,  292,  293,  294,   59,   91,  123,  126,  251,
          291,  330,  238,  126,   44,  269,  242,  264,  368,  320,
          321,   91,  272,  257,   33,  315,   61,  269,   58,   38,
          287,   40,  289,   42,   43,  126,   45,  126,  357,  389,
          270,  126,  361,  276,  363,  271,  246,  366,  270,  126,
           59,  290,  306,  290,  290,  255,  126,  291,  270,  113,
          350,  287,  412,  289,  298,  261,  262,  263,  270,  269,
           41,  298,  391,  307,  308,  394,  238,  277,  304,  305,
          307,  308,  260,  261,  262,  448,  266,  267,  332,  333,
          334,  335,  336,  337,  338,  339,  340,  341,  342,  343,
          344,  345,  346,  347,  348,  349,  320,  321,  476,  353,
          478,   61,  533,  534,  123,  296,  125,  126,   41,  482,
           93,   44,  472,   33,  368,  393,  486,  490,   38,  448,
           40,  257,   42,   43,  502,   45,  504,  363,   41,  257,
          459,  257,  510,   41,  257,  389,   44,  525,   33,   40,
          518,  511,  520,   38,  532,   40,   58,   42,   43,  522,
           45,  415,   58,  482,   41,  291,  190,   44,  412,  285,
           58,  490,  257,  291,   59,  320,  321,  285,  291,  123,
          257,  320,  321,   93,   40,   41,   41,  257,   44,  479,
          480,  481,   41,  393,  285,   44,  285,  337,  338,   44,
          291,  298,  291,  522,   61,   38,  291,   40,   41,   42,
          307,  308,  343,  344,  291,  415,  126,  271,   40,  273,
           38,  291,   40,   41,   42,  304,  305,   37,  472,   38,
          257,   40,   42,   42,   40,   91,   41,   47,  123,   44,
          125,  126,  486,  339,  340,  341,  342,   40,  257,  258,
          259,  260,   33,  262,  263,   40,   59,   38,   91,   40,
           59,   42,   43,  287,   45,  289,   41,  511,   41,   44,
          126,   44,   41,   91,   41,   44,  285,   44,   59,   38,
          304,  305,   91,  292,  293,  294,  295,  296,  297,  298,
          299,  300,  301,  302,  303,  304,  305,  306,  307,  308,
          309,  310,  311,  312,  313,  314,  315,  316,  317,  318,
          257,  320,  321,  322,   94,  324,  325,  326,  327,  328,
          329,  330,  331,  273,  274,  275,  276,  277,  278,  279,
          280,  281,  282,  283,  284,  124,  271,  270,  285,  286,
          287,  288,  123,   41,  125,  126,   44,  257,  258,  259,
          260,   41,  262,  263,   44,   41,   41,   33,   44,   44,
           41,   41,   38,   44,   40,   93,   42,   43,   41,   45,
          257,   44,  257,  258,  259,  260,   40,  262,  263,   61,
          325,  257,   59,   59,  294,   41,  257,  257,  298,  257,
           93,   41,   61,   41,   59,   41,   41,  307,  308,   41,
          285,  257,   93,  313,  314,   41,   41,  292,  293,  294,
          295,  296,  297,  298,  299,  300,  301,  302,  303,  304,
          305,  306,  307,  308,  309,  310,  311,  312,  313,  314,
          315,  316,  317,  318,   40,  291,   93,  322,   93,  324,
          325,  326,  327,  328,  329,  330,  331,  123,   59,   33,
          126,   41,   93,   93,   38,  123,   40,  123,   42,   43,
           59,   45,  323,   58,   41,   59,   58,   41,   41,  482,
          314,  345,  347,  346,  117,   59,  257,  258,  259,  260,
          349,  262,  263,  348,  383,   78,  277,   29,  514,  143,
           -1,  358,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,   -1,   40,   41,  285,   -1,   44,   -1,   -1,   -1,
           -1,  292,  293,  294,  295,  296,  297,  298,  299,  300,
          301,  302,  303,  304,  305,  306,  307,  308,  309,  310,
          311,  312,  313,  314,  315,  316,  317,  318,   -1,  123,
           -1,  322,  126,  324,  325,  326,  327,  328,  329,  330,
          331,   -1,   -1,   91,   33,   -1,   -1,   -1,   -1,   38,
           -1,   40,   -1,   42,   43,   -1,   45,   -1,   -1,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,  257,  258,  259,  260,   -1,  262,  263,  126,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,   33,   -1,   -1,   -1,   -1,   38,  285,
           40,   -1,   42,   43,   -1,   45,  292,  293,  294,  295,
          296,  297,  298,  299,  300,  301,  302,  303,  304,  305,
          306,  307,  308,  309,  310,  311,  312,  313,  314,  315,
          316,  317,  318,   -1,  123,   -1,  322,  126,  324,  325,
          326,  327,  328,  329,  330,  331,   -1,   -1,   -1,   -1,
           35,   -1,   -1,   93,   -1,   40,   41,   -1,   -1,   44,
           -1,   -1,   -1,  257,  258,  259,  260,   -1,  262,  263,
           -1,   -1,   -1,   -1,   59,   -1,   61,   -1,   -1,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,  126,   -1,   -1,   -1,
           -1,   35,   -1,   -1,   -1,   -1,   -1,   41,   -1,   -1,
           44,   -1,   -1,   -1,   -1,   -1,   91,   92,   -1,  257,
           -1,   -1,   -1,   -1,   -1,   59,   -1,   61,   -1,  313,
          314,   -1,   -1,   38,   -1,   40,   41,   42,  322,   44,
          324,  325,  326,  327,  328,  329,  330,  331,  123,   -1,
           35,   -1,   41,  291,   59,   44,   41,   -1,   92,   44,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   58,
           59,   -1,   -1,   -1,   59,   -1,   61,   -1,  257,  258,
          259,  260,   -1,  262,  263,   -1,   91,   -1,   -1,  123,
           -1,   35,   -1,   -1,   -1,   -1,   -1,   41,   -1,   -1,
           44,   -1,   -1,   -1,   93,   -1,   -1,   92,   -1,   -1,
           -1,   -1,   -1,   33,   -1,   59,   -1,   61,   38,   -1,
           40,  126,   42,   43,   -1,   45,   46,  257,  258,  259,
          260,   -1,  262,  263,  313,  314,  125,   -1,  123,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   92,   -1,
           35,   38,   -1,   40,   41,   42,   41,   44,   -1,   44,
           -1,   -1,   -1,   -1,  294,   -1,   -1,   -1,  298,   -1,
           -1,   91,   59,   -1,   59,   -1,   61,  307,  308,  123,
           -1,   -1,   -1,  313,  314,   -1,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,  123,   91,  125,  126,   92,   -1,   -1,
          285,   -1,   -1,   -1,   -1,  290,   -1,  292,  293,  294,
          295,  296,  297,  298,  299,  300,  301,  302,  303,  304,
          305,  306,  307,  308,  309,  310,  311,  312,  123,  126,
          315,  316,  317,  318,   59,   -1,   -1,   -1,   -1,   -1,
           -1,  285,  257,   -1,   -1,   -1,  290,  332,  292,  293,
          294,  295,  296,  297,  298,  299,  300,  301,  302,  303,
          304,  305,  306,  307,  308,  309,  310,  311,  312,   -1,
           -1,  315,  316,  317,  318,   33,  291,   -1,   -1,   -1,
           38,   -1,   40,   41,   42,   43,   -1,   45,  332,   -1,
          285,  290,   -1,   -1,   -1,  290,   -1,  292,  293,  294,
          295,  296,  297,  298,  299,  300,  301,  302,  303,  304,
          305,  306,  307,  308,  309,  310,  311,  312,   -1,   -1,
          315,  316,  317,  318,   -1,   -1,   -1,  257,  258,  259,
          260,  285,  262,  263,   -1,   -1,  290,  332,  292,  293,
          294,  295,  296,  297,  298,  299,  300,  301,  302,  303,
          304,  305,  306,  307,  308,  309,  310,  311,  312,   -1,
          257,  315,  316,  317,  318,   -1,   -1,   35,  126,   -1,
           -1,   -1,   -1,   -1,   41,   -1,   -1,   44,  332,   -1,
           -1,   -1,   -1,  313,  314,   -1,   -1,   -1,   -1,   -1,
          285,   58,   59,   61,  291,  290,   63,  292,  293,  294,
          295,  296,  297,  298,  299,  300,  301,  302,  303,  304,
          305,  306,  307,  308,  309,  310,  311,  312,   -1,   -1,
          315,  316,  317,  318,   92,   -1,   93,   -1,   -1,   -1,
           -1,   33,  257,   -1,   -1,   -1,   38,  332,   40,   -1,
           42,   43,   -1,   45,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,   -1,   -1,  123,   -1,   59,  125,   -1,
          285,   -1,   -1,   -1,   -1,   -1,   -1,  292,  293,  294,
          295,  296,  297,  298,  299,  300,  301,  302,  303,  304,
          305,  306,  307,  308,  309,  310,  311,  312,   -1,   -1,
          315,  316,  317,  318,   -1,   -1,   -1,   -1,   -1,  257,
          258,  259,  260,   33,  262,  263,   -1,   -1,   38,   -1,
           40,   -1,   42,   43,   -1,   45,   33,   -1,   -1,   -1,
           -1,   38,   -1,   40,  126,   42,   43,  285,   45,   46,
           -1,   -1,   -1,   -1,  292,  293,  294,  295,  296,  297,
          298,  299,  300,  301,  302,  303,  304,  305,  306,  307,
          308,  309,  310,  311,  312,  313,  314,  315,  316,  317,
          318,   41,   -1,   -1,   44,   -1,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,   -1,   91,   33,   -1,   -1,   58,   59,
           38,   -1,   40,   63,   42,   43,   -1,   45,   33,   -1,
           -1,   -1,   -1,   38,   -1,   40,  126,   42,   43,   -1,
           45,   46,   -1,   -1,  271,  272,  123,   -1,  125,  126,
           -1,   -1,   -1,   93,   -1,   -1,   -1,  285,   -1,   -1,
           -1,   -1,   -1,  290,  292,  293,  294,  295,  296,  297,
          298,  299,  300,  301,  302,  303,  304,  305,  306,  307,
          308,  309,  310,  311,  312,  125,   91,  315,  316,  317,
          318,   -1,   -1,   -1,   -1,  257,  258,  259,  260,   -1,
          262,  263,   -1,   -1,  332,   33,   -1,   -1,  126,   -1,
           38,   -1,   40,   -1,   42,   43,   -1,   45,  123,   -1,
           -1,  126,   -1,  285,   -1,   -1,   -1,   -1,   -1,   -1,
          292,  293,  294,  295,  296,  297,  298,  299,  300,  301,
          302,  303,  304,  305,  306,  307,  308,  309,  310,  311,
          312,  313,  314,  315,  316,  317,  318,   -1,   -1,   -1,
           -1,   -1,   -1,   -1,   -1,   93,   -1,  257,  258,  259,
          260,   -1,  262,  263,   -1,   -1,   -1,   -1,   -1,   -1,
          257,  258,  259,  260,   33,  262,  263,   -1,   -1,   38,
           -1,   40,   41,   42,   43,  285,   45,   -1,  126,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,  298,  299,
          300,  301,  302,  303,  304,  305,  306,  307,  308,  309,
          310,  311,  312,  313,  314,  315,  316,  317,  318,   -1,
           -1,  271,  272,   -1,   -1,   -1,  313,  314,   -1,  257,
          258,  259,  260,   -1,  262,  263,   -1,   -1,   -1,   -1,
          290,   -1,  257,  258,  259,  260,   33,  262,  263,   -1,
           -1,   38,   -1,   40,   33,   42,   43,   -1,   45,   38,
           -1,   40,   -1,   42,   43,   -1,   45,  126,   -1,   -1,
          298,   -1,   59,   -1,   -1,   -1,   -1,   -1,   -1,  307,
          308,   -1,   -1,   -1,   33,  313,  314,   -1,   -1,   38,
           -1,   40,   -1,   42,   43,   33,   45,   -1,  313,  314,
           38,   -1,   40,   -1,   42,   43,   -1,   45,   -1,   -1,
           -1,   -1,   -1,   -1,   93,   -1,   -1,   -1,   -1,  257,
          258,  259,  260,   33,  262,  263,   -1,   -1,   38,   -1,
           40,   33,   42,   43,   -1,   45,   38,   -1,   40,  126,
           42,   43,   -1,   45,   93,   -1,   -1,  126,   -1,   -1,
           -1,   -1,   -1,   33,   -1,   93,   -1,   59,   38,   -1,
           40,   -1,   42,   43,   -1,   45,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,   -1,   -1,  313,  314,  126,   -1,   -1,
           -1,   -1,   -1,   93,   -1,   -1,   -1,   -1,  126,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,  257,  258,
          259,  260,   33,  262,  263,   -1,   -1,   38,   -1,   40,
           -1,   42,   43,   -1,   45,   -1,  126,   -1,   -1,   -1,
           -1,   -1,   33,   -1,  126,   -1,   -1,   38,   -1,   40,
           33,   42,   43,   -1,   45,   38,   -1,   40,   41,   42,
           43,   -1,   45,  123,   -1,   -1,  126,   -1,   -1,   -1,
           -1,   33,   -1,   -1,  313,  314,   38,   -1,   40,   41,
           42,   43,   -1,   45,   -1,   -1,   -1,   -1,   -1,   -1,
          257,  258,  259,  260,   -1,  262,  263,   -1,  257,  258,
          259,  260,   93,  262,  263,   -1,   -1,   -1,   -1,   -1,
           -1,   33,  123,   -1,   -1,  126,   38,   -1,   40,   -1,
           42,   43,   -1,   45,   -1,   -1,   -1,   -1,  257,  258,
          259,  260,   -1,  262,  263,  126,   -1,   -1,   -1,  257,
          258,  259,  260,  126,  262,  263,  313,  314,   -1,   -1,
           -1,   33,   -1,   -1,  313,  314,   38,   -1,   40,   -1,
           42,   43,   -1,   45,  126,   -1,   -1,  257,  258,  259,
          260,   -1,  262,  263,   -1,  257,  258,  259,  260,   -1,
          262,  263,   -1,   -1,  313,  314,   -1,   -1,   -1,   41,
           -1,   -1,   44,   -1,   -1,  313,  314,  257,  258,  259,
          260,   -1,  262,  263,  126,   -1,   58,   59,   -1,   -1,
           -1,   63,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,  313,  314,   -1,   -1,   -1,   33,   -1,
           -1,  313,  314,   38,   -1,   40,   -1,   42,   43,   -1,
           45,   93,   -1,   -1,  126,   -1,  257,  258,  259,  260,
           -1,  262,  263,  313,  314,   -1,   -1,   38,   -1,   40,
           41,   42,   -1,   44,   -1,   -1,  257,  258,  259,  260,
           -1,  262,  263,  125,  257,  258,  259,  260,   59,  262,
          263,   -1,   -1,   -1,   -1,   -1,   38,   -1,   40,   41,
           42,   -1,   44,   -1,   -1,  257,  258,  259,  260,   -1,
          262,  263,  313,  314,   -1,   -1,   -1,   59,   -1,   -1,
           91,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,  126,  313,  314,   -1,   -1,   -1,   -1,   -1,   -1,
          313,  314,   -1,   -1,   -1,  257,  258,  259,  260,   91,
          262,  263,   -1,   41,   -1,  126,   44,   -1,   -1,   -1,
           -1,  313,  314,   38,   -1,   40,   41,   42,   -1,   -1,
           58,   59,   -1,   -1,   -1,   63,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,   -1,  126,  257,  258,  259,  260,   -1,
          262,  263,   38,   -1,   40,   41,   42,   -1,   44,   -1,
           -1,  313,  314,   -1,   -1,   93,   94,   -1,   -1,   -1,
           -1,   -1,   -1,   59,   -1,   -1,   91,   -1,   -1,   38,
           -1,   40,   -1,   42,   -1,   -1,   -1,   -1,   -1,   -1,
          272,   -1,   -1,   -1,   -1,   -1,  124,  125,   -1,   -1,
           59,  313,  314,   -1,   -1,   91,   -1,   -1,  290,   -1,
           -1,  126,   -1,   -1,   -1,   -1,   -1,   41,   -1,   -1,
           -1,   -1,  257,  258,  259,  260,   -1,  262,  263,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
          126,   -1,   -1,   38,   -1,   -1,  257,   42,   -1,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,   -1,   59,   -1,   -1,  126,   -1,   -1,
           -1,   -1,   -1,   -1,  285,  257,   -1,   -1,  313,  314,
          291,  292,  293,  294,  295,  296,  297,  298,  299,  300,
          301,  302,  303,  304,  305,  306,  307,  308,  309,  310,
          311,  312,   41,  285,  315,  316,  317,  318,   -1,  291,
          292,  293,  294,  295,  296,  297,  298,  299,  300,  301,
          302,  303,  304,  305,  306,  307,  308,  309,  310,  311,
          312,  126,   -1,  315,  316,  317,  318,   -1,   -1,   -1,
           -1,   -1,  257,  271,  272,   -1,   38,   -1,   40,   41,
           42,   -1,   -1,   -1,   -1,   -1,  123,   -1,   -1,   -1,
           -1,   -1,  290,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
          285,  257,   -1,   -1,   -1,   -1,  291,  292,  293,  294,
          295,  296,  297,  298,  299,  300,  301,  302,  303,  304,
          305,  306,  307,  308,  309,  310,  311,  312,  257,   91,
          315,  316,  317,  318,   41,  291,   -1,   -1,   -1,   -1,
           -1,  270,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,  285,   -1,   -1,   -1,
           -1,   -1,  291,  292,  293,  294,  295,  296,  297,  298,
          299,  300,  301,  302,  303,  304,  305,  306,  307,  308,
          309,  310,  311,  312,   41,   -1,  315,  316,  317,  318,
           -1,  285,  257,   -1,   -1,   -1,   -1,   -1,  292,  293,
          294,  295,  296,  297,  298,  299,  300,  301,  302,  303,
          304,  305,  306,  307,  308,  309,  310,  311,  312,   -1,
          285,  315,  316,  317,  318,   -1,  291,  292,  293,  294,
          295,  296,  297,  298,  299,  300,  301,  302,  303,  304,
          305,  306,  307,  308,  309,  310,  311,  312,  285,   -1,
          315,  316,  317,  318,   -1,  292,  293,  294,  295,  296,
          297,  298,  299,  300,  301,  302,  303,  304,  305,  306,
          307,  308,  309,  310,  311,  312,  285,   -1,  315,  316,
          317,  318,   -1,  292,  293,  294,  295,  296,  297,  298,
          299,  300,  301,  302,  303,  304,  305,  306,  307,  308,
          309,  310,  311,  312,   -1,   -1,  315,  316,  317,  318,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,  285,   -1,   -1,   -1,   -1,   -1,   -1,
          292,  293,  294,  295,  296,  297,  298,  299,  300,  301,
          302,  303,  304,  305,  306,  307,  308,  309,  310,  311,
          312,   -1,   -1,  315,  316,  317,  318,   -1,   -1,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,  285,   -1,
           -1,   -1,   -1,   -1,   -1,  292,  293,  294,  295,  296,
          297,  298,  299,  300,  301,  302,  303,  304,  305,  306,
          307,  308,  309,  310,  311,  312,   -1,   -1,  315,  316,
          317,  318,   41,   -1,   -1,   44,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,  285,   58,
           59,   -1,   -1,   -1,   63,  292,  293,  294,  295,  296,
          297,  298,  299,  300,  301,  302,  303,  304,  305,  306,
          307,  308,  309,  310,  311,  312,   -1,   -1,  315,  316,
          317,  318,   37,   38,   93,   40,   41,   42,   43,   44,
           45,   46,   47,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,   58,   59,   60,   61,   62,   63,   -1,
           -1,   -1,   -1,   -1,   -1,  124,  125,   37,   38,   -1,
           40,   -1,   42,   43,   44,   45,   46,   47,   -1,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,   91,   -1,   93,   94,
           60,   61,   62,   63,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,   -1,   37,   38,   -1,   -1,   41,   42,
           43,   44,   45,   -1,   47,   -1,   -1,   -1,   -1,  124,
          125,   91,   -1,   -1,   94,   58,   59,   60,   61,   62,
           63,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,   37,   38,   -1,   -1,   41,   42,   43,   44,   45,
           -1,   47,   -1,   -1,  124,   -1,   -1,   -1,   -1,   -1,
           93,   94,   58,   59,   60,   61,   62,   63,   38,   -1,
           -1,   41,   -1,   -1,   44,   -1,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   58,   59,
           -1,  124,  125,   63,   -1,   37,   38,   93,   94,   41,
           42,   43,   44,   45,   -1,   47,   -1,   -1,   -1,   -1,
           -1,   -1,  271,  272,   -1,   -1,   58,   59,   60,   -1,
           62,   63,   -1,   93,   94,   -1,   -1,   -1,  124,  125,
           -1,  290,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,   93,   94,   -1,  124,  125,  261,  262,  263,  264,
          265,  266,  267,  268,  269,   -1,  271,  272,  273,  274,
          275,  276,  277,  278,  279,  280,  281,  282,  283,  284,
           -1,   -1,  124,  125,   -1,  290,   -1,   -1,   -1,   -1,
           -1,  261,  262,  263,  264,  265,  266,  267,  268,  269,
           -1,  271,  272,  273,  274,  275,  276,  277,  278,  279,
          280,  281,  282,  283,  284,   -1,   -1,   -1,   -1,   -1,
          290,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,  264,  265,  266,  267,  268,  269,   -1,  271,  272,
          273,  274,  275,  276,  277,  278,  279,  280,  281,  282,
          283,  284,   -1,   -1,   -1,   -1,   -1,  290,   -1,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,  264,  265,
          266,  267,  268,  269,   -1,  271,  272,  273,  274,  275,
          276,  277,  278,  279,  280,  281,  282,  283,  284,   -1,
           38,   -1,   -1,   41,  290,   43,   44,   45,  268,  269,
           -1,  271,  272,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           58,   59,   60,   -1,   62,   63,   -1,   -1,   -1,   -1,
          290,   -1,  264,  265,  266,  267,  268,  269,   -1,  271,
          272,   38,   -1,   -1,   41,   -1,   43,   44,   45,   -1,
           -1,   -1,   -1,   -1,   -1,   93,   94,   -1,  290,   -1,
           -1,   58,   59,   60,   -1,   62,   63,   -1,   38,   -1,
           -1,   41,   -1,   43,   44,   45,   -1,   -1,   38,   -1,
           -1,   41,   -1,   -1,   44,   -1,  124,  125,   58,   59,
           60,   -1,   62,   63,   -1,   -1,   93,   94,   58,   59,
           60,   -1,   62,   63,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,   -1,   -1,   38,   -1,   -1,   41,   -1,
           -1,   44,   -1,   93,   94,   -1,   38,  124,  125,   41,
           -1,   -1,   44,   93,   94,   58,   59,   60,   -1,   62,
           63,   -1,   -1,   -1,   -1,   -1,   58,   59,   60,   -1,
           62,   63,   -1,   38,  124,  125,   41,   -1,   -1,   44,
           -1,   -1,   -1,   38,  124,  125,   41,   -1,   -1,   44,
           93,   94,   -1,   58,   59,   60,   -1,   62,   63,   -1,
           -1,   93,   94,   58,   59,   60,   -1,   62,   63,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           38,  124,  125,   41,   -1,   -1,   44,   -1,   93,   94,
           -1,   -1,  124,  125,   -1,   -1,   -1,   -1,   93,   94,
           58,   59,   60,   -1,   62,   63,  264,  265,  266,  267,
          268,  269,   -1,  271,  272,   -1,   -1,   -1,   -1,  124,
          125,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,  124,
          125,   -1,  290,   -1,   -1,   93,   94,   -1,   -1,   -1,
           -1,   -1,   -1,   41,   -1,   -1,   44,  264,  265,  266,
          267,  268,  269,   -1,  271,  272,   -1,   -1,   -1,   -1,
           58,   59,   -1,   -1,   -1,   63,  124,  125,   -1,   -1,
           -1,   -1,   -1,  290,  264,  265,  266,  267,  268,  269,
           -1,  271,  272,   -1,  264,  265,  266,  267,  268,  269,
           -1,  271,  272,   38,   -1,   93,   41,   -1,   -1,   44,
          290,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
          290,   -1,   -1,   58,   59,   60,   -1,   62,   63,   -1,
           -1,  264,  265,  266,  267,  268,  269,  125,  271,  272,
           -1,   -1,  264,  265,  266,  267,  268,  269,   -1,  271,
          272,   -1,   -1,   -1,   -1,   -1,   -1,  290,   93,   94,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,  290,   -1,
           -1,  266,  267,  268,  269,   -1,  271,  272,   -1,   -1,
           -1,  266,  267,  268,  269,   -1,  271,  272,   38,  124,
          125,   41,   -1,   -1,   44,  290,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,   -1,   -1,  290,   -1,   -1,   58,   59,
           60,   38,   62,   63,   41,   -1,   -1,   44,  266,  267,
          268,  269,   -1,  271,  272,   -1,   -1,   38,   -1,   -1,
           41,   58,   59,   44,   38,   -1,   63,   41,   -1,   -1,
           44,   -1,  290,   93,   94,   -1,   -1,   58,   59,   -1,
           -1,   -1,   63,   -1,   58,   59,   -1,   -1,   -1,   63,
           -1,   -1,   -1,   -1,   -1,   -1,   93,   94,   -1,   -1,
           -1,   -1,   -1,   -1,  124,  125,   -1,   41,   -1,   -1,
           44,   -1,   93,   94,  272,   38,   -1,   -1,   41,   93,
           94,   44,   -1,   -1,   58,   59,   -1,  124,  125,   63,
           41,   -1,  290,   44,   -1,   58,   59,   -1,   -1,   -1,
           63,   -1,   -1,  124,  125,   -1,   -1,   58,   59,   -1,
          124,  125,   63,   -1,   -1,   -1,   -1,   -1,   -1,   93,
           94,  266,  267,  268,  269,   -1,  271,  272,   -1,   -1,
           93,   94,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,   -1,   93,   -1,   -1,  290,   -1,   -1,   -1,   -1,
          124,  125,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,  124,  125,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,  124,  125,   -1,   -1,   -1,   -1,   37,
           38,   -1,   40,   -1,   42,   43,   44,   45,   46,   47,
          125,  126,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,   59,   60,   61,   62,   63,  266,  267,  268,  269,
           33,  271,  272,   -1,   37,   38,   -1,   40,   -1,   42,
           43,   -1,   45,   -1,   47,   -1,   -1,   -1,   -1,   -1,
          290,  268,  269,   91,  271,  272,   94,   60,   61,   62,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,  268,  269,   -1,
          271,  272,   -1,  290,   -1,   -1,   -1,  271,  272,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,  124,   -1,   91,  290,
           -1,   94,   -1,   -1,   -1,   -1,  290,   -1,   -1,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,   -1,   -1,  125,  126,  271,  272,   -1,
           -1,  124,   -1,  126,   -1,   -1,   -1,   -1,  271,  272,
           -1,   -1,   -1,   -1,   -1,   -1,  290,   -1,   -1,   -1,
          271,  272,  257,   -1,   -1,   -1,   -1,  290,   -1,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,  290,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
          285,  286,  287,  288,  289,   -1,  291,  292,  293,  294,
          295,  296,  297,  298,  299,  300,  301,  302,  303,  304,
          305,  306,  307,  308,  309,  310,  311,  312,   -1,   -1,
          315,  316,  317,  318,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,
           -1,   -1,   -1,  261,  262,  263,  264,  265,  266,  267,
          268,  269,   -1,  271,  272,  273,  274,  275,  276,  277,
          278,  279,  280,  281,  282,  283,  284,  257,   -1,   -1,
           -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,   -1,  262,
          263,  264,  265,  266,  267,  268,  269,   -1,  271,  272,
          273,  274,  275,  276,  277,  285,  286,  287,  288,  289,
           -1,  291,  292,  293,  294,  295,  296,  297,  298,  299,
          300,  301,  302,  303,  304,  305,  306,  307,  308,  309,
          310,  311,  312,  285,   -1,  315,  316,  317,  318,   -1,
          292,  293,  294,  295,  296,  297,  298,  299,  300,  301,
          302,  303,  304,  305,  306,  307,  308,  309,  310,  311,
          312,  285,   -1,  315,  316,  317,  318,  319,  292,  293,
          294,  295,  296,  297,  298,  299,  300,  301,  302,  303,
          304,  305,  306,  307,  308,  309,  310,  311,  312,  285,
           -1,  315,  316,  317,  318,   -1,   -1,   -1,   -1,   -1,
           -1,   -1,  298,  299,  300,  301,  302,  303,  304,  305,
          306,  307,  308,  309,  310,  311,  312,   -1,   -1,  315,
          316,  317,  318,
    ];

    // Reduction action helper methods

    protected function case_18()
    {
        {
		$l = [];
		$l->Add($yyVals[$yyTop+0]);
		$yyVal = $l;
	}
    }

    protected function case_19()
    {
        {
		$l = $yyVals[$yyTop+-2];
		$l->Add($yyVals[$yyTop+0]);
		$yyVal = $l;
	}
    }

    protected function case_65()
    {
        {
		if (($r = $yyVals[$yyTop+-1]) instanceof RelationalOp && r == \parse\RelationalOp::Equals()) {
			$yyVal = new \parse\AssignExpression($yyVals[$yyTop+-2], $yyVals[$yyTop+0]);
		}
		else if (($b = $yyVals[$yyTop+-1]) instanceof Binop) {
			$left = $yyVals[$yyTop+-2];
			$yyVal = new \parse\AssignExpression($left, new \parse\BinaryExpression($left, b, $yyVals[$yyTop+0]));
		}
        else if (($l = $yyVals[$yyTop+-1]) instanceof LogicOp) {
            $left = $yyVals[$yyTop+-2];
            $yyVal = new \parse\AssignExpression($left, new \parse\LogicExpression($left, l, $yyVals[$yyTop+0]));
        }
        else {
            throw new \RuntimeException(sprintf("'{0}' not supported", $yyVals[$yyTop+-1]));
        }
	}
    }

    protected function case_83()
    {
        {
		$ds = $yyVals[$yyTop+-2];
		$decls = $yyVals[$yyTop+-1];
		$yyVal = new \parse\MultiDeclaratorStatement($ds, $decls);
	}
    }

    protected function case_84()
    {
        {
		$ds = new \parse\DeclarationSpecifiers();
		$ds->StorageClassSpecifier = $yyVals[$yyTop+0];
		$yyVal = $ds;
	}
    }

    protected function case_85()
    {
        {
		$ds = $yyVals[$yyTop+0];
		$ds->StorageClassSpecifier = $ds->StorageClassSpecifier | $yyVals[$yyTop+-1];		
		$yyVal = $ds;
	}
    }

    protected function case_86()
    {
        {
		$ds = new \parse\DeclarationSpecifiers();
		$ds->TypeSpecifiers->Add($yyVals[$yyTop+0]);
		$yyVal = $ds;
	}
    }

    protected function case_87()
    {
        {
		$ds = $yyVals[$yyTop+0];
		$ds->TypeSpecifiers->Add($yyVals[$yyTop+-1]);
		$yyVal = $ds;
	}
    }

    protected function case_88()
    {
        {
		$ds = new \parse\DeclarationSpecifiers();
		$ds->TypeQualifiers = $yyVals[$yyTop+0];
		$yyVal = $ds;
	}
    }

    protected function case_89()
    {
        {
		$ds = $yyVals[$yyTop+0];
		$ds->TypeQualifiers = $yyVals[$yyTop+-1];
		$yyVal = $ds;
	}
    }

    protected function case_90()
    {
        {
		$ds = new \parse\DeclarationSpecifiers();
		$ds->FunctionSpecifier = $yyVals[$yyTop+0];
		$yyVal = $ds;
	}
    }

    protected function case_91()
    {
        {
		$ds = $yyVals[$yyTop+0];
		$ds->FunctionSpecifier = $yyVals[$yyTop+-1];
		$yyVal = $ds;
	}
    }

    protected function case_92()
    {
        {
		$idl = [];
		$idl->Add($yyVals[$yyTop+0]);
		$yyVal = $idl;
	}
    }

    protected function case_93()
    {
        {
		$idl = $yyVals[$yyTop+-2];
		$idl->Add($yyVals[$yyTop+0]);
		$yyVal = $idl;
	}
    }

    protected function case_119()
    {
        {
		$ts = new \parse\TypeSpecifier($yyVals[$yyTop+-4], ($yyVals[$yyTop+-3]), $yyVals[$yyTop+0]);
		$ts->BaseSpecifiers = $yyVals[$yyTop+-1];
		$yyVal = $ts;
	}
    }

    protected function case_123()
    {
        {
		($yyVals[$yyTop+-2])->Add($yyVals[$yyTop+0]);
		$yyVal = $yyVals[$yyTop+-2];
	}
    }

    protected function case_131()
    {
        {
        ($yyVals[$yyTop+0])->TypeSpecifiers->Add ($yyVals[$yyTop+-1]);
        $yyVal = $yyVals[$yyTop+0];
    }
    }

    protected function case_132()
    {
        {
        $list = new \parse\DeclarationSpecifiers();
        $list->TypeSpecifiers->Add ($yyVals[$yyTop+0]);
        $yyVal = $list;
    }
    }

    protected function case_133()
    {
        {
        ($yyVals[$yyTop+0])->TypeQualifiers = ($yyVals[$yyTop+0])->TypeQualifiers | ($yyVals[$yyTop+-1]);
        $yyVal = $yyVals[$yyTop+0];
    }
    }

    protected function case_134()
    {
        {
        $list = new \parse\DeclarationSpecifiers();
        $list->TypeQualifiers = $yyVals[$yyTop+0];
        $yyVal = $list;
    }
    }

    protected function case_140()
    {
        {
        $l = new \parse\Block(\parse\VariableScope::Global());
        $l->AddStatement($yyVals[$yyTop+0]);
        $yyVal = $l;
    }
    }

    protected function case_141()
    {
        {
        $l = $yyVals[$yyTop+-2];
        $l->AddStatement($yyVals[$yyTop+0]);
        $yyVal = $l;
    }
    }

    protected function case_186()
    {
        {
		$d = $yyVals[$yyTop+-1];
		$f = $this->fixPointerAndArrayPrecedence($d);
		if ($f != null) {
			$yyVal = $f;
		}
		else {
			$d->StrongBinding = true;
			$yyVal = $d;
		}		
	}
    }

    protected function case_196()
    {
        {
		$d = new \parse\FunctionDeclarator( $yyVals[$yyTop+-3], []);
		foreach ($yyVals[$yyTop+-1] as $n) {
			$d->Parameters->Add(new \parse\ParameterDeclaration( n));
		}
		$yyVal = $d;
	}
    }

    protected function case_208()
    {
        {
		$l = $yyVals[$yyTop+-2];
		$l->Add(new \parse\VarParameter());
		$yyVal = $l;
	}
    }

    protected function case_209()
    {
        {
		$l = [];
		$l->Add($yyVals[$yyTop+0]);
		$yyVal = $l;
	}
    }

    protected function case_210()
    {
        {
		$l = $yyVals[$yyTop+-2];
		$l->Add($yyVals[$yyTop+0]);
		$yyVal = $l;
	}
    }

    protected function case_221()
    {
        {
		$d = $yyVals[$yyTop+-1];
		$f = $this->fixPointerAndArrayPrecedence($d);
		if ($f != null) {
			$yyVal = $f;
		}
		else {
			$d->StrongBinding = true;
			$yyVal = $d;
		}		
	}
    }

    protected function case_235()
    {
        {
		$l = new \parse\StructuredInitializer();
		$i = $yyVals[$yyTop+0];
		$l->Add($i);
		$yyVal = $l;
	}
    }

    protected function case_236()
    {
        {
		$l = new \parse\StructuredInitializer();
		$i = $yyVals[$yyTop+0];
		$i->Designation = $yyVals[$yyTop+-1];
		$l->Add($i);
		$yyVal = $l;
	}
    }

    protected function case_237()
    {
        {
		$l = $yyVals[$yyTop+-2];
		$i = $yyVals[$yyTop+0];
		$l->Add($i);
		$yyVal = $l;
	}
    }

    protected function case_238()
    {
        {
		$l = $yyVals[$yyTop+-3];
		$i = $yyVals[$yyTop+0];
		$i->Designation = $yyVals[$yyTop+-1];
		$l->Add($i);
		$yyVal = $l;
	}
    }

    protected function case_265()
    {
        {
		$fdecl = $yyVals[$yyTop+-1];
		$yyVal = new \parse\FunctionDefinition(
			new \parse\DeclarationSpecifiers(),
			$fdecl,
			null,
			$yyVals[$yyTop+0]);
	}
    }

    protected function case_267()
    {
        {
		$inner = new \parse\MultiDeclaratorStatement($yyVals[$yyTop+-3], $yyVals[$yyTop+-2]);
		$yyVal = new \parse\VirtualDeclarationStatement($inner) ;
	}
    }

    protected function case_268()
    {
        {
		$inner = new \parse\MultiDeclaratorStatement($yyVals[$yyTop+-3], $yyVals[$yyTop+-2]);
		$yyVal = new \parse\VirtualDeclarationStatement($inner) ;
	}
    }

    protected function case_270()
    {
        {
		$decls = [
			new \parse\InitDeclarator($yyVals[$yyTop+-3], null) ];
		$inner = new \parse\MultiDeclaratorStatement($yyVals[$yyTop+-4], $decls);
		$yyVal = new \parse\VirtualDeclarationStatement($inner) ;
	}
    }

    protected function case_279()
    {
        {
		$fdecl = $yyVals[$yyTop+-1];
		$ds = new \parse\DeclarationSpecifiers();
		$decls = [
			new \parse\InitDeclarator($fdecl, null) ];
		$yyVal = new \parse\MultiDeclaratorStatement($ds, $decls);
	}
    }

    protected function case_287()
    {
        {
		($yyVals[$yyTop+-1])->Add($yyVals[$yyTop+0]);
		$yyVal = $yyVals[$yyTop+-1];
	}
    }

    protected function case_301()
    {
        {
		$this->addDeclaration($yyVals[$yyTop+0]);
		$yyVal = _tu;
	}
    }

    protected function case_302()
    {
        {
		$this->addDeclaration($yyVals[$yyTop+0]);
		$yyVal = _tu;
	}
    }

    protected function case_307()
    {
        {
		$f = new \parse\FunctionDefinition(
			$yyVals[$yyTop+-3],
			$yyVals[$yyTop+-2],
			$yyVals[$yyTop+-1],
			$yyVals[$yyTop+0]);
		$yyVal = $f;
	}
    }

    protected function case_308()
    {
        {
		$f = new \parse\FunctionDefinition(
			$yyVals[$yyTop+-2],
			$yyVals[$yyTop+-1],
			null,
			$yyVals[$yyTop+0]);
		$yyVal = $f;
	}
    }

    protected function case_316()
    {
        {
		$d = new \parse\FunctionDeclarator( $yyVals[$yyTop+-3], []);
		$yyVal = new \parse\FunctionDefinition(
			new \parse\DeclarationSpecifiers(),
			$d,
			null,
			$yyVals[$yyTop+0]);
	}
    }

    protected function case_317()
    {
        {
		$d = new \parse\FunctionDeclarator( $yyVals[$yyTop+-4], $yyVals[$yyTop+-2]);
		$yyVal = new \parse\FunctionDefinition(
			new \parse\DeclarationSpecifiers(),
			$d,
			null,
			$yyVals[$yyTop+0]);
	}
    }

    protected function case_318()
    {
        {
		$l = [];
		$l->Add($yyVals[$yyTop+0]);
		$yyVal = $l;
	}
    }

    protected function case_319()
    {
        {
        $l = [];
        $l->Add($yyVals[$yyTop+0]);
        $yyVal = $l;
    }
    }

    protected function case_320()
    {
        {
		$l = $yyVals[$yyTop+-1];
		$l->Add($yyVals[$yyTop+0]);
		$yyVal = $l;
	}
    }

    public function parseTranslationUnit($report, string $name, $include, array ...$tokens)
    {
        $preprocessor = new \parse\Preprocessor($include, $report, ...$tokens);
        $this->lexer = new \parse\ParserInput($preprocessor->preprocess());

        $this->_tu = new \parse\TranslationUnit($name);

        if (count($this->lexer->Tokens) === 0)
            return $this->_tu;

        try {
            $this->yyparse($this->lexer);
        } catch (\RuntimeException $err) {
            $msg = $err->getMessage();
            if ($msg === 'irrecoverable syntax error') {
                $report->error(1001, $this->lexer->getCurrentToken()->Location, $this->lexer->getCurrentToken()->EndLocation, "Syntax error");
            } else {
                $report->error(9000, $this->lexer->getCurrentToken()->Location, $this->lexer->getCurrentToken()->EndLocation, "Parser Error: " . $msg);
            }
        } catch (\parse\yyUnexpectedEof $err) {
            $report->error(1513, $this->lexer->getCurrentToken()->Location, $this->lexer->getCurrentToken()->EndLocation, "Incomplete");
        } catch (\Exception $err) {
            $report->error(9000, $this->lexer->getCurrentToken()->Location, $this->lexer->getCurrentToken()->EndLocation, "Parser Error: " . $err->getMessage());
        }

        return $this->_tu;
    }

    protected function addDeclaration($a): void
    {
        if (!($a instanceof \parse\Statement)) return;
        $this->_tu->addStatement($a);

        if ($a instanceof \parse\MultiDeclaratorStatement) {
            $mds = $a;
            if ($mds->Specifiers->StorageClassSpecifier === \parse\StorageClassSpecifier::Typedef() && $mds->InitDeclarators !== null) {
                foreach ($mds->InitDeclarators as $i) {
                    $this->lexer->addTypedef($i->Declarator->DeclaredIdentifier);
                }
            } elseif ($mds->Specifiers->StorageClassSpecifier === \parse\StorageClassSpecifier::None() && count($mds->Specifiers->TypeSpecifiers) > 0) {
                foreach ($mds->Specifiers->TypeSpecifiers as $i) {
                    if ($i->Kind === \parse\TypeSpecifierKind::Class() ||
                        $i->Kind === \parse\TypeSpecifierKind::Struct() ||
                        $i->Kind === \parse\TypeSpecifierKind::Union() ||
                        $i->Kind === \parse\TypeSpecifierKind::Enum()) {
                        $this->lexer->addTypedef($i->Name);
                    }
                }
            }
        }
    }

    protected function fixPointerAndArrayPrecedence($d)
    {
        if ($d instanceof \parse\PointerDeclarator && $d->InnerDeclarator !== null && $d->InnerDeclarator instanceof \parse\ArrayDeclarator) {
            $a = $d->InnerDeclarator;
            $p = $d;
            $i = $a->InnerDeclarator;
            $a->InnerDeclarator = $p;
            $p->InnerDeclarator = $i;
            return $a;
        }
        return null;
    }

    protected function makeArrayDeclarator($left, $tq, $len, bool $isStatic)
    {
        if ($left !== null && $left->StrongBinding) {
            $i = $left->InnerDeclarator;
            $a = new \parse\ArrayDeclarator($i, $len);
            $left->InnerDeclarator = $a;
            return $left;
        }
        return new \parse\ArrayDeclarator($left, $len);
    }

    protected function getLocation($obj)
    {
        return \parse\Location::Null();
    }

    public static function tryParseExpression(\parse\Report $report, array $tokens)
    {
        $p = new self();
        $prefix = [new \parse\Token(\parse\TokenKind::AUTO, "auto"), new \parse\Token(\parse\TokenKind::IDENTIFIER, "_"), new \parse\Token('=')];
        $suffix = [new \parse\Token(';')];
        $tu = $p->parseTranslationUnit($report, \parse\CLanguageService::DefaultCodePath, function($a, $b) { return null; }, $prefix, $tokens, $suffix);
        $stmts = $tu->Statements;
        if (count($stmts) > 0 && $stmts[0] instanceof \parse\MultiDeclaratorStatement) {
            $mds = $stmts[0];
            if ($mds->InitDeclarators !== null && count($mds->InitDeclarators) === 1 && $mds->InitDeclarators[0]->Initializer instanceof \parse\ExpressionInitializer) {
                return $mds->InitDeclarators[0]->Initializer->Expression;
            }
        }
        return null;
    }
}