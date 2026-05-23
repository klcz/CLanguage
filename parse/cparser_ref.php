<?php
namespace parse;

// created by jay 0.7 (c) 1998 Axel.Schreiner@informatik.uni-osnabrueck.de

class CParser
{
    public $errorOutput = '';

    public $yaccVerboseFlag = 0;

    public $_tu;
    public $lexer;

    /** simplified error message */
    public function yyerror(string $message): void
    {
        $this->yyerror2($message, null);
    }

    /** (syntax) error message. */
    public function yyerror2(string $message, ?array $expected): void
    {
        if ($this->yaccVerboseFlag > 0 && $expected !== null && count($expected) > 0) {
            $this->errorOutput .= $message . ', expecting';
            foreach ($expected as $n) {
                $this->errorOutput .= ' ' . $n;
            }
            $this->errorOutput .= "\n";
        } else {
            $this->errorOutput .= $message . "\n";
        }
    }

    protected const yyFinal = 29;

    protected static $yyNames = [
        'end-of-file', null, null, null, null, null, null, null, null, null, null, null,
    ];

    public static function yyname(int $token): string
    {
        if ($token < 0 || $token >= count(self::$yyNames)) return '[illegal]';
        $name = self::$yyNames[$token];
        if ($name !== null) return $name;
        return '[unknown]';
    }

    public $yyExpectingState;

