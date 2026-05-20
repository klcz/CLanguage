<?php
/**
 * Generator: Reads CLanguage/Parser/CParser.cs and generates parse/CParser.php
 * PHP 7.4 compatible.
 *
 * Usage: php parse/gen_all.php
 */

// ============================================================
// Configuration
// ============================================================
$csFile = __DIR__ . '/../CLanguage/Parser/CParser.cs';
$csImplFile = __DIR__ . '/../CLanguage/Parser/CParserImpl.cs';
$outFile = __DIR__ . '/CParser.php';
$tokenKindFile = __DIR__ . '/TokenKind.php';

// ============================================================
// Step 1: Read source files
// ============================================================
echo "=== Step 1: Reading source files...\n";
$csLines = file($csFile, FILE_IGNORE_NEW_LINES);
$implLines = file($csImplFile, FILE_IGNORE_NEW_LINES);
$totalLines = count($csLines);
echo "  CParser.cs: $totalLines lines\n";

// ============================================================
// Step 2: Extract TokenKind constants
// ============================================================
echo "\n=== Step 2: Extracting TokenKind constants...\n";
// Read from existing TokenKind.php to preserve exact values
$tkContent = file_get_contents($tokenKindFile);
preg_match_all('/const\s+(\w+)\s*=\s*(-?\d+)/', $tkContent, $tkMatches, PREG_SET_ORDER);
$tokenConstants = [];
foreach ($tkMatches as $m) {
    $tokenConstants[$m[1]] = (int)$m[2];
}
$tokenNameToValue = $tokenConstants;

function genTokenKind(array $constants): string
{
    $code = "<?php\nnamespace parse;\n\nclass TokenKind\n{\n";
    foreach ($constants as $name => $value) {
        if ($name === 'yyErrorCode') continue;
        $code .= "    const $name = $value;\n";
    }
    $code .= "    const yyErrorCode = 256;\n";
    $code .= "}\n";
    return $code;
}

// ============================================================
// Step 3: Extract yyNames array
// ============================================================
echo "\n=== Step 3: Extracting yyNames array...\n";

function extractArray(array $lines, int $startLine, string $arrayName): ?array
{
    $inArray = false;
    $depth = 0;
    $result = [];
    $currentLine = '';
    for ($i = $startLine - 1; $i < count($lines); $i++) {
        $line = $lines[$i];
        if (!$inArray) {
            if (preg_match('/' . preg_quote($arrayName, '/') . '\s*=\s*\{/', $line)) {
                $inArray = true;
                $depth = 1;
                $rest = substr($line, strpos($line, '{') + 1);
                $currentLine = $rest;
                continue;
            }
        } else {
            $currentLine .= ' ' . $line;
            // Count braces
            foreach (str_split($line) as $ch) {
                if ($ch === '{') $depth++;
                if ($ch === '}') $depth--;
            }
            if ($depth <= 0) {
                // Remove trailing };
                $currentLine = preg_replace('/\}\s*;\s*$/', '', $currentLine);
                // Parse the values
                $currentLine = trim($currentLine);
                $parts = explode(',', $currentLine);
                foreach ($parts as $p) {
                    $p = trim($p);
                    if ($p !== '') {
                        $result[] = $p;
                    }
                }
                break;
            }
        }
    }
    return $result;
}

// Extract yyNames
$yyNamesRaw = extractArray($csLines, 395, 'yyNames');
if (!$yyNamesRaw) {
    die("ERROR: Could not extract yyNames\n");
}
echo "  yyNames: " . count($yyNamesRaw) . " entries\n";

// ============================================================
// Step 4: Extract parser table arrays
// ============================================================
echo "\n=== Step 4: Extracting parser tables...\n";

