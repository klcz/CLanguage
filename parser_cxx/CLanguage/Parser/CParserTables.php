<?php declare(strict_types=1);

namespace CLanguage\Parser;

require_once __DIR__ . '/cparser_table_gen.php';

use CLanguage\Compiler;
use CLanguage\Syntax\AddressOfExpression;
use CLanguage\Syntax\ArrayElementExpression;
use CLanguage\Syntax\AssignExpression;
use CLanguage\Syntax\BaseSpecifier;
use CLanguage\Syntax\BinaryExpression;
use CLanguage\Syntax\Binop;
use CLanguage\Syntax\Block;
use CLanguage\Syntax\BreakStatement;
use CLanguage\Syntax\CastExpression;
use CLanguage\Syntax\ConditionalExpression;
use CLanguage\Syntax\ConstantExpression;
use CLanguage\Syntax\ContinueStatement;
use CLanguage\Syntax\DeclarationSpecifiers;
use CLanguage\Syntax\DeclarationsVisibility;
use CLanguage\Syntax\DereferenceExpression;
use CLanguage\Syntax\EnumeratorStatement;
use CLanguage\Syntax\ExpressionInitializer;
use CLanguage\Syntax\ExpressionStatement;
use CLanguage\Syntax\ForStatement;
use CLanguage\Syntax\FuncallExpression;
use CLanguage\Syntax\FunctionDeclarator;
use CLanguage\Syntax\FunctionDefinition;
use CLanguage\Syntax\FunctionSpecifier;
use CLanguage\Syntax\GotoStatement;
use CLanguage\Syntax\IdentifierDeclarator;
use CLanguage\Syntax\IfStatement;
use CLanguage\Syntax\InitDeclarator;
use CLanguage\Syntax\InitializerDesignation;
use CLanguage\Syntax\LabeledStatement;
use CLanguage\Syntax\LogicExpression;
use CLanguage\Syntax\LogicOp;
use CLanguage\Syntax\MemberFromPointerExpression;
use CLanguage\Syntax\MemberFromReferenceExpression;
use CLanguage\Syntax\MultiDeclaratorStatement;
use CLanguage\Syntax\ParameterDeclaration;
use CLanguage\Syntax\Pointer;
use CLanguage\Syntax\PointerDeclarator;
use CLanguage\Syntax\ReferenceDeclarator;
use CLanguage\Syntax\RelationalExpression;
use CLanguage\Syntax\RelationalOp;
use CLanguage\Syntax\ReturnStatement;
use CLanguage\Syntax\ScopeResolutionExpression;
use CLanguage\Syntax\SequenceExpression;
use CLanguage\Syntax\SizeOfExpression;
use CLanguage\Syntax\SizeOfTypeExpression;
use CLanguage\Syntax\StorageClassSpecifier;
use CLanguage\Syntax\StructuredInitializer;
use CLanguage\Syntax\SwitchCase;
use CLanguage\Syntax\SwitchStatement;
use CLanguage\Syntax\TypeName;
use CLanguage\Syntax\TypeQualifiers;
use CLanguage\Syntax\TypeSpecifier;
use CLanguage\Syntax\TypeSpecifierKind;
use CLanguage\Syntax\UnaryExpression;
use CLanguage\Syntax\Unop;
use CLanguage\Syntax\VariableExpression;
use CLanguage\Syntax\VarParameter;
use CLanguage\Syntax\VirtualDeclarationStatement;
use CLanguage\Syntax\VisibilityStatement;
use CLanguage\Syntax\WhileStatement;
use RuntimeException;

trait CParserTables
{
    protected const int yyFinal = 29;
    protected static array $yyLhs = Gen_d::yyLhs;
    protected static array $yyLen = Gen_d::yyLen;
    protected static array $yyDefRed = Gen_d::yyDefRed;
    protected static array $yyDgoto = Gen_d::yyDgoto;
    protected static array $yySindex = Gen_d::yySindex;
    protected static array $yyRindex = Gen_d::yyRindex;
    protected static array $yyGindex = Gen_d::yyGindex;
    protected static array $yyTable = Gen_d::yyTable;
    protected static array $yyCheck = Gen_d::yyCheck;
    protected static array $yyNames = Gen_d::yyNames;
    protected static ?array $global_yyStates = null;
    protected static ?array $global_yyVals = null;
    protected int $yyExpectingState = 0;
    protected int $eof_token = 0;
    protected bool $use_global_stacks = false;
    protected int $yyMax = 256;

    public static function yyname(int $token): string
    {
        if ($token < 0 || $token > count(self::$yyNames)) return '[illegal]';
        $name = self::$yyNames[$token] ?? null;
        return $name ?? '[unknown]';
    }

    public function yyerror(string $message, ?array $expected = null): void
    {
        if (self::$yacc_verbose_flag > 0 && $expected !== null && count($expected) > 0) {
            error_log($message . ', expecting ' . implode(' ', $expected));
        } else {
            error_log($message);
        }
    }

    public function reject(): void
    {
        error_log('reject');
    }

