<?php
/**
 * Convert CParser_.code (C# switch case actions) to PHP, then generate cparser.php
 * by replacing the switch body in cparser_tmpl.php.
 *
 * Usage: php parse/convert.php
 * Output: parse/cparser.php
 */

$code = file_get_contents(__DIR__ . '/CParser_.code');
if ($code === false) die("Error: Cannot read CParser_.code\n");

// === Step 1: Parse into case blocks ===
$lines = explode("\n", $code);
$caseBlocks = [];
$braceDepth = 0;
$currentCase = null;

foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === '') continue;

    if (strpos($trimmed, 'switch yyn {') === 0) continue;

    if (preg_match('/^case\s/', $trimmed)) {
        $rest = substr($trimmed, 4);
        $rest = rtrim($rest, ':');
        $headers = preg_split('/\s*,\s*/', $rest);
        $phpHeaders = [];
        foreach ($headers as $h) {
            $h = trim($h);
            if ($h !== '') $phpHeaders[] = 'case ' . $h;
        }
        $currentCase = ['header' => implode(': ', $phpHeaders) . ':', 'body' => []];
        continue;
    }

    if ($currentCase === null) continue;

    $opens = substr_count($trimmed, '{');
    $closes = substr_count($trimmed, '}');
    $net = $opens - $closes;

    if ($braceDepth === 0 && $opens > 0) {
        $braceDepth = $net;
        continue;
    }

    if ($braceDepth > 0) {
        $newDepth = $braceDepth + $net;
        if ($newDepth > 0) {
            $currentCase['body'][] = $line;
            $braceDepth = $newDepth;
        } else {
            $caseBlocks[] = $currentCase;
            $currentCase = null;
            $braceDepth = 0;
        }
        continue;
    }
}
if ($currentCase !== null) $caseBlocks[] = $currentCase;

// === Step 2: Convert each case body ===
$convertedCases = [];
foreach ($caseBlocks as $cb) {
    $bodyLines = $cb['body'];
    $convertedBody = [];
    foreach ($bodyLines as $bl) {
        $convertedBody[] = convertLine($bl);
    }
    $convertedCases[] = ['header' => $cb['header'], 'body' => $convertedBody];
}

// === Step 3: Generate switch body ===
$switchBodyLines = [];
foreach ($convertedCases as $cc) {
    $switchBodyLines[] = "\t" . $cc['header'];
    $switchBodyLines[] = "\t{";
    foreach ($cc['body'] as $b) {
        $switchBodyLines[] = $b;
    }
    $switchBodyLines[] = "\t\tbreak;";
    $switchBodyLines[] = "\t}";
}
$switchBody = implode("\n", $switchBodyLines);

// === Step 4: Write into cparser.php ===
$template = file_get_contents(__DIR__ . '/cparser_tmpl.php');
if ($template === false) die("Error: Cannot read cparser_tmpl.php\n");

$switchStart = strpos($template, 'switch ($yyN) {');
if ($switchStart === false) die("Error: Cannot find switch statement in template\n");

$bracePos = strpos($template, '{', $switchStart);
if ($bracePos === false) die("Error: Cannot find switch opening brace\n");

$depth = 0;
$switchEnd = $bracePos;
for ($i = $bracePos; $i < strlen($template); $i++) {
    if ($template[$i] === '{') $depth++;
    elseif ($template[$i] === '}') $depth--;
    if ($depth === 0) { $switchEnd = $i; break; }
}
if ($switchEnd <= $bracePos) die("Error: Cannot find switch closing brace\n");

$newContent = substr($template, 0, $bracePos + 1) . "\n" . $switchBody . "\n" . substr($template, $switchEnd);
$result = file_put_contents(__DIR__ . '/cparser.php', $newContent);
if ($result === false) die("Error: Cannot write cparser.php\n");

echo "Generated parse/cparser.php successfully.\n";

// ================================================================
// convertLine: C# → PHP
// ================================================================

