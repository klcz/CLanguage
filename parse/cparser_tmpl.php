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
                        $t = $this->lexer->CurrentToken;
                        $this->yyVal = new VariableExpression((string)$yyVals[0 + $this->yyTop], $t->Location, $t->EndLocation);
                        break;
                    case 320:
                        $l = $yyVals[-1 + $this->yyTop];
                        $l[] = $yyVals[0 + $this->yyTop];
                        $this->yyVal = $l;
                        break;
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