function extractTableLines(array $lines, int $scanStart, int $scanEnd): array
{
    $tables = [];
    $currentName = null;
    $currentData = [];
    $inTable = false;
    $braceDepth = 0;

    for ($i = $scanStart - 1; $i < $scanEnd && $i < count($lines); $i++) {
        $line = $lines[$i];
        $trimmed = trim($line);
        if ($trimmed === '' || preg_match('/^#(line|default|endregion)/', $trimmed)) continue;

        if (!$inTable) {
            if (preg_match('/(?:static readonly|protected static readonly)\s+short\s+\*?\s*\[\]\s*(\w+)\s*=\s*\{/', $line, $m)) {
                $currentName = $m[1];
                $currentData = [];
                $inTable = true;
                $braceDepth = 0;
                // Process content on same line after {
                $rest = substr($line, strpos($line, '{') + 1);
                if (trim($rest) !== '') {
                    $currentData[] = $rest;
                }
                $braceDepth += substr_count($rest, '{') - substr_count($rest, '}');
                continue;
            }
        } else {
            $currentData[] = $line;
            $braceDepth += substr_count($line, '{') - substr_count($line, '}');
            if ($braceDepth <= 0 && preg_match('/^\s*\};/', $line)) {
                // Remove last element (should be }; )
                array_pop($currentData);
                $tables[$currentName] = $currentData;
                $inTable = false;
                $currentName = null;
                $currentData = [];
            }
        }
    }
    return $tables;
}

$tables = extractTableLines($csLines, 2399, 3496);
$expectedTables = ['yyLhs', 'yyLen', 'yyDefRed', 'yyDgoto', 'yySindex', 'yyRindex', 'yyGindex', 'yyTable', 'yyCheck'];
foreach ($expectedTables as $t) {
    if (!isset($tables[$t])) {
        die("ERROR: Missing table '$t'\n");
    }
    echo "  $t: " . count($tables[$t]) . " lines\n";
}

// ============================================================
// Step 5: Extract switch(yyN) case actions from yyparseInternal
// ============================================================
echo "\n=== Step 5: Extracting switch(yyN) case actions...\n";

function extractSwitchCases(array $lines): array
{
    $cases = [];
    $inSwitch = false;
    $switchDepth = 0;
    $currentCase = null;
    $currentCode = '';
    $braceDepth = 0;

    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];
        $trimmed = trim($line);

        // Find "switch (yyN) {"
        if (!$inSwitch && preg_match('/switch\s*\(yyN\)\s*\{/', $trimmed)) {
            $inSwitch = true;
            $switchDepth = 1;
            // Count any extra braces on this line
            $switchDepth += substr_count($line, '{') - 1; // -1 for the switch's own {
            $switchDepth -= substr_count($line, '}');
            continue;
        }

        if (!$inSwitch) continue;

        // Track overall switch brace depth
        $switchDepth += substr_count($line, '{') - substr_count($line, '}');

        // If switch body is closed, stop
        if ($switchDepth <= 0) break;

        // Find "case N:"
        if (preg_match('/^case\s+(\d+):\s*$/', $trimmed, $m)) {
            // Save previous case
            if ($currentCase !== null) {
                $cases[$currentCase] = trim($currentCode);
            }
            $currentCase = (int)$m[1];
            $currentCode = '';
            $braceDepth = 0;
            continue;
        }

        if ($currentCase === null) continue;

        // Handle break statements
        if ($trimmed === 'break;') {
            $currentCode .= "break;\n";
            continue;
        }

        // Track brace depth for current case's action block
        $currentCode .= $line . "\n";
        foreach (str_split($line) as $ch) {
            if ($ch === '{') $braceDepth++;
            if ($ch === '}') $braceDepth--;
        }
    }

    // Save last case
    if ($currentCase !== null) {
        $cases[$currentCase] = trim($currentCode);
    }

    return $cases;
}

$switchCases = extractSwitchCases($csLines);
echo "  Extracted " . count($switchCases) . " switch case entries\n";

// ============================================================
// Step 6: Extract void case_XX() methods
// ============================================================
echo "\n=== Step 6: Extracting case_XX() methods...\n";

function extractCaseMethods(array $lines): array
{
    $methods = [];
    $currentName = null;
    $currentLines = [];
    $braceDepth = 0;
    $inMethod = false;

    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];
        $trimmed = trim($line);

        if (preg_match('/^void\s+(case_\d+)\s*\(\)\s*$/', $trimmed, $m)) {
            if ($inMethod) {
                $methods[$currentName] = $currentLines;
            }
            $currentName = $m[1];
            $currentLines = [];
            $inMethod = true;
            $braceDepth = 0;
            continue;
        }

        if ($inMethod) {
            if (preg_match('/^#(line|default)/', $trimmed)) continue;
            $currentLines[] = $line;
            $braceDepth += substr_count($line, '{') - substr_count($line, '}');
            if ($braceDepth <= 0 && preg_match('/^\s*\}/', $line)) {
                $methods[$currentName] = $currentLines;
                $inMethod = false;
                $currentName = null;
                $currentLines = [];
                $braceDepth = 0;
            }
        }
    }
    return $methods;
}

