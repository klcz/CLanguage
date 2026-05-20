<?php
/**
 * Convert zend_language_parser_.code (C bison actions) to Go switch cases.
 * Reads .code and outputs bison_gen.go matching bison_gen_ref.go logic.
 * PHP 7.4 compatible.
 */

// ─── helpers ─────────────────────────────────────────────────────────────

function splitArgs(string $s): array {
    $args = [];
    $depth = 0;
    $buf = '';
    for ($i = 0, $len = strlen($s); $i < $len; $i++) {
        $ch = $s[$i];
        if ($ch === '(' || $ch === '[') { $depth++; $buf .= $ch; }
        elseif ($ch === ')' || $ch === ']') { $depth--; $buf .= $ch; }
        elseif ($ch === ',' && $depth === 0) { $args[] = trim($buf); $buf = ''; }
        else { $buf .= $ch; }
    }
    if (trim($buf) !== '') $args[] = trim($buf);
    return $args;
}

// ─── parse .code into case blocks ───────────────────────────────────────

function parseCodeFile(string $path): array {
    $text = file_get_contents($path);
    $text = str_replace("\r\n", "\n", $text);
    $text = str_replace("\r", "\n", $text);
    $lines = explode("\n", $text);

    $blocks = [];
    $i = 0;
    $n = count($lines);

    while ($i < $n) {
        $line = $lines[$i];
        if (preg_match('/^\s*case\s+([\d,\s]+):\s*$/', $line, $m)) {
            $cases = trim($m[1]);
            $i++;
            while ($i < $n && trim($lines[$i]) === '') $i++;
            if ($i >= $n) break;

            $body = [];
            $firstBodyLine = $lines[$i];
            $i++;

            if (trim($firstBodyLine) !== '{') {
                $body[] = $firstBodyLine;
            } else {
                $depth = 1;
                while ($i < $n && $depth > 0) {
                    $lineText = $lines[$i];
                    $openBraces = substr_count($lineText, '{');
                    $closeBraces = substr_count($lineText, '}');
                    $depth += $openBraces - $closeBraces;
                    if ($depth > 0) {
                        $body[] = $lineText;
                    }
                    $i++;
                }
            }

            while ($body && trim($body[0]) === '') array_shift($body);
            while ($body && trim($body[count($body)-1]) === '') array_pop($body);

            $blocks[] = ['cases' => $cases, 'body' => $body];
        } else {
            $i++;
        }
    }
    return $blocks;
}

// ─── set of cases needing tka guard before body ──────────────────────────

$TKA_GUARD_CASES = [87, 146, 325, 378];

// ─── main transform ─────────────────────────────────────────────────────

