<?php declare(strict_types=1);

namespace CLanguage;

use CLanguage\Compiler\CCompiler;
use CLanguage\Compiler\CompilerOptions;
use CLanguage\Parser\CParser;
use CLanguage\Parser\LexedDocument;
use CLanguage\Parser\Lexer;
use CLanguage\Parser\TokenKind;
use CLanguage\Syntax\ColorSpan;
use CLanguage\Syntax\Document;
use CLanguage\Syntax\SyntaxColor;
use CLanguage\Syntax\Token;
use CLanguage\Syntax\TranslationUnit;
use CLanguage\Types\CStructType;

class CLanguageService
{
    public const string DefaultCodePath = 'main.cpp';

    private function __construct()
    {
    }

    public static function ParseTranslationUnitWithPrinter(string $code, ?Printer $printer = null): TranslationUnit
    {
        return self::ParseTranslationUnit($code, new Report($printer));
    }

    public static function ParseTranslationUnit(string $code, ?Report $report = null): TranslationUnit
    {
        if ($report === null) {
            $report = new Report();
        }
        $parser = new CParser();
        return $parser->ParseTranslationUnit(self::DefaultCodePath, $code, fn($_, $__) => null, $report);
    }

    public static function CreateInterpreter(string $code, ?MachineInfo $machineInfo = null, ?Printer $printer = null): Interpreter\CInterpreter
    {
        $exe = self::Compile($code, $machineInfo, $printer);
        return new Interpreter\CInterpreter($exe);
    }

    public static function Compile(string $code, ?MachineInfo $machineInfo = null, ?Printer $printer = null): Interpreter\Executable
    {
        $report = new Report($printer);
        $mi = $machineInfo ?? new MachineInfo();
        $doc = new Document(self::DefaultCodePath, $code);
        $options = new CompilerOptions($mi, $report, [$doc]);
        $c = new CCompiler($options);
        return $c->Compile();
    }

    /** @return ColorSpan[] */
    public static function Colorize(string $code, ?MachineInfo $machineInfo = null, ?Printer $printer = null): array
    {
        $report = new Report($printer);
        $mi = $machineInfo ?? new MachineInfo();
        $doc = new Document(self::DefaultCodePath, $code);
        $lexed = new LexedDocument($doc, $report);
        $compiler = new CCompiler(new CompilerOptions($mi, $report, [$doc]));
        $exe = $compiler->Compile();

        $funcs = [];
        foreach ($mi->InternalFunctions as $f) {
            $funcs[$f->Name] = true;
        }
        foreach ($exe->Globals as $g) {
            if ($g->VariableType instanceof CStructType) {
                $funcs[$g->Name] = true;
            }
        }

        $tokens = [];
        foreach ($lexed->Tokens as $t) {
            if ($t->Kind !== TokenKind::EOL) {
                $tokens[] = self::colorizeToken($t, $funcs);
            }
        }
        return $tokens;
    }

    private static function colorizeToken(Token $token, array $funcs): ColorSpan
    {
        $span = new ColorSpan(0, 0, SyntaxColor::Comment);
        $span->Index = $token->Location->Index;
        $span->Length = $token->EndLocation->Index - $token->Location->Index;
        $span->Color = self::getTokenColor($token, $funcs);
        return $span;
    }

    private static function getTokenColor(Token $token, array $funcs): SyntaxColor
    {
        return match ($token->Kind) {
            TokenKind::INT, TokenKind::SHORT, TokenKind::LONG, TokenKind::CHAR,
            TokenKind::FLOAT, TokenKind::DOUBLE, TokenKind::BOOL, TokenKind::TYPE_NAME
            => SyntaxColor::Type,

            TokenKind::IDENTIFIER => self::getIdentifierColor($token, $funcs),

            TokenKind::CONSTANT => (is_string($token->Value) && strlen($token->Value) === 1)
                ? SyntaxColor::String
                : SyntaxColor::Number,

            TokenKind::TRUE, TokenKind::FALSE => SyntaxColor::Number,

            TokenKind::STRING_LITERAL => SyntaxColor::String,

            default => self::getDefaultTokenColor($token),
        };
    }

    private static function getIdentifierColor(Token $token, array $funcs): SyntaxColor
    {
        if (is_string($token->Value)) {
            $s = $token->Value;
            if (isset($funcs[$s])) {
                return SyntaxColor::Function;
            }
            return match ($s) {
                'uint8_t', 'uint16_t', 'uint32_t', 'uint64_t',
                'int8_t', 'int16_t', 'int32_t', 'int64_t',
                'boolean' => SyntaxColor::Type,
                'include', 'define', 'ifdef', 'ifndef', 'elif', 'endif' => SyntaxColor::Keyword,
                default => SyntaxColor::Identifier,
            };
        }
        return SyntaxColor::Identifier;
    }

    private static function getDefaultTokenColor(Token $token): SyntaxColor
    {
        if ($token->Kind < 128 || isset(Lexer::$OperatorTokens[$token->Kind])) {
            return SyntaxColor::Operator;
        }
        if (isset(Lexer::$KeywordTokens[$token->Kind])) {
            return match ((string)$token->Value) {
                'unsigned', 'signed' => SyntaxColor::Type,
                default => SyntaxColor::Keyword,
            };
        }
        return SyntaxColor::Comment;
    }

    public static function Eval(string $expression, ?string $includeCode = ""): mixed
    {
        $codeToCompile = ($includeCode ?? '') . "\nauto __evalResult = " . $expression . ";\nvoid start() {\n    __cinit();\n}\n";
        $exe = CCompiler::compileFromString($codeToCompile);

        $global = null;
        foreach ($exe->Globals as $g) {
            if ($g->Name === '__evalResult') {
                $global = $g;
                break;
            }
        }

        $interpreter = new Interpreter\CInterpreter($exe);
        $interpreter->Reset('start');
        $interpreter->Run();

        $globalType = $global->VariableType;
        $resultValues = [];
        for ($i = 0; $i < $globalType->NumValues; $i++) {
            $resultValues[] = clone $interpreter->Stack[$global->StackOffset + $i];
        }

        return $globalType->GetClrValue($resultValues, $exe->MachineInfo);
    }

    public static function Run(string $code): void
    {
        Interpreter\CInterpreter::runString($code);
    }
}
