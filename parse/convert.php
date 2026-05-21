<?php
/**
 * Convert CParser_.code (C# switch case actions) to PHP, then generate cparser.php
 * by replacing the switch body in cparser_tmpl.php.
 *
 * Usage: php parse/convert.php
 * Output: parse/cparser.php
 */

$code = file_get_contents(__DIR__ . '/CParser_.code') or die("Error: Cannot read CParser_.code\n");

// === Step 1: Parse into case blocks ===
$lines = explode("\n", $code);
$caseBlocks = [];
$braceDepth = 0;
$currentCase = null;

foreach ($lines as $line) {
    $t = trim($line);
    if ($t === '') continue;
    if ($t === 'switch yyn {') continue;

    if (substr($t, 0, 4) === 'case') {
        $rest = rtrim(substr($t, 4), ':');
        $parts = [];
        foreach (explode(',', $rest) as $h) {
            $h = trim($h);
            if ($h !== '') $parts[] = 'case ' . $h;
        }
        $currentCase = ['header' => implode(': ', $parts) . ':', 'body' => []];
        continue;
    }

    if ($currentCase === null) continue;

    $opens = substr_count($t, '{');
    $closes = substr_count($t, '}');
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
    }
}
if ($currentCase) $caseBlocks[] = $currentCase;

// === Step 2: Convert each case body ===
$converted = [];
foreach ($caseBlocks as $cb) {
    $body = [];
    foreach ($cb['body'] as $bl) {
        $body[] = convertLine($bl);
    }
    $converted[] = ['header' => $cb['header'], 'body' => $body];
}

// === Step 3: Assemble switch body ===
$switchBody = '';
foreach ($converted as $cc) {
    $switchBody .= "\t" . $cc['header'] . "\n";
    $switchBody .= "\t{\n";
    foreach ($cc['body'] as $b) $switchBody .= $b . "\n";
    $switchBody .= "\t\tbreak;\n";
    $switchBody .= "\t}\n";
}

// === Step 4: Merge into template ===
$template = file_get_contents(__DIR__ . '/cparser_tmpl.php') or die("Error: Cannot read cparser_tmpl.php\n");

$pos = strpos($template, 'switch ($yyN) {');
if ($pos === false) die("Error: Cannot find switch statement\n");

$brace = strpos($template, '{', $pos);
if ($brace === false) die("Error: Cannot find switch brace\n");

$depth = 0;
$end = $brace;
for ($i = $brace; $i < strlen($template); $i++) {
    if ($template[$i] === '{') $depth++;
    elseif ($template[$i] === '}') $depth--;
    if ($depth === 0) { $end = $i; break; }
}
if ($end <= $brace) die("Error: Cannot find switch closing brace\n");

$output = substr($template, 0, $brace + 1) . "\n" . $switchBody . substr($template, $end);
file_put_contents(__DIR__ . '/cparser.php', $output) or die("Error: Cannot write cparser.php\n");

echo "Generated parse/cparser.php successfully.\n";

// ================================================================
// convertLine: C# -> PHP
// ================================================================