$caseMethods = extractCaseMethods($csLines);
echo "  Extracted " . count($caseMethods) . " case methods\n";

// ============================================================
// Step 7: Extract utility methods from CParserImpl.cs
// ============================================================
echo "\n=== Step 7: Extracting utility methods from CParserImpl.cs...\n";

function extractUtilityMethods(array $lines): array
{
    $methods = [];
    $currentName = null;
    $currentLines = [];
    $braceDepth = 0;
    $inMethod = false;
    $signatures = [
        'ParseTranslationUnit' => 'ParseTranslationUnit',
        'AddDeclaration' => 'AddDeclaration',
        'FixPointerAndArrayPrecedence' => 'FixPointerAndArrayPrecedence',
        'MakeArrayDeclarator' => 'MakeArrayDeclarator',
        'GetLocation' => 'GetLocation',
        'TryParseExpression' => 'TryParseExpression',
    ];

    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];
        $trimmed = trim($line);

        // Match method signatures
        foreach ($signatures as $name => $sig) {
            if (preg_match('/\b' . preg_quote($sig, '/') . '\s*\(/', $trimmed) && preg_match('/^\s*(public|private|protected|static|Declarator\??|Expression\??|Location|void)\s+/', $trimmed)) {
                if ($inMethod) {
                    $methods[$currentName] = $currentLines;
                }
                $currentName = $name;
                $currentLines = [$line];
                $inMethod = true;
                $braceDepth = substr_count($line, '{') - substr_count($line, '}');
                continue 2;
            }
        }

        if ($inMethod) {
            $currentLines[] = $line;
            $braceDepth += substr_count($line, '{') - substr_count($line, '}');
            if ($braceDepth <= 0 && preg_match('/^\s*\}/', $line)) {
                $methods[$currentName] = $currentLines;
                $inMethod = false;
                $currentName = null;
                $currentLines = [];
                $braceDepth = 0;
            }
        }
    }
    return $methods;
}

$utilityMethods = extractUtilityMethods($implLines);
echo "  Extracted " . count($utilityMethods) . " utility methods: " . implode(', ', array_keys($utilityMethods)) . "\n";

// ============================================================
// Step 8: Convert C# code to PHP 7.4
// ============================================================
echo "\n=== Step 8: Converting C# to PHP 7.4...\n";