    protected function yyExpectingTokens(int $state): array
    {
        $len = 0;
        $ok = array_fill(0, count(self::$yyNames), false);
        $n = self::$yySindex[$state];
        if ($n != 0) {
            $start = $n < 0 ? -$n : 0;
            $limit = min(count(self::$yyNames), count(self::$yyTable) - $n);
            for ($token = $start; $token < $limit; $token++) {
                if (self::$yyCheck[$n + $token] == $token && !$ok[$token] && self::$yyNames[$token] !== null) {
                    $len++;
                    $ok[$token] = true;
                }
            }
        }
        $n = self::$yyRindex[$state];
        if ($n != 0) {
            $start = $n < 0 ? -$n : 0;
            $limit = min(count(self::$yyNames), count(self::$yyTable) - $n);
            for ($token = $start; $token < $limit; $token++) {
                if (self::$yyCheck[$n + $token] == $token && !$ok[$token] && self::$yyNames[$token] !== null) {
                    $len++;
                    $ok[$token] = true;
                }
            }
        }
        $result = [];
        $n = 0;
        for ($token = 0; $n < $len; $token++) {
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
        foreach ($tokens as $n) {
            $result[] = self::$yyNames[$n];
        }
        return $result;
    }

    protected $yyMax = 0;

    protected function yyDefault($first)
    {
        return $first;
    }

    protected static $globalYyStates;
    protected static $globalYyVals;
    protected $useGlobalStacks = false;
    protected $yyVals;
    protected $yyVal;
    protected $yyToken;
    protected $yyTop;

    public function yyparse($yyLex)
    {
        if ($this->yyMax <= 0) $this->yyMax = 256;
        $yyState = 0;
        $yyErrorFlag = 0;
        $this->yyToken = -1;
        $this->yyVal = null;

        if ($this->useGlobalStacks && self::$globalYyStates !== null) {
            $yyVals = self::$globalYyVals;
            $yyStates = self::$globalYyStates;
        } else {
            $yyVals = array_fill(0, $this->yyMax, null);
            $yyStates = array_fill(0, $this->yyMax, 0);
            if ($this->useGlobalStacks) {
                self::$globalYyVals = $yyVals;
                self::$globalYyStates = $yyStates;
            }
        }

        for ($this->yyTop = 0; ; ++$this->yyTop) {
            if ($this->yyTop >= count($yyStates)) {
                $newSize = count($yyStates) + $this->yyMax;
                $yyStates = array_pad($yyStates, $newSize, 0);
                $yyVals = array_pad($yyVals, $newSize, null);
            }
            $yyStates[$this->yyTop] = $yyState;
            $yyVals[$this->yyTop] = $this->yyVal;

            while (true) {
                $yyN = 0;
                if (($yyN = self::$yyDefRed[$yyState]) == 0) {
                    if ($this->yyToken < 0) {
                        $this->yyToken = $yyLex->advance() ? $yyLex->token() : 0;
                    }
                    if (($yyN = self::$yySindex[$yyState]) != 0 && ($yyN += $this->yyToken) >= 0
                        && $yyN < count(self::$yyTable) && self::$yyCheck[$yyN] == $this->yyToken) {
                        $yyState = self::$yyTable[$yyN];
                        $this->yyVal = $yyLex->value();
                        $this->yyToken = -1;
                        if ($yyErrorFlag > 0) --$yyErrorFlag;
                        continue 2;
                    }
                    if (($yyN = self::$yyRindex[$yyState]) != 0 && ($yyN += $this->yyToken) >= 0
                        && $yyN < count(self::$yyTable) && self::$yyCheck[$yyN] == $this->yyToken) {
                        $yyN = self::$yyTable[$yyN];
                    } else {
                        switch ($yyErrorFlag) {
                            case 0:
                                $this->yyExpectingState = $yyState;
                                if ($this->yyToken == 0 || $this->yyToken == TokenKind::yyErrorCode) {
                                    throw new yyUnexpectedEof();
                                }
                            case 1:
                            case 2:
                                $yyErrorFlag = 3;
                                do {
                                    if (($yyN = self::$yySindex[$yyStates[$this->yyTop]]) != 0
                                        && ($yyN += TokenKind::yyErrorCode) >= 0 && $yyN < count(self::$yyTable)
                                        && self::$yyCheck[$yyN] == TokenKind::yyErrorCode) {
                                        $yyState = self::$yyTable[$yyN];
                                        $this->yyVal = $yyLex->value();
                                        continue 3;
                                    }
                                } while (--$this->yyTop >= 0);
                                throw new yyException('irrecoverable syntax error');

                            case 3:
                                if ($this->yyToken == 0) {
                                    throw new yyException('irrecoverable syntax error at end-of-file');
                                }
                                $this->yyToken = -1;
                                continue 2;
                        }
                    }
                }

                $yyV = $this->yyTop + 1 - self::$yyLen[$yyN];
                $this->yyVal = $yyV > $this->yyTop ? null : $yyVals[$yyV];

                switch ($yyN) {
	case 1:
	{
		$t= $this->lexer->CurrentToken;
		$this->yyVal = new VariableExpression(($yyVals[0+$this->yyTop]), $t->Location, $t->EndLocation);
		break;
	}
	case 2: case 3:
	{
		$this->yyVal = new ConstantExpression($yyVals[0+$this->yyTop]);
		break;
	}
	case 4:
	{
		$this->yyVal = ConstantExpression::$True;
		break;
	}
	case 5:
	{
		$this->yyVal = ConstantExpression::$False;
		break;
	}
	case 6: case 233:
	{
		$this->yyVal = $yyVals[-1+$this->yyTop];
		break;
	}
	case 7:
	{
		$this->yyVal = new ScopeResolutionExpression(($yyVals[-2+$this->yyTop]), ($yyVals[0+$this->yyTop]));
		break;
	}
	case 8: case 20: case 32: case 34: case 62: case 79: case 148: case 202: case 207: case 269:
	{
		$this->yyVal = $yyVals[0+$this->yyTop];
		break;
	}
	case 9:
	{
		$this->yyVal = new ArrayElementExpression($yyVals[-3+$this->yyTop], $yyVals[-1+$this->yyTop]);
		break;
	}
	case 10:
	{
		$this->yyVal = new FuncallExpression($yyVals[-2+$this->yyTop]);
		break;
	}
	case 11:
	{
		$this->yyVal = new FuncallExpression($yyVals[-3+$this->yyTop], $yyVals[-1+$this->yyTop]);
		break;
	}
	case 12:
	{
		$this->yyVal = new MemberFromReferenceExpression($yyVals[-2+$this->yyTop], ($yyVals[0+$this->yyTop]));
		break;
	}
	case 13:
	{
		$this->yyVal = new MemberFromPointerExpression($yyVals[-2+$this->yyTop], ($yyVals[0+$this->yyTop]));
		break;
	}
	case 14:
	{
		$this->yyVal = new UnaryExpression(Unop::PostIncrement, $yyVals[-1+$this->yyTop]);
		break;
	}
	case 15:
	{
		$this->yyVal = new UnaryExpression(Unop::PostDecrement, $yyVals[-1+$this->yyTop]);
		break;
	}
	case 16:
	{
		throw new \RuntimeException("Syntax: '(' type_name ')' '{' initializer_list '}'");
		break;
	}
	case 17:
	{
		throw new \RuntimeException("Syntax: '(' type_name ')' '{' initializer_list ',' '}'");
		break;
	}
	case 18:
	{
		$l= [];
		$l[] = $yyVals[0+$this->yyTop];
		$this->yyVal = $l;
		break;
	}
	case 19:
	{
		$l= $yyVals[-2+$this->yyTop];
		$l[] = $yyVals[0+$this->yyTop];
		$this->yyVal = $l;
		break;
	}
	case 21:
	{
		$this->yyVal = new UnaryExpression(Unop::PreIncrement, $yyVals[0+$this->yyTop]);
		break;
	}
	case 22:
	{
		$this->yyVal = new UnaryExpression(Unop::PreDecrement, $yyVals[0+$this->yyTop]);
		break;
	}
	case 23:
	{
		$this->yyVal = new AddressOfExpression($yyVals[0+$this->yyTop]);
		break;
	}
	case 24:
	{
		$this->yyVal = new DereferenceExpression($yyVals[0+$this->yyTop]);
		break;
	}
	case 25:
	{
		$this->yyVal = new UnaryExpression($yyVals[-1+$this->yyTop], $yyVals[0+$this->yyTop]);
		break;
	}
	case 26:
	{
		$this->yyVal = new SizeOfExpression($yyVals[0+$this->yyTop]);
		break;
	}
	case 27:
	{
		$this->yyVal = new SizeOfTypeExpression($yyVals[-1+$this->yyTop]);
		break;
	}
	case 28:
	{
		$this->yyVal = Unop::None;
		break;
	}
	case 29:
	{
		$this->yyVal = Unop::Negate;
		break;
	}
	case 30:
	{
		$this->yyVal = Unop::BinaryComplement;
		break;
	}
	case 31:
	{
		$this->yyVal = Unop::Not;
		break;
	}
	case 33:
	{
		$this->yyVal = new CastExpression ($yyVals[-2+$this->yyTop], $yyVals[0+$this->yyTop]);
		break;
	}
	case 35:
	{
		$this->yyVal = new BinaryExpression($yyVals[-2+$this->yyTop], Binop::Multiply, $yyVals[0+$this->yyTop]);
		break;
	}
	case 36:
	{
		$this->yyVal = new BinaryExpression($yyVals[-2+$this->yyTop], Binop::Divide, $yyVals[0+$this->yyTop]);
		break;
	}
	case 37:
	{
		$this->yyVal = new BinaryExpression($yyVals[-2+$this->yyTop], Binop::Mod, $yyVals[0+$this->yyTop]);
		break;
	}
	case 39:
	{
		$this->yyVal = new BinaryExpression($yyVals[-2+$this->yyTop], Binop::Add, $yyVals[0+$this->yyTop]);
		break;
	}
	case 40:
	{
		$this->yyVal = new BinaryExpression($yyVals[-2+$this->yyTop], Binop::Subtract, $yyVals[0+$this->yyTop]);
		break;
	}
	case 42:
	{
		$this->yyVal = new BinaryExpression($yyVals[-2+$this->yyTop], Binop::ShiftLeft, $yyVals[0+$this->yyTop]);
		break;
	}
	case 43:
	{
		$this->yyVal = new BinaryExpression($yyVals[-2+$this->yyTop], Binop::ShiftRight, $yyVals[0+$this->yyTop]);
		break;
	}
	case 45:
	{
		$this->yyVal = new RelationalExpression($yyVals[-2+$this->yyTop], RelationalOp::LessThan, $yyVals[0+$this->yyTop]);
		break;
	}
	case 46:
	{
		$this->yyVal = new RelationalExpression($yyVals[-2+$this->yyTop], RelationalOp::GreaterThan, $yyVals[0+$this->yyTop]);
		break;
	}
	case 47:
	{
		$this->yyVal = new RelationalExpression($yyVals[-2+$this->yyTop], RelationalOp::LessThanOrEqual, $yyVals[0+$this->yyTop]);
		break;
	}
	case 48:
	{
		$this->yyVal = new RelationalExpression($yyVals[-2+$this->yyTop], RelationalOp::GreaterThanOrEqual, $yyVals[0+$this->yyTop]);
		break;
	}
	case 50:
	{
		$this->yyVal = new RelationalExpression($yyVals[-2+$this->yyTop], RelationalOp::Equals, $yyVals[0+$this->yyTop]);
		break;
	}
	case 51:
	{
		$this->yyVal = new RelationalExpression($yyVals[-2+$this->yyTop], RelationalOp::NotEquals, $yyVals[0+$this->yyTop]);
		break;
	}
	case 53:
	{
		$this->yyVal = new BinaryExpression($yyVals[-2+$this->yyTop], Binop::BinaryAnd, $yyVals[0+$this->yyTop]);
		break;
	}
	case 55:
	{
		$this->yyVal = new BinaryExpression($yyVals[-2+$this->yyTop], Binop::BinaryXor, $yyVals[0+$this->yyTop]);
		break;
	}
	case 57:
	{
		$this->yyVal = new BinaryExpression($yyVals[-2+$this->yyTop], Binop::BinaryOr, $yyVals[0+$this->yyTop]);
		break;
	}
	case 59:
	{
		$this->yyVal = new LogicExpression($yyVals[-2+$this->yyTop], LogicOp::And, $yyVals[0+$this->yyTop]);
		break;
	}
	case 61:
	{
		$this->yyVal = new LogicExpression($yyVals[-2+$this->yyTop], LogicOp::Or, $yyVals[0+$this->yyTop]);
		break;
	}
	case 63:
	{
		$this->yyVal = new ConditionalExpression ($yyVals[-4+$this->yyTop], $yyVals[-2+$this->yyTop], $yyVals[0+$this->yyTop]);
		break;
	}
	case 65:
	{
		if ($yyVals[-1+$this->yyTop] === RelationalOp::Equals) {
			$this->yyVal = new AssignExpression($yyVals[-2+$this->yyTop], $yyVals[0+$this->yyTop]);
		} else if (is_int($yyVals[-1+$this->yyTop]) && $yyVals[-1+$this->yyTop] >= 0 && $yyVals[-1+$this->yyTop] <= 9) {
			$left= $yyVals[-2+$this->yyTop];
			$this->yyVal = new AssignExpression($left, new BinaryExpression ($left, $yyVals[-1 + $this->yyTop], $yyVals[0+$this->yyTop]));
		} else if (in_array($yyVals[-1+$this->yyTop], [0, 1], true)) {
			$left= $yyVals[-2+$this->yyTop];
			$this->yyVal = new AssignExpression($left, new LogicExpression ($left, $yyVals[-1 + $this->yyTop], $yyVals[0+$this->yyTop]));
		} else {
			throw new \RuntimeException(sprintf("'%s' not supported", $yyVals[-1 + $this->yyTop]));
		}
		break;
	}
	case 66:
	{
		$this->yyVal = RelationalOp::Equals;
		break;
	}
	case 67:
	{
		$this->yyVal = Binop::Multiply;
		break;
	}
	case 68:
	{
		$this->yyVal = Binop::Divide;
		break;
	}
	case 69:
	{
		$this->yyVal = Binop::Mod;
		break;
	}
	case 70:
	{
		$this->yyVal = Binop::Add;
		break;
	}
	case 71:
	{
		$this->yyVal = Binop::Subtract;
		break;
	}
	case 72:
	{
		$this->yyVal = Binop::ShiftLeft;
		break;
	}
	case 73:
	{
		$this->yyVal = Binop::ShiftRight;
		break;
	}
	case 74:
	{
		$this->yyVal = Binop::BinaryAnd;
		break;
	}
	case 75:
	{
		$this->yyVal = Binop::BinaryXor;
		break;
	}
	case 76:
	{
		$this->yyVal = Binop::BinaryOr;
		break;
	}
	case 77:
	{
		$this->yyVal = LogicOp::And;
		break;
	}
	case 78:
	{
		$this->yyVal = LogicOp::Or;
		break;
	}
	case 80:
	{
		$this->yyVal = new SequenceExpression ($yyVals[-2+$this->yyTop], $yyVals[0+$this->yyTop]);
		break;
	}
	case 82:
	{
		$this->yyVal = new MultiDeclaratorStatement ($yyVals[-1+$this->yyTop], null);
		break;
	}
	case 83:
	{
		$ds= $yyVals[-2+$this->yyTop];
		$decls= $yyVals[-1+$this->yyTop];
		$this->yyVal = new MultiDeclaratorStatement ($ds, $decls);
		break;
	}
	case 84:
	{
		$ds= new DeclarationSpecifiers();
		$ds->StorageClassSpecifier = $yyVals[0+$this->yyTop];
		$this->yyVal = $ds;
		break;
	}
	case 85:
	{
		$ds= $yyVals[0+$this->yyTop];
		$ds->StorageClassSpecifier = $ds->StorageClassSpecifier | $yyVals[-1+$this->yyTop];
		$this->yyVal = $ds;
		break;
	}
	case 86:
	{
		$ds= new DeclarationSpecifiers();
		$ds->TypeSpecifiers[] = $yyVals[0+$this->yyTop];
		$this->yyVal = $ds;
		break;
	}
	case 87:
	{
		$ds= $yyVals[0+$this->yyTop];
		$ds->TypeSpecifiers[] = $yyVals[-1+$this->yyTop];
		$this->yyVal = $ds;
		break;
	}
	case 88:
	{
		$ds= new DeclarationSpecifiers();
		$ds->TypeQualifiers = $yyVals[0+$this->yyTop];
		$this->yyVal = $ds;
		break;
	}
	case 89:
	{
		$ds= $yyVals[0+$this->yyTop];
		$ds->TypeQualifiers = $yyVals[-1+$this->yyTop];
		$this->yyVal = $ds;
		break;
	}
	case 90:
	{
		$ds= new DeclarationSpecifiers();
		$ds->FunctionSpecifier = $yyVals[0+$this->yyTop];
		$this->yyVal = $ds;
		break;
	}
	case 91:
	{
		$ds= $yyVals[0+$this->yyTop];
		$ds->FunctionSpecifier = $yyVals[-1+$this->yyTop];
		$this->yyVal = $ds;
		break;
	}
	case 92:
	{
		$idl= [];
		$idl[] = $yyVals[0+$this->yyTop];
		$this->yyVal = $idl;
		break;
	}
	case 93:
	{
		$idl= $yyVals[-2+$this->yyTop];
		$idl[] = $yyVals[0+$this->yyTop];
		$this->yyVal = $idl;
		break;
	}
	case 94:
	{
		$this->yyVal = new InitDeclarator($yyVals[0+$this->yyTop], null);
		break;
	}
	case 95:
	{
		$this->yyVal = new InitDeclarator($yyVals[-2+$this->yyTop], $yyVals[0+$this->yyTop]);
		break;
	}
	case 96:
	{
		$this->yyVal = StorageClassSpecifier::Typedef;
		break;
	}
	case 97:
	{
		$this->yyVal = StorageClassSpecifier::Extern;
		break;
	}
	case 98:
	{
		$this->yyVal = StorageClassSpecifier::Static;
		break;
	}
	case 99:
	{
		$this->yyVal = StorageClassSpecifier::Auto;
		break;
	}
	case 100:
	{
		$this->yyVal = StorageClassSpecifier::Register;
		break;
	}
	case 101:
	{
		$this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "void");
		break;
	}
	case 102:
	{
		$this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "char");
		break;
	}
	case 103:
	{
		$this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "short");
		break;
	}
	case 104:
	{
		$this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "int");
		break;
	}
	case 105:
	{
		$this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "long");
		break;
	}
	case 106:
	{
		$this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "float");
		break;
	}
	case 107:
	{
		$this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "double");
		break;
	}
	case 108:
	{
		$this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "signed");
		break;
	}
	case 109:
	{
		$this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "unsigned");
		break;
	}
	case 110:
	{
		$this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "bool");
		break;
	}
	case 111:
	{
		$this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "complex");
		break;
	}
	case 112:
	{
		$this->yyVal = new TypeSpecifier(TypeSpecifierKind::Builtin, "imaginary");
		break;
	}
	case 115:
	{
		$this->yyVal = new TypeSpecifier(TypeSpecifierKind::Typename, ($yyVals[0+$this->yyTop]));
		break;
	}
	case 118:
	{
		$this->yyVal = new TypeSpecifier($yyVals[-2+$this->yyTop], ($yyVals[-1+$this->yyTop]), $yyVals[0+$this->yyTop]);
		break;
	}
	case 119:
	{
		$ts= new TypeSpecifier($yyVals[-4+$this->yyTop], ($yyVals[-3+$this->yyTop]), $yyVals[0+$this->yyTop]);
		$ts->BaseSpecifiers = $yyVals[-1+$this->yyTop];
		$this->yyVal = $ts;
		break;
	}
	case 120:
	{
		$this->yyVal = new TypeSpecifier($yyVals[-1+$this->yyTop], "", $yyVals[0+$this->yyTop]);
		break;
	}
	case 121:
	{
		$this->yyVal = new TypeSpecifier($yyVals[-1+$this->yyTop], ($yyVals[0+$this->yyTop]));
		break;
	}
	case 122:
	{
		$this->yyVal = [ $yyVals[0+$this->yyTop] ];
		break;
	}
	case 123:
	{
		($yyVals[-2+$this->yyTop])[] = $yyVals[0+$this->yyTop];
		$this->yyVal = $yyVals[-2+$this->yyTop];
		break;
	}
	case 124:
	{
		$this->yyVal = new BaseSpecifier(($yyVals[0+$this->yyTop]));
		break;
	}
	case 125:
	{
		$this->yyVal = new BaseSpecifier(($yyVals[0+$this->yyTop]), DeclarationsVisibility::Public);
		break;
	}
	case 126:
	{
		$this->yyVal = new BaseSpecifier(($yyVals[0+$this->yyTop]), DeclarationsVisibility::Private);
		break;
	}
	case 127:
	{
		$this->yyVal = new BaseSpecifier(($yyVals[0+$this->yyTop]), DeclarationsVisibility::Protected);
		break;
	}
	case 128:
	{
		$this->yyVal = TypeSpecifierKind::Struct;
		break;
	}
	case 129:
	{
		$this->yyVal = TypeSpecifierKind::Class;
		break;
	}
	case 130:
	{
		$this->yyVal = TypeSpecifierKind::Union;
		break;
	}
	case 131:
	{
		($yyVals[0+$this->yyTop])->TypeSpecifiers[] = $yyVals[-1+$this->yyTop];
		$this->yyVal = $yyVals[0+$this->yyTop];
		break;
	}
	case 132:
	{
		$list= new DeclarationSpecifiers ();
		$list->TypeSpecifiers[] = $yyVals[0+$this->yyTop];
		$this->yyVal = $list;
		break;
	}
	case 133:
	{
		($yyVals[0+$this->yyTop])->TypeQualifiers = ($yyVals[0+$this->yyTop])->TypeQualifiers | ($yyVals[-1+$this->yyTop]);
		$this->yyVal = $yyVals[0+$this->yyTop];
		break;
	}
	case 134:
	{
		$list= new DeclarationSpecifiers ();
		$list->TypeQualifiers = $yyVals[0+$this->yyTop];
		$this->yyVal = $list;
		break;
	}
	case 135:
	{
		$this->yyVal = new TypeSpecifier(TypeSpecifierKind::Enum, "", $yyVals[-1+$this->yyTop]);
		break;
	}
	case 136:
	{
		$this->yyVal = new TypeSpecifier(TypeSpecifierKind::Enum, ($yyVals[-3+$this->yyTop]), $yyVals[-1+$this->yyTop]);
		break;
	}
	case 137:
	{
		$this->yyVal = new TypeSpecifier(TypeSpecifierKind::Enum, "", $yyVals[-2+$this->yyTop]);
		break;
	}
	case 138:
	{
		$this->yyVal = new TypeSpecifier(TypeSpecifierKind::Enum, ($yyVals[-4+$this->yyTop]), $yyVals[-2+$this->yyTop]);
		break;
	}
	case 139:
	{
		$this->yyVal = new TypeSpecifier(TypeSpecifierKind::Enum, ($yyVals[0+$this->yyTop]));
		break;
	}
	case 140:
	{
		$l= new Block (VariableScope::Global);
		$l->AddStatement($yyVals[0+$this->yyTop]);
		$this->yyVal = $l;
		break;
	}
	case 141:
	{
		$l= $yyVals[-2+$this->yyTop];
		$l->AddStatement($yyVals[0+$this->yyTop]);
		$this->yyVal = $l;
		break;
	}
	case 142:
	{
		$this->yyVal = new EnumeratorStatement ((string)$yyVals[0+$this->yyTop]);
		break;
	}
	case 143:
	{
		$this->yyVal = new EnumeratorStatement ((string)$yyVals[-2+$this->yyTop], $yyVals[0+$this->yyTop]);
		break;
	}
	case 144:
	{
		$this->yyVal = FunctionSpecifier::Inline;
		break;
	}
	case 145: case 220:
	{
		$this->yyVal = new PointerDeclarator($yyVals[-1+$this->yyTop], $yyVals[0+$this->yyTop]);
		break;
	}
	case 146:
	{
		$this->yyVal = new ReferenceDeclarator($yyVals[0+$this->yyTop]);
		break;
	}
	case 147:
	{
		$this->yyVal = new ReferenceDeclarator($yyVals[0+$this->yyTop], $yyVals[-1+$this->yyTop]);
		break;
	}
	case 149:
	{
		$this->yyVal = "+";
		break;
	}
	case 150:
	{
		$this->yyVal = "-";
		break;
	}
	case 151:
	{
		$this->yyVal = "*";
		break;
	}
	case 152:
	{
		$this->yyVal = "/";
		break;
	}
	case 153:
	{
		$this->yyVal = "%";
		break;
	}
	case 154:
	{
		$this->yyVal = "==";
		break;
	}
	case 155:
	{
		$this->yyVal = "!=";
		break;
	}
	case 156:
	{
		$this->yyVal = "<";
		break;
	}
	case 157:
	{
		$this->yyVal = ">";
		break;
	}
	case 158:
	{
		$this->yyVal = "<=";
		break;
	}
	case 159:
	{
		$this->yyVal = ">=";
		break;
	}
	case 160:
	{
		$this->yyVal = "<<";
		break;
	}
	case 161:
	{
		$this->yyVal = ">>";
		break;
	}
	case 162:
	{
		$this->yyVal = "&";
		break;
	}
	case 163:
	{
		$this->yyVal = "|";
		break;
	}
	case 164:
	{
		$this->yyVal = "^";
		break;
	}
	case 165:
	{
		$this->yyVal = "!";
		break;
	}
	case 166:
	{
		$this->yyVal = "~";
		break;
	}
	case 167:
	{
		$this->yyVal = "&&";
		break;
	}
	case 168:
	{
		$this->yyVal = "||";
		break;
	}
	case 169:
	{
		$this->yyVal = "++";
		break;
	}
	case 170:
	{
		$this->yyVal = "--";
		break;
	}
	case 171:
	{
		$this->yyVal = "=";
		break;
	}
	case 172:
	{
		$this->yyVal = "+=";
		break;
	}
	case 173:
	{
		$this->yyVal = "-=";
		break;
	}
	case 174:
	{
		$this->yyVal = "*=";
		break;
	}
	case 175:
	{
		$this->yyVal = "/=";
		break;
	}
	case 176:
	{
		$this->yyVal = "%=";
		break;
	}
	case 177:
	{
		$this->yyVal = "()";
		break;
	}
	case 178:
	{
		$this->yyVal = "[]";
		break;
	}
	case 179: case 309:
	{
		$this->yyVal = new IdentifierDeclarator(($yyVals[0+$this->yyTop]));
		break;
	}
	case 180:
	{
		$this->yyVal = new IdentifierDeclarator("~" . ($yyVals[-1+$this->yyTop]));
		break;
	}
	case 181:
	{
		$this->yyVal = new IdentifierDeclarator("operator" . (string)$yyVals[0+$this->yyTop]);
		break;
	}
	case 182: case 312:
	{
		$this->yyVal = (($yyVals[-2+$this->yyTop]))->Push (($yyVals[0+$this->yyTop]));
		break;
	}
	case 183:
	{
		$this->yyVal = (($yyVals[-3+$this->yyTop]))->Push ("~" . ($yyVals[-1+$this->yyTop]));
		break;
	}
	case 184: case 315:
	{
		$this->yyVal = (($yyVals[-3+$this->yyTop]))->Push ("operator" . (string)$yyVals[0+$this->yyTop]);
		break;
	}
	case 186: case 221:
	{
		$d= $yyVals[-1+$this->yyTop];
		$f= $this->fixPointerAndArrayPrecedence($d);
		if ($f != null) {
			$this->yyVal = $f;
		} else {
			$d->StrongBinding = true;
			$this->yyVal = $d;
		}
		break;
	}
	case 187:
	{
		$this->yyVal = $this->makeArrayDeclarator($yyVals[-4+$this->yyTop], $yyVals[-2+$this->yyTop], $yyVals[-1+$this->yyTop], false);
		break;
	}
	case 188: case 193: case 227:
	{
		$this->yyVal = $this->makeArrayDeclarator($yyVals[-3+$this->yyTop], TypeQualifiers::None, null, false);
		break;
	}
	case 189: case 225:
	{
		$this->yyVal = $this->makeArrayDeclarator($yyVals[-3+$this->yyTop], TypeQualifiers::None, $yyVals[-1+$this->yyTop], false);
		break;
	}
	case 190:
	{
		$this->yyVal = $this->makeArrayDeclarator($yyVals[-5+$this->yyTop], $yyVals[-2+$this->yyTop], $yyVals[-1+$this->yyTop], true);
		break;
	}
	case 191:
	{
		$this->yyVal = $this->makeArrayDeclarator($yyVals[-5+$this->yyTop], $yyVals[-3+$this->yyTop], $yyVals[-1+$this->yyTop], true);
		break;
	}
	case 192:
	{
		$this->yyVal = $this->makeArrayDeclarator($yyVals[-4+$this->yyTop], $yyVals[-2+$this->yyTop], null, false);
		break;
	}
	case 194: case 224:
	{
		$this->yyVal = $this->makeArrayDeclarator($yyVals[-2+$this->yyTop], TypeQualifiers::None, null, false);
		break;
	}
	case 195: case 231: case 274:
	{
		$this->yyVal = new FunctionDeclarator($yyVals[-3+$this->yyTop], $yyVals[-1+$this->yyTop]);
		break;
	}
	case 196:
	{
		$d= new FunctionDeclarator($yyVals[-3+$this->yyTop], []);
		foreach ($yyVals[-1+$this->yyTop] as $n) {
			$d->Parameters[] = new ParameterDeclaration($n);
		}
		$this->yyVal = $d;
		break;
	}
	case 197: case 230: case 275:
	{
		$this->yyVal = new FunctionDeclarator($yyVals[-2+$this->yyTop], []);
		break;
	}
	case 198:
	{
		$this->yyVal = new Pointer(TypeQualifiers::None);
		break;
	}
	case 199:
	{
		$this->yyVal = new Pointer($yyVals[0+$this->yyTop]);
		break;
	}
	case 200:
	{
		$this->yyVal = new Pointer(TypeQualifiers::None, $yyVals[0+$this->yyTop]);
		break;
	}
	case 201:
	{
		$this->yyVal = new Pointer($yyVals[-1+$this->yyTop], $yyVals[0+$this->yyTop]);
		break;
	}
	case 203:
	{
		$this->yyVal = ($yyVals[-1+$this->yyTop]) | ($yyVals[0+$this->yyTop]);
		break;
	}
	case 204:
	{
		$this->yyVal = TypeQualifiers::Const;
		break;
	}
	case 205:
	{
		$this->yyVal = TypeQualifiers::Restrict;
		break;
	}
	case 206:
	{
		$this->yyVal = TypeQualifiers::Volatile;
		break;
	}
	case 208:
	{
		$l= $yyVals[-2+$this->yyTop];
		$l[] = new VarParameter();
		$this->yyVal = $l;
		break;
	}
	case 209:
	{
		$l= [];
		$l[] = $yyVals[0+$this->yyTop];
		$this->yyVal = $l;
		break;
	}
	case 210:
	{
		$l= $yyVals[-2+$this->yyTop];
		$l[] = $yyVals[0+$this->yyTop];
		$this->yyVal = $l;
		break;
	}
	case 211: case 213:
	{
		$this->yyVal = new ParameterDeclaration($yyVals[-1+$this->yyTop], $yyVals[0+$this->yyTop]);
		break;
	}
	case 212:
	{
		$this->yyVal = new ParameterDeclaration($yyVals[-3+$this->yyTop], $yyVals[-2+$this->yyTop], $yyVals[0+$this->yyTop]);
		break;
	}
	case 214:
	{
		$this->yyVal = new ParameterDeclaration($yyVals[0+$this->yyTop]);
		break;
	}
	case 215:
	{
		$this->yyVal = new TypeName ($yyVals[0+$this->yyTop], null);
		break;
	}
	case 216:
	{
		$this->yyVal = new TypeName ($yyVals[-1+$this->yyTop], $yyVals[0+$this->yyTop]);
		break;
	}
	case 217:
	{
		$this->yyVal = new PointerDeclarator($yyVals[0+$this->yyTop], null);
		break;
	}
	case 218:
	{
		$this->yyVal = new ReferenceDeclarator(null);
		break;
	}
	case 222: case 226:
	{
		$this->yyVal = $this->makeArrayDeclarator(null, TypeQualifiers::None, null, false);
		break;
	}
	case 223:
	{
		$this->yyVal = $this->makeArrayDeclarator(null, TypeQualifiers::None, $yyVals[-1+$this->yyTop], false);
		break;
	}
	case 228:
	{
		$this->yyVal = new FunctionDeclarator([]);
		break;
	}
	case 229:
	{
		$this->yyVal = new FunctionDeclarator($yyVals[-1+$this->yyTop]);
		break;
	}
	case 232:
	{
		$this->yyVal = new ExpressionInitializer($yyVals[0+$this->yyTop]);
		break;
	}
	case 234:
	{
		$this->yyVal = $yyVals[-2+$this->yyTop];
		break;
	}
	case 235:
	{
		$l= new StructuredInitializer();
		$i= $yyVals[0+$this->yyTop];
		$l[] = $i;
		$this->yyVal = $l;
		break;
	}
	case 236:
	{
		$l= new StructuredInitializer();
		$i= $yyVals[0+$this->yyTop];
		$i->Designation = $yyVals[-1+$this->yyTop];
		$l[] = $i;
		$this->yyVal = $l;
		break;
	}
	case 237:
	{
		$l= $yyVals[-2+$this->yyTop];
		$i= $yyVals[0+$this->yyTop];
		$l[] = $i;
		$this->yyVal = $l;
		break;
	}
	case 238:
	{
		$l= $yyVals[-3+$this->yyTop];
		$i= $yyVals[0+$this->yyTop];
		$i->Designation = $yyVals[-1+$this->yyTop];
		$l[] = $i;
		$this->yyVal = $l;
		break;
	}
	case 239:
	{
		$this->yyVal = new InitializerDesignation($yyVals[-1+$this->yyTop]);
		break;
	}
	case 250:
	{
		$this->yyVal = new LabeledStatement ((string)$yyVals[-2+$this->yyTop], $yyVals[0+$this->yyTop], $this->getLocation($yyVals[-2+$this->yyTop]));
		break;
	}
	case 251: case 257:
	{
		$this->yyVal = new Block (VariableScope::Local);
		break;
	}
	case 252: case 258:
	{
		$this->yyVal = new Block (VariableScope::Local, $yyVals[-1+$this->yyTop]);
		break;
	}
	case 253: case 259:
	{
		$this->yyVal = [ $yyVals[0+$this->yyTop] ];
		break;
	}
	case 254: case 260:
	{
		($yyVals[-1+$this->yyTop])[] = $yyVals[0+$this->yyTop];
		$this->yyVal = $yyVals[-1+$this->yyTop];
		break;
	}
	case 265:
	{
		$fdecl= $yyVals[-1+$this->yyTop];
		$this->yyVal = new FunctionDefinition(new DeclarationSpecifiers(), $fdecl, null, $yyVals[0+$this->yyTop]);
		break;
	}
	case 266:
	{
		$this->yyVal = new VirtualDeclarationStatement($yyVals[0+$this->yyTop]) ;
		break;
	}
	case 267:
	{
		$inner= new MultiDeclaratorStatement ($yyVals[-3+$this->yyTop], $yyVals[-2+$this->yyTop]);
		$this->yyVal = new VirtualDeclarationStatement($inner) ;
		break;
	}
	case 268:
	{
		$inner= new MultiDeclaratorStatement ($yyVals[-3+$this->yyTop], $yyVals[-2+$this->yyTop]);
		$this->yyVal = new VirtualDeclarationStatement($inner) ;
		break;
	}
	case 270:
	{
		$decls= [ new InitDeclarator($yyVals[-3+$this->yyTop], null) ];
		$inner= new MultiDeclaratorStatement ($yyVals[-4+$this->yyTop], $decls);
		$this->yyVal = new VirtualDeclarationStatement($inner) ;
		break;
	}
	case 271:
	{
		$this->yyVal = new VisibilityStatement(DeclarationsVisibility::Public);
		break;
	}
	case 272:
	{
		$this->yyVal = new VisibilityStatement(DeclarationsVisibility::Private);
		break;
	}
	case 273:
	{
		$this->yyVal = new VisibilityStatement(DeclarationsVisibility::Protected);
		break;
	}
	case 276:
	{
		$this->yyVal = new FunctionDeclarator(new IdentifierDeclarator(($yyVals[-3+$this->yyTop])), $yyVals[-1+$this->yyTop]);
		break;
	}
	case 277:
	{
		$this->yyVal = new FunctionDeclarator(new IdentifierDeclarator(($yyVals[-2+$this->yyTop])), []);
		break;
	}
	case 278:
	{
		$this->yyVal = new FunctionDeclarator(new IdentifierDeclarator("~" . ($yyVals[-2+$this->yyTop])), []);
		break;
	}
	case 279:
	{
		$fdecl= $yyVals[-1+$this->yyTop];
		$ds= new DeclarationSpecifiers();
		$decls= [ new InitDeclarator($fdecl, null) ];
		$this->yyVal = new MultiDeclaratorStatement ($ds, $decls);
		break;
	}
	case 280:
	{
		$this->yyVal = null;
		break;
	}
	case 281:
	{
		$this->yyVal = new ExpressionStatement($yyVals[-1+$this->yyTop]);
		break;
	}
	case 282:
	{
		$this->yyVal = new IfStatement($yyVals[-2+$this->yyTop], $yyVals[0+$this->yyTop], $this->getLocation($yyVals[-4+$this->yyTop]));
		break;
	}
	case 283:
	{
		$this->yyVal = new IfStatement($yyVals[-4+$this->yyTop], $yyVals[-2+$this->yyTop], $yyVals[0+$this->yyTop], $this->getLocation($yyVals[-6+$this->yyTop]));
		break;
	}
	case 284:
	{
		$this->yyVal = new SwitchStatement($yyVals[-4+$this->yyTop], $yyVals[-1+$this->yyTop], $this->getLocation($yyVals[-6+$this->yyTop]));
		break;
	}
	case 285:
	{
		$this->yyVal = new SwitchStatement($yyVals[-3+$this->yyTop], [], $this->getLocation($yyVals[-5+$this->yyTop]));
		break;
	}
	case 286:
	{
		$this->yyVal = [ $yyVals[0+$this->yyTop] ];
		break;
	}
	case 287:
	{
		($yyVals[-1+$this->yyTop])[] = $yyVals[0+$this->yyTop];
		$this->yyVal = $yyVals[-1+$this->yyTop];
		break;
	}
	case 288:
	{
		$this->yyVal = new SwitchCase($yyVals[-2+$this->yyTop], $yyVals[0+$this->yyTop]);
		break;
	}
	case 289:
	{
		$this->yyVal = new SwitchCase(null, $yyVals[0+$this->yyTop]);
		break;
	}
	case 290:
	{
		$this->yyVal = new WhileStatement(false, $yyVals[-2+$this->yyTop], ($yyVals[0+$this->yyTop])->ToBlock ());
		break;
	}
	case 291:
	{
		$this->yyVal = new WhileStatement(true, $yyVals[-2+$this->yyTop], ($yyVals[-5+$this->yyTop])->ToBlock ());
		break;
	}
	case 292: case 294:
	{
		$this->yyVal = new ForStatement($yyVals[-3+$this->yyTop], ($yyVals[-2+$this->yyTop] instanceof ExpressionStatement ? $yyVals[-2+$this->yyTop]->Expression : null), ($yyVals[0+$this->yyTop])->ToBlock ());
		break;
	}
	case 293: case 295:
	{
		$this->yyVal = new ForStatement($yyVals[-4+$this->yyTop], ($yyVals[-3+$this->yyTop] instanceof ExpressionStatement ? $yyVals[-3+$this->yyTop]->Expression : null), $yyVals[-2+$this->yyTop], ($yyVals[0+$this->yyTop])->ToBlock ());
		break;
	}
	case 296:
	{
		$this->yyVal = new GotoStatement ((string)$yyVals[-1+$this->yyTop], $this->getLocation($yyVals[-2+$this->yyTop]));
		break;
	}
	case 297:
	{
		$this->yyVal = new ContinueStatement ();
		break;
	}
	case 298:
	{
		$this->yyVal = new BreakStatement ();
		break;
	}
	case 299:
	{
		$this->yyVal = new ReturnStatement ();
		break;
	}
	case 300:
	{
		$this->yyVal = new ReturnStatement ($yyVals[-1+$this->yyTop]);
		break;
	}
	case 301: case 302:
	{
		$this->addDeclaration($yyVals[0+$this->yyTop]);
		$this->yyVal = $this->_tu;
		break;
	}
	case 307:
	{
		$f= new FunctionDefinition($yyVals[-3+$this->yyTop], $yyVals[-2+$this->yyTop], $yyVals[-1+$this->yyTop], $yyVals[0+$this->yyTop]);
		$this->yyVal = $f;
		break;
	}
	case 308:
	{
		$f= new FunctionDefinition($yyVals[-2+$this->yyTop], $yyVals[-1+$this->yyTop], null, $yyVals[0+$this->yyTop]);
		$this->yyVal = $f;
		break;
	}
	case 310:
	{
		$this->yyVal = (new IdentifierDeclarator(($yyVals[-2+$this->yyTop])))->Push(($yyVals[0+$this->yyTop]));
		break;
	}
	case 311:
	{
		$this->yyVal = (new IdentifierDeclarator(($yyVals[-3+$this->yyTop])))->Push("~" . ($yyVals[0+$this->yyTop]));
		break;
	}
	case 313:
	{
		$this->yyVal = (($yyVals[-3+$this->yyTop]))->Push ("~" . ($yyVals[0+$this->yyTop]));
		break;
	}
	case 314:
	{
		$this->yyVal = (new IdentifierDeclarator(($yyVals[-3+$this->yyTop])))->Push("operator" . (string)$yyVals[0+$this->yyTop]);
		break;
	}
	case 316:
	{
		$d= new FunctionDeclarator($yyVals[-3+$this->yyTop], []);
		$this->yyVal = new FunctionDefinition(new DeclarationSpecifiers(), $d, null, $yyVals[0+$this->yyTop]);
		break;
	}
	case 317:
	{
		$d= new FunctionDeclarator($yyVals[-4+$this->yyTop], $yyVals[-2+$this->yyTop]);
		$this->yyVal = new FunctionDefinition(new DeclarationSpecifiers(), $d, null, $yyVals[0+$this->yyTop]);
		break;
	}
	case 318: case 319:
	{
		$l= [];
		$l[] = $yyVals[0+$this->yyTop];
		$this->yyVal = $l;
		break;
	}
	case 320:
	{
		$l= $yyVals[-1+$this->yyTop];
		$l[] = $yyVals[0+$this->yyTop];
		$this->yyVal = $l;
		break;
	}
}

                $this->yyTop -= self::$yyLen[$yyN];
                $yyState = $yyStates[$this->yyTop];
                $yyM = self::$yyLhs[$yyN];
                if ($yyState == 0 && $yyM == 0) {
                    $yyState = self::yyFinal;
                    if ($this->yyToken < 0) {
                        $this->yyToken = $yyLex->advance() ? $yyLex->token() : 0;
                    }
                    if ($this->yyToken == 0) {
                        return $this->yyVal;
                    }
                    continue 2;
                }
                if ((($yyN = self::$yyGindex[$yyM]) != 0) && ($yyN += $yyState) >= 0
                    && $yyN < count(self::$yyTable) && self::$yyCheck[$yyN] == $yyState) {
                    $yyState = self::$yyTable[$yyN];
                } else {
                    $yyState = self::$yyDgoto[$yyM];
                }
                continue 2;
            }
        }
    }

    // ---- Parse tables (generated by jay) ----
    protected static $yyLhs = [-1,
        1, 1, 1, 1, 1, 1, 1, 3, 3, 3,
        79, 79, 79,
    ];
    protected static $yyLen = [2,
        1, 1, 1, 1, 1, 3, 3, 1, 4, 3,
        1, 1, 1,
    ];
    protected static $yyDefRed = [0,
        0, 0, 96, 97, 98, 99, 100, 144, 205, 102,
        17, 0, 0, 0,
    ];
    protected static $yyDgoto = [29,
        199, 200, 201, 235, 303, 355, 202, 203, 204, 205,
        148, 252, 514, 515, 40, 41, 127, 42, 128,
    ];
    protected static $yySindex = [1086,
        -197, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 525, 525, 525,
    ];
    protected static $yyRindex = [0,
        0, 2232, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 0, 56, 62,
    ];
    protected static $yyGindex = [0,
        0, -89, 0, 357, -56, 188, -118, -43, -149, 0,
        0, 0, 0, 175, 659, 0, 0, 0, 0,
    ];
    protected static $yyTable = [114,
        31, 279, 229, 156, 352, 117, 51, 117, 94, 110,
        17, 18, 19, 20, 21, 22, 23, 0, 0, 24,
        25, 26, 27,
    ];
    protected static $yyCheck = [53,
        0, 161, 121, 71, 225, 44, 40, 44, 44, 51,
        306, 307, 308, 309, 310, 311, 312, -1, -1, 315,
        316, 317, 318,
    ];

    // ---- End of parse tables ----

    // ========================================================================
    // CParserImpl members
    // ========================================================================

    protected static $noTokens = [];
    protected static $noObjects = [];

    public function __construct()
    {
        $this->yyVals = self::$noObjects;
        $this->yyVal = '';
        $this->_tu = new TranslationUnit('uninitialized.c');
        $this->lexer = new ParserInput(self::$noTokens);
    }

    public function parseTranslationUnitFromString(string $name, string $code, callable $include, Report $report)
    {
        $lexed = new LexedDocument(new Document($name, $code), $report);
        return $this->parseTranslationUnit($report, pathinfo($name, PATHINFO_FILENAME), $include, $lexed->Tokens);
    }

    public function parseTranslationUnit(Report $report, string $name, callable $include, array ...$tokensArrays)
    {
        $flatTokens = [];
        foreach ($tokensArrays as $arr) {
            foreach ($arr as $t) {
                $flatTokens[] = $t;
            }
        }
        $preprocessor = new Preprocessor($include, $report, $flatTokens);
        $tokens = $preprocessor->preprocess();
        $this->lexer = new ParserInput($tokens);
        $this->_tu = new TranslationUnit($name);

        if (count($this->lexer->Tokens) == 0) {
            return $this->_tu;
        }

        try {
            $this->yyparse($this->lexer);
        } catch (\RuntimeException $err) {
            $msg = $err->getMessage();
            if (strpos($msg, 'Not Supported') !== false) {
                $report->error(9999, $this->lexer->CurrentToken->Location, $this->lexer->CurrentToken->EndLocation,
                    'Not Supported: ' . $msg);
            } else {
                $report->error(9001, $this->lexer->CurrentToken->Location, $this->lexer->CurrentToken->EndLocation,
                    'Not Supported: ' . $msg);
            }
        } catch (yyUnexpectedEof $err) {
            $report->error(1513, $this->lexer->CurrentToken->Location, $this->lexer->CurrentToken->EndLocation,
                'Incomplete');
        } catch (\Exception $err) {
            if ($err->getMessage() === 'irrecoverable syntax error') {
                $report->error(1001, $this->lexer->CurrentToken->Location, $this->lexer->CurrentToken->EndLocation,
                    'Syntax error');
            } else {
                $report->error(9000, $this->lexer->CurrentToken->Location, $this->lexer->CurrentToken->EndLocation,
                    'Parser Error: ' . $err->getMessage());
            }
        }

        return $this->_tu;
    }

    public static function tryParseExpression(Report $report, array $tokens): ?Expression
    {
        $p = new CParser();
        $prefix = [new Token(TokenKind::AUTO, 'auto'), new Token(TokenKind::IDENTIFIER, '_'), new Token(ord('='))];
        $suffix = [new Token(ord(';'))];
        $allTokens = array_merge($prefix, $tokens, $suffix);
        $tu = $p->parseTranslationUnit($report, '__expr__', function ($_, $__) { return null; }, $allTokens);
        $stmts = $tu->Statements;
        if (count($stmts) > 0 && $stmts[0] instanceof MultiDeclaratorStatement) {
            $mds = $stmts[0];
            if ($mds->InitDeclarators !== null && count($mds->InitDeclarators) == 1 && $mds->InitDeclarators[0]->Initializer instanceof ExpressionInitializer) {
                return $mds->InitDeclarators[0]->Initializer->Expression;
            }
        }
        return null;
    }

    protected function addDeclaration($a): void
    {
        if (!($a instanceof Statement)) return;
        $this->_tu->addStatement($a);

        if ($a instanceof MultiDeclaratorStatement) {
            $mds = $a;
            switch ($mds->Specifiers->StorageClassSpecifier) {
                case StorageClassSpecifier::Typedef:
                    if ($mds->InitDeclarators !== null) {
                        foreach ($mds->InitDeclarators as $i) {
                            $this->lexer->addTypedef($i->Declarator->getDeclaredIdentifier());
                        }
                    }
                    break;
                case StorageClassSpecifier::None:
                    if (count($mds->Specifiers->TypeSpecifiers) > 0) {
                        foreach ($mds->Specifiers->TypeSpecifiers as $i) {
                            if ($i->Kind == TypeSpecifierKind::Class_ ||
                                $i->Kind == TypeSpecifierKind::Struct ||
                                $i->Kind == TypeSpecifierKind::Union ||
                                $i->Kind == TypeSpecifierKind::Enum) {
                                $this->lexer->addTypedef($i->Name);
                            }
                        }
                    }
                    break;
            }
        }
    }

    protected function fixPointerAndArrayPrecedence(?Declarator $d): ?Declarator
    {
        if ($d instanceof PointerDeclarator && $d->InnerDeclarator !== null && $d->InnerDeclarator instanceof ArrayDeclarator) {
            $a = $d->InnerDeclarator;
            $p = $d;
            $i = $a->InnerDeclarator;
            $a->InnerDeclarator = $p;
            $p->InnerDeclarator = $i;
            return $a;
        } else {
            return null;
        }
    }

    protected function makeArrayDeclarator(?Declarator $left, int $tq, $len, bool $isStatic): Declarator
    {
        if ($left !== null && $left->StrongBinding) {
            $i = $left->InnerDeclarator;
            $a = new ArrayDeclarator($i, $len);
            $left->InnerDeclarator = $a;
            return $left;
        } else {
            return new ArrayDeclarator($left, $len);
        }
    }

    protected function getLocation($obj): Location
    {
        return Location::$Null;
    }
}