function transformBody(array $bodyLines, string $casesStr): string {
    $body = implode("\n", $bodyLines);
    $caseList = array_map('intval', explode(',', str_replace(' ', '', $casesStr)));

    // ═══ PHASE 0: Detect and handle multi-line special patterns ═══════

    // ── 0a. zend_lex_tstring pattern (zval + lex + FAILURE + create_zval) ──
    // Clean two-step approach:
    //   1. Replace `zval zv;` decl / `if (zend_lex_tstring(&zv, X) == FAILURE) { YYABORT; }` block
    //   2. Replace `zend_ast_create_zval(&zv)` → `zast` everywhere in the body
    if (preg_match('/zval\s+zv;\s*if\s*\(\s*zend_lex_tstring\s*\(/', $body)) {
        $body = preg_replace('/zval\s+zv;\s*/', '', $body);
        $body = preg_replace_callback(
            '/\s*if\s*\(\s*zend_lex_tstring\s*\(\s*&zv\s*,\s*((?:\([^)]+\)|[^)]+))\s*\)\s*==\s*FAILURE\s*\)\s*\{\s*YYABORT;\s*\}/s',
            function ($m) {
                $arg = trim($m[1]);
                return "zast, errLexT := p.lexTString($arg); if errLexT != nil { err = errLexT; goto yyabortlab };";
            },
            $body
        );
        $body = str_replace('zend_ast_create_zval(&zv)', 'zast', $body);
    }

    // ── 0b. ZVAL_INTERNED_STR pattern (case 522) ──
    $body = preg_replace_callback(
        '/zval\s+zv;\s*ZVAL_INTERNED_STR\s*\(\s*&zv\s*,\s*([^)]+)\);\s*\(\s*yyval\s*\.\s*ast\s*\)\s*=\s*zend_ast_create_zval_ex\s*\(\s*&zv\s*,\s*([^)]+)\)\s*;/s',
        function ($m) {
            $str = trim($m[1]);
            $attr = trim($m[2]);
            return "zv := p.newTknValStr(tka, $str); yyval.ast = astCreateZvalEx(tka, zv, $attr)";
        },
        $body
    );

    // ── 0c. zend_throw_exception pattern (case 150) ──
    if (preg_match('/zend_throw_exception\s*\([^)]*__HALT_COMPILER/', $body)) {
        return '{ yyval.ast, err = nil, errHalt; p.HandelError(yyval.tka, err); goto yyerrorlab }';
    }

    // ── 0d. LANG_SCNG pattern (case 507) ──
    if (preg_match('/LANG_SCNG\s*\(\s*yy_text\s*\)/', $body)) {
        return '{ /* yyval.ptr = p.cursor */ }';
    }

    // ── 0e. Multi-line zend_ast *name = create + name->attr + final (cases 420,421,486) ──
    //   zend_ast *name = zend_ast_create_zval_from_str(ZSTR_KNOWN(ZEND_STR_xxx));
    //   name->attr = ZEND_NAME_FQ;
    //   (yyval.ast) = zend_ast_create(ZEND_AST_CALL, name, ARGS);
    // → yyval.ast = astCreate2(tka, ZEND_AST_CALL, p.astCreateZvalStrAttr(tka, STR, ATTR), ARGS)
    $body = preg_replace_callback(
        '/zend_ast\s*\*\s*name\s*=\s*zend_ast_create_zval_from_str\s*\(\s*ZSTR_KNOWN\s*\(\s*(ZEND_STR_\w+)\s*\)\s*\)\s*;\s*name\s*->\s*attr\s*=\s*(ZEND_NAME_\w+)\s*;\s*\(\s*yyval\s*\.\s*ast\s*\)\s*=\s*zend_ast_create\s*\(\s*ZEND_AST_CALL\s*,\s*name\s*,\s*(.+?)\)\s*;/s',
        function ($m) {
            $strConst = $m[1];
            $nameAttr = $m[2];
            $arg = trim($m[3]);
            return "yyval.ast = astCreate2(tka, ZEND_AST_CALL, p.astCreateZvalStrAttr(tka, $strConst, $nameAttr), $arg)";
        },
        $body
    );

    // ── 0f. modifiers + astCreateEx + destroy pattern (cases 347, 348) ──
    $body = preg_replace_callback(
        '/uint32_t\s+modifiers\s*=\s*zend_modifier_token_to_flag\s*\(([^)]+)\)\s*;\s*\(\s*yyval\s*\.\s*ast\s*\)\s*=\s*zend_ast_create_ex\s*\(([^)]+)\)\s*;.*?if\s*\(!\s*modifiers\s*\)\s*\{(.*?)\s*YYERROR;\s*\}\s*/s',
        function ($m) {
            $flagArgs = $m[1];
            $createArgs = $m[2];
            $destroyBody = $m[3];
            // Remove zend_ast_destroy line
            $destroyBody = preg_replace('/zend_ast_destroy\s*\([^)]+\)\s*;/', '', $destroyBody);
            return "modifiers, err = p.modifierTokenToFlag($flagArgs); yyval.ast = astCreateEx($createArgs); /* identifier nonterminal can cause allocations, so we need to free the node */ if modifiers == 0 { goto yyerrorlab }";
        },
        $body
    );

    // Remaining zend_ast_destroy calls
    $body = preg_replace('/zend_ast_destroy\s*\([^)]+\)\s*;/', '', $body);

    // ── 0g. Remove remaining zval zv; declarations ──
    $body = preg_replace('/zval\s+zv;\s*/', '', $body);

    // ── 0h. Inline zend_ast *decl = [expr]; zend_ast_create(..., decl, ...) (case 410) ──
    // zend_ast *decl = zend_ast_create_decl(INIT); (yyval.ast) = zend_ast_create(ZEND_AST_NEW, decl, ARG);
    // → (yyval.ast) = zend_ast_create(ZEND_AST_NEW, zend_ast_create_decl(INIT), ARG);
    // Note: regex consumes `))`) — two closing parens for ARG close and zend_ast_create close,
    // plus semicolon. Replacement adds ) for zend_ast_create_decl, ) for ARG, ) for zend_ast_create.
    $body = preg_replace(
        '/\s*zend_ast\s*\*\s*decl\s*=\s*zend_ast_create_decl\s*\(([^;]+)\)\s*;\s*\(\s*yyval\s*\.\s*ast\s*\)\s*=\s*zend_ast_create\s*\(\s*ZEND_AST_NEW\s*,\s*decl\s*,\s*([^)]+)\)\s*\)\s*;/s',
        '(yyval.ast) = zend_ast_create(ZEND_AST_NEW, zend_ast_create_decl($1), $2));',
        $body
    );

    // ═══ PHASE 1: CG(...) replacements ═══════════════════════════════

    // CG(doc_comment) = (yyvsp[N].str)   — must be before other CG transforms
    $body = preg_replace_callback(
        '/CG\s*\(\s*doc_comment\s*\)\s*=\s*\(\s*yyvsp\s*\[\s*(-?\d+)\s*\]\s*\.\s*str\s*\)\s*;/',
        function ($m) {
            $n = (int)$m[1];
            $ref = ($n === 0) ? 'yyvsa[yyvsp].tka' : 'yyvsa[yyvsp' . $n . '].tka';
            return "p.lexer.docComment = Token(int($ref) + p.tknOffset);";
        },
        $body
    );

    $body = preg_replace('/CG\s*\(\s*ast\s*\)/', 'p.rootAst', $body);
    $body = preg_replace('/CG\s*\(\s*zend_lineno\s*\)/', 'tka', $body);
    $body = preg_replace('/CG\s*\(\s*doc_comment\s*\)/', 'p.lexer.docComment', $body);
    $body = preg_replace('/CG\s*\(\s*extra_fn_flags\s*\)/', 'p.extraFnFlags', $body);
    $body = str_replace('RESET_DOC_COMMENT();', 'p.lexer.docComment = 0', $body);
    $body = str_replace('(void) zendnerrs;', 'yynerrs = 0', $body);

    // ═══ PHASE 2: zend_ast_get_str → p.getTknStr (before yyvsp transforms) ═══

    // zend_ast_get_str((yyvsp[N].ast)) → p.getTknStr(yyvsa[yyvsp+N].tka)
    $body = preg_replace_callback(
        '/zend_ast_get_str\s*\(\(\s*yyvsp\s*\[\s*(-?\d+)\s*\]\s*\.\s*ast\s*\)\)/',
        function ($m) {
            $n = (int)$m[1];
            $ref = ($n === 0) ? 'yyvsa[yyvsp].tka' : 'yyvsa[yyvsp' . $n . '].tka';
            return "p.getTknStr($ref)";
        },
        $body
    );

    // ═══ PHASE 3: yyval / yyvsp field transforms ═════════════════════

    // (yyval.xxx) → yyval.xxx
    $body = preg_replace('/\(\s*yyval\s*\.\s*(\w+)\s*\)/', 'yyval.$1', $body);

    // (yyvsp[N].ast) → yyvsa[yyvsp+N].ast
    $body = preg_replace_callback(
        '/\(\s*yyvsp\s*\[\s*(-?\d+)\s*\]\s*\.\s*ast\s*\)/',
        function ($m) {
            $n = (int)$m[1];
            if ($n === 0) return 'yyvsa[yyvsp].ast';
            return 'yyvsa[yyvsp' . $n . '].ast';  // $n already has sign
        },
        $body
    );

    // (yyvsp[N].num) → yyvsa[yyvsp+N].num
    $body = preg_replace_callback(
        '/\(\s*yyvsp\s*\[\s*(-?\d+)\s*\]\s*\.\s*num\s*\)/',
        function ($m) {
            $n = (int)$m[1];
            if ($n === 0) return 'yyvsa[yyvsp].num';
            return 'yyvsa[yyvsp' . $n . '].num';
        },
        $body
    );

    // (yyvsp[N].ident) → yyvsa[yyvsp+N].tka
    $body = preg_replace_callback(
        '/\(\s*yyvsp\s*\[\s*(-?\d+)\s*\]\s*\.\s*ident\s*\)/',
        function ($m) {
            $n = (int)$m[1];
            if ($n === 0) return 'yyvsa[yyvsp].tka';
            return 'yyvsa[yyvsp' . $n . '].tka';
        },
        $body
    );

    // (yyvsp[N].str) → p.getAstTknVal(yyvsa[yyvsp+N].tka)
    $body = preg_replace_callback(
        '/\(\s*yyvsp\s*\[\s*(-?\d+)\s*\]\s*\.\s*str\s*\)/',
        function ($m) {
            $n = (int)$m[1];
            $ref = ($n === 0) ? 'yyvsa[yyvsp].tka' : 'yyvsa[yyvsp' . $n . '].tka';
            return "p.getAstTknVal($ref)";
        },
        $body
    );

    // (yyvsp[N].ptr) → nil
    $body = preg_replace_callback(
        '/\(\s*yyvsp\s*\[\s*(-?\d+)\s*\]\s*\.\s*ptr\s*\)/',
        function ($m) { return 'nil'; },
        $body
    );

    // ═══ PHASE 3a: Wrap brace-less if bodies in { } ══════════════════
    // Go requires braces; C allows if (cond) stmt;
    $body = preg_replace_callback(
        '/\bif\s*\(\s*([!]?)\s*((?:[^()]|\([^()]*\))*)\s*\)\s*(\S[^;]*);/',
        function ($m) {
            $bodyText = trim($m[3]);
            if (strpos($bodyText, '{') === 0) return $m[0]; // already has braces
            return 'if (' . ($m[1] !== '' ? '!' : '') . $m[2] . ') { ' . $bodyText . '; }';
        },
        $body
    );

    // ═══ PHASE 3b: C-style if (cond) → Go if cond ═══════════════════
    // Balanced-paren regex handles nested parens in conditions
    $body = preg_replace_callback(
        '/\bif\s*\(\s*([!]?)\s*((?:[^()]|\([^()]*\))*)\s*\)\s*(\{)?/',
        function ($m) {
            $neg = $m[1];
            $cond = $m[2];
            $hasBlock = !empty($m[3]);
            return 'if ' . ($neg !== '' ? '!' : '') . $cond . ($hasBlock ? ' {' : ' ');
        },
        $body
    );

    // ═══ PHASE 4: Function-call transforms ═══════════════════════════
    // Use balanced-paren patterns: depth-1 for inner calls, depth-2 for
    // outer calls that may contain transformed inner calls with parens.

    // `zend_ast_create_list(N, TYPE, ...)` → `astCreateList{N}(tka, TYPE, ...)`
    // Depth-1: args may contain p.getAstTknVal(...) etc.
    $body = preg_replace_callback(
        '/zend_ast_create_list\s*\(((?:[^()]|\([^()]*\))*)\)/',
        function ($m) {
            $args = splitArgs($m[1]);
            $count = (int)trim($args[0]);
            $type = trim($args[1]);
            $rest = array_slice($args, 2);
            $r = implode(', ', $rest);
            if ($count === 0) return "astCreateList0(tka, $type)";
            return "astCreateList$count(tka, $type, $r)";
        },
        $body
    );

    // `zend_ast_create_decl(TYPE, ...)` → `astCreateDecl(tka, TYPE, ...)`
    // Depth-1: args may contain p.getAstTknVal(...) etc.
    $body = preg_replace_callback(
        '/zend_ast_create_decl\s*\(((?:[^()]|\([^()]*\))*)\)/',
        function ($m) {
            return 'astCreateDecl(tka, ' . $m[1] . ')';
        },
        $body
    );

    // `zend_ast_create_ex(TYPE, ATTR, ...)` → `astCreateEx{N}(tka, TYPE, ATTR, ...)`
    // Depth-1: args may contain p.getAstTknVal(...) etc.
    $body = preg_replace_callback(
        '/zend_ast_create_ex\s*\(((?:[^()]|\([^()]*\))*)\)/',
        function ($m) {
            $args = splitArgs($m[1]);
            $type = trim($args[0]);
            $attr = trim($args[1]);
            $children = array_slice($args, 2);
            $n = count($children);
            $rest = implode(', ', $children);
            return "astCreateEx{$n}(tka, $type, $attr, $rest)";
        },
        $body
    );

    // `zend_ast_create(TYPE, ...)` → `astCreate{N}(tka, TYPE, ...)`
    // Depth-2: args may contain transformed astCreateDecl(tka, ..., p.getAstTknVal(...))
    // which has two levels of nesting.
    $body = preg_replace_callback(
        '/zend_ast_create\s*\(((?:[^()]|\((?:[^()]|\([^()]*\))*\))*)\)/',
        function ($m) {
            $args = splitArgs($m[1]);
            $type = trim($args[0]);
            $children = array_slice($args, 1);
            $n = count($children);
            $rest = implode(', ', $children);
            if ($n === 0) return "astCreate0(tka, $type)";
            return "astCreate{$n}(tka, $type, $rest)";
        },
        $body
    );

    // ═══ PHASE 5: Named function mappings ════════════════════════════

    $funcMap = [
        'zend_ast_list_add('                     => 'astListAdd(',
        'zend_ast_with_attributes('              => 'astWithAttributes(',
        'zend_ast_create_zval_from_str('         => 'p.astCreateZvalFromStr(tka, ',
        'zend_ast_create_zval_from_long('        => 'p.astCreateZvalFromLong(tka, ',
        'zend_ast_create_fcc('                   => 'astCreateFcc(tka, ',
        'zend_ast_create_assign_op('             => 'astCreateAssignOp(tka, ',
        'zend_ast_create_binary_op('             => 'astCreateBinaryOp(tka, ',
        'zend_ast_create_concat_op('             => 'astCreateConcatOp(tka, ',
        'zend_ast_create_class_const_or_name('   => 'astCreateClassConstOrName(tka, ',
        'zend_ast_create_cast('                  => 'astCreateCast(tka, ',
        'zend_ast_list_rtrim('                   => 'astListRtrim(',
        'zend_negate_num_string('                => 'negateNumString(',
        'zend_ast_create_zval_ex('               => 'astCreateZvalEx(tka, ',
        'zend_ast_create_zval('                  => 'astCreateZval(tka, ',
        'zend_handle_encoding_declaration('      => 'p.handleEncodingDeclaration(',
        'zend_get_scanned_file_offset('          => 'p.getScannedFileOffset(',
        'zend_stop_lexing('                      => 'p.stopLexing(',
        'zend_modifier_list_to_flags('           => 'p.modifierListToFlags(tka, ',
        'zend_add_class_modifier('               => 'p.classModifier(tka, ',
        'zend_add_anonymous_class_modifier('     => 'p.anonymousClassModifier(tka, ',
        'zend_modifier_token_to_flag('           => 'p.modifierTokenToFlag(',
        'zend_lex_tstring('                      => 'p.lexTString(',
    ];
    foreach ($funcMap as $cFunc => $goFunc) {
        $body = str_replace($cFunc, $goFunc, $body);
    }

    // `ZSTR_KNOWN(...)` → inner content (unwrap)
    $body = preg_replace('/ZSTR_KNOWN\s*\(\s*(ZEND_STR_\w+)\s*\)/', '$1', $body);

    // `ZSTR_EMPTY_ALLOC()` → `""`
    $body = str_replace('ZSTR_EMPTY_ALLOC()', '""', $body);

    // Ternary: (cond ? zend_ast_create_zval_from_str(X) : NULL)
    // funcMap already changed zend_ast_create_zval_from_str → p.astCreateZvalFromStr(tka,.
    // NULL is still NULL at this point (Phase 6 converts it to nil).
    $body = preg_replace(
        '/\(\s*(\S+)\s*\?\s*p\.astCreateZvalFromStr\s*\(\s*tka\s*,\s*(\S+)\s*\)\s*:\s*NULL\s*\)/',
        'astCreateZvalStrOrNil(tka, $2)',
        $body
    );

    // Handle ternary with p.getAstTknVal(...):
    //   (p.getAstTknVal(ARG) ? p.astCreateZvalFromStr(tka, p.getAstTknVal(ARG)) : NULL)
    // → astCreateZvalStrOrNil(tka, p.getAstTknVal(ARG))
    // First with wrapping parens (inside zend_ast_create args):
    $body = preg_replace(
        '/\(p\.getAstTknVal\(((?:[^()]|\([^()]*\))*)\)\s*\?\s*p\.astCreateZvalFromStr\s*\(\s*tka\s*,\s*p\.getAstTknVal\(\1\)\)\s*:\s*NULL\)/',
        'astCreateZvalStrOrNil(tka, p.getAstTknVal($1))',
        $body
    );
    // Then without wrapping parens (for zend_ast_create_ex args):
    $body = preg_replace(
        '/p\.getAstTknVal\(((?:[^()]|\([^()]*\))*)\)\s*\?\s*p\.astCreateZvalFromStr\s*\(\s*tka\s*,\s*p\.getAstTknVal\(\1\)\)\s*:\s*NULL/',
        'astCreateZvalStrOrNil(tka, p.getAstTknVal($1))',
        $body
    );

    // ═══ PHASE 5b: Re-run zend_ast_create transforms after ternary fix ═══

    // zend_ast_create_ex again (ternary was blocking depth-1 match;
    // astCreateZvalStrOrNil(tka, p.getAstTknVal(...)) has depth-2 nesting)
    $body = preg_replace_callback(
        '/zend_ast_create_ex\s*\(((?:[^()]|\((?:[^()]|\([^()]*\))*\))*)\)/',
        function ($m) {
            $args = splitArgs($m[1]);
            $type = trim($args[0]);
            $attr = trim($args[1]);
            $children = array_slice($args, 2);
            $n = count($children);
            $rest = implode(', ', $children);
            return "astCreateEx{$n}(tka, $type, $attr, $rest)";
        },
        $body
    );

    // zend_ast_create again (ternary was blocking depth-2 match)
    $body = preg_replace_callback(
        '/zend_ast_create\s*\(((?:[^()]|\((?:[^()]|\([^()]*\))*\))*)\)/',
        function ($m) {
            $args = splitArgs($m[1]);
            $type = trim($args[0]);
            $children = array_slice($args, 1);
            $n = count($children);
            $rest = implode(', ', $children);
            if ($n === 0) return "astCreate0(tka, $type)";
            return "astCreate{$n}(tka, $type, $rest)";
        },
        $body
    );

    // ═══ PHASE 6: C → Go keywords ════════════════════════════════════

    $body = str_replace([
        'NULL', 'FAILURE', 'SUCCESS',
        '/* allow single trailing comma */',
        '/* identifier nonterminal can cause allocations, so we need to free the node */',
    ], [
        'nil', 'nil', 'nil', '', '',
    ], $body);

    // ═══ PHASE 7: Control flow ═══════════════════════════════════════

    // if (!func(args)) { YYERROR; } → if !func(args) { goto yyerrorlab }
    $body = preg_replace('/if\s*\(!\s*(\S[^)]*)\)\s*\{\s*YYERROR;\s*\}/', 'if !$1 { goto yyerrorlab }', $body);
    // General: if (!cond) { YYERROR; }
    $body = preg_replace('/if\s*\(!\s*([^)]+)\)\s*\{\s*YYERROR;\s*\}/', 'if !$1 { goto yyerrorlab }', $body);
    // General if rewrapping
    $body = preg_replace('/if\s*\(!(\s*[^)]+\s*)\)\s*\{([^}]*)\s*\}/', 'if !$1 { $2 }', $body);
    // Standalone YYABORT / YYERROR
    $body = str_replace('YYABORT;', 'err = errLexT; goto yyabortlab', $body);
    $body = str_replace('YYABORT', 'err = errLexT; goto yyabortlab', $body);
    $body = str_replace('YYERROR;', 'goto yyerrorlab', $body);
    $body = str_replace('YYERROR', 'goto yyerrorlab', $body);

    // ═══ PHASE 8: Cast patterns ══════════════════════════════════════

    // ((zend_ast_decl *) (yyval.ast))->flags → (*AstDecl)(unsafe.Pointer(yyval.ast)).Flags
    // After Phase 3, (yyval.ast) is already → yyval.ast, so match both forms
    $body = preg_replace(
        '/\(\(\s*zend_ast_decl\s*\*\)\s*(?:\(\s*yyval\s*\.\s*ast\s*\)|yyval\s*\.\s*ast)\s*\)\s*->\s*flags/',
        '(*AstDecl)(unsafe.Pointer(yyval.ast)).Flags',
        $body
    );

    // ═══ PHASE 9: -> → . for struct access ═══════════════════════════

    // Replace -> with . but only for known struct fields
    $body = str_replace('->attr', '.Attr', $body);
    $body = str_replace('->kind', '.Kind', $body);
    $body = str_replace('->child', '.Child', $body);
    $body = str_replace('->flags', '.Flags', $body);

    // ═══ PHASE 10: Clean up remaining C artifacts ════════════════════

    // Remove & in function calls (C address-of in Go is not needed)
    $body = str_replace('(&zv, ', '(', $body);
    // Remove leftover zval zv; or zval related
    $body = preg_replace('/zval\s+\w+;\s*/', '', $body);

    // Handle zendnerrs → yynerrs (remaining)
    $body = str_replace('zendnerrs', 'yynerrs', $body);

    // ═══ PHASE 10a: Remaining C→Go transforms ════════════════════════

    // C type declarations → Go (uint32_t var = expr → var := expr)
    $body = preg_replace('/\b(uint32_t|uint16_t|int32_t|zend_ast|zval)\s+(\w+)\s*=\s*(.+?);/', '$2 := $3;', $body);

    // Remove zend_ast_destroy calls (Go has GC)
    $body = preg_replace('/zend_ast_destroy\s*\([^)]+\)\s*;?\s*/', '', $body);

    // Token constants → int() wrapper
    $body = preg_replace('/\bT_PUBLIC\b/', 'int(T_PUBLIC)', $body);

    // ═══ PHASE 11: Consolidate to single line ════════════════════════

    $body = trim($body);
    $body = preg_replace('/\s*\n\s*/', ' ', $body);
    $body = preg_replace('/;\s*;\s*/', '; ', $body);
    $body = preg_replace('/\s*{\s*/', '{ ', $body);
    $body = preg_replace('/\s*}\s*/', ' }', $body);
    $body = preg_replace('/\s{2,}/', ' ', $body);
    $body = preg_replace('/;\s*}/', ' }', $body);
    $body = trim($body);

    // Fix missing space before { in if/for statements (Phase 11 removes it)
    $body = preg_replace('/\b(if|for|switch|while)\s+(\S[^{]*?)(?<!\s)\{/', '$1 $2 {', $body);

    // Remove trailing comma before ) (invalid Go syntax)
    $body = preg_replace('/,\s*\)/', ')', $body);

    // ── Add tka guard for certain cases ──
    global $TKA_GUARD_CASES;
    $needsGuard = false;
    foreach ($caseList as $c) {
        if (in_array($c, $TKA_GUARD_CASES)) { $needsGuard = true; break; }
    }
    if ($needsGuard) {
        $guard = 'if tka == 0 { tka = AstTkn((int(p.cursor<<16)|int(T_OPEN_TAG))-p.tknOffset) }';
        // Insert guard before any yyval assignment
        if (substr($body, 0, 1) === '{') {
            $body = $guard . '; ' . $body;
        } else {
            $body = $guard . '; { ' . $body . ' }';
        }
    }

    // Ensure wrapped in { }
    if (substr($body, 0, 1) !== '{') {
        $body = '{ ' . $body . ' }';
    }

    return $body;
}

// ─── main ────────────────────────────────────────────────────────────────

$blocks = parseCodeFile(__DIR__ . '/zend_language_parser_.code');

// Merge consecutive blocks with same transformed body
$merged = [];
foreach ($blocks as $b) {
    $goBody = transformBody($b['body'], $b['cases']);
    $key = $goBody;
    if (!isset($merged[$key])) {
        $merged[$key] = ['cases' => $b['cases'], 'body' => $goBody];
    } else {
        $merged[$key]['cases'] .= ', ' . $b['cases'];
    }
}

$outLines = [];
$outLines[] = '// Code generated from zend_language_parser_.code, DO NOT EDIT.';
$outLines[] = '//go:generate -- DO NOT EDIT directly; edit the .code file and rerun convert.php';
$outLines[] = 'package phpParser';
$outLines[] = '';
$outLines[] = 'switch yyn {';

foreach ($merged as $item) {
    $outLines[] = "\tcase {$item['cases']}:";
    $outLines[] = "\t\t{$item['body']}";
}

$outLines[] = '}';
$outLines[] = '';

file_put_contents(__DIR__ . '/bison_gen.go', implode("\n", $outLines));

echo "Done. Generated " . count($merged) . " case groups.\n";
echo "Output: bison_gen.go\n";