function csharpToPhp(string $code): string
{
    // === Pre-processing ===
    // Remove #line / #nullable / #pragma directives (handles leading whitespace)
    $code = preg_replace('/^[ \t]*#(line|nullable|pragma|region|endregion).*$/m', '', $code);

    // Remove nullable type marker from type names: (Type?) -> (Type)
    $code = preg_replace('/\(\s*(\w+)\s*\?\s*\)/', '($1)', $code);

    // Remove C# type casts (Type)expr - handle specific known types and List<T> generics
    $types = implode('|', [
        'Expression', 'DeclarationSpecifiers', 'Block', 'Declarator', 'TypeQualifiers',
        'FunctionSpecifier', 'StorageClassSpecifier', 'TypeSpecifierKind', 'FunctionDeclarator',
        'Statement', 'TypeSpecifier', 'StructuredInitializer', 'Initializer',
        'InitializerDesignation', 'SwitchCase', 'Declaration',
        'List<Expression>', 'List<InitDeclarator>', 'List<ParameterDeclaration>',
        'List<BaseSpecifier>', 'List<SwitchCase>', 'List<Declaration>',
        'Unop', 'TypeName', 'BaseSpecifier', 'EnumeratorStatement',
        'Pointer', 'VarParameter', 'MemberName', 'DeclarationsVisibility',
        'InitDeclarator', 'ParameterDeclaration',
        'string', 'int',
    ]);
    $code = preg_replace('/\((?:' . $types . ')\)/', '', $code);
    // General List<T> cast removal (any generic List type)
    $code = preg_replace('/\(List<\w+>\)/', '', $code);

    // C# null-conditional ?. -> ->
    $code = preg_replace('/\?\./', '->', $code);

    // Remove C# namespace prefixes
    $code = preg_replace('/\bCompiler\./', '', $code);

    // === Variable/Value transforms ===
    // yyVals[N+yyTop] -> $yyVals[$yyTop+N]  (N can be negative or positive)
    $code = preg_replace('/yyVals\[(-?\d+)\s*\+\s*yyTop\]/', '\$yyVals[\$yyTop+$1]', $code);
    $code = preg_replace('/yyVals\[yyTop\]/', '\$yyVals[\$yyTop]', $code);
    // Bare yyVals/yyVal/yyTop -> $yyVals/$yyVal/$yyTop (not already prefixed with $)
    $code = preg_replace('/(?<!\$)yyVals\b/', '\$yyVals', $code);
    $code = preg_replace('/(?<!\$)yyVal\b/', '\$yyVal', $code);
    $code = preg_replace('/(?<!\$)yyTop\b/', '\$yyTop', $code);

    // var name = expr -> $name = expr (handles C# 'var' keyword)
    $code = preg_replace('/\bvar\s+(\w+)\s*=/', '\$$1 =', $code);

    // TypeName name = expr -> $name = expr (handles explicit C# types before variables)
    $csVarTypes = [
        'DeclarationSpecifiers', 'List<InitDeclarator>', 'List<ParameterDeclaration>',
        'List<BaseSpecifier>', 'List<SwitchCase>', 'List<Declaration>',
        'List<Expression>', 'List<Statement>', 'Expression', 'Statement',
        'Declarator', 'Block', 'Pointer', 'TypeName',
        'Initializer', 'ExpressionStatement', 'Unop', 'Binop', 'RelationalOp', 'LogicOp',
        'TypeSpecifier', 'ConstantExpression',
        'string', 'int', 'bool', 'char', 'float', 'double',
    ];
    foreach ($csVarTypes as $t) {
        $escaped = preg_quote($t, '/');
        $code = preg_replace('/\b' . $escaped . '\s+(\w+)\s*=\s*/', '\$$1 = ', $code);
    }

    // lexer.CurrentToken -> $this->lexer->CurrentToken
    $code = preg_replace('/\blexer\b/', '\$this->lexer', $code);
    $code = preg_replace('/\$_tu\b/', '\$this->_tu', $code);

    // === Collection transforms ===
    // new List<T>() -> []
    $code = preg_replace('/new\s+List<\w+>\s*\(\)/', '[]', $code);
    // new List<T>(N) -> strip constructor arg so { -> [ rule below can match
    $code = preg_replace('/new\s+(List<\w+>)\s*\(\d+\)/', 'new $1', $code);
    // new List<T> { ... } -> [ ... ]  (opening brace only)
    $code = preg_replace('/new\s+List<\w+>\s*\{/', '[', $code);

    // === Object/constructor transforms ===
    // .ToString() removal
    $code = preg_replace('/\.ToString\(\)/', '', $code);

    // new Type( -> new \parse\Type(  for all known types
    $csNamespaceTypes = [
        'VariableExpression', 'ConstantExpression', 'ScopeResolutionExpression',
        'ArrayElementExpression', 'FuncallExpression', 'MemberFromReferenceExpression',
        'MemberFromPointerExpression', 'UnaryExpression', 'AddressOfExpression',
        'DereferenceExpression', 'SizeOfExpression', 'SizeOfTypeExpression',
        'CastExpression', 'BinaryExpression', 'RelationalExpression',
        'LogicExpression', 'ConditionalExpression', 'AssignExpression',
        'SequenceExpression', 'MultiDeclaratorStatement', 'DeclarationSpecifiers',
        'InitDeclarator', 'TypeSpecifier', 'PointerDeclarator', 'ReferenceDeclarator',
        'IdentifierDeclarator', 'FunctionDeclarator', 'Block', 'VariableScope',
        'TypeQualifiers', 'ParameterDeclaration', 'VarParameter', 'TypeName',
        'EnumeratorStatement', 'Enumerator', 'BaseSpecifier', 'TypeSpecifierKind',
        'StorageClassSpecifier', 'FunctionSpecifier', 'DeclarationsVisibility',
        'ExpressionInitializer', 'InitializerDesignation', 'StructuredInitializer',
        'Initializer', 'VirtualDeclarationStatement', 'VisibilityStatement',
        'LabeledStatement', 'ExpressionStatement', 'IfStatement', 'SwitchStatement',
        'SwitchCase', 'WhileStatement', 'ForStatement', 'GotoStatement',
        'ContinueStatement', 'BreakStatement', 'ReturnStatement', 'FunctionDefinition',
        'Unop', 'Binop', 'RelationalOp', 'LogicOp',
    ];
    foreach ($csNamespaceTypes as $t) {
        $code = preg_replace('/\bnew\s+' . $t . '\s*\(/', 'new \\parse\\' . $t . '(', $code);
    }

    // C# object initializer { Prop = value } -> remove (handled as separate statements in methods)
    // Handle single property: { X = true }
    $code = preg_replace('/\{\s*\w+\s*=\s*(?:true|false|null|\w+\.\w+)\s*\}/', '', $code);
    // Handle multiple properties: { X = true, Y = false } or { X = true, Y = false, ... }
    $code = preg_replace('/\{\s*\w+\s*=\s*[^}]+\}/', '', $code);

    // C# list->Add(x) -> list[] = x
    $code = preg_replace('/->Add\s*\(([^)]*)\)\s*;/', '[] = $1;', $code);

    // === Enum/static member transforms ===
    $code = preg_replace('/\bUnop\.(\w+)\b/', '\parse\Unop::$1()', $code);
    $code = preg_replace('/\bBinop\.(\w+)\b/', '\parse\Binop::$1()', $code);
    $code = preg_replace('/\bRelationalOp\.(\w+)\b/', '\parse\RelationalOp::$1()', $code);
    $code = preg_replace('/\bLogicOp\.(\w+)\b/', '\parse\LogicOp::$1()', $code);
    $code = preg_replace('/\bStorageClassSpecifier\.(\w+)\b/', '\parse\StorageClassSpecifier::$1()', $code);
    $code = preg_replace('/\bTypeQualifiers\.(\w+)\b/', '\parse\TypeQualifiers::$1()', $code);
    $code = preg_replace('/\bTypeSpecifierKind\.(\w+)\b/', '\parse\TypeSpecifierKind::$1()', $code);
    $code = preg_replace('/\bFunctionSpecifier\.(\w+)\b/', '\parse\FunctionSpecifier::$1()', $code);
    $code = preg_replace('/\bVariableScope\.(\w+)\b/', '\parse\VariableScope::$1()', $code);
    $code = preg_replace('/\bDeclarationsVisibility\.(\w+)\b/', '\parse\DeclarationsVisibility::$1()', $code);
    $code = preg_replace('/\bConstantExpression\.(True|False)\b/', '\parse\ConstantExpression::get$1()', $code);

    // === Operator/exception transforms ===
    // Must run BEFORE dot conversion so String.Format() -> sprintf() works
    $code = preg_replace('/throw new NotSupportedException\s*\(/', 'throw new \RuntimeException(', $code);
    $code = preg_replace('/String\.Format\s*\(/', 'sprintf(', $code);

    // C# .Method()/.Property -> ->method()/->Property
    // Match .identifier when preceded by ) or ] or [a-z_][a-z0-9_]* (variable-like name)
    // Must run AFTER enum transforms so EnumType.Value doesn't become EnumType->Value
    // Must run AFTER String.Format so it doesn't interfere with sprintf conversion
    $code = preg_replace('/(?<=[\w)\]])\.(\w+)/', '->$1', $code);

    // === Language construct transforms ===
    // C# pattern matching: expr is Type varname -> ($varname = expr) instanceof Type
    // Also handles nullable: expr is Type? varname -> ($varname = expr) instanceof Type
    $code = preg_replace_callback('/(\$\w+(?:\[[^\]]*\])*(?:\s*->\w+)*|\w+)\s+is\s+(\w+)\??\s+(\w+)\b/', function ($m) {
        return '($' . $m[3] . ' = ' . $m[1] . ') instanceof ' . $m[2];
    }, $code);
    // is operator -> instanceof (simple form: expr is Type without variable)
    $code = preg_replace('/(\$\w+|\w+)\s+is\s+(\w+)\b/', '$1 instanceof $2', $code);

    // foreach (var x in list) -> foreach (list as $x)
    $code = preg_replace_callback('/foreach\s*\((.+?)\s+(\w+)\s+in\s+(.+)\)/', function ($m) {
        return "foreach ($m[3] as \${$m[2]})";
    }, $code);

    // case_X(); -> $this->case_X();
    $code = preg_replace('/\bcase_(\d+)\(\);/', '\$this->case_$1();', $code);

    // C# method calls that became PHP methods
    $code = preg_replace('/\bMakeArrayDeclarator\(/', '\$this->makeArrayDeclarator(', $code);
    $code = preg_replace('/\bAddDeclaration\(/', '\$this->addDeclaration(', $code);
    $code = preg_replace('/\bFixPointerAndArrayPrecedence\(/', '\$this->fixPointerAndArrayPrecedence(', $code);
    $code = preg_replace('/\bGetLocation\(/', '\$this->getLocation(', $code);

    // 'as' type casts
    $code = preg_replace('/\s+as\s+\w+\b/', '', $code);

    // Remove C# named arguments: (name: expr or , name: expr) -> (expr or , expr)
    $code = preg_replace('/([(,])\s*\w+:\s*/', '$1 ', $code);

    // === Cleanup ===
    // PHP needs parens around (new Foo())->method()
    // Handle one level of nested parens in the arguments
    $code = preg_replace('/(new\s+\\\\parse\\\\\w+\s*\((?:[^()]|\([^()]*\))*\))\s*->/', '($1)->', $code);

    // Remove C# modifiers
    $code = preg_replace('/\b(public|private|protected|internal|sealed|override|virtual|abstract|partial)\s+/', '', $code);

    // Nullable type marker
    $code = str_replace('? ', ' ', $code);

    // Debug.WriteLine comments
    $code = preg_replace('/\/\/Debug\.WriteLine/', '//', $code);

    // Remove empty lines (left from #line removal etc)
    $code = preg_replace('/^[ \t]*[\r\n]+/m', '', $code);

    // Clean up multiple blank lines
    $code = preg_replace("/\n{3,}/", "\n\n", $code);

    // Add semicolons to return/throw/break/continue if missing
    $lines = explode("\n", $code);
    foreach ($lines as &$l) {
        $t = trim($l);
        if (preg_match('/^(return|throw|break|continue)\b/', $t) && !preg_match('/[;{}]\s*$/', $t)) {
            $l = rtrim($l) . ';';
        }
    }
    $code = implode("\n", $lines);

    return trim($code);
}

