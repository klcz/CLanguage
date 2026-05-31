<?php declare(strict_types=1);

namespace CLanguage\Parser;

use CLanguage\Compiler\EmitContext;
use CLanguage\Compiler\ResolvedVariable;
use CLanguage\MachineInfo;
use CLanguage\Report;
use CLanguage\Types\CBasicType;
use Closure;
use Override;
use Throwable;

class Preprocessor
{
    private static array $noTokens = [];
    private static array $noStrings = [];
    private array $tokens;
    private Closure $include;
    private Report $report;

    public function __construct(callable $include, Report $report, array ...$tokenArrays)
    {
        $merged = [];
        foreach ($tokenArrays as $arr) {
            array_push($merged, ...$arr);
        }
        $this->tokens = $merged;
        $this->include = $include(...);
        $this->report = $report;
    }

    /** @noinspection PhpStatementHasEmptyBodyInspection */
    public function preprocess(): array
    {
        $defines = [];
        while (self::preprocessIteration($defines, $this->includeBuiltins(...), $this->tokens, $this->report)) {
        }
        return array_values($this->tokens);
    }

    private static function preprocessIteration(array &$defines, callable $include, array &$tokens, Report $report): bool
    {
        $anotherIterationNeeded = false;
        $i = 0;

        while ($i < count($tokens)) {
            $t = $tokens[$i];

            if ($t->kind === TokenKind::EOL || $t->kind === ord('\\')) {
                array_splice($tokens, $i, 1);
            } elseif ($t->kind === TokenKind::IDENTIFIER) {
                $ident = $t->value !== null ? (string)$t->value : null;
                if ($ident !== null && isset($defines[$ident])) {
                    $define = $defines[$ident];
                    if ($define->hasParameters) {
                        [$args, $len] = self::readDefineArgs($i + 1, $tokens);
                        $newDefines = $defines;
                        unset($newDefines[$define->Name]);
                        for ($ai = 0; $ai < min(count($args), count($define->parameters)); $ai++) {
                            $args[$ai]->Name = $define->parameters[$ai];
                            $newDefines[$args[$ai]->Name] = $args[$ai];
                        }
                        $newBody = $define->body;
                        /** @noinspection PhpStatementHasEmptyBodyInspection */
                        while (self::preprocessIteration($newDefines, $include, $newBody, $report)) {
                        }
                        array_splice($tokens, $i, $len + 1);
                        array_splice($tokens, $i, 0, $newBody);
                        $anotherIterationNeeded = true;
                    } else {
                        $newBody = $define->body;
                        $newDefines = $defines;
                        unset($newDefines[$define->Name]);
                        /** @noinspection PhpStatementHasEmptyBodyInspection */
                        while (self::preprocessIteration($newDefines, $include, $newBody, $report)) {
                        }
                        array_splice($tokens, $i, 1);
                        array_splice($tokens, $i, 0, $newBody);
                        $i += count($newBody);
                    }
                } else {
                    $i++;
                }
            } elseif ($t->kind === ord('#')
                && $i + 1 < count($tokens)
                && ($tokens[$i + 1]->kind === TokenKind::IDENTIFIER
                    || $tokens[$i + 1]->kind === TokenKind::IF
                    || $tokens[$i + 1]->kind === TokenKind::ELSE)
            ) {
                $eol = $i + 1;
                while ($eol < count($tokens) && $tokens[$eol]->kind !== TokenKind::EOL) {
                    if ($tokens[$eol]->kind === ord('\\') && $eol + 1 < count($tokens) && $tokens[$eol + 1]->kind === TokenKind::EOL) {
                        $eol++;
                    }
                    $eol++;
                }
                $insertTokens = null;
                $tokenValueString = $tokens[$i + 1]->value !== null ? (string)$tokens[$i + 1]->value : '';

                switch ($tokenValueString) {
                    case 'define':
                        if ($eol - $i > 2) {
                            $nameToken = $tokens[$i + 2];
                            $body = array_slice($tokens, $i + 3, $eol - $i - 3);
                            $ps = self::$noStrings;
                            $hasPs = false;
                            if (count($body) >= 2 && $body[0]->kind === ord('(') && $body[0]->location->index === $nameToken->endLocation->index) {
                                $endParam = -1;
                                for ($j = 1; $j < count($body); $j++) {
                                    if ($body[$j]->kind === ord(')')) {
                                        $endParam = $j;
                                        break;
                                    }
                                }
                                if ($endParam >= 0 && $endParam + 1 < count($body)) {
                                    $ps = [];
                                    for ($j = 0; $j < $endParam; $j++) {
                                        if ($body[$j]->kind === TokenKind::IDENTIFIER) {
                                            $ps[] = $body[$j]->stringValue();
                                        }
                                    }
                                    array_splice($body, 0, $endParam + 1);
                                    $hasPs = true;
                                }
                            }
                            $define = new Define(
                                name: $nameToken->stringValue(),
                                hasParameters: $hasPs,
                                parameters: $ps,
                                body: array_values($body),
                            );
                            if (trim($define->Name) !== '') {
                                $defines[$define->Name] = $define;
                            }
                        } else {
                            $report->warning(1025, $tokens[$i]->location, $tokens[$eol - 1]->endLocation, 'Incomplete #define');
                        }
                        break;

                    case 'include':
                        if ($eol - $i > 2) {
                            $relative = $tokens[$i + 2]->kind === TokenKind::STRING_LITERAL;
                            $iname = '';
                            for ($j = $i + 2; $j < $eol; $j++) {
                                $k = $tokens[$j]->kind;
                                if ($k === ord('/') || $k === ord('\\') || $k === ord('.')) {
                                    $iname .= chr($k);
                                } elseif ($k === TokenKind::IDENTIFIER || $k === TokenKind::STRING_LITERAL) {
                                    $iname .= $tokens[$j]->stringValue();
                                }
                            }
                            $insertTokens = $include($iname, $relative);
                            if ($insertTokens === null) {
                                $report->warning(1027, $tokens[$i + 2]->location, $tokens[$eol - 1]->endLocation, 'Failed to find file');
                            }
                        } else {
                            $report->warning(1026, $tokens[$i]->location, $tokens[$eol - 1]->endLocation, 'Incomplete #include');
                        }
                        break;

                    case 'endif':
                    case 'else':
                        $report->warning(1028, $tokens[$i]->location, $tokens[$eol - 1]->endLocation, 'Unexpected preprocessor directive');
                        break;

                    case 'if':
                    case 'ifdef':
                    case 'ifndef':
                        $isTrue = true;
                        if ($tokenValueString === 'if') {
                            $conditionTokens = array_slice($tokens, $i + 2, $eol - ($i + 2));
                            $isTrue = self::evalIfCondition($defines, $conditionTokens);
                        } else {
                            $isDefined = ($i + 2 < count($tokens)
                                && is_string($tokens[$i + 2]->value)
                                && isset($defines[$tokens[$i + 2]->value]));
                            $isTrue = $tokenValueString === 'ifdef' ? $isDefined : !$isDefined;
                        }

                        $elseStartIndex = -1;
                        $elseEndIndex = -1;
                        $endifStartIndex = count($tokens);
                        $endifEndIndex = count($tokens);
                        $ifDepth = 1;

                        for ($j = $i + 3; $j < count($tokens) - 1 && $endifEndIndex === count($tokens); $j++) {
                            if ($tokens[$j]->kind === ord('#') && is_string($tokens[$j + 1]->value ?? null)) {
                                $eis = $tokens[$j + 1]->value;
                                switch ($eis) {
                                    case 'if':
                                    case 'ifdef':
                                    case 'ifndef':
                                        $ifDepth++;
                                        break;
                                    case 'else':
                                        if ($ifDepth === 1) {
                                            $elseStartIndex = $j;
                                            $elseEndIndex = $j + 2;
                                        }
                                        break;
                                    case 'endif':
                                        $ifDepth--;
                                        if ($ifDepth === 0) {
                                            $endifStartIndex = $j;
                                            $endifEndIndex = $j + 2;
                                        }
                                        break;
                                }
                            }
                        }

                        if ($isTrue) {
                            if ($elseStartIndex >= $eol) {
                                $insertTokens = array_slice($tokens, $eol, $elseStartIndex - $eol);
                            } else {
                                $insertTokens = array_slice($tokens, $eol, $endifStartIndex - $eol);
                            }
                        } else {
                            if ($elseEndIndex >= $eol) {
                                $insertTokens = array_slice($tokens, $elseEndIndex, $endifStartIndex - $elseEndIndex);
                            }
                        }
                        $eol = $endifEndIndex;
                        break;

                    default:
                        $report->warning(1024, $tokens[$i]->location, $tokens[$eol - 1]->endLocation, 'Cannot understand preprocessor');
                        break;
                }

                if ($eol < count($tokens)) {
                    $eol++;
                }
                array_splice($tokens, $i, $eol - $i);
                if ($insertTokens !== null) {
                    array_splice($tokens, $i, 0, $insertTokens);
                }
                $anotherIterationNeeded = true;
            } else {
                $i++;
            }
        }

        return $anotherIterationNeeded;
    }

