<?php declare(strict_types=1);

namespace CLanguage\Parser;

use CLanguage\CLanguageService;
use CLanguage\Report;
use CLanguage\Syntax\ArrayDeclarator;
use CLanguage\Syntax\Declarator;
use CLanguage\Syntax\Document;
use CLanguage\Syntax\Expression;
use CLanguage\Syntax\ExpressionInitializer;
use CLanguage\Syntax\Location;
use CLanguage\Syntax\MultiDeclaratorStatement;
use CLanguage\Syntax\PointerDeclarator;
use CLanguage\Syntax\Statement;
use CLanguage\Syntax\StorageClassSpecifier;
use CLanguage\Syntax\Token;
use CLanguage\Syntax\TranslationUnit;
use CLanguage\Syntax\TypeSpecifierKind;
use Error;
use Exception;
use RuntimeException;

class CParser
{
    use CParserTables;

    static public int $yacc_verbose_flag = 0;
    static public string $DebugHelper = '+DebugYyN';
    private static array $noTokens = [];
    private static array $noObjects = [];
    protected array $yyVals = [];
    protected mixed $yyVal = null;

    // Generated parser fields
    private TranslationUnit $_tu;
    private ParserInput $lexer;

    public function __construct()
    {
        $this->yyVals = self::$noObjects;
        $this->yyVal = '';
        $this->_tu = new TranslationUnit('uninitialized.c');
        $this->lexer = new ParserInput(self::$noTokens);
    }

    public static function TryParseExpression(Report $report, array $tokens): ?Expression
    {
        $p = new CParser();
        $prefix = [
            new Token(TokenKind::AUTO, 'auto'),
            new Token(TokenKind::IDENTIFIER, '_'),
            new Token(ord('=')),
        ];
        $suffix = [new Token(ord(';'))];
        $tu = $p->ParseTranslationUnit("main.c", CLanguageService::DefaultCodePath, fn($_, $__) => null, $report, $prefix, $tokens, $suffix);
        $stmts = $tu->Statements;
        $first = $stmts[0] ?? null;
        if ($first instanceof MultiDeclaratorStatement && $first->InitDeclarators !== null && count($first->InitDeclarators) === 1 && $first->InitDeclarators[0]->Initializer instanceof ExpressionInitializer) {
            return $first->InitDeclarators[0]->Initializer->Expression;
        }
        return null;
    }

    public function ParseTranslationUnit(
        string|Report $nameOrReport,
        ?string       $codeOrName = null,
        ?callable     $include = null,
        ?Report       $report = null,
        array         ...$tokens
    ): TranslationUnit
    {
        if ($nameOrReport instanceof Report) {
            return $this->parseTranslationUnitFromTokens($nameOrReport, $codeOrName ?? '', $include, ...$tokens);
        }

        return $this->parseTranslationUnitFromCode($nameOrReport, $codeOrName ?? '', $include, $report ?? new Report());
    }

    private function parseTranslationUnitFromTokens(Report $report, string $name, ?callable $include, array ...$tokenArrays): TranslationUnit
    {
        $preprocessor = new Preprocessor($include, $report, ...$tokenArrays);
        $this->lexer = new ParserInput($preprocessor->preprocess());

        $this->_tu = new TranslationUnit($name);

        if (count($this->lexer->Tokens) === 0) {
            return $this->_tu;
        }

        try {
            $this->yyparse($this->lexer);
        } catch (yyUnexpectedEof $err) {
            $report->error(1513, $this->lexer->getCurrentToken()->Location, $this->lexer->getCurrentToken()->EndLocation, 'Incomplete err:' . $err->getMessage());
        } catch (RuntimeException $err) {
            $report->error(9999, $this->lexer->getCurrentToken()->Location, $this->lexer->getCurrentToken()->EndLocation,
                'Not Supported: ' . $err->getMessage());
        } catch (Exception $err) {
            if ($err->getMessage() === 'irrecoverable syntax error') {
                $report->error(1001, $this->lexer->getCurrentToken()->Location, $this->lexer->getCurrentToken()->EndLocation,
                    'Syntax error');
                return $this->_tu;
            }
            if ($err instanceof Error && str_contains($err->getMessage(), 'NotSupported')) {
                $report->error(9001, $this->lexer->getCurrentToken()->Location, $this->lexer->getCurrentToken()->EndLocation,
                    'Not Supported: ' . $err->getMessage());
                return $this->_tu;
            }
            error_log((string)$err);
            $report->error(9000, $this->lexer->getCurrentToken()->Location, $this->lexer->getCurrentToken()->EndLocation,
                'Parser Error: ' . $err->getMessage());
        }

        return $this->_tu;
    }

    private function parseTranslationUnitFromCode(string $name, string $code, ?callable $include, Report $report): TranslationUnit
    {
        $lexed = new LexedDocument(new Document($name, $code), $report);
        return $this->parseTranslationUnitFromTokens($report, pathinfo($name, PATHINFO_FILENAME), $include, $lexed->Tokens);
    }

    private function AddDeclaration(mixed $a): void
    {
        if (!$a instanceof Statement) return;
        $this->_tu->addStatement($a);

        if ($a instanceof MultiDeclaratorStatement) {
            $mds = $a;
            if (($mds->Specifiers->StorageClassSpecifier & StorageClassSpecifier::Typedef) !== 0) {
                if ($mds->InitDeclarators !== null) {
                    foreach ($mds->InitDeclarators as $i) {
                        $this->lexer->addTypedef($i->Declarator->getDeclaredIdentifier());
                    }
                }
            } elseif ($mds->Specifiers->StorageClassSpecifier === StorageClassSpecifier::None && count($mds->Specifiers->TypeSpecifiers) > 0) {
                foreach ($mds->Specifiers->TypeSpecifiers as $i) {
                    if ($i->Kind === TypeSpecifierKind::ClassType ||
                        $i->Kind === TypeSpecifierKind::Struct ||
                        $i->Kind === TypeSpecifierKind::Union ||
                        $i->Kind === TypeSpecifierKind::Enum) {
                        if ($i->Name !== '') {
                            $this->lexer->addTypedef($i->Name);
                        }
                    }
                }
            }
        }
    }

    private function FixPointerAndArrayPrecedence(?Declarator $d): ?Declarator
    {
        if ($d instanceof PointerDeclarator && $d->InnerDeclarator instanceof ArrayDeclarator) {
            $a = $d->InnerDeclarator;
            $p = $d;
            $i = $a->InnerDeclarator;
            $a->InnerDeclarator = $p;
            $p->InnerDeclarator = $i;
            return $a;
        }
        return null;
    }

    private function MakeArrayDeclarator(?Declarator $left, int $tq, ?Expression $len, bool $isStatic): Declarator
    {
        if ($left !== null && $left->StrongBinding) {
            $i = $left->InnerDeclarator;
            $a = new ArrayDeclarator($i, $len);
            $a->TypeQualifiers = $tq;
            $a->LengthIsStatic = $isStatic;
            $left->InnerDeclarator = $a;
            return $left;
        }
        $a = new ArrayDeclarator($left, $len);
        $a->TypeQualifiers = $tq;
        $a->LengthIsStatic = $isStatic;
        return $a;
    }

    private function GetLocation(mixed $obj): Location
    {
        return Location::null();
    }

}

class yyException extends Exception
{
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}

class yyUnexpectedEof extends yyException
{
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}