function extractVarNames(string $raw): array
{
    $varNames = [];
    preg_match_all('/\bvar\s+(\w+)\s*[=;]/', $raw, $matches);
    foreach ($matches[1] as $name) {
        $varNames[$name] = true;
    }
    preg_match_all('/\b(?:DeclarationSpecifiers|List<\w+>|Expression|Statement|Block|Declarator|Pointer|TypeName|Initializer|Unop|Binop|RelationalOp|LogicOp|TypeSpecifier|ConstantExpression|StructuredInitializer|FunctionDeclarator|int|bool|string|char|float|double)\s+(\w+)\s*[=;]/', $raw, $matches2);
    foreach ($matches2[1] as $name) {
        $varNames[$name] = true;
    }
    return $varNames;
}

function prefixVars(string $code, array $varNames): string
{
    foreach ($varNames as $name => $_) {
        $code = preg_replace('/(?<!\$)\b' . preg_quote($name, '/') . '\b(?!\s*\()/', '\$' . $name, $code);
    }
    return $code;
}

function convertCSharpMethodToPhp(array $lines): string
{
    $raw = implode("\n", $lines);

    $varNames = extractVarNames($raw);

    // Remove opening { and closing }
    $body = preg_replace('/^\s*\{\s*$/', '', $raw);
    $body = preg_replace('/^\s*\}\s*$/', '', $body);
    $body = csharpToPhp($body);

    $body = prefixVars($body, $varNames);

    // Cleanup List initializer braces: [ ... }; -> [ ... ];
    $body = preg_replace('/\};$/m', '];', $body);
    $body = preg_replace('/\}(\s*)\)/m', '])', $body);

    return $body;
}