    private static function readDefineArgs(int $startIndex, array $tokens): array
    {
        $defines = [];

        if ($startIndex < 0 || $startIndex >= count($tokens) || $tokens[$startIndex]->kind !== ord('(')) {
            return [$defines, 0];
        }

        $parenDepth = 0;
        $i = $startIndex;
        $startArgIndex = $startIndex + 1;

        for (; $i < count($tokens) && $startArgIndex > $startIndex && $tokens[$i]->kind !== TokenKind::EOL; $i++) {
            switch ($tokens[$i]->kind) {
                case ord('('):
                    $parenDepth++;
                    break;
                case ord(','):
                    if ($parenDepth === 1) {
                        $body = array_slice($tokens, $startArgIndex, $i - $startArgIndex);
                        $defines[] = new Define(body: array_values($body));
                        $startArgIndex = $i + 1;
                    }
                    break;
                case ord(')'):
                    $parenDepth--;
                    if ($parenDepth === 0) {
                        $body = array_slice($tokens, $startArgIndex, $i - $startArgIndex);
                        $defines[] = new Define(body: array_values($body));
                        $startArgIndex = -1;
                    }
                    break;
            }
        }

        return [$defines, $i - $startIndex];
    }

    private static function evalIfCondition(array $defines, array $tokens): bool
    {
        try {
            $report = new Report();
            $expressions = [];
            foreach ($defines as $key => $d) {
                if (count($d->body) === 0) {
                    continue;
                }
                $e = CParser::tryParseExpression($report, $d->body);
                if ($e !== null) {
                    $expressions[$key] = $e;
                }
            }
            $expression = CParser::tryParseExpression($report, $tokens);
            if ($expression === null) {
                return false;
            }
            $context = new PreprocessorContext($report, $defines, $expressions);
            $value = $expression->evalConstant($context);
            return $value->Int32Value !== 0;
        } catch (Throwable $ex) {
            error_log((string)$ex);
            return false;
        }
    }