function convertLine(string $line): string
{
    $trimmed = trim($line);
    if ($trimmed === '') return $line;

    // === A. Exact string replacements (case-specific) ===

    $line = str_replace(
        "throw new NotSupportedException (String.Format (\"'{0}' not supported\", yyVals[-1+yyTop]));",
        'throw new \RuntimeException(sprintf("\'%s\' not supported", $yyVals[-1 + $this->yyTop]));',
        $line
    );
    $line = str_replace(
        'throw new NotSupportedException ("Syntax: \'(\' type_name \')\' \'{\' initializer_list \'}\'");',
        'throw new \RuntimeException("Syntax: \'(\' type_name \')\' \'{\' initializer_list \'}\'");',
        $line
    );
    $line = str_replace(
        'throw new NotSupportedException ("Syntax: \'(\' type_name \')\' \'{\' initializer_list \',\' \'}\'");',
        'throw new \RuntimeException("Syntax: \'(\' type_name \')\' \'{\' initializer_list \',\' \'}\'");',
        $line
    );
    $line = str_replace(
        "String.Format (\"'{0}' not supported\", yyVals[-1+yyTop])",
        'sprintf("\'%s\' not supported", $yyVals[-1 + $this->yyTop])',
        $line
    );

    // === B. Generic throw new NotSupportedException ===
    $line = preg_replace('/throw\s+new\s+NotSupportedException\s*/', 'throw new \\RuntimeException ', $line);

    // === C. Variable and member references ===

    // yyVals[ -> $yyVals[ (only if not already $)
    $line = preg_replace('/(?<!\$)yyVals\[/', '$yyVals[', $line);

    // yyTop -> $this->yyTop (not after ->)
    $line = preg_replace('/(?<!->)yyTop\b/', '$this->yyTop', $line);

    // yyVal -> $this->yyVal (not after -> or $)
    $line = preg_replace('/(?<!->)(?<!\$)yyVal\b/', '$this->yyVal', $line);

    // lexer. -> $this->lexer->
    $line = preg_replace('/(?<!\$)lexer\./', '$this->lexer->', $line);

    // _tu -> $this->_tu
    $line = preg_replace('/\b_tu\b/', '$this->_tu', $line);

    // Helpers
    $line = preg_replace('/\bAddDeclaration\s*\(/', '$this->addDeclaration(', $line);
    $line = preg_replace('/\bGetLocation\s*\(/', '$this->getLocation(', $line);
    $line = preg_replace('/\bFixPointerAndArrayPrecedence\s*\(/', '$this->fixPointerAndArrayPrecedence(', $line);
    $line = preg_replace('/\bMakeArrayDeclarator\s*\(/', '$this->makeArrayDeclarator(', $line);

    // === D. Named argument removal ===
    $line = preg_replace('/\b(innerDeclarator|parameters|ctorArgumentValue)\s*:\s*/', '', $line);

    // === E. Compiler.VariableScope.X -> VariableScope::X ===
    $line = preg_replace('/Compiler\s*\.\s*VariableScope\s*\.\s*(\w+)/', 'VariableScope::$1', $line);

    // === F. C# constructs -> PHP ===

    // var keyword
    $line = preg_replace('/\bvar\s+(?=\w+\s*[=;])/', '', $line);

    // C# type casts: (Type)  — no dots in type name
    $line = preg_replace('/\(\s*[A-Z][A-Za-z<>]*(?:\s*,\s*[A-Z][A-Za-z<>]*)*\s*\)\s*/', '', $line);

    // Type declarations: TypeName varName =
    $line = preg_replace('/\b[A-Z][A-Za-z<>]*(?:\.[A-Z][A-Za-z<>]*)*\s+([a-z]\w*)\s*=\s*/', '$1 = ', $line);

    // .ToString() remove
    $line = preg_replace('/\.ToString\s*\(\s*\)/', '', $line);

    // String.Format -> sprintf
    $line = preg_replace('/String\s*\.\s*Format\s*\(/', 'sprintf(', $line);

    // new List<...>() -> []
    $line = preg_replace('/new\s+List\s*<[^>]*>\s*\(\s*\)/', '[]', $line);
    $line = preg_replace('/new\s+List\s*<[^>]*>\s*\(\s*\d+\s*\)\s*\{/', '[', $line);
    $line = preg_replace('/new\s+List\s*<[^>]*>\s*\{/', '[', $line);

    // } -> ];  and  } -> ]  (before )
    $line = preg_replace('/\}\s*;/', '];', $line);
    $line = preg_replace('/\}(?=\s*\))/', ']', $line);

    // .Add( -> [] =
    $line = preg_replace('/\.\s*Add\s*\(((?:[^()]*(?:\([^()]*\))?)*)\)\s*/', '[] = $1', $line);

    // === G. C# is-pattern matching (BEFORE static member .->::) ===

    // Specific: "X is Type v && v == Type.Member"
    $line = preg_replace_callback(
        '/\$yyVals\[([^\]]+)\]\s+is\s+(\w+)\s+\w+\s*&&\s*\w+\s*==\s*\2\.(\w+)/',
        function ($m) {
            return "\$yyVals[{$m[1]}] === {$m[2]}::{$m[3]}";
        },
        $line
    );

    // Generic fallbacks (with negative lookahead to avoid matching if "&&" follows)
    $line = preg_replace(
        '/\$yyVals\[([^\]]+)\]\s+is\s+Binop\s+\w+(?!\s*&&)/',
        'is_int($yyVals[$1]) && $yyVals[$1] >= 0 && $yyVals[$1] <= 9',
        $line
    );
    $line = preg_replace(
        '/\$yyVals\[([^\]]+)\]\s+is\s+RelationalOp\s+\w+(?!\s*&&)/',
        'is_int($yyVals[$1]) && $yyVals[$1] >= 0 && $yyVals[$1] <= 5',
        $line
    );
    $line = preg_replace(
        '/\$yyVals\[([^\]]+)\]\s+is\s+LogicOp\s+\w+(?!\s*&&)/',
        'in_array($yyVals[$1], [0, 1], true)',
        $line
    );

    // === H. C# null-conditional ?. ===
    $line = preg_replace_callback(
        '/\(\s*(\$yyVals\[[^\]]+\])\s+as\s+(\w+)\s*\)\s*\?\.\s*(\w+)/',
        function ($m) {
            return "({$m[1]} instanceof {$m[2]} ? {$m[1]}->{$m[3]} : null)";
        },
        $line
    );

    // === I. Static enum/class: Enum.Member -> Enum::$Member ===
    $line = preg_replace('/\b(ConstantExpression|Expression|Statement|CBasicType|CPointerType|Location)\.(\w+)\b/', '$1::$$2', $line);
    $line = preg_replace('/\b(Unop|Binop|RelationalOp|LogicOp|TypeQualifiers|FunctionSpecifier|TypeSpecifierKind|StorageClassSpecifier|DeclarationsVisibility|VariableScope|Signedness|TokenKind)\.(\w+)\b/', '$1::$2', $line);

    // === J. Parenthesized expr member access: ($yyVals[...]).Property -> ($yyVals[...])->Property ===
    $line = preg_replace('/\)\.([A-Z]\w*)/', ')->$1', $line);

    // === K. Method calls: .Method( -> ->Method( ===
    $line = preg_replace('/\.Push\s*\(/', '->Push(', $line);
    $line = preg_replace('/\.ToBlock\s*\(/', '->toBlock(', $line);
    $line = preg_replace('/\.asKind\s*\(/', '->asKind(', $line);

    // === K. String concatenation + -> . ===
    $line = preg_replace('/("(?:[^"\\\\]|\\\\.)*")\s*\+/', '$1 .', $line);
    $line = preg_replace('/\+\s*("(?:[^"\\\\]|\\\\.)*")/', '. $1', $line);

    // === L. foreach (var X in Y) -> foreach (Y as $X) ===
    $line = preg_replace_callback(
        '/foreach\s*\(\s*var\s+(\w+)\s+in\s+(.+?)\)/',
        function ($m) {
            return 'foreach (' . $m[2] . ' as $' . $m[1] . ')';
        },
        $line
    );

    // === M. Add $ to local variables ===
    $localVars = [
        't', 'l', 'ds', 'idl', 'fdecl', 'left', 'a', 'p', 'i', 'b', 'r', 'list',
        's', 'm', 'd', 'f', 'inner', 'n', 'tok', 'isTypedef', 'hex', 'vals',
        'onlydigits', 'islong', 'isunsigned', 'isfloat', 'ishex', 'done',
        'advanceAfterEscape', 'nr', 'nl', 'output', 'code', 'err', 'msg', 'loc',
        'tokens', 'arr', 'result', 'count', 'item', 'numItemValues', 'itemOffset',
        'elementType', 'curSize', 'val', 'evalType', 'cval', 'size', 'sign',
        'tempThisOffset', 'numValues', 'baseOffset', 'newSlot', 'slot',
        'methods', 'method', 'mt', 'score', 'bestScore', 'best', 'argTypes',
        'resolvedOp', 'res', 'funcType', 'emptyTypes', 'emptyArgs', 'freeArgTypes',
        'total', 'offset', 'decls', 'ctorArgumentValue', 'op', 'mds', 'stmts',
        'ts', 'args',
    ];

    foreach ($localVars as $varName) {
        // varName -> -> $varName->
        $line = preg_replace(
            '/(?<!\$)(?<!\w)' . preg_quote($varName, '/') . '(?=\s*->)/',
            '\$' . $varName,
            $line
        );
        // varName = ; , ) ] [   (any delimiter following)
        $line = preg_replace(
            '/(?<!\$)(?<!\w)' . preg_quote($varName, '/') . '\s*(?=\s*[=;,)\]\[ ])/',
            '\$' . $varName,
            $line
        );
        // varName.Property -> $varName->Property  (C# member access)
        $line = preg_replace(
            '/(?<!\$)(?<!\w)' . preg_quote($varName, '/') . '\.([A-Z]\w*)/',
            '\$' . $varName . '->$1',
            $line
        );
    }

    // === N. Fix $b/$l in case 65 (after $ prefixing) ===
    $line = preg_replace(
        '/new\s+BinaryExpression\s*\(\s*\$left\s*,\s*\$b\s*,/',
        'new BinaryExpression ($left, $yyVals[-1 + $this->yyTop],',
        $line
    );
    $line = preg_replace(
        '/new\s+LogicExpression\s*\(\s*\$left\s*,\s*\$l\s*,/',
        'new LogicExpression ($left, $yyVals[-1 + $this->yyTop],',
        $line
    );

    // === O. C# object initializer: { Prop = val } or { Prop = val, ... } remove ===
    $line = preg_replace('/\{\s*(?:\w+\s*=\s*(?:true|false|null|\d+|"[^"]*"|\w+(?:::?\w+)?)\s*(?:,\s*)?)+[\}\]]/', '', $line);

    // === P. new Class; -> new Class() ===
    $line = preg_replace('/new\s+(\w+)\s*;/', 'new $1();', $line);

    // === Q. new Foo(...)->method(  ->  (new Foo(...))->method(  (PHP 7.4 compat) ===
    $line = preg_replace(
        '/(new\s+\w+\s*\((?:[^()]|\([^()]*\))*\))\s*->(\w+\s*\()/',
        '($1)->$2',
        $line
    );

    return $line;
}