function convertSwitchCasesToPhp(array $cases): string
{
    $out = '';
    foreach ($cases as $num => $code) {
        $php = csharpToPhp($code);
        $out .= "                    case $num: $php\n";
    }
    return $out;
}

// ============================================================
// Step 9: Generate final CParser.php
// ============================================================
echo "\n=== Step 9: Assembling CParser.php...\n";

// Header
$phpCode = <<<'PHP'
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

PHP;

// yyNames
$phpCode .= "    protected static \$yyNames = [\n";
foreach ($yyNamesRaw as $name) {
    $name = trim($name);
    if ($name === 'null') {
        $phpCode .= "        null,\n";
    } elseif (substr($name, 0, 1) === '"' || substr($name, 0, 1) === "'") {
        $phpCode .= "        $name,\n";
    } else {
        $phpCode .= "        \"$name\",\n";
    }
}
$phpCode .= "    ];\n\n";

// yyname + yyExpectingTokens + yyExpecting methods
$phpCode .= <<<'PHP'
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

PHP;

// yyparseInternal method
$phpCode .= <<<'PHP'
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
PHP;

// Convert and insert switch cases
$phpCode .= "\n";
foreach ($switchCases as $num => $code) {
    // Track variable names from raw C# code
    $varNames = extractVarNames($code);

    $converted = csharpToPhp($code);

    // Prefix bare variables with $
    $converted = prefixVars($converted, $varNames);

    // Remove lines that are just #line or empty after conversion
    $lines = explode("\n", $converted);
    $filtered = [];
    foreach ($lines as $l) {
        $t = trim($l);
        if ($t === '' || preg_match('/^#line/', $t)) continue;
        if ($t === 'break;') continue;
        $filtered[] = $l;
    }
    $body = trim(implode("\n", $filtered));
    // Remove outer C# action block { ... }
    if (substr($body, 0, 1) === '{') {
        $body = trim(substr($body, 1));
    }
    if (substr($body, -1) === '}') {
        $body = trim(substr($body, 0, -1));
    }
    // Handle any remaining } that came from List<...> { ... } conversion
    // The pattern [ ... }; should become [ ... ];
    $body = preg_replace('/\};$/m', '];', $body);
    $body = preg_replace('/\}(\s*)\)/m', '])', $body);
    $body = preg_replace('/\},\s*\)\)/m', '])', $body);
    // Indent
    $body = "                        " . str_replace("\n", "\n                        ", $body);
    $phpCode .= "                    case $num:\n";
    $phpCode .= $body . "\n";
    $phpCode .= "                        break;\n";
}
$phpCode .= "                }\n";

