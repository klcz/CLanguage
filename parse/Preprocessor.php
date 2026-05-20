<?php
namespace parse;

class Preprocessor
{
    private $tokens;
    private $include;
    private $report;

    public function __construct(callable $include, Report $report, array ...$tokenArrays)
    {
        $this->tokens = [];
        foreach ($tokenArrays as $arr) {
            foreach ($arr as $t) {
                $this->tokens[] = $t;
            }
        }
        $this->include = $include;
        $this->report = $report;
    }

    public function preprocess(): array
    {
        $defines = [];
        while (self::preprocessIteration($defines, \Closure::fromCallable([$this, 'includeBuiltins']), $this->tokens, $this->report)) {
        }
        return $this->tokens;
    }

    private function includeBuiltins(string $filePath, bool $relative): ?array
    {
        if ($filePath === 'stdint.h') {
            return [];
        }
        $cb = $this->include;
        return $cb($filePath, $relative);
    }

    private static function preprocessIteration(array &$defines, callable $include, array &$tokens, Report $report): bool
    {
        $anotherIterationNeeded = false;

        $i = 0;
        while ($i < count($tokens)) {
            $t = $tokens[$i];
            if (!is_object($t)) {
                $i++;
                continue;
            }
            if ($t->Kind === TokenKind::EOL || $t->Kind === ord('\\')) {
                array_splice($tokens, $i, 1);
            } elseif ($t->Kind === TokenKind::IDENTIFIER) {
                $ident = $t->Value !== null ? (string)$t->Value : null;
                if ($ident !== null && isset($defines[$ident])) {
                    $define = $defines[$ident];
                    if ($define->HasParameters) {
                        $result = self::readDefineArgs($i + 1, $tokens);
                        $args = $result[0];
                        $len = $result[1];
                        $newDefines = $defines;
                        unset($newDefines[$define->Name]);
                        $ai = 0;
                        foreach ($args as $arg) {
                            if ($ai < count($define->Parameters)) {
                                $arg->Name = $define->Parameters[$ai];
                                $newDefines[$arg->Name] = $arg;
                            }
                            $ai++;
                        }
                        $newBody = $define->Body;
                        while (self::preprocessIteration($newDefines, $include, $newBody, $report)) {
                        }
                        array_splice($tokens, $i, $len + 1, $newBody);
                        $anotherIterationNeeded = true;
                    } else {
                        $newBody = $define->Body;
                        $newDefines = $defines;
                        unset($newDefines[$define->Name]);
                        while (self::preprocessIteration($newDefines, $include, $newBody, $report)) {
                        }
                        array_splice($tokens, $i, 1, $newBody);
                        $i += count($newBody);
                    }
                } else {
                    $i++;
                }
            } elseif ($t->Kind === ord('#') && $i + 1 < count($tokens) &&
                ($tokens[$i + 1]->Kind === TokenKind::IDENTIFIER || $tokens[$i + 1]->Kind === TokenKind::IF || $tokens[$i + 1]->Kind === TokenKind::ELSE)) {
                $eol = $i + 1;
                while ($eol < count($tokens) && $tokens[$eol]->Kind !== TokenKind::EOL) {
                    if ($tokens[$eol]->Kind === ord('\\') && $eol + 1 < count($tokens) && $tokens[$eol + 1]->Kind === TokenKind::EOL) {
                        $eol++;
                    }
                    $eol++;
                }
                $insertTokens = null;
                $tokenValueString = $tokens[$i + 1]->Value !== null ? (string)$tokens[$i + 1]->Value : '';
                switch ($tokenValueString) {
                    case 'define':
                        if ($eol - $i > 2) {
                            $nameToken = $tokens[$i + 2];
                            $body = array_slice($tokens, $i + 3, $eol - $i - 3);
                            $ps = [];
                            $hasPs = false;
                            if (count($body) >= 2 && $body[0]->Kind === ord('(') && $body[0]->Location->Index === $nameToken->EndLocation->Index) {
                                $endParam = -1;
                                foreach ($body as $bi => $bx) {
                                    if ($bi >= 1 && $bx->Kind === ord(')')) {
                                        $endParam = $bi;
                                        break;
                                    }
                                }
                                if ($endParam >= 0 && $endParam + 1 < count($body)) {
                                    $ps = [];
                                    foreach (array_slice($body, 0, $endParam) as $bx) {
                                        if ($bx->Kind === TokenKind::IDENTIFIER) {
                                            $ps[] = $bx->getStringValue();
                                        }
                                    }
                                    array_splice($body, 0, $endParam + 1);
                                    $hasPs = true;
                                }
                            }
                            $define = new Define(
                                $nameToken->getStringValue(),
                                $hasPs,
                                $ps,
                                $body
                            );
                            if (trim($define->Name) !== '') {
                                $defines[$define->Name] = $define;
                            }
                        } else {
                            $report->warning(1025, $tokens[$i]->Location, $tokens[$eol - 1]->EndLocation, 'Incomplete #define');
                        }
                        break;
                    case 'include':
                        if ($eol - $i > 2) {
                            $relative = $tokens[$i + 2]->Kind === TokenKind::STRING_LITERAL;
                            $iname = '';
                            for ($j = $i + 2; $j < $eol; $j++) {
                                $k = $tokens[$j]->Kind;
                                if ($k === ord('/') || $k === ord('\\') || $k === ord('.')) {
                                    $iname .= chr($k);
                                } elseif ($k === TokenKind::IDENTIFIER || $k === TokenKind::STRING_LITERAL) {
                                    $iname .= $tokens[$j]->getStringValue();
                                }
                            }
                            $insertTokens = $include($iname, $relative);
                            if ($insertTokens === null) {
                                $report->warning(1027, $tokens[$i + 2]->Location, $tokens[$eol - 1]->EndLocation, 'Failed to find file');
                            }
                        } else {
                            $report->warning(1026, $tokens[$i]->Location, $tokens[$eol - 1]->EndLocation, 'Incomplete #include');
                        }
                        break;
                    case 'endif':
                    case 'else':
                        $report->warning(1028, $tokens[$i]->Location, $tokens[$eol - 1]->EndLocation, 'Unexpected preprocessor directive');
                        break;
                    case 'if':
                    case 'ifdef':
                    case 'ifndef':
                        $isTrue = true;
                        if ($tokenValueString === 'if') {
                            $isTrue = self::evalIfCondition($defines, array_slice($tokens, $i + 2, $eol - ($i + 2)));
                        } else {
                            $s = ($i + 2 < count($tokens) && is_string($tokens[$i + 2]->Value)) ? $tokens[$i + 2]->Value : null;
                            $isDefined = $s !== null && isset($defines[$s]);
                            $isTrue = $tokenValueString === 'ifdef' ? $isDefined : !$isDefined;
                        }

                        $elseStartIndex = -1;
                        $elseEndIndex = -1;
                        $endifStartIndex = count($tokens);
                        $endifEndIndex = count($tokens);
                        $ifDepth = 1;
                        for ($j = $i + 3; $j < count($tokens) - 1 && $endifEndIndex === count($tokens); $j++) {
                            if ($tokens[$j]->Kind === ord('#') && is_string($tokens[$j + 1]->Value)) {
                                $eis = $tokens[$j + 1]->Value;
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
                        $report->warning(1024, $tokens[$i]->Location, $tokens[$eol - 1]->EndLocation, 'Cannot understand preprocessor');
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

    private static function evalIfCondition(array $defines, array $tokens): bool
    {
        try {
            $report = new Report();
            $expressions = [];
            foreach ($defines as $d) {
                if (count($d->Body) === 0) {
                    continue;
                }
                $e = CParser::tryParseExpression($report, $d->Body);
                if ($e !== null) {
                    $expressions[$d->Name] = $e;
                }
            }
            $expression = CParser::tryParseExpression($report, $tokens);
            if ($expression === null) {
                return false;
            }
            $context = new PreprocessorContext($report, $defines, $expressions);
            $value = $expression->evalConstant($context);
            return $value->Int32Value !== 0;
        } catch (\Exception $ex) {
            return false;
        }
    }

    private static function readDefineArgs(int $startIndex, array $tokens): array
    {
        $defines = [];

        if ($startIndex < 0 || $startIndex >= count($tokens) || $tokens[$startIndex]->Kind !== ord('(')) {
            return [$defines, 0];
        }

        $parenDepth = 0;
        $i = $startIndex;
        $startArgIndex = $startIndex + 1;
        for (; $i < count($tokens) && $startArgIndex > $startIndex && $tokens[$i]->Kind !== TokenKind::EOL; $i++) {
            switch ($tokens[$i]->Kind) {
                case ord('('):
                    $parenDepth++;
                    break;
                case ord(','):
                    if ($parenDepth === 1) {
                        $body = array_slice($tokens, $startArgIndex, $i - $startArgIndex);
                        $defines[] = new Define($body);
                        $startArgIndex = $i + 1;
                    }
                    break;
                case ord(')'):
                    $parenDepth--;
                    if ($parenDepth === 0) {
                        $body = array_slice($tokens, $startArgIndex, $i - $startArgIndex);
                        $defines[] = new Define($body);
                        $startArgIndex = -1;
                    }
                    break;
            }
        }

        return [$defines, $i - $startIndex];
    }
}

class Define
{
    public $Name;
    public $Parameters;
    public $HasParameters;
    public $Body;

    public function __construct($bodyOrName, $hasParameters = false, $parameters = [], $body = [])
    {
        if (is_array($bodyOrName) && func_num_args() === 1) {
            $this->Name = '';
            $this->HasParameters = false;
            $this->Parameters = [];
            $this->Body = $bodyOrName;
        } else {
            $this->Name = $bodyOrName;
            $this->HasParameters = $hasParameters;
            $this->Parameters = $parameters;
            $this->Body = $body;
        }
    }

    public function __toString(): string
    {
        return $this->Name . ': [' . implode(', ', $this->Body) . ']';
    }
}

class PreprocessorContext
{
    private $report;
    private $defines;
    private $expressions;

    public function __construct(Report $report, array $defines, array $expressions)
    {
        $this->report = $report;
        $this->defines = $defines;
        $this->expressions = $expressions;
    }

    public function tryResolveVariable(string $name, ?array $argTypes): ?ResolvedVariable
    {
        if (isset($this->expressions[$name])) {
            $expression = $this->expressions[$name];
            $nex = $this->expressions;
            $nctx = new PreprocessorContext($this->report, $this->defines, $nex);
            $value = $expression->evalConstant($nctx);
            return new ResolvedVariable($value, PPCBasicType::SignedInt);
        }
        return $this->baseTryResolveVariable($name, $argTypes);
    }

    private function baseTryResolveVariable(string $name, ?array $argTypes): ?ResolvedVariable
    {
        return null;
    }
}

class ResolvedVariable
{
    public $Value;
    public $Type;

    public function __construct($value, $type)
    {
        $this->Value = $value;
        $this->Type = $type;
    }
}

class CBasicType
{
    public static $SignedInt;

    public static function init(): void
    {
        if (self::$SignedInt === null) {
            self::$SignedInt = new CBasicType('int');
        }
    }

    public $Name;

    public function __construct(string $name)
    {
        $this->Name = $name;
    }
}

CBasicType::init();