    private function includeBuiltins(string $filePath, bool $relative): ?array
    {
        if ($filePath === 'stdint.h') {
            return self::$noTokens;
        }
        return ($this->include)($filePath, $relative);
    }
}

class Define
{
    public string $Name;
    public array $Parameters;
    public bool $HasParameters;
    public array $Body;

    public function __construct(
        string $name = '',
        bool   $hasParameters = false,
        array  $parameters = [],
        array  $body = [],
    )
    {
        $this->Name = $name;
        $this->HasParameters = $hasParameters;
        $this->Parameters = $parameters;
        $this->Body = $body;
    }

    public function __toString(): string
    {
        return $this->Name . ': [' . implode(', ', array_map(fn($t) => (string)$t, $this->Body)) . ']';
    }
}

class PreprocessorContext extends EmitContext
{
    private array $defines;
    private array $expressions;

    public function __construct(Report $report, array $defines, array $expressions)
    {
        parent::__construct(new MachineInfo(), $report);
        $this->defines = $defines;
        $this->expressions = $expressions;
    }

    #[Override]
    public function tryResolveVariable(string $name, ?array $argTypes): ?ResolvedVariable
    {
        if (isset($this->expressions[$name])) {
            $expression = $this->expressions[$name];
            $nex = $this->expressions;
            $nctx = new PreprocessorContext($this->Report, $this->defines, $nex);
            $value = $expression->evalConstant($nctx);
            return ResolvedVariable::fromConstant($value, CBasicType::signedInt());
        }
        return parent::tryResolveVariable($name, $argTypes);
    }
}