// Close switch, yyparseInternal method
$phpCode .= <<<'PHP'
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

PHP;

// Parser tables
echo "  Adding parser tables...\n";
foreach ($tables as $name => $lines) {
    $phpCode .= "    protected static \$$name = [\n";
    foreach ($lines as $l) {
        $l = rtrim($l);
        if ($l === '') continue;
        $phpCode .= "        $l\n";
    }
    $phpCode .= "    ];\n\n";
}

// case_XX methods
echo "  Adding case methods...\n";
$phpCode .= "    // Reduction action helper methods\n\n";
foreach ($caseMethods as $name => $lines) {
    $body = convertCSharpMethodToPhp($lines);
    $phpCode .= "    protected function $name()\n    {\n";
    $phpCode .= "        $body\n    }\n\n";
}

// Utility methods
echo "  Adding utility methods...\n";

// Parse the token constants for TokenKind reference
$tkCode = file_get_contents($tokenKindFile);
preg_match_all('/const\s+(\w+)\s*=\s*(-?\d+)/', $tkCode, $tkMatches, PREG_SET_ORDER);
$tkMap = [];
foreach ($tkMatches as $m) {
    $tkMap[$m[1]] = (int)$m[2];
}

$utilityCode = <<<'PHP'
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
PHP;

$phpCode .= $utilityCode;

// Write output
file_put_contents($outFile, $phpCode);
echo "\n=== Done! Generated $outFile (" . strlen($phpCode) . " bytes)\n";