function convertLine(string $line): string
{
    if (trim($line) === '') return $line;

    // ---- Exact C# -> PHP string replacements (use raw yyVals/yyTop; generic regex adds $this-> later) ----
    $line = str_replace(
        'throw new NotSupportedException (String.Format ("\'{0}\' not supported", yyVals[-1+yyTop]));',
        'throw new \RuntimeException(sprintf("\'%s\' not supported", yyVals[-1 + yyTop]));',
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
        'String.Format ("\'{0}\' not supported", yyVals[-1+yyTop])',
        'sprintf("\'%s\' not supported", yyVals[-1 + yyTop])',
        $line
    );
    $line = str_replace('throw new NotSupportedException (', 'throw new \RuntimeException (', $line);

    // ---- Main variable/type references (must use regex for substring overlap: yyVal⊂yyVals) ----
    $line = preg_replace('/(?<!\$)yyVals\[/', '$yyVals[', $line);
    $line = preg_replace('/\byyVal\b/', '$this->yyVal', $line);
    $line = preg_replace('/\byyTop\b/', '$this->yyTop', $line);
    $line = str_replace('lexer.', '$this->lexer->', $line);
    $line = str_replace('_tu', '$this->_tu', $line);

    // ---- Helper method names ----
    $line = str_replace('AddDeclaration(', '$this->addDeclaration(', $line);
    $line = str_replace('GetLocation(', '$this->getLocation(', $line);
    $line = str_replace('FixPointerAndArrayPrecedence(', '$this->fixPointerAndArrayPrecedence(', $line);
    $line = str_replace('MakeArrayDeclarator(', '$this->makeArrayDeclarator(', $line);

    // ---- Named argument labels ----
    $line = str_replace('innerDeclarator: ', '', $line);
    $line = str_replace('parameters: ', '', $line);
    $line = str_replace('ctorArgumentValue: ', '', $line);

    // ---- Compiler.VariableScope.X -> VariableScope::X ----
    $line = str_replace('Compiler.VariableScope.', 'VariableScope::', $line);

    // ---- foreach (var X in Y) -> foreach (Y as $X) ----
    // Use paren-balanced group to avoid stopping at first ')' inside cast like (List<Foo>)
    $line = preg_replace_callback(
        '/foreach\s*\(\s*var\s+(\w+)\s+in\s+((?:[^()]+|\([^()]*\))+)\)/',
        fn($m) => 'foreach (' . $m[2] . ' as $' . $m[1] . ')',
        $line
    );

    // ---- C# -> PHP constructs ----
    $line = str_replace('var ', '', $line);
    $line = str_replace('.ToString()', '', $line);
    $line = str_replace('String.Format (', 'sprintf(', $line);
    $line = str_replace('String.Format(', 'sprintf(', $line);
    $line = str_replace('};', '];', $line);
    $line = str_replace('})', '])', $line);
    // .Add(...) -> [] = ...
    $line = preg_replace('/\.Add\s*\(((?:[^()]+|\([^()]*\))*)\)/', '[] = $1', $line);

    // ---- Remove C# type casts: (TypeName) ----
    $line = preg_replace('/\(\s*[A-Z][A-Za-z<>]*(?:\s*,\s*[A-Z][A-Za-z<>]*)*\s*\)\s*/', '', $line);

    // ---- Remove C# type declarations: TypeName varname =  ---
    $line = preg_replace('/\b[A-Z][A-Za-z<>]*(?:\.[A-Z][A-Za-z<>]*)*\s+([a-z]\w*)\s*=\s*/', '$1 = ', $line);

    // ---- new List<...>() -> [] ----
    $line = preg_replace('/new\s+List\s*<[^>]*>\s*\(\s*\)/', '[]', $line);
    $line = preg_replace('/new\s+List\s*<[^>]*>\s*\(\s*\d+\s*\)\s*\{/', '[', $line);
    $line = preg_replace('/new\s+List\s*<[^>]*>\s*\{/', '[', $line);

    // ---- C# is-pattern matching ----
    $line = preg_replace_callback(
        '/\$yyVals\[([^\]]+)\]\s+is\s+(\w+)\s+\w+\s*&&\s*\w+\s*==\s*\2\.(\w+)/',
        fn($m) => "\$yyVals[{$m[1]}] === {$m[2]}::{$m[3]}",
        $line
    );
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

    // ---- C# null-conditional ?. -> instanceof ----
    $line = preg_replace_callback(
        '/\(\s*(\$yyVals\[[^\]]+\])\s+as\s+(\w+)\s*\)\s*\?\.\s*(\w+)/',
        fn($m) => "({$m[1]} instanceof {$m[2]} ? {$m[1]}->{$m[3]} : null)",
        $line
    );

    // ---- Static member: Enum.Member -> Enum::Member ----
    $line = preg_replace('/\b(ConstantExpression|Expression|Statement|CBasicType|CPointerType|Location)\.(\w+)\b/', '$1::$$2', $line);
    $line = preg_replace('/\b(Unop|Binop|RelationalOp|LogicOp|TypeQualifiers|FunctionSpecifier|TypeSpecifierKind|StorageClassSpecifier|DeclarationsVisibility|VariableScope|Signedness|TokenKind)\.(\w+)\b/', '$1::$2', $line);

    // ---- C# member access on parenthesized expressions: ($yyVals[...]).Prop -> ($yyVals[...])->Prop ----
    $line = preg_replace('/\)\.([A-Z]\w*)/', ')->$1', $line);

    // ---- Method calls: .Method( -> ->Method( ----
    $line = str_replace('.Push(', '->Push(', $line);
    $line = str_replace('.ToBlock(', '->toBlock(', $line);
    $line = str_replace('.asKind(', '->asKind(', $line);

    // ---- String concatenation "str" + expr -> "str" . expr ----
    $line = str_replace('" + ', '" . ', $line);
    $line = str_replace('+ "', '. "', $line);

    // ---- Add $ to local variable names ----
    $localVars = ['t','l','ds','idl','fdecl','left','a','p','i','b','r','list',
        's','m','d','f','inner','n','tok','isTypedef','hex','vals',
        'onlydigits','islong','isunsigned','isfloat','ishex','done',
        'advanceAfterEscape','nr','nl','output','code','err','msg','loc',
        'tokens','arr','result','count','item','numItemValues','itemOffset',
        'elementType','curSize','val','evalType','cval','size','sign',
        'tempThisOffset','numValues','baseOffset','newSlot','slot',
        'methods','method','mt','score','bestScore','best','argTypes',
        'resolvedOp','res','funcType','emptyTypes','emptyArgs','freeArgTypes',
        'total','offset','decls','ctorArgumentValue','op','mds','stmts','ts','args'];

    foreach ($localVars as $v) {
        $q = preg_quote($v, '/');
        $line = preg_replace('/(?<!\$)(?<!\w)' . $q . '(?=\s*->)/', '$' . $v, $line);
        $line = preg_replace('/(?<!\$)(?<!\w)' . $q . '\s*(?=\s*[=;,)\]\[ ])/', '$' . $v, $line);
        $line = preg_replace('/(?<!\$)(?<!\w)' . $q . '\.([A-Z]\w*)/', '$' . $v . '->$1', $line);
    }

    // ---- Fix $b/$l in case 65 ----
    $line = str_replace('new BinaryExpression ($left, $b,', 'new BinaryExpression ($left, $yyVals[-1 + $this->yyTop],', $line);
    $line = str_replace('new LogicExpression ($left, $l,', 'new LogicExpression ($left, $yyVals[-1 + $this->yyTop],', $line);

    // ---- Remove C# object initializer: { Prop = val, ... } ----
    $line = preg_replace('/\{\s*(?:\w+\s*=\s*(?:true|false|null|\d+|"[^"]*"|\w+(?:::?\w+)?)\s*(?:,\s*)?)+[\}\]]/', '', $line);

    // ---- new Class; -> new Class() ----
    $line = preg_replace('/new\s+(\w+)\s*;/', 'new $1();', $line);

    // ---- new Foo(...)->method( -> (new Foo(...))->method( (PHP 7.4 compat) ----
    $line = preg_replace(
        '/(new\s+\w+\s*\((?:[^()]|\([^()]*\))*\))\s*->(\w+\s*\()/',
        '($1)->$2',
        $line
    );

    return $line;
}