    /** @noinspection PhpUnnecessaryStringCastInspection
     * @noinspection PhpDuplicateSwitchCaseBodyInspection
     * @noinspection PhpParenthesesCanBeOmittedForNewCallInspection
     * @noinspection PhpUnusedLocalVariableInspection
     */
    public function yyparse(ParserInput $yyLex): void
    {
        if ($this->yyMax <= 0) $this->yyMax = 256;
        $yyState = 0;
        $yyStates = [];
        $this->yyVal = null;
        $yyToken = -1;
        $yyErrorFlag = 0;

        if ($this->use_global_stacks && self::$global_yyStates !== null) {
            $this->yyVals = self::$global_yyVals;
            $yyStates = self::$global_yyStates;
        } else {
            $this->yyVals = array_fill(0, $this->yyMax, null);
            $yyStates = array_fill(0, $this->yyMax, 0);
            if ($this->use_global_stacks) {
                self::$global_yyVals = $this->yyVals;
                self::$global_yyStates = $yyStates;
            }
        }

        for ($yyTop = 0; ; $yyTop++) {
            if ($yyTop >= count($yyStates)) {
                $yyStates = array_pad($yyStates, count($yyStates) + $this->yyMax, 0);
                $this->yyVals = array_pad($this->yyVals, count($this->yyVals) + $this->yyMax, null);
            }
            $yyStates[$yyTop] = $yyState;
            $this->yyVals[$yyTop] = $this->yyVal;

            while (true) {
                $yyN = 0;
                if (($yyN = self::$yyDefRed[$yyState]) == 0) {
                    if ($yyToken < 0) {
                        $yyToken = $yyLex->advance() ? $yyLex->token() : 0;
                    }
                    if (($yyN = self::$yySindex[$yyState]) != 0 && (($yyN += $yyToken) >= 0)
                        && ($yyN < count(self::$yyTable)) && (self::$yyCheck[$yyN] == $yyToken)) {
                        $yyState = self::$yyTable[$yyN];
                        $this->yyVal = $yyLex->value();
                        $yyToken = -1;
                        if ($yyErrorFlag > 0) $yyErrorFlag--;
                        continue 2;
                    }
                    if (($yyN = self::$yyRindex[$yyState]) != 0 && ($yyN += $yyToken) >= 0
                        && $yyN < count(self::$yyTable) && self::$yyCheck[$yyN] == $yyToken)
                        $yyN = self::$yyTable[$yyN];
                    else
                        switch ($yyErrorFlag) {
                            /** @noinspection PhpMissingBreakStatementInspection */
                            case 0:
                                $this->yyExpectingState = $yyState;
                                if ($yyToken == 0 || $yyToken == $this->eof_token) throw new yyUnexpectedEof();
                            case 1:
                            case 2:
                                $yyErrorFlag = 3;
                                do {
                                    if (($yyN = self::$yySindex[$yyStates[$yyTop]]) != 0
                                        && ($yyN += TokenKind::yyErrorCode) >= 0 && $yyN < count(self::$yyTable)
                                        && self::$yyCheck[$yyN] == TokenKind::yyErrorCode) {
                                        $yyState = self::$yyTable[$yyN];
                                        $this->yyVal = $yyLex->value();
                                        continue 3;
                                    }
                                } while (--$yyTop >= 0);
                                throw new yyException("irrecoverable syntax error");
                            case 3:
                                if ($yyToken == 0) {
                                    throw new yyException("irrecoverable syntax error at end-of-file");
                                }
                                $yyToken = -1;
                                continue 2;
                        }
                }
                $yyV = $yyTop + 1 - self::$yyLen[$yyN];
                $this->yyVal = $yyV > $yyTop ? null : $this->yyVals[$yyV];

                switch ($yyN) {
                    case 1:
                        {
                            $t = $this->lexer->getCurrentToken();
                            $this->yyVal = new VariableExpression((string)$this->yyVals[0 + $yyTop], $t->Location, $t->EndLocation);
                        }
                        break;
                    case 2:
                    case 3:
                        {
                            $this->yyVal = new ConstantExpression($this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 4:
                        {
                            $this->yyVal = ConstantExpression::trueVal();
                        }
                        break;
                    case 5:
                        {
                            $this->yyVal = ConstantExpression::falseVal();
                        }
                        break;
                    case 6:
                    case 233:
                        {
                            $this->yyVal = $this->yyVals[-1 + $yyTop];
                        }
                        break;
                    case 7:
                        {
                            $this->yyVal = new ScopeResolutionExpression((string)$this->yyVals[-2 + $yyTop], (string)$this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 8:
                    case 20:
                    case 32:
                    case 34:
                    case 62:
                    case 79:
                    case 148:
                    case 202:
                    case 207:
                    case 269:
                        {
                            $this->yyVal = $this->yyVals[0 + $yyTop];
                        }
                        break;
                    case 9:
                        {
                            $this->yyVal = new ArrayElementExpression($this->yyVals[-3 + $yyTop], $this->yyVals[-1 + $yyTop]);
                        }
                        break;
                    case 10:
                        {
                            $this->yyVal = new FuncallExpression($this->yyVals[-2 + $yyTop]);
                        }
                        break;
                    case 11:
                        {
                            $this->yyVal = new FuncallExpression($this->yyVals[-3 + $yyTop], $this->yyVals[-1 + $yyTop]);
                        }
                        break;
                    case 12:
                        {
                            $this->yyVal = new MemberFromReferenceExpression($this->yyVals[-2 + $yyTop], (string)$this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 13:
                        {
                            $this->yyVal = new MemberFromPointerExpression($this->yyVals[-2 + $yyTop], (string)$this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 14:
                        {
                            $this->yyVal = new UnaryExpression(Unop::PostIncrement, $this->yyVals[-1 + $yyTop]);
                        }
                        break;
                    case 15:
                        {
                            $this->yyVal = new UnaryExpression(Unop::PostDecrement, $this->yyVals[-1 + $yyTop]);
                        }
                        break;
                    case 16:
                    {
                        throw new RuntimeException("Not Supported: Syntax: '(' type_name ')' '{' initializer_list '}'");
                    }
                    case 17:
                    {
                        throw new RuntimeException("Not Supported: Syntax: '(' type_name ')' '{' initializer_list ',' '}'");
                    }
                    case 18:
                        {
                            $l = [];
                            $l[] = $this->yyVals[0 + $yyTop];
                            $this->yyVal = $l;
                        }
                        break;
                    case 19:
                        {
                            $l = $this->yyVals[-2 + $yyTop];
                            $l[] = $this->yyVals[0 + $yyTop];
                            $this->yyVal = $l;
                        }
                        break;
                    case 21:
                        {
                            $this->yyVal = new UnaryExpression(Unop::PreIncrement, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 22:
                        {
                            $this->yyVal = new UnaryExpression(Unop::PreDecrement, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 23:
                        {
                            $this->yyVal = new AddressOfExpression($this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 24:
                        {
                            $this->yyVal = new DereferenceExpression($this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 25:
                        {
                            $this->yyVal = new UnaryExpression($this->yyVals[-1 + $yyTop], $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 26:
                        {
                            $this->yyVal = new SizeOfExpression($this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 27:
                        {
                            $this->yyVal = new SizeOfTypeExpression($this->yyVals[-1 + $yyTop]);
                        }
                        break;
                    case 28:
                        {
                            $this->yyVal = Unop::None;
                        }
                        break;
                    case 29:
                        {
                            $this->yyVal = Unop::Negate;
                        }
                        break;
                    case 30:
                        {
                            $this->yyVal = Unop::BinaryComplement;
                        }
                        break;
                    case 31:
                        {
                            $this->yyVal = Unop::Not;
                        }
                        break;
                    case 33:
                        {
                            $this->yyVal = new CastExpression($this->yyVals[-2 + $yyTop], $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 35:
                        {
                            $this->yyVal = new BinaryExpression($this->yyVals[-2 + $yyTop], Binop::Multiply, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 36:
                        {
                            $this->yyVal = new BinaryExpression($this->yyVals[-2 + $yyTop], Binop::Divide, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 37:
                        {
                            $this->yyVal = new BinaryExpression($this->yyVals[-2 + $yyTop], Binop::Mod, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 39:
                        {
                            $this->yyVal = new BinaryExpression($this->yyVals[-2 + $yyTop], Binop::Add, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 40:
                        {
                            $this->yyVal = new BinaryExpression($this->yyVals[-2 + $yyTop], Binop::Subtract, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 42:
                        {
                            $this->yyVal = new BinaryExpression($this->yyVals[-2 + $yyTop], Binop::ShiftLeft, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 43:
                        {
                            $this->yyVal = new BinaryExpression($this->yyVals[-2 + $yyTop], Binop::ShiftRight, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 45:
                        {
                            $this->yyVal = new RelationalExpression($this->yyVals[-2 + $yyTop], RelationalOp::LessThan, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 46:
                        {
                            $this->yyVal = new RelationalExpression($this->yyVals[-2 + $yyTop], RelationalOp::GreaterThan, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 47:
                        {
                            $this->yyVal = new RelationalExpression($this->yyVals[-2 + $yyTop], RelationalOp::LessThanOrEqual, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 48:
                        {
                            $this->yyVal = new RelationalExpression($this->yyVals[-2 + $yyTop], RelationalOp::GreaterThanOrEqual, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 50:
                        {
                            $this->yyVal = new RelationalExpression($this->yyVals[-2 + $yyTop], RelationalOp::Equals, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 51:
                        {
                            $this->yyVal = new RelationalExpression($this->yyVals[-2 + $yyTop], RelationalOp::NotEquals, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 53:
                        {
                            $this->yyVal = new BinaryExpression($this->yyVals[-2 + $yyTop], Binop::BinaryAnd, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 55:
                        {
                            $this->yyVal = new BinaryExpression($this->yyVals[-2 + $yyTop], Binop::BinaryXor, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 57:
                        {
                            $this->yyVal = new BinaryExpression($this->yyVals[-2 + $yyTop], Binop::BinaryOr, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 59:
                        {
                            $this->yyVal = new LogicExpression($this->yyVals[-2 + $yyTop], LogicOp::And, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 61:
                        {
                            $this->yyVal = new LogicExpression($this->yyVals[-2 + $yyTop], LogicOp::Or, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 63:
                        {
                            $this->yyVal = new ConditionalExpression($this->yyVals[-4 + $yyTop], $this->yyVals[-2 + $yyTop], $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 65:
                        {
                            if ($this->yyVals[-1 + $yyTop] === RelationalOp::Equals) {
                                $this->yyVal = new AssignExpression($this->yyVals[-2 + $yyTop], $this->yyVals[0 + $yyTop]);
                            } elseif ($this->yyVals[-1 + $yyTop] instanceof Binop) {
                                $left = $this->yyVals[-2 + $yyTop];
                                $this->yyVal = new AssignExpression($left, new BinaryExpression($left, $this->yyVals[-1 + $yyTop], $this->yyVals[0 + $yyTop]));
                            } elseif ($this->yyVals[-1 + $yyTop] instanceof LogicOp) {
                                $left = $this->yyVals[-2 + $yyTop];
                                $this->yyVal = new AssignExpression($left, new LogicExpression($left, $this->yyVals[-1 + $yyTop], $this->yyVals[0 + $yyTop]));
                            } else {
                                throw new RuntimeException(sprintf("'%s' not supported", $this->yyVals[-1 + $yyTop]));
                            }
                        }
                        break;
                    case 66:
                        {
                            $this->yyVal = RelationalOp::Equals;
                        }
                        break;
                    case 67:
                        {
                            $this->yyVal = Binop::Multiply;
                        }
                        break;
                    case 68:
                        {
                            $this->yyVal = Binop::Divide;
                        }
                        break;
                    case 69:
                        {
                            $this->yyVal = Binop::Mod;
                        }
                        break;
                    case 70:
                        {
                            $this->yyVal = Binop::Add;
                        }
                        break;
                    case 71:
                        {
                            $this->yyVal = Binop::Subtract;
                        }
                        break;
                    case 72:
                        {
                            $this->yyVal = Binop::ShiftLeft;
                        }
                        break;
                    case 73:
                        {
                            $this->yyVal = Binop::ShiftRight;
                        }
                        break;
                    case 74:
                        {
                            $this->yyVal = Binop::BinaryAnd;
                        }
                        break;
                    case 75:
                        {
                            $this->yyVal = Binop::BinaryXor;
                        }
                        break;
                    case 76:
                        {
                            $this->yyVal = Binop::BinaryOr;
                        }
                        break;
                    case 77:
                        {
                            $this->yyVal = LogicOp::And;
                        }
                        break;
                    case 78:
                        {
                            $this->yyVal = LogicOp::Or;
                        }
                        break;
                    case 80:
                        {
                            $this->yyVal = new SequenceExpression($this->yyVals[-2 + $yyTop], $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 82:
                        {
                            $this->yyVal = new MultiDeclaratorStatement($this->yyVals[-1 + $yyTop], null);
                        }
                        break;
                    case 83:
                        {
                            $ds = $this->yyVals[-2 + $yyTop];
                            $decls = $this->yyVals[-1 + $yyTop];
                            $this->yyVal = new MultiDeclaratorStatement($ds, $decls);
                        }
                        break;
                    case 84:
                        {
                            $ds = new DeclarationSpecifiers();
                            $ds->StorageClassSpecifier = $this->yyVals[0 + $yyTop];
                            $this->yyVal = $ds;
                        }
                        break;
                    case 85:
                        {
                            $ds = $this->yyVals[0 + $yyTop];
                            $ds->StorageClassSpecifier |= $this->yyVals[-1 + $yyTop];
                            $this->yyVal = $ds;
                        }
                        break;
                    case 86:
                        {
                            $ds = new DeclarationSpecifiers();
                            $ds->TypeSpecifiers[] = $this->yyVals[0 + $yyTop];
                            $this->yyVal = $ds;
                        }
                        break;
                    case 87:
                        {
                            $ds = $this->yyVals[0 + $yyTop];
                            $ds->TypeSpecifiers[] = $this->yyVals[-1 + $yyTop];
                            $this->yyVal = $ds;
                        }
                        break;
                    case 88:
                        {
                            $ds = new DeclarationSpecifiers();
                            $ds->TypeQualifiers = $this->yyVals[0 + $yyTop];
                            $this->yyVal = $ds;
                        }
                        break;
                    case 89:
                        {
                            $ds = $this->yyVals[0 + $yyTop];
                            $ds->TypeQualifiers = $this->yyVals[-1 + $yyTop];
                            $this->yyVal = $ds;
                        }
                        break;
                    case 90:
                        {
                            $ds = new DeclarationSpecifiers();
                            $ds->FunctionSpecifier = $this->yyVals[0 + $yyTop];
                            $this->yyVal = $ds;
                        }
                        break;
                    case 91:
                        {
                            $ds = $this->yyVals[0 + $yyTop];
                            $ds->FunctionSpecifier = $this->yyVals[-1 + $yyTop];
                            $this->yyVal = $ds;
                        }
                        break;
                    case 92:
                        {
                            $idl = [];
                            $idl[] = $this->yyVals[0 + $yyTop];
                            $this->yyVal = $idl;
                        }
                        break;
                    case 93:
                        {
                            $idl = $this->yyVals[-2 + $yyTop];
                            $idl[] = $this->yyVals[0 + $yyTop];
                            $this->yyVal = $idl;
                        }
                        break;
                    case 94:
                        {
                            $this->yyVal = new InitDeclarator($this->yyVals[0 + $yyTop], null);
                        }
                        break;
                    case 95:
                        {
                            $this->yyVal = new InitDeclarator($this->yyVals[-2 + $yyTop], $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 96:
                        {
                            $this->yyVal = StorageClassSpecifier::Typedef;
                        }
                        break;
                    case 97:
                        {
                            $this->yyVal = StorageClassSpecifier::Extern;
                        }
                        break;
                    case 98:
                        {
                            $this->yyVal = StorageClassSpecifier::Static;
                        }
                        break;
                    case 99:
                        {
                            $this->yyVal = StorageClassSpecifier::Auto;
                        }
                        break;
                    case 100:
                        {
                            $this->yyVal = StorageClassSpecifier::Register;
                        }
                        break;
                    case 101:
                        {
                            $this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "void");
                        }
                        break;
                    case 102:
                        {
                            $this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "char");
                        }
                        break;
                    case 103:
                        {
                            $this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "short");
                        }
                        break;
                    case 104:
                        {
                            $this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "int");
                        }
                        break;
                    case 105:
                        {
                            $this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "long");
                        }
                        break;
                    case 106:
                        {
                            $this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "float");
                        }
                        break;
                    case 107:
                        {
                            $this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "double");
                        }
                        break;
                    case 108:
                        {
                            $this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "signed");
                        }
                        break;
                    case 109:
                        {
                            $this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "unsigned");
                        }
                        break;
                    case 110:
                        {
                            $this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "bool");
                        }
                        break;
                    case 111:
                        {
                            $this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "complex");
                        }
                        break;
                    case 112:
                        {
                            $this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "imaginary");
                        }
                        break;
                    case 115:
                        {
                            $this->yyVal = new TypeSpecifier(TypeSpecifierKind::Typename, (string)$this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 118:
                        {
                            $this->yyVal = new TypeSpecifier($this->yyVals[-2 + $yyTop], (string)$this->yyVals[-1 + $yyTop], $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 119:
                        {
                            $ts = new TypeSpecifier($this->yyVals[-4 + $yyTop], (string)$this->yyVals[-3 + $yyTop], $this->yyVals[0 + $yyTop]);
                            $ts->BaseSpecifiers = $this->yyVals[-1 + $yyTop];
                            $this->yyVal = $ts;
                        }
                        break;
                    case 120:
                        {
                            $this->yyVal = new TypeSpecifier($this->yyVals[-1 + $yyTop], "", $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 121:
                        {
                            $this->yyVal = new TypeSpecifier($this->yyVals[-1 + $yyTop], (string)$this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 122:
                        {
                            $this->yyVal = [$this->yyVals[0 + $yyTop]];
                        }
                        break;
                    case 123:
                        {
                            $list = $this->yyVals[-2 + $yyTop];
                            $list[] = $this->yyVals[0 + $yyTop];
                            $this->yyVal = $this->yyVals[-2 + $yyTop];
                        }
                        break;
                    case 124:
                        {
                            $this->yyVal = new BaseSpecifier((string)$this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 125:
                        {
                            $this->yyVal = new BaseSpecifier((string)$this->yyVals[0 + $yyTop], DeclarationsVisibility::Public);
                        }
                        break;
                    case 126:
                        {
                            $this->yyVal = new BaseSpecifier((string)$this->yyVals[0 + $yyTop], DeclarationsVisibility::Private);
                        }
                        break;
                    case 127:
                        {
                            $this->yyVal = new BaseSpecifier((string)$this->yyVals[0 + $yyTop], DeclarationsVisibility::Protected);
                        }
                        break;
                    case 128:
                        {
                            $this->yyVal = TypeSpecifierKind::Struct;
                        }
                        break;
                    case 129:
                        {
                            $this->yyVal = TypeSpecifierKind::ClassType;
                        }
                        break;
                    case 130:
                        {
                            $this->yyVal = TypeSpecifierKind::Union;
                        }
                        break;
                    case 131:
                        {
                            $ds = $this->yyVals[0 + $yyTop];
                            $ds->TypeSpecifiers[] = $this->yyVals[-1 + $yyTop];
                            $this->yyVal = $this->yyVals[0 + $yyTop];
                        }
                        break;
                    case 132:
                        {
                            $list = new DeclarationSpecifiers();
                            $list->TypeSpecifiers[] = $this->yyVals[0 + $yyTop];
                            $this->yyVal = $list;
                        }
                        break;
                    case 133:
                        {
                            $ds = $this->yyVals[0 + $yyTop];
                            $ds->TypeQualifiers |= $this->yyVals[-1 + $yyTop];
                            $this->yyVal = $this->yyVals[0 + $yyTop];
                        }
                        break;
                    case 134:
                        {
                            $list = new DeclarationSpecifiers();
                            $list->TypeQualifiers = $this->yyVals[0 + $yyTop];
                            $this->yyVal = $list;
                        }
                        break;
                    case 135:
                        {
                            $this->yyVal = new TypeSpecifier(TypeSpecifierKind::Enum, "", $this->yyVals[-1 + $yyTop]);
                        }
                        break;
                    case 136:
                        {
                            $this->yyVal = new TypeSpecifier(TypeSpecifierKind::Enum, (string)$this->yyVals[-3 + $yyTop], $this->yyVals[-1 + $yyTop]);
                        }
                        break;
                    case 137:
                        {
                            $this->yyVal = new TypeSpecifier(TypeSpecifierKind::Enum, "", $this->yyVals[-2 + $yyTop]);
                        }
                        break;
                    case 138:
                        {
                            $this->yyVal = new TypeSpecifier(TypeSpecifierKind::Enum, (string)$this->yyVals[-4 + $yyTop], $this->yyVals[-2 + $yyTop]);
                        }
                        break;
                    case 139:
                        {
                            $this->yyVal = new TypeSpecifier(TypeSpecifierKind::Enum, (string)$this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 140:
                        {
                            $l = new Block(Compiler\VariableScope::Global);
                            $l->addStatement($this->yyVals[0 + $yyTop]);
                            $this->yyVal = $l;
                        }
                        break;
                    case 141:
                        {
                            $l = $this->yyVals[-2 + $yyTop];
                            $l->addStatement($this->yyVals[0 + $yyTop]);
                            $this->yyVal = $l;
                        }
                        break;
                    case 142:
                        {
                            $this->yyVal = new EnumeratorStatement((string)$this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 143:
                        {
                            $this->yyVal = new EnumeratorStatement((string)$this->yyVals[-2 + $yyTop], $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 144:
                        {
                            $this->yyVal = FunctionSpecifier::Inline;
                        }
                        break;
                    case 145:
                    case 220:
                        {
                            $this->yyVal = new PointerDeclarator($this->yyVals[-1 + $yyTop], $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 146:
                        {
                            $this->yyVal = new ReferenceDeclarator($this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 147:
                        {
                            $this->yyVal = new ReferenceDeclarator($this->yyVals[0 + $yyTop], $this->yyVals[-1 + $yyTop]);
                        }
                        break;
                    case 149:
                        {
                            $this->yyVal = "+";
                        }
                        break;
                    case 150:
                        {
                            $this->yyVal = "-";
                        }
                        break;
                    case 151:
                        {
                            $this->yyVal = "*";
                        }
                        break;
                    case 152:
                        {
                            $this->yyVal = "/";
                        }
                        break;
                    case 153:
                        {
                            $this->yyVal = "%";
                        }
                        break;
                    case 154:
                        {
                            $this->yyVal = "==";
                        }
                        break;
                    case 155:
                        {
                            $this->yyVal = "!=";
                        }
                        break;
                    case 156:
                        {
                            $this->yyVal = "<";
                        }
                        break;
                    case 157:
                        {
                            $this->yyVal = ">";
                        }
                        break;
                    case 158:
                        {
                            $this->yyVal = "<=";
                        }
                        break;
                    case 159:
                        {
                            $this->yyVal = ">=";
                        }
                        break;
                    case 160:
                        {
                            $this->yyVal = "<<";
                        }
                        break;
                    case 161:
                        {
                            $this->yyVal = ">>";
                        }
                        break;
                    case 162:
                        {
                            $this->yyVal = "&";
                        }
                        break;
                    case 163:
                        {
                            $this->yyVal = "|";
                        }
                        break;
                    case 164:
                        {
                            $this->yyVal = "^";
                        }
                        break;
                    case 165:
                        {
                            $this->yyVal = "!";
                        }
                        break;
                    case 166:
                        {
                            $this->yyVal = "~";
                        }
                        break;
                    case 167:
                        {
                            $this->yyVal = "&&";
                        }
                        break;
                    case 168:
                        {
                            $this->yyVal = "||";
                        }
                        break;
                    case 169:
                        {
                            $this->yyVal = "++";
                        }
                        break;
                    case 170:
                        {
                            $this->yyVal = "--";
                        }
                        break;
                    case 171:
                        {
                            $this->yyVal = "=";
                        }
                        break;
                    case 172:
                        {
                            $this->yyVal = "+=";
                        }
                        break;
                    case 173:
                        {
                            $this->yyVal = "-=";
                        }
                        break;
                    case 174:
                        {
                            $this->yyVal = "*=";
                        }
                        break;
                    case 175:
                        {
                            $this->yyVal = "/=";
                        }
                        break;
                    case 176:
                        {
                            $this->yyVal = "%=";
                        }
                        break;
                    case 177:
                        {
                            $this->yyVal = "()";
                        }
                        break;
                    case 178:
                        {
                            $this->yyVal = "[]";
                        }
                        break;
                    case 179:
                    case 309:
                        {
                            $this->yyVal = new IdentifierDeclarator((string)$this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 180:
                        {
                            $this->yyVal = new IdentifierDeclarator("~" . (string)$this->yyVals[-1 + $yyTop]);
                        }
                        break;
                    case 181:
                        {
                            $this->yyVal = new IdentifierDeclarator("operator" . (string)$this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 182:
                    case 312:
                        {
                            $id = $this->yyVals[-2 + $yyTop];
                            $this->yyVal = $id->push((string)$this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 183:
                        {
                            $id = $this->yyVals[-3 + $yyTop];
                            $this->yyVal = $id->push("~" . (string)$this->yyVals[-1 + $yyTop]);
                        }
                        break;
                    case 184:
                    case 315:
                        {
                            $id = $this->yyVals[-3 + $yyTop];
                            $this->yyVal = $id->push("operator" . (string)$this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 186:
                    case 221:
                        {
                            $d = $this->yyVals[-1 + $yyTop];
                            $f = $this->FixPointerAndArrayPrecedence($d);
                            if ($f !== null) {
                                $this->yyVal = $f;
                            } else {
                                $d->StrongBinding = true;
                                $this->yyVal = $d;
                            }
                        }
                        break;
                    case 187:
                        {
                            $this->yyVal = $this->MakeArrayDeclarator($this->yyVals[-4 + $yyTop], $this->yyVals[-2 + $yyTop], $this->yyVals[-1 + $yyTop], false);
                        }
                        break;
                    case 188:
                    case 193:
                    case 227:
                        {
                            $this->yyVal = $this->MakeArrayDeclarator($this->yyVals[-3 + $yyTop], TypeQualifiers::None, null, false);
                        }
                        break;
                    case 189:
                    case 225:
                        {
                            $this->yyVal = $this->MakeArrayDeclarator($this->yyVals[-3 + $yyTop], TypeQualifiers::None, $this->yyVals[-1 + $yyTop], false);
                        }
                        break;
                    case 190:
                        {
                            $this->yyVal = $this->MakeArrayDeclarator($this->yyVals[-5 + $yyTop], $this->yyVals[-2 + $yyTop], $this->yyVals[-1 + $yyTop], true);
                        }
                        break;
                    case 191:
                        {
                            $this->yyVal = $this->MakeArrayDeclarator($this->yyVals[-5 + $yyTop], $this->yyVals[-3 + $yyTop], $this->yyVals[-1 + $yyTop], true);
                        }
                        break;
                    case 192:
                        {
                            $this->yyVal = $this->MakeArrayDeclarator($this->yyVals[-4 + $yyTop], $this->yyVals[-2 + $yyTop], null, false);
                        }
                        break;
                    case 194:
                    case 224:
                        {
                            $this->yyVal = $this->MakeArrayDeclarator($this->yyVals[-2 + $yyTop], TypeQualifiers::None, null, false);
                        }
                        break;
                    case 195:
                    case 231:
                    case 274:
                        {
                            $this->yyVal = new FunctionDeclarator(innerDeclarator: $this->yyVals[-3 + $yyTop], parameters: $this->yyVals[-1 + $yyTop]);
                        }
                        break;
                    case 196:
                        {
                            $d = new FunctionDeclarator(innerDeclarator: $this->yyVals[-3 + $yyTop], parameters: []);
                            foreach ($this->yyVals[-1 + $yyTop] as $n) {
                                $d->Parameters[] = new ParameterDeclaration(declarator: $n);
                            }
                            $this->yyVal = $d;
                        }
                        break;
                    case 197:
                    case 230:
                    case 275:
                        {
                            $this->yyVal = new FunctionDeclarator(innerDeclarator: $this->yyVals[-2 + $yyTop], parameters: []);
                        }
                        break;
                    case 198:
                        {
                            $this->yyVal = new Pointer(TypeQualifiers::None);
                        }
                        break;
                    case 199:
                        {
                            $this->yyVal = new Pointer($this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 200:
                        {
                            $this->yyVal = new Pointer(TypeQualifiers::None, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 201:
                        {
                            $this->yyVal = new Pointer($this->yyVals[-1 + $yyTop], $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 203:
                        {
                            $this->yyVal = $this->yyVals[-1 + $yyTop] | $this->yyVals[0 + $yyTop];
                        }
                        break;
                    case 204:
                        {
                            $this->yyVal = TypeQualifiers::Const;
                        }
                        break;
                    case 205:
                        {
                            $this->yyVal = TypeQualifiers::Restrict;
                        }
                        break;
                    case 206:
                        {
                            $this->yyVal = TypeQualifiers::Volatile;
                        }
                        break;
                    case 208:
                        {
                            $l = $this->yyVals[-2 + $yyTop];
                            $l[] = new VarParameter();
                            $this->yyVal = $l;
                        }
                        break;
                    case 209:
                        {
                            $l = [];
                            $l[] = $this->yyVals[0 + $yyTop];
                            $this->yyVal = $l;
                        }
                        break;
                    case 210:
                        {
                            $l = $this->yyVals[-2 + $yyTop];
                            $l[] = $this->yyVals[0 + $yyTop];
                            $this->yyVal = $l;
                        }
                        break;
                    case 211:
                    case 213:
                        {
                            $this->yyVal = new ParameterDeclaration($this->yyVals[-1 + $yyTop], $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 212:
                        {
                            $this->yyVal = new ParameterDeclaration($this->yyVals[-3 + $yyTop], $this->yyVals[-2 + $yyTop], $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 214:
                        {
                            $this->yyVal = new ParameterDeclaration($this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 215:
                        {
                            $this->yyVal = new TypeName($this->yyVals[0 + $yyTop], null);
                        }
                        break;
                    case 216:
                        {
                            $this->yyVal = new TypeName($this->yyVals[-1 + $yyTop], $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 217:
                        {
                            $this->yyVal = new PointerDeclarator($this->yyVals[0 + $yyTop], null);
                        }
                        break;
                    case 218:
                        {
                            $this->yyVal = new ReferenceDeclarator(null);
                        }
                        break;
                    case 222:
                    case 226:
                        {
                            $this->yyVal = $this->MakeArrayDeclarator(null, TypeQualifiers::None, null, false);
                        }
                        break;
                    case 223:
                        {
                            $this->yyVal = $this->MakeArrayDeclarator(null, TypeQualifiers::None, $this->yyVals[-1 + $yyTop], false);
                        }
                        break;
                    case 228:
                        {
                            $this->yyVal = new FunctionDeclarator(parameters: []);
                        }
                        break;
                    case 229:
                        {
                            $this->yyVal = new FunctionDeclarator(parameters: $this->yyVals[-1 + $yyTop]);
                        }
                        break;
                    case 232:
                        {
                            $this->yyVal = new ExpressionInitializer($this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 234:
                        {
                            $this->yyVal = $this->yyVals[-2 + $yyTop];
                        }
                        break;
                    case 235:
                        {
                            $l = new StructuredInitializer();
                            $l->add($this->yyVals[0 + $yyTop]);
                            $this->yyVal = $l;
                        }
                        break;
                    case 236:
                        {
                            $l = new StructuredInitializer();
                            $i = $this->yyVals[0 + $yyTop];
                            $i->Designation = $this->yyVals[-1 + $yyTop];
                            $l->add($i);
                            $this->yyVal = $l;
                        }
                        break;
                    case 237:
                        {
                            $l = $this->yyVals[-2 + $yyTop];
                            $l->add($this->yyVals[0 + $yyTop]);
                            $this->yyVal = $l;
                        }
                        break;
                    case 238:
                        {
                            $l = $this->yyVals[-3 + $yyTop];
                            $i = $this->yyVals[0 + $yyTop];
                            $i->Designation = $this->yyVals[-1 + $yyTop];
                            $l->add($i);
                            $this->yyVal = $l;
                        }
                        break;
                    case 239:
                        {
                            $this->yyVal = new InitializerDesignation($this->yyVals[-1 + $yyTop]);
                        }
                        break;
                    case 250:
                        {
                            $this->yyVal = new LabeledStatement((string)$this->yyVals[-2 + $yyTop], $this->yyVals[0 + $yyTop], $this->GetLocation($this->yyVals[-2 + $yyTop]));
                        }
                        break;
                    case 251:
                    case 257:
                        {
                            $this->yyVal = new Block(Compiler\VariableScope::Local);
                        }
                        break;
                    case 252:
                    case 258:
                        {
                            $this->yyVal = new Block(Compiler\VariableScope::Local, $this->yyVals[-1 + $yyTop]);
                        }
                        break;
                    case 253:
                    case 259:
                        {
                            $this->yyVal = [$this->yyVals[0 + $yyTop]];
                        }
                        break;
                    case 254:
                    case 260:
                        {
                            $list = $this->yyVals[-1 + $yyTop];
                            $list[] = $this->yyVals[0 + $yyTop];
                            $this->yyVal = $this->yyVals[-1 + $yyTop];
                        }
                        break;
                    case 265:
                        {
                            $fdecl = $this->yyVals[-1 + $yyTop];
                            $this->yyVal = new FunctionDefinition(new DeclarationSpecifiers(), $fdecl, null, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 266:
                        {
                            $this->yyVal = new VirtualDeclarationStatement($this->yyVals[0 + $yyTop]);
                            $this->yyVal->IsVirtual = true;
                        }
                        break;
                    case 267:
                        {
                            $inner = new MultiDeclaratorStatement($this->yyVals[-3 + $yyTop], $this->yyVals[-2 + $yyTop]);
                            $this->yyVal = new VirtualDeclarationStatement($inner);
                            $this->yyVal->IsOverride = true;
                        }
                        break;
                    case 268:
                        {
                            $inner = new MultiDeclaratorStatement($this->yyVals[-3 + $yyTop], $this->yyVals[-2 + $yyTop]);
                            $this->yyVal = new VirtualDeclarationStatement($inner);
                            $this->yyVal->IsVirtual = true;
                            $this->yyVal->IsOverride = true;
                        }
                        break;
                    case 270:
                        {
                            $decls = [new InitDeclarator($this->yyVals[-3 + $yyTop], null)];
                            $inner = new MultiDeclaratorStatement($this->yyVals[-4 + $yyTop], $decls);
                            $this->yyVal = new VirtualDeclarationStatement($inner);
                            $this->yyVal->IsVirtual = true;
                            $this->yyVal->IsPureVirtual = true;
                        }
                        break;
                    case 271:
                        {
                            $this->yyVal = new VisibilityStatement(DeclarationsVisibility::Public);
                        }
                        break;
                    case 272:
                        {
                            $this->yyVal = new VisibilityStatement(DeclarationsVisibility::Private);
                        }
                        break;
                    case 273:
                        {
                            $this->yyVal = new VisibilityStatement(DeclarationsVisibility::Protected);
                        }
                        break;
                    case 276:
                        {
                            $this->yyVal = new FunctionDeclarator(innerDeclarator: new IdentifierDeclarator((string)$this->yyVals[-3 + $yyTop]), parameters: $this->yyVals[-1 + $yyTop]);
                        }
                        break;
                    case 277:
                        {
                            $this->yyVal = new FunctionDeclarator(innerDeclarator: new IdentifierDeclarator((string)$this->yyVals[-2 + $yyTop]), parameters: []);
                        }
                        break;
                    case 278:
                        {
                            $this->yyVal = new FunctionDeclarator(innerDeclarator: new IdentifierDeclarator("~" . (string)$this->yyVals[-2 + $yyTop]), parameters: []);
                        }
                        break;
                    case 279:
                        {
                            $fdecl = $this->yyVals[-1 + $yyTop];
                            $ds = new DeclarationSpecifiers();
                            $decls = [new InitDeclarator($fdecl, null)];
                            $this->yyVal = new MultiDeclaratorStatement($ds, $decls);
                        }
                        break;
                    case 280:
                        {
                            $this->yyVal = null;
                        }
                        break;
                    case 281:
                        {
                            $this->yyVal = new ExpressionStatement($this->yyVals[-1 + $yyTop]);
                        }
                        break;
                    case 282:
                        {
                            $this->yyVal = new IfStatement($this->yyVals[-2 + $yyTop], $this->yyVals[0 + $yyTop], null, $this->GetLocation($this->yyVals[-4 + $yyTop]));
                        }
                        break;
                    case 283:
                        {
                            $this->yyVal = new IfStatement($this->yyVals[-4 + $yyTop], $this->yyVals[-2 + $yyTop], $this->yyVals[0 + $yyTop], $this->GetLocation($this->yyVals[-6 + $yyTop]));
                        }
                        break;
                    case 284:
                        {
                            $this->yyVal = new SwitchStatement($this->yyVals[-4 + $yyTop], $this->yyVals[-1 + $yyTop], $this->GetLocation($this->yyVals[-6 + $yyTop]));
                        }
                        break;
                    case 285:
                        {
                            $this->yyVal = new SwitchStatement($this->yyVals[-3 + $yyTop], [], $this->GetLocation($this->yyVals[-5 + $yyTop]));
                        }
                        break;
                    case 286:
                        {
                            $this->yyVal = [$this->yyVals[0 + $yyTop]];
                        }
                        break;
                    case 287:
                        {
                            $list = $this->yyVals[-1 + $yyTop];
                            $list[] = $this->yyVals[0 + $yyTop];
                            $this->yyVal = $this->yyVals[-1 + $yyTop];
                        }
                        break;
                    case 288:
                        {
                            $this->yyVal = new SwitchCase($this->yyVals[-2 + $yyTop], $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 289:
                        {
                            $this->yyVal = new SwitchCase(null, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 290:
                        {
                            $this->yyVal = new WhileStatement(false, $this->yyVals[-2 + $yyTop], $this->yyVals[0 + $yyTop]->toBlock());
                        }
                        break;
                    case 291:
                        {
                            $this->yyVal = new WhileStatement(true, $this->yyVals[-2 + $yyTop], $this->yyVals[-5 + $yyTop]->toBlock());
                        }
                        break;
                    case 292:
                    case 294:
                        {
                            $expr = $this->yyVals[-2 + $yyTop];
                            $this->yyVal = new ForStatement($this->yyVals[-3 + $yyTop], $expr instanceof ExpressionStatement ? $expr->Expression : null, $this->yyVals[0 + $yyTop]->toBlock());
                        }
                        break;
                    case 293:
                    case 295:
                        {
                            $expr = $this->yyVals[-3 + $yyTop];
                            $this->yyVal = new ForStatement($this->yyVals[-4 + $yyTop], $expr instanceof ExpressionStatement ? $expr->Expression : null, $this->yyVals[-2 + $yyTop], $this->yyVals[0 + $yyTop]->toBlock());
                        }
                        break;
                    case 296:
                        {
                            $this->yyVal = new GotoStatement((string)$this->yyVals[-1 + $yyTop], $this->GetLocation($this->yyVals[-2 + $yyTop]));
                        }
                        break;
                    case 297:
                        {
                            $this->yyVal = new ContinueStatement();
                        }
                        break;
                    case 298:
                        {
                            $this->yyVal = new BreakStatement();
                        }
                        break;
                    case 299:
                        {
                            $this->yyVal = new ReturnStatement();
                        }
                        break;
                    case 300:
                        {
                            $this->yyVal = new ReturnStatement($this->yyVals[-1 + $yyTop]);
                        }
                        break;
                    case 301:
                    case 302:
                        {
                            $this->AddDeclaration($this->yyVals[0 + $yyTop]);
                            $this->yyVal = $this->_tu;
                        }
                        break;
                    case 307:
                        {
                            $f = new FunctionDefinition($this->yyVals[-3 + $yyTop], $this->yyVals[-2 + $yyTop], $this->yyVals[-1 + $yyTop], $this->yyVals[0 + $yyTop]);
                            $this->yyVal = $f;
                        }
                        break;
                    case 308:
                        {
                            $f = new FunctionDefinition($this->yyVals[-2 + $yyTop], $this->yyVals[-1 + $yyTop], null, $this->yyVals[0 + $yyTop]);
                            $this->yyVal = $f;
                        }
                        break;
                    case 310:
                        {
                            $this->yyVal = (new IdentifierDeclarator((string)$this->yyVals[-2 + $yyTop]))->push((string)$this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 311:
                        {
                            $this->yyVal = (new IdentifierDeclarator((string)$this->yyVals[-3 + $yyTop]))->push("~" . (string)$this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 313:
                        {
                            $id = $this->yyVals[-3 + $yyTop];
                            $this->yyVal = $id->push("~" . (string)$this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 314:
                        {
                            $this->yyVal = (new IdentifierDeclarator((string)$this->yyVals[-3 + $yyTop]))->push("operator" . (string)$this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 316:
                        {
                            $d = new FunctionDeclarator(innerDeclarator: $this->yyVals[-3 + $yyTop], parameters: []);
                            $this->yyVal = new FunctionDefinition(new DeclarationSpecifiers(), $d, null, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 317:
                        {
                            $d = new FunctionDeclarator(innerDeclarator: $this->yyVals[-4 + $yyTop], parameters: $this->yyVals[-2 + $yyTop]);
                            $this->yyVal = new FunctionDefinition(new DeclarationSpecifiers(), $d, null, $this->yyVals[0 + $yyTop]);
                        }
                        break;
                    case 318:
                    case 319:
                        {
                            $l = [];
                            $l[] = $this->yyVals[0 + $yyTop];
                            $this->yyVal = $l;
                        }
                        break;
                    case 320:
                        {
                            $l = $this->yyVals[-1 + $yyTop];
                            $l[] = $this->yyVals[0 + $yyTop];
                            $this->yyVal = $l;
                        }
                        break;
                }

                $yyTop -= self::$yyLen[$yyN];
                $yyState = $yyStates[$yyTop];
                $yyM = self::$yyLhs[$yyN];
                if ($yyState == 0 && $yyM == 0) {
                    $yyState = self::yyFinal;
                    if ($yyToken < 0) {
                        $yyToken = $yyLex->advance() ? $yyLex->token() : 0;
                    }
                    if ($yyToken == 0) {
                        return;
                    }
                    continue 2;
                }
                if ((($yyN = self::$yyGindex[$yyM]) != 0) && (($yyN += $yyState) >= 0)
                    && ($yyN < count(self::$yyTable)) && (self::$yyCheck[$yyN] == $yyState))
                    $yyState = self::$yyTable[$yyN];
                else
                    $yyState = self::$yyDgoto[$yyM];
                continue 2;
            }
        }
    }

    protected function yyExpecting(int $state): array
    {
        $tokens = $this->yyExpectingTokens($state);
        $result = [];
        foreach ($tokens as $tok) {
            $result[] = self::$yyNames[$tok];
        }
        return $result;
    }

    protected function yyExpectingTokens(int $state): array
    {
        $len = 0;
        $ok = array_fill(0, count(self::$yyNames), false);
        $n = self::$yySindex[$state];
        if ($n != 0) {
            $start = $n < 0 ? -$n : 0;
            $end = min(count(self::$yyNames), count(self::$yyTable) - $n);
            for ($token = $start; $token < $end; $token++) {
                if (self::$yyCheck[$n + $token] == $token && !$ok[$token] && self::$yyNames[$token] !== null) {
                    $len++;
                    $ok[$token] = true;
                }
            }
        }
        $n = self::$yyRindex[$state];
        if ($n != 0) {
            $start = $n < 0 ? -$n : 0;
            $end = min(count(self::$yyNames), count(self::$yyTable) - $n);
            for ($token = $start; $token < $end; $token++) {
                if (self::$yyCheck[$n + $token] == $token && !$ok[$token] && self::$yyNames[$token] !== null) {
                    $len++;
                    $ok[$token] = true;
                }
            }
        }
        $result = [];
        $idx = 0;
        for ($token = 0; $token < count($ok) && $idx < $len; $token++) {
            if ($ok[$token]) $result[$idx++] = $token;
        }
        return $result;
    }
}
