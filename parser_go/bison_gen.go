// Code generated from zend_language_parser_.code, DO NOT EDIT.
//go:generate -- DO NOT EDIT directly; edit the .code file and rerun convert.php
package phpParser

switch yyn {
	case 2:
		{ p.rootAst = yyvsa[yyvsp].ast; yynerrs = 0 }
	case 84, 88, 89, 90, 91, 92, 93, 105, 106, 107, 108, 109, 110, 112, 113, 147, 148, 152, 153, 181, 187, 214, 217, 219, 221, 223, 224, 228, 230, 232, 252, 256, 267, 273, 274, 276, 277, 278, 280, 286, 288, 289, 292, 293, 300, 315, 316, 331, 332, 350, 404, 412, 415, 473, 474, 478, 488, 496, 500, 514, 523, 524, 525, 528, 529, 531, 534, 536, 537, 541, 542, 543, 559, 560, 561, 563, 564, 565, 566, 567, 568, 570, 571, 576, 577, 578, 581, 592, 595, 600, 622, 623, 633:
		{ yyval.ast = yyvsa[yyvsp].ast; }
	case 85, 188:
		{ zast, errLexT := p.lexTString(yyvsa[yyvsp].tka); if errLexT != nil { err = errLexT; goto yyabortlab }; yyval.ast = zast; }
	case 86, 104, 145, 324, 341, 379, 610, 611:
		{ yyval.ast = astListAdd(yyvsa[yyvsp-1].ast, yyvsa[yyvsp].ast); }
	case 87, 146, 325, 378:
		{ if tka == 0 { tka = AstTkn((int(p.cursor<<16)|int(T_OPEN_TAG))-p.tknOffset) }; { yyval.ast = astCreateList0(tka, ZEND_AST_STMT_LIST); } }
	case 94, 95:
		{ yyval.ast = yyvsa[yyvsp].ast; yyval.ast.Attr = ZEND_NAME_NOT_FQ; }
	case 96:
		{ yyval.ast = yyvsa[yyvsp].ast; yyval.ast.Attr = ZEND_NAME_FQ; }
	case 97:
		{ yyval.ast = yyvsa[yyvsp].ast; yyval.ast.Attr = ZEND_NAME_RELATIVE; }
	case 98:
		{ yyval.ast = astCreate2(tka, ZEND_AST_ATTRIBUTE, yyvsa[yyvsp].ast, nil); }
	case 99:
		{ yyval.ast = astCreate2(tka, ZEND_AST_ATTRIBUTE, yyvsa[yyvsp-1].ast, yyvsa[yyvsp].ast); }
	case 100:
		{ yyval.ast = astCreateList1(tka, ZEND_AST_ATTRIBUTE_GROUP, yyvsa[yyvsp].ast); }
	case 101, 131, 133, 135, 143, 179, 185, 247, 251, 265, 283, 285, 296, 298, 305, 312, 317, 320, 336, 372, 392, 397, 401, 405, 512, 601:
		{ yyval.ast = astListAdd(yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 102:
		{ yyval.ast = yyvsa[yyvsp-2].ast; p.lexer.docComment = Token(int(yyvsa[yyvsp-3].tka) + p.tknOffset); }
	case 103:
		{ yyval.ast = astCreateList1(tka, ZEND_AST_ATTRIBUTE_LIST, yyvsa[yyvsp].ast); }
	case 111, 121, 151, 161, 162, 163, 165, 183, 234, 235, 245, 262, 281, 294, 339, 342, 343, 353, 382, 388, 391, 526, 535, 538, 540, 569, 582, 593, 596, 621:
		{ yyval.ast = yyvsa[yyvsp-1].ast; }
	case 114, 149, 266, 333, 497:
		{ yyval.ast = astWithAttributes(yyvsa[yyvsp].ast, yyvsa[yyvsp-1].ast); }
	case 115:
		{ yyval.ast = astCreate1(tka, ZEND_AST_HALT_COMPILER, p.astCreateZvalFromLong(tka, p.getScannedFileOffset())); p.stopLexing(); }
	case 116:
		{ yyval.ast = astCreate2(tka, ZEND_AST_NAMESPACE, yyvsa[yyvsp-1].ast, nil); p.lexer.docComment = 0 }
	case 117, 119:
		{ p.lexer.docComment = 0 }
	case 118:
		{ yyval.ast = astCreate2(tka, ZEND_AST_NAMESPACE, yyvsa[yyvsp-4].ast, yyvsa[yyvsp-1].ast); }
	case 120:
		{ yyval.ast = astCreate2(tka, ZEND_AST_NAMESPACE, nil, yyvsa[yyvsp-1].ast); }
	case 122, 124:
		{ yyval.ast = yyvsa[yyvsp-1].ast; yyval.ast.Attr = yyvsa[yyvsp-2].num; }
	case 123:
		{ yyval.ast = yyvsa[yyvsp-1].ast; yyval.ast.Attr = ZEND_SYMBOL_CLASS; }
	case 125:
		{ yyval.num = ZEND_SYMBOL_FUNCTION; }
	case 126:
		{ yyval.num = ZEND_SYMBOL_CONST; }
	case 127, 128:
		{ yyval.ast = astCreate2(tka, ZEND_AST_GROUP_USE, yyvsa[yyvsp-5].ast, yyvsa[yyvsp-2].ast); }
	case 132, 134, 136:
		{ yyval.ast = astCreateList1(tka, ZEND_AST_USE, yyvsa[yyvsp].ast); }
	case 137:
		{ yyval.ast = yyvsa[yyvsp].ast; yyval.ast.Attr = ZEND_SYMBOL_CLASS; }
	case 138:
		{ yyval.ast = yyvsa[yyvsp].ast; yyval.ast.Attr = yyvsa[yyvsp-1].num; }
	case 139, 141:
		{ yyval.ast = astCreate2(tka, ZEND_AST_USE_ELEM, yyvsa[yyvsp].ast, nil); }
	case 140, 142:
		{ yyval.ast = astCreate2(tka, ZEND_AST_USE_ELEM, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 144:
		{ yyval.ast = astCreateList1(tka, ZEND_AST_CONST_DECL, yyvsa[yyvsp].ast); }
	case 150:
		{ yyval.ast, err = nil, errHalt; p.HandelError(yyval.tka, err); goto yyerrorlab }
	case 154:
		{ yyval.ast = astCreate2(tka, ZEND_AST_WHILE, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 155:
		{ yyval.ast = astCreate2(tka, ZEND_AST_DO_WHILE, yyvsa[yyvsp-5].ast, yyvsa[yyvsp-2].ast); }
	case 156:
		{ yyval.ast = astCreate4(tka, ZEND_AST_FOR, yyvsa[yyvsp-6].ast, yyvsa[yyvsp-4].ast, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 157:
		{ yyval.ast = astCreate2(tka, ZEND_AST_SWITCH, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 158:
		{ yyval.ast = astCreate1(tka, ZEND_AST_BREAK, yyvsa[yyvsp-1].ast); }
	case 159:
		{ yyval.ast = astCreate1(tka, ZEND_AST_CONTINUE, yyvsa[yyvsp-1].ast); }
	case 160:
		{ yyval.ast = astCreate1(tka, ZEND_AST_RETURN, yyvsa[yyvsp-1].ast); }
	case 164, 399:
		{ yyval.ast = astCreate1(tka, ZEND_AST_ECHO, yyvsa[yyvsp].ast); }
	case 166:
		{ yyval.ast = yyvsa[yyvsp-3].ast; }
	case 167:
		{ yyval.ast = astCreate4(tka, ZEND_AST_FOREACH, yyvsa[yyvsp-4].ast, yyvsa[yyvsp-2].ast, nil, yyvsa[yyvsp].ast); }
	case 168:
		{ yyval.ast = astCreate4(tka, ZEND_AST_FOREACH, yyvsa[yyvsp-6].ast, yyvsa[yyvsp-2].ast, yyvsa[yyvsp-4].ast, yyvsa[yyvsp].ast); }
	case 169:
		{ if !p.handleEncodingDeclaration(yyvsa[yyvsp-1].ast) { goto yyerrorlab } }
	case 170:
		{ yyval.ast = astCreate2(tka, ZEND_AST_DECLARE, yyvsa[yyvsp-3].ast, yyvsa[yyvsp].ast); }
	case 171, 180, 182, 213, 216, 218, 220, 222, 272, 299, 337, 338, 352, 381, 387, 390, 400, 403, 510, 558, 599:
		{ yyval.ast = nil; }
	case 172:
		{ yyval.ast = astCreate3(tka, ZEND_AST_TRY, yyvsa[yyvsp-3].ast, yyvsa[yyvsp-1].ast, yyvsa[yyvsp].ast); }
	case 173:
		{ yyval.ast = astCreate1(tka, ZEND_AST_GOTO, yyvsa[yyvsp-1].ast); }
	case 174:
		{ yyval.ast = astCreate1(tka, ZEND_AST_LABEL, yyvsa[yyvsp-1].ast); }
	case 175:
		{ yyval.ast = astCreate1(tka, ZEND_AST_CAST_VOID, yyvsa[yyvsp-1].ast); }
	case 176:
		{ yyval.ast = astCreateList0(tka, ZEND_AST_CATCH_LIST); }
	case 177:
		{ yyval.ast = astListAdd(yyvsa[yyvsp-8].ast, astCreate3(tka, ZEND_AST_CATCH, yyvsa[yyvsp-5].ast, yyvsa[yyvsp-4].ast, yyvsa[yyvsp-1].ast)); }
	case 178, 335:
		{ yyval.ast = astCreateList1(tka, ZEND_AST_NAME_LIST, yyvsa[yyvsp].ast); }
	case 184, 318, 321, 398:
		{ yyval.ast = astCreateList1(tka, ZEND_AST_STMT_LIST, yyvsa[yyvsp].ast); }
	case 186:
		{ yyval.ast = astCreate1(tka, ZEND_AST_UNSET, yyvsa[yyvsp].ast); }
	case 189:
		{ yyval.ast = astCreateDecl(tka, ZEND_AST_FUNC_DECL, yyvsa[yyvsp-11].num | yyvsa[yyvsp].num, yyvsa[yyvsp-12].num, p.getAstTknVal(yyvsa[yyvsp-9].tka), p.getTknStr(yyvsa[yyvsp-10].tka), yyvsa[yyvsp-7].ast, nil, yyvsa[yyvsp-2].ast, yyvsa[yyvsp-5].ast, nil); p.extraFnFlags = yyvsa[yyvsp-4].num; }
	case 190, 192, 202, 268, 383, 508:
		{ yyval.num = 0; }
	case 191:
		{ yyval.num = ZEND_PARAM_REF; }
	case 193:
		{ yyval.num = ZEND_PARAM_VARIADIC; }
	case 194, 196, 207, 209, 211, 385, 503, 504, 520:
		{ yyval.num = tka; }
	case 195:
		{ yyval.ast = astCreateDecl(tka, ZEND_AST_CLASS, yyvsa[yyvsp-9].num, yyvsa[yyvsp-7].num, p.getAstTknVal(yyvsa[yyvsp-3].tka), p.getTknStr(yyvsa[yyvsp-6].tka), yyvsa[yyvsp-5].ast, yyvsa[yyvsp-4].ast, yyvsa[yyvsp-1].ast, nil, nil); }
	case 197:
		{ yyval.ast = astCreateDecl(tka, ZEND_AST_CLASS, 0, yyvsa[yyvsp-7].num, p.getAstTknVal(yyvsa[yyvsp-3].tka), p.getTknStr(yyvsa[yyvsp-6].tka), yyvsa[yyvsp-5].ast, yyvsa[yyvsp-4].ast, yyvsa[yyvsp-1].ast, nil, nil); }
	case 198, 203:
		{ yyval.num = yyvsa[yyvsp].num; }
	case 199:
		{ yyval.num = p.classModifier(tka, yyvsa[yyvsp-1].num, yyvsa[yyvsp].num); if !yyval.num { goto yyerrorlab } }
	case 200:
		{ yyval.num = p.anonymousClassModifier(tka, 0, yyvsa[yyvsp].num); if !yyval.num { goto yyerrorlab } }
	case 201:
		{ yyval.num = p.anonymousClassModifier(tka, yyvsa[yyvsp-1].num, yyvsa[yyvsp].num); if !yyval.num { goto yyerrorlab } }
	case 204:
		{ yyval.num = ZEND_ACC_EXPLICIT_ABSTRACT_CLASS; }
	case 205:
		{ yyval.num = ZEND_ACC_FINAL; }
	case 206:
		{ yyval.num = ZEND_ACC_READONLY_CLASS|ZEND_ACC_NO_DYNAMIC_PROPERTIES; }
	case 208:
		{ yyval.ast = astCreateDecl(tka, ZEND_AST_CLASS, ZEND_ACC_TRAIT, yyvsa[yyvsp-5].num, p.getAstTknVal(yyvsa[yyvsp-3].tka), p.getTknStr(yyvsa[yyvsp-4].tka), nil, nil, yyvsa[yyvsp-1].ast, nil, nil); }
	case 210:
		{ yyval.ast = astCreateDecl(tka, ZEND_AST_CLASS, ZEND_ACC_INTERFACE, yyvsa[yyvsp-6].num, p.getAstTknVal(yyvsa[yyvsp-3].tka), p.getTknStr(yyvsa[yyvsp-5].tka), nil, yyvsa[yyvsp-4].ast, yyvsa[yyvsp-1].ast, nil, nil); }
	case 212:
		{ yyval.ast = astCreateDecl(tka, ZEND_AST_CLASS, ZEND_ACC_ENUM|ZEND_ACC_FINAL, yyvsa[yyvsp-7].num, p.getAstTknVal(yyvsa[yyvsp-3].tka), p.getTknStr(yyvsa[yyvsp-6].tka), nil, yyvsa[yyvsp-4].ast, yyvsa[yyvsp-1].ast, nil, yyvsa[yyvsp-5].ast); }
	case 215:
		{ yyval.ast = astCreate4(tka, ZEND_AST_ENUM_CASE, yyvsa[yyvsp-2].ast, yyvsa[yyvsp-1].ast, astCreateZvalStrOrNil(tka, p.getAstTknVal(yyvsa[yyvsp-3].tka)), nil); }
	case 225:
		{ yyval.ast = astCreate1(tka, ZEND_AST_REF, yyvsa[yyvsp].ast); }
	case 226:
		{ yyval.ast = yyvsa[yyvsp-1].ast; yyval.ast.Attr = ZEND_ARRAY_SYNTAX_LIST; }
	case 227, 533:
		{ yyval.ast = yyvsa[yyvsp-1].ast; yyval.ast.Attr = ZEND_ARRAY_SYNTAX_SHORT; }
	case 229, 231, 233, 236, 237, 253, 260, 302, 307, 511, 626:
		{ yyval.ast = yyvsa[yyvsp-2].ast; }
	case 238:
		{ yyval.ast = astCreateList0(tka, ZEND_AST_SWITCH_LIST); }
	case 239:
		{ yyval.ast = astListAdd(yyvsa[yyvsp-4].ast, astCreate2(tka, ZEND_AST_SWITCH_CASE, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast)); }
	case 240:
		{ yyval.ast = astListAdd(yyvsa[yyvsp-4].ast, astCreateEx2(tka, ZEND_AST_SWITCH_CASE, ZEND_ALT_CASE_SYNTAX, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast)); }
	case 241:
		{ yyval.ast = astListAdd(yyvsa[yyvsp-3].ast, astCreate2(tka, ZEND_AST_SWITCH_CASE, nil, yyvsa[yyvsp].ast)); }
	case 242:
		{ yyval.ast = astListAdd(yyvsa[yyvsp-3].ast, astCreateEx2(tka, ZEND_AST_SWITCH_CASE, ZEND_ALT_CASE_SYNTAX, nil, yyvsa[yyvsp].ast)); }
	case 243:
		{ yyval.ast = astCreate2(tka, ZEND_AST_MATCH, yyvsa[yyvsp-4].ast, yyvsa[yyvsp-1].ast); }
	case 244:
		{ yyval.ast = astCreateList0(tka, ZEND_AST_MATCH_ARM_LIST); }
	case 246:
		{ yyval.ast = astCreateList1(tka, ZEND_AST_MATCH_ARM_LIST, yyvsa[yyvsp].ast); }
	case 248:
		{ yyval.ast = astCreate2(tka, ZEND_AST_MATCH_ARM, yyvsa[yyvsp-3].ast, yyvsa[yyvsp].ast); }
	case 249:
		{ yyval.ast = astCreate2(tka, ZEND_AST_MATCH_ARM, nil, yyvsa[yyvsp].ast); }
	case 250, 402, 408:
		{ yyval.ast = astCreateList1(tka, ZEND_AST_EXPR_LIST, yyvsa[yyvsp].ast); }
	case 254:
		{ yyval.ast = astCreateList1(tka, ZEND_AST_IF, astCreate2(tka, ZEND_AST_IF_ELEM, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast)); }
	case 255:
		{ yyval.ast = astListAdd(yyvsa[yyvsp-5].ast, astCreate2(tka, ZEND_AST_IF_ELEM, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast)); }
	case 257:
		{ yyval.ast = astListAdd(yyvsa[yyvsp-2].ast, astCreate2(tka, ZEND_AST_IF_ELEM, nil, yyvsa[yyvsp].ast)); }
	case 258:
		{ yyval.ast = astCreateList1(tka, ZEND_AST_IF, astCreate2(tka, ZEND_AST_IF_ELEM, yyvsa[yyvsp-3].ast, yyvsa[yyvsp].ast)); }
	case 259:
		{ yyval.ast = astListAdd(yyvsa[yyvsp-6].ast, astCreate2(tka, ZEND_AST_IF_ELEM, yyvsa[yyvsp-3].ast, yyvsa[yyvsp].ast)); }
	case 261:
		{ yyval.ast = astListAdd(yyvsa[yyvsp-5].ast, astCreate2(tka, ZEND_AST_IF_ELEM, nil, yyvsa[yyvsp-2].ast)); }
	case 263:
		{ yyval.ast = astCreateList0(tka, ZEND_AST_PARAM_LIST); }
	case 264:
		{ yyval.ast = astCreateList1(tka, ZEND_AST_PARAM_LIST, yyvsa[yyvsp].ast); }
	case 269:
		{ yyval.num = p.modifierListToFlags(tka, ZEND_MODIFIER_TARGET_CPP, yyvsa[yyvsp].ast); if !yyval.num { goto yyerrorlab } }
	case 270:
		{ yyval.ast = astCreateEx6(tka, ZEND_AST_PARAM, yyvsa[yyvsp-6].num | yyvsa[yyvsp-4].num | yyvsa[yyvsp-3].num, yyvsa[yyvsp-5].ast, yyvsa[yyvsp-2].ast, nil, nil, astCreateZvalStrOrNil(tka, p.getAstTknVal(yyvsa[yyvsp-1].tka)), yyvsa[yyvsp].ast); }
	case 271:
		{ yyval.ast = astCreateEx6(tka, ZEND_AST_PARAM, yyvsa[yyvsp-8].num | yyvsa[yyvsp-6].num | yyvsa[yyvsp-5].num, yyvsa[yyvsp-7].ast, yyvsa[yyvsp-4].ast, yyvsa[yyvsp-1].ast, nil, astCreateZvalStrOrNil(tka, p.getAstTknVal(yyvsa[yyvsp-3].tka)), yyvsa[yyvsp].ast); }
	case 275, 287:
		{ yyval.ast = yyvsa[yyvsp].ast; yyval.ast.Attr |= ZEND_TYPE_nilABLE; }
	case 279:
		{ yyval.ast = astCreateEx0(tka, ZEND_AST_TYPE, IS_STATIC); }
	case 282, 295:
		{ yyval.ast = astCreateList2(tka, ZEND_AST_TYPE_UNION, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 284, 297:
		{ yyval.ast = astCreateList2(tka, ZEND_AST_TYPE_INTERSECTION, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 290:
		{ yyval.ast = astCreateEx0(tka, ZEND_AST_TYPE, IS_ARRAY); }
	case 291:
		{ yyval.ast = astCreateEx0(tka, ZEND_AST_TYPE, IS_CALLABLE); }
	case 301, 306, 530:
		{ yyval.ast = astCreateList0(tka, ZEND_AST_ARG_LIST); }
	case 303, 309:
		{ yyval.ast = astCreateFcc(tka); }
	case 304, 311:
		{ yyval.ast = astCreateList1(tka, ZEND_AST_ARG_LIST, yyvsa[yyvsp].ast); }
	case 308:
		{ yyval.ast = astCreateList1(tka, ZEND_AST_ARG_LIST, yyvsa[yyvsp-2].ast); }
	case 310:
		{ yyval.ast = astCreateList2(tka, ZEND_AST_ARG_LIST, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 313:
		{ yyval.ast = astCreate2(tka, ZEND_AST_NAMED_ARG, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 314, 607:
		{ yyval.ast = astCreate1(tka, ZEND_AST_UNPACK, yyvsa[yyvsp].ast); }
	case 319:
		{ yyval.ast = astCreate1(tka, ZEND_AST_GLOBAL, astCreate1(tka, ZEND_AST_VAR, yyvsa[yyvsp].ast)); }
	case 322:
		{ yyval.ast = astCreate2(tka, ZEND_AST_STATIC, yyvsa[yyvsp].ast, nil); }
	case 323:
		{ yyval.ast = astCreate2(tka, ZEND_AST_STATIC, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 326:
		{ yyval.ast = astCreate3(tka, ZEND_AST_PROP_GROUP, yyvsa[yyvsp-2].ast, yyvsa[yyvsp-1].ast, nil); yyval.ast.Attr = yyvsa[yyvsp-3].num; }
	case 327:
		{ yyval.ast = astCreate3(tka, ZEND_AST_PROP_GROUP, yyvsa[yyvsp-1].ast, astCreateList1(tka, ZEND_AST_PROP_DECL, yyvsa[yyvsp].ast), nil); yyval.ast.Attr = yyvsa[yyvsp-2].num; }
	case 328:
		{ yyval.ast = astCreate3(tka, ZEND_AST_CLASS_CONST_GROUP, yyvsa[yyvsp-1].ast, nil, nil); yyval.ast.Attr = yyvsa[yyvsp-3].num; }
	case 329:
		{ yyval.ast = astCreate3(tka, ZEND_AST_CLASS_CONST_GROUP, yyvsa[yyvsp-1].ast, nil, yyvsa[yyvsp-2].ast); yyval.ast.Attr = yyvsa[yyvsp-4].num; }
	case 330:
		{ yyval.ast = astCreateDecl(tka, ZEND_AST_METHOD, yyvsa[yyvsp-9].num | yyvsa[yyvsp-11].num | yyvsa[yyvsp].num, yyvsa[yyvsp-10].num, p.getAstTknVal(yyvsa[yyvsp-7].tka), p.getTknStr(yyvsa[yyvsp-8].tka), yyvsa[yyvsp-5].ast, nil, yyvsa[yyvsp-1].ast, yyvsa[yyvsp-3].ast, nil); p.extraFnFlags = yyvsa[yyvsp-2].num; }
	case 334:
		{ yyval.ast = astCreate2(tka, ZEND_AST_USE_TRAIT, yyvsa[yyvsp-1].ast, yyvsa[yyvsp].ast); }
	case 340:
		{ yyval.ast = astCreateList1(tka, ZEND_AST_TRAIT_ADAPTATIONS, yyvsa[yyvsp].ast); }
	case 344:
		{ yyval.ast = astCreate2(tka, ZEND_AST_TRAIT_PRECEDENCE, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 345:
		{ yyval.ast = astCreate2(tka, ZEND_AST_TRAIT_ALIAS, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 346:
		{ zast, errLexT := p.lexTString(yyvsa[yyvsp].tka); if errLexT != nil { err = errLexT; goto yyabortlab }; yyval.ast = astCreate2(tka, ZEND_AST_TRAIT_ALIAS, yyvsa[yyvsp-2].ast, zast); }
	case 347:
		{ modifiers := p.modifierTokenToFlag(ZEND_MODIFIER_TARGET_METHOD, yyvsa[yyvsp-1].num); yyval.ast = astCreateEx2(tka, ZEND_AST_TRAIT_ALIAS, modifiers, yyvsa[yyvsp-3].ast, yyvsa[yyvsp].ast); if !modifiers { goto yyerrorlab } }
	case 348:
		{ modifiers := p.modifierTokenToFlag(ZEND_MODIFIER_TARGET_METHOD, yyvsa[yyvsp].num); yyval.ast = astCreateEx2(tka, ZEND_AST_TRAIT_ALIAS, modifiers, yyvsa[yyvsp-2].ast, nil); if !modifiers { goto yyerrorlab } }
	case 349:
		{ yyval.ast = astCreate2(tka, ZEND_AST_METHOD_REFERENCE, nil, yyvsa[yyvsp].ast); }
	case 351:
		{ yyval.ast = astCreate2(tka, ZEND_AST_METHOD_REFERENCE, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 354:
		{ yyval.num = p.modifierListToFlags(tka, ZEND_MODIFIER_TARGET_PROPERTY, yyvsa[yyvsp].ast); if !yyval.num { goto yyerrorlab } }
	case 355, 356, 358:
		{ yyval.num = ZEND_ACC_PUBLIC; }
	case 357:
		{ yyval.num = p.modifierListToFlags(tka, ZEND_MODIFIER_TARGET_METHOD, yyvsa[yyvsp].ast); if !yyval.num { goto yyerrorlab }if !(yyval.num & ZEND_ACC_PPP_MASK) { yyval.num |= ZEND_ACC_PUBLIC } }
	case 359:
		{ yyval.num = p.modifierListToFlags(tka, ZEND_MODIFIER_TARGET_CONSTANT, yyvsa[yyvsp].ast); if !yyval.num { goto yyerrorlab }if !(yyval.num & ZEND_ACC_PPP_MASK) { yyval.num |= ZEND_ACC_PUBLIC } }
	case 360:
		{ yyval.ast = astCreateList1(tka, ZEND_AST_MODIFIER_LIST, p.astCreateZvalFromLong(tka, yyvsa[yyvsp].num)); }
	case 361:
		{ yyval.ast = astListAdd(yyvsa[yyvsp-1].ast, p.astCreateZvalFromLong(tka, yyvsa[yyvsp].num)); }
	case 362:
		{ yyval.num = int(T_PUBLIC); }
	case 363:
		{ yyval.num = T_PROTECTED; }
	case 364:
		{ yyval.num = T_PRIVATE; }
	case 365:
		{ yyval.num = T_PUBLIC_SET; }
	case 366:
		{ yyval.num = T_PROTECTED_SET; }
	case 367:
		{ yyval.num = T_PRIVATE_SET; }
	case 368:
		{ yyval.num = T_STATIC; }
	case 369:
		{ yyval.num = T_ABSTRACT; }
	case 370:
		{ yyval.num = T_FINAL; }
	case 371:
		{ yyval.num = T_READONLY; }
	case 373:
		{ yyval.ast = astCreateList1(tka, ZEND_AST_PROP_DECL, yyvsa[yyvsp].ast); }
	case 374:
		{ yyval.ast = astCreate4(tka, ZEND_AST_PROP_ELEM, yyvsa[yyvsp-1].ast, nil, astCreateZvalStrOrNil(tka, p.getAstTknVal(yyvsa[yyvsp].tka)), nil); }
	case 375:
		{ yyval.ast = astCreate4(tka, ZEND_AST_PROP_ELEM, yyvsa[yyvsp-3].ast, yyvsa[yyvsp-1].ast, astCreateZvalStrOrNil(tka, p.getAstTknVal(yyvsa[yyvsp].tka)), nil); }
	case 376:
		{ yyval.ast = astCreate4(tka, ZEND_AST_PROP_ELEM, yyvsa[yyvsp-4].ast, nil, astCreateZvalStrOrNil(tka, p.getAstTknVal(yyvsa[yyvsp-3].tka)), yyvsa[yyvsp-1].ast); }
	case 377:
		{ yyval.ast = astCreate4(tka, ZEND_AST_PROP_ELEM, yyvsa[yyvsp-6].ast, yyvsa[yyvsp-4].ast, astCreateZvalStrOrNil(tka, p.getAstTknVal(yyvsa[yyvsp-3].tka)), yyvsa[yyvsp-1].ast); }
	case 380:
		{ yyval.ast = astListAdd(yyvsa[yyvsp-2].ast, astWithAttributes(yyvsa[yyvsp].ast, yyvsa[yyvsp-1].ast)); }
	case 384:
		{ yyval.num = p.modifierListToFlags(tka, ZEND_MODIFIER_TARGET_PROPERTY_HOOK, yyvsa[yyvsp].ast); if !yyval.num { goto yyerrorlab } }
	case 386:
		{ yyval.ast = astCreateDecl(tka, ZEND_AST_PROPERTY_HOOK, yyvsa[yyvsp-8].num | yyvsa[yyvsp-7].num | yyvsa[yyvsp].num, yyvsa[yyvsp-4].num, p.getAstTknVal(yyvsa[yyvsp-5].tka), p.getTknStr(yyvsa[yyvsp-6].tka), yyvsa[yyvsp-3].ast, nil, yyvsa[yyvsp-1].ast, nil, nil); p.extraFnFlags = yyvsa[yyvsp-2].num; }
	case 389:
		{ yyval.ast = astCreate1(tka, ZEND_AST_PROPERTY_HOOK_SHORT_BODY, yyvsa[yyvsp-1].ast); }
	case 393:
		{ yyval.ast = astCreateList1(tka, ZEND_AST_CLASS_CONST_DECL, yyvsa[yyvsp].ast); }
	case 394, 396:
		{ yyval.ast = astCreate3(tka, ZEND_AST_CONST_ELEM, yyvsa[yyvsp-3].ast, yyvsa[yyvsp-1].ast, astCreateZvalStrOrNil(tka, p.getAstTknVal(yyvsa[yyvsp].tka))); }
	case 395:
		{ zast, errLexT := p.lexTString(yyvsa[yyvsp-3].tka); if errLexT != nil { err = errLexT; goto yyabortlab }; yyval.ast = astCreate3(tka, ZEND_AST_CONST_ELEM, zast, yyvsa[yyvsp-1].ast, astCreateZvalStrOrNil(tka, p.getAstTknVal(yyvsa[yyvsp].tka))); }
	case 406:
		{ yyval.ast = astListAdd(yyvsa[yyvsp-3].ast, astCreate1(tka, ZEND_AST_CAST_VOID, yyvsa[yyvsp].ast)); }
	case 407:
		{ yyval.ast = astCreateList1(tka, ZEND_AST_EXPR_LIST, astCreate1(tka, ZEND_AST_CAST_VOID, yyvsa[yyvsp].ast)); }
	case 410:
		{ yyval.ast = astCreate2(tka, ZEND_AST_NEW, astCreateDecl(tka, ZEND_AST_CLASS, ZEND_ACC_ANON_CLASS | yyvsa[yyvsp-9].num, yyvsa[yyvsp-7].num, p.getAstTknVal(yyvsa[yyvsp-3].tka), nil, yyvsa[yyvsp-5].ast, yyvsa[yyvsp-4].ast, yyvsa[yyvsp-1].ast, nil, nil), yyvsa[yyvsp-6].ast); }
	case 411:
		{ yyval.ast = astCreate2(tka, ZEND_AST_NEW, yyvsa[yyvsp-1].ast, yyvsa[yyvsp].ast); }
	case 413:
		{ astWithAttributes(yyvsa[yyvsp].ast.Child[0], yyvsa[yyvsp-1].ast); yyval.ast = yyvsa[yyvsp].ast; }
	case 414:
		{ yyval.ast = astCreate2(tka, ZEND_AST_NEW, yyvsa[yyvsp].ast, astCreateList0(tka, ZEND_AST_ARG_LIST)); }
	case 416:
		{ yyvsa[yyvsp-3].ast.Attr = ZEND_ARRAY_SYNTAX_LIST; yyval.ast = astCreate2(tka, ZEND_AST_ASSIGN, yyvsa[yyvsp-3].ast, yyvsa[yyvsp].ast); }
	case 417:
		{ yyvsa[yyvsp-3].ast.Attr = ZEND_ARRAY_SYNTAX_SHORT; yyval.ast = astCreate2(tka, ZEND_AST_ASSIGN, yyvsa[yyvsp-3].ast, yyvsa[yyvsp].ast); }
	case 418:
		{ yyval.ast = astCreate2(tka, ZEND_AST_ASSIGN, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 419:
		{ yyval.ast = astCreate2(tka, ZEND_AST_ASSIGN_REF, yyvsa[yyvsp-3].ast, yyvsa[yyvsp].ast); }
	case 420:
		{ yyval.ast = astCreate2(tka, ZEND_AST_CALL, p.astCreateZvalStrAttr(tka, ZEND_STR_CLONE, ZEND_NAME_FQ), yyvsa[yyvsp].ast) }
	case 421:
		{ yyval.ast = astCreate2(tka, ZEND_AST_CALL, p.astCreateZvalStrAttr(tka, ZEND_STR_CLONE, ZEND_NAME_FQ), astCreateList1(tka, ZEND_AST_ARG_LIST, yyvsa[yyvsp].ast)) }
	case 422:
		{ yyval.ast = astCreateAssignOp(tka, ZEND_ADD, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 423:
		{ yyval.ast = astCreateAssignOp(tka, ZEND_SUB, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 424:
		{ yyval.ast = astCreateAssignOp(tka, ZEND_MUL, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 425:
		{ yyval.ast = astCreateAssignOp(tka, ZEND_POW, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 426:
		{ yyval.ast = astCreateAssignOp(tka, ZEND_DIV, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 427:
		{ yyval.ast = astCreateAssignOp(tka, ZEND_CONCAT, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 428:
		{ yyval.ast = astCreateAssignOp(tka, ZEND_MOD, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 429:
		{ yyval.ast = astCreateAssignOp(tka, ZEND_BW_AND, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 430:
		{ yyval.ast = astCreateAssignOp(tka, ZEND_BW_OR, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 431:
		{ yyval.ast = astCreateAssignOp(tka, ZEND_BW_XOR, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 432:
		{ yyval.ast = astCreateAssignOp(tka, ZEND_SL, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 433:
		{ yyval.ast = astCreateAssignOp(tka, ZEND_SR, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 434:
		{ yyval.ast = astCreate2(tka, ZEND_AST_ASSIGN_COALESCE, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 435:
		{ yyval.ast = astCreate1(tka, ZEND_AST_POST_INC, yyvsa[yyvsp-1].ast); }
	case 436:
		{ yyval.ast = astCreate1(tka, ZEND_AST_PRE_INC, yyvsa[yyvsp].ast); }
	case 437:
		{ yyval.ast = astCreate1(tka, ZEND_AST_POST_DEC, yyvsa[yyvsp-1].ast); }
	case 438:
		{ yyval.ast = astCreate1(tka, ZEND_AST_PRE_DEC, yyvsa[yyvsp].ast); }
	case 439, 441:
		{ yyval.ast = astCreate2(tka, ZEND_AST_OR, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 440, 442, 634:
		{ yyval.ast = astCreate2(tka, ZEND_AST_AND, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 443:
		{ yyval.ast = astCreateBinaryOp(tka, ZEND_BOOL_XOR, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 444:
		{ yyval.ast = astCreateBinaryOp(tka, ZEND_BW_OR, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 445, 446:
		{ yyval.ast = astCreateBinaryOp(tka, ZEND_BW_AND, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 447:
		{ yyval.ast = astCreateBinaryOp(tka, ZEND_BW_XOR, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 448:
		{ yyval.ast = astCreateConcatOp(tka, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 449:
		{ yyval.ast = astCreateBinaryOp(tka, ZEND_ADD, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 450:
		{ yyval.ast = astCreateBinaryOp(tka, ZEND_SUB, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 451:
		{ yyval.ast = astCreateBinaryOp(tka, ZEND_MUL, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 452:
		{ yyval.ast = astCreateBinaryOp(tka, ZEND_POW, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 453:
		{ yyval.ast = astCreateBinaryOp(tka, ZEND_DIV, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 454:
		{ yyval.ast = astCreateBinaryOp(tka, ZEND_MOD, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 455:
		{ yyval.ast = astCreateBinaryOp(tka, ZEND_SL, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 456:
		{ yyval.ast = astCreateBinaryOp(tka, ZEND_SR, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 457:
		{ yyval.ast = astCreate1(tka, ZEND_AST_UNARY_PLUS, yyvsa[yyvsp].ast); }
	case 458:
		{ yyval.ast = astCreate1(tka, ZEND_AST_UNARY_MINUS, yyvsa[yyvsp].ast); }
	case 459:
		{ yyval.ast = astCreateEx1(tka, ZEND_AST_UNARY_OP, ZEND_BOOL_NOT, yyvsa[yyvsp].ast); }
	case 460:
		{ yyval.ast = astCreateEx1(tka, ZEND_AST_UNARY_OP, ZEND_BW_NOT, yyvsa[yyvsp].ast); }
	case 461:
		{ yyval.ast = astCreateBinaryOp(tka, ZEND_IS_IDENTICAL, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 462:
		{ yyval.ast = astCreateBinaryOp(tka, ZEND_IS_NOT_IDENTICAL, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 463:
		{ yyval.ast = astCreateBinaryOp(tka, ZEND_IS_EQUAL, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 464:
		{ yyval.ast = astCreateBinaryOp(tka, ZEND_IS_NOT_EQUAL, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 465:
		{ yyval.ast = astCreate2(tka, ZEND_AST_PIPE, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 466:
		{ yyval.ast = astCreateBinaryOp(tka, ZEND_IS_SMALLER, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 467:
		{ yyval.ast = astCreateBinaryOp(tka, ZEND_IS_SMALLER_OR_EQUAL, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 468:
		{ yyval.ast = astCreate2(tka, ZEND_AST_GREATER, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 469:
		{ yyval.ast = astCreate2(tka, ZEND_AST_GREATER_EQUAL, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 470:
		{ yyval.ast = astCreateBinaryOp(tka, ZEND_SPACESHIP, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 471:
		{ yyval.ast = astCreate2(tka, ZEND_AST_INSTANCEOF, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 472:
		{ yyval.ast = yyvsa[yyvsp-1].ast; if yyval.ast.Kind == ZEND_AST_CONDITIONAL { yyval.ast.Attr = ZEND_PARENTHESIZED_CONDITIONAL }if yyval.ast.Kind == ZEND_AST_ARROW_FUNC { yyval.ast.Attr = ZEND_PARENTHESIZED_ARROW_FUNC } }
	case 475:
		{ yyval.ast = astCreate3(tka, ZEND_AST_CONDITIONAL, yyvsa[yyvsp-4].ast, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 476:
		{ yyval.ast = astCreate3(tka, ZEND_AST_CONDITIONAL, yyvsa[yyvsp-3].ast, nil, yyvsa[yyvsp].ast); }
	case 477:
		{ yyval.ast = astCreate2(tka, ZEND_AST_COALESCE, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 479:
		{ yyval.ast = astCreateCast(tka, IS_LONG, yyvsa[yyvsp].ast); }
	case 480:
		{ yyval.ast = astCreateCast(tka, IS_DOUBLE, yyvsa[yyvsp].ast); }
	case 481:
		{ yyval.ast = astCreateCast(tka, IS_STRING, yyvsa[yyvsp].ast); }
	case 482:
		{ yyval.ast = astCreateCast(tka, IS_ARRAY, yyvsa[yyvsp].ast); }
	case 483:
		{ yyval.ast = astCreateCast(tka, IS_OBJECT, yyvsa[yyvsp].ast); }
	case 484:
		{ yyval.ast = astCreateCast(tka, _IS_BOOL, yyvsa[yyvsp].ast); }
	case 485:
		{ yyval.ast = astCreateCast(tka, IS_nil, yyvsa[yyvsp].ast); }
	case 486:
		{ yyval.ast = astCreate2(tka, ZEND_AST_CALL, p.astCreateZvalStrAttr(tka, ZEND_STR_EXIT, ZEND_NAME_FQ), yyvsa[yyvsp].ast) }
	case 487:
		{ yyval.ast = astCreate1(tka, ZEND_AST_SILENCE, yyvsa[yyvsp].ast); }
	case 489:
		{ yyval.ast = astCreate1(tka, ZEND_AST_SHELL_EXEC, yyvsa[yyvsp-1].ast); }
	case 490:
		{ yyval.ast = astCreate1(tka, ZEND_AST_PRINT, yyvsa[yyvsp].ast); }
	case 491:
		{ yyval.ast = astCreate2(tka, ZEND_AST_YIELD, nil, nil); p.extraFnFlags |= ZEND_ACC_GENERATOR; }
	case 492:
		{ yyval.ast = astCreate2(tka, ZEND_AST_YIELD, yyvsa[yyvsp].ast, nil); p.extraFnFlags |= ZEND_ACC_GENERATOR; }
	case 493:
		{ yyval.ast = astCreate2(tka, ZEND_AST_YIELD, yyvsa[yyvsp].ast, yyvsa[yyvsp-2].ast); p.extraFnFlags |= ZEND_ACC_GENERATOR; }
	case 494:
		{ yyval.ast = astCreate1(tka, ZEND_AST_YIELD_FROM, yyvsa[yyvsp].ast); p.extraFnFlags |= ZEND_ACC_GENERATOR; }
	case 495:
		{ yyval.ast = astCreate1(tka, ZEND_AST_THROW, yyvsa[yyvsp].ast); }
	case 498:
		{ yyval.ast = yyvsa[yyvsp].ast; (*AstDecl)(unsafe.Pointer(yyval.ast)).Flags |= ZEND_ACC_STATIC; }
	case 499:
		{ yyval.ast = astWithAttributes(yyvsa[yyvsp].ast, yyvsa[yyvsp-2].ast); (*AstDecl)(unsafe.Pointer(yyval.ast)).Flags |= ZEND_ACC_STATIC; }
	case 501:
		{ yyval.ast = astCreateDecl(tka, ZEND_AST_CLOSURE, yyvsa[yyvsp-11].num | yyvsa[yyvsp].num, yyvsa[yyvsp-12].num, p.getAstTknVal(yyvsa[yyvsp-10].tka), nil, yyvsa[yyvsp-8].ast, yyvsa[yyvsp-6].ast, yyvsa[yyvsp-2].ast, yyvsa[yyvsp-5].ast, nil); p.extraFnFlags = yyvsa[yyvsp-4].num; }
	case 502:
		{ yyval.ast = astCreateDecl(tka, ZEND_AST_ARROW_FUNC, yyvsa[yyvsp-10].num | yyvsa[yyvsp].num, yyvsa[yyvsp-11].num, p.getAstTknVal(yyvsa[yyvsp-9].tka), nil, yyvsa[yyvsp-7].ast, nil, yyvsa[yyvsp-1].ast, yyvsa[yyvsp-5].ast, nil); p.extraFnFlags = yyvsa[yyvsp-3].num; }
	case 505:
		{ yyval.str = p.lexer.docComment; p.lexer.docComment = nil; }
	case 506:
		{ yyval.num = p.extraFnFlags; p.extraFnFlags = 0; }
	case 507:
		{ /* yyval.ptr = p.cursor */ }
	case 509:
		{ yyval.num = ZEND_ACC_RETURN_REFERENCE; }
	case 513:
		{ yyval.ast = astCreateList1(tka, ZEND_AST_CLOSURE_USES, yyvsa[yyvsp].ast); }
	case 515:
		{ yyval.ast = yyvsa[yyvsp].ast; yyval.ast.Attr = ZEND_BIND_REF; }
	case 516:
		{ yyval.ast = astCreate2(tka, ZEND_AST_CALL, yyvsa[yyvsp-1].ast, yyvsa[yyvsp].ast); }
	case 517:
		{ zast, errLexT := p.lexTString(yyvsa[yyvsp-1].tka); if errLexT != nil { err = errLexT; goto yyabortlab }; yyval.ast = astCreate2(tka, ZEND_AST_CALL, zast, yyvsa[yyvsp].ast); }
	case 518, 519:
		{ yyval.ast = astCreate3(tka, ZEND_AST_STATIC_CALL, yyvsa[yyvsp-3].ast, yyvsa[yyvsp-1].ast, yyvsa[yyvsp].ast); }
	case 522:
		{ ZVAL_INTERNED_STR(ZEND_STR_STATIC); yyval.ast = astCreateZvalEx(tka, &zv, ZEND_NAME_NOT_FQ); }
	case 527, 539:
		{ yyval.ast = p.astCreateZvalFromStr(tka, ""); }
	case 532:
		{ yyval.ast = yyvsa[yyvsp-1].ast; yyval.ast.Attr = ZEND_ARRAY_SYNTAX_LONG; }
	case 544:
		{ yyval.ast = astCreate1(tka, ZEND_AST_CONST, yyvsa[yyvsp].ast); }
	case 545:
		{ yyval.ast = astCreateEx0(tka, ZEND_AST_MAGIC_CONST, T_LINE); }
	case 546:
		{ yyval.ast = astCreateEx0(tka, ZEND_AST_MAGIC_CONST, T_FILE); }
	case 547:
		{ yyval.ast = astCreateEx0(tka, ZEND_AST_MAGIC_CONST, T_DIR); }
	case 548:
		{ yyval.ast = astCreateEx0(tka, ZEND_AST_MAGIC_CONST, T_TRAIT_C); }
	case 549:
		{ yyval.ast = astCreateEx0(tka, ZEND_AST_MAGIC_CONST, T_METHOD_C); }
	case 550:
		{ yyval.ast = astCreateEx0(tka, ZEND_AST_MAGIC_CONST, T_FUNC_C); }
	case 551:
		{ yyval.ast = astCreateEx0(tka, ZEND_AST_MAGIC_CONST, T_PROPERTY_C); }
	case 552:
		{ yyval.ast = astCreateEx0(tka, ZEND_AST_MAGIC_CONST, T_NS_C); }
	case 553:
		{ yyval.ast = astCreateEx0(tka, ZEND_AST_MAGIC_CONST, T_CLASS_C); }
	case 554, 555:
		{ yyval.ast = astCreateClassConstOrName(tka, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 556, 557:
		{ yyval.ast = astCreate2(tka, ZEND_AST_CLASS_CONST, yyvsa[yyvsp-4].ast, yyvsa[yyvsp-1].ast); }
	case 562:
		{ yyval.ast = yyvsa[yyvsp-1].ast; if yyval.ast.Kind == ZEND_AST_STATIC_PROP { yyval.ast.Attr = ZEND_PARENTHESIZED_STATIC_PROP } }
	case 572, 583, 586, 594, 597, 614, 625:
		{ yyval.ast = astCreate1(tka, ZEND_AST_VAR, yyvsa[yyvsp].ast); }
	case 573, 587:
		{ yyval.ast = astCreate2(tka, ZEND_AST_DIM, yyvsa[yyvsp-3].ast, yyvsa[yyvsp-1].ast); }
	case 574:
		{ yyval.ast = astCreate3(tka, ZEND_AST_METHOD_CALL, yyvsa[yyvsp-3].ast, yyvsa[yyvsp-1].ast, yyvsa[yyvsp].ast); }
	case 575:
		{ yyval.ast = astCreate3(tka, ZEND_AST_nilSAFE_METHOD_CALL, yyvsa[yyvsp-3].ast, yyvsa[yyvsp-1].ast, yyvsa[yyvsp].ast); }
	case 579, 588:
		{ yyval.ast = astCreate2(tka, ZEND_AST_PROP, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 580, 589:
		{ yyval.ast = astCreate2(tka, ZEND_AST_nilSAFE_PROP, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 584, 585, 590, 591:
		{ yyval.ast = astCreate2(tka, ZEND_AST_STATIC_PROP, yyvsa[yyvsp-2].ast, yyvsa[yyvsp].ast); }
	case 598:
		{ yyval.ast = astListRtrim(yyvsa[yyvsp].ast); }
	case 602:
		{ yyval.ast = astCreateList1(tka, ZEND_AST_ARRAY, yyvsa[yyvsp].ast); }
	case 603:
		{ yyval.ast = astCreate2(tka, ZEND_AST_ARRAY_ELEM, yyvsa[yyvsp].ast, yyvsa[yyvsp-2].ast); }
	case 604:
		{ yyval.ast = astCreate2(tka, ZEND_AST_ARRAY_ELEM, yyvsa[yyvsp].ast, nil); }
	case 605:
		{ yyval.ast = astCreateEx2(tka, ZEND_AST_ARRAY_ELEM, 1, yyvsa[yyvsp].ast, yyvsa[yyvsp-3].ast); }
	case 606:
		{ yyval.ast = astCreateEx2(tka, ZEND_AST_ARRAY_ELEM, 1, yyvsa[yyvsp].ast, nil); }
	case 608:
		{ yyvsa[yyvsp-1].ast.Attr = ZEND_ARRAY_SYNTAX_LIST; yyval.ast = astCreate2(tka, ZEND_AST_ARRAY_ELEM, yyvsa[yyvsp-1].ast, yyvsa[yyvsp-5].ast); }
	case 609:
		{ yyvsa[yyvsp-1].ast.Attr = ZEND_ARRAY_SYNTAX_LIST; yyval.ast = astCreate2(tka, ZEND_AST_ARRAY_ELEM, yyvsa[yyvsp-1].ast, nil); }
	case 612:
		{ yyval.ast = astCreateList1(tka, ZEND_AST_ENCAPS_LIST, yyvsa[yyvsp].ast); }
	case 613:
		{ yyval.ast = astCreateList2(tka, ZEND_AST_ENCAPS_LIST, yyvsa[yyvsp-1].ast, yyvsa[yyvsp].ast); }
	case 615:
		{ yyval.ast = astCreate2(tka, ZEND_AST_DIM, astCreate1(tka, ZEND_AST_VAR, yyvsa[yyvsp-3].ast), yyvsa[yyvsp-1].ast); }
	case 616:
		{ yyval.ast = astCreate2(tka, ZEND_AST_PROP, astCreate1(tka, ZEND_AST_VAR, yyvsa[yyvsp-2].ast), yyvsa[yyvsp].ast); }
	case 617:
		{ yyval.ast = astCreate2(tka, ZEND_AST_nilSAFE_PROP, astCreate1(tka, ZEND_AST_VAR, yyvsa[yyvsp-2].ast), yyvsa[yyvsp].ast); }
	case 618:
		{ yyval.ast = astCreateEx1(tka, ZEND_AST_VAR, ZEND_ENCAPS_VAR_DOLLAR_CURLY_VAR_VAR, yyvsa[yyvsp-1].ast); }
	case 619:
		{ yyval.ast = astCreateEx1(tka, ZEND_AST_VAR, ZEND_ENCAPS_VAR_DOLLAR_CURLY, yyvsa[yyvsp-1].ast); }
	case 620:
		{ yyval.ast = astCreateEx2(tka, ZEND_AST_DIM, ZEND_ENCAPS_VAR_DOLLAR_CURLY, astCreate1(tka, ZEND_AST_VAR, yyvsa[yyvsp-4].ast), yyvsa[yyvsp-2].ast); }
	case 624:
		{ yyval.ast = negateNumString(yyvsa[yyvsp].ast); }
	case 627:
		{ yyval.ast = astCreate1(tka, ZEND_AST_EMPTY, yyvsa[yyvsp-1].ast); }
	case 628:
		{ yyval.ast = astCreateEx1(tka, ZEND_AST_INCLUDE_OR_EVAL, ZEND_INCLUDE, yyvsa[yyvsp].ast); }
	case 629:
		{ yyval.ast = astCreateEx1(tka, ZEND_AST_INCLUDE_OR_EVAL, ZEND_INCLUDE_ONCE, yyvsa[yyvsp].ast); }
	case 630:
		{ yyval.ast = astCreateEx1(tka, ZEND_AST_INCLUDE_OR_EVAL, ZEND_EVAL, yyvsa[yyvsp-1].ast); }
	case 631:
		{ yyval.ast = astCreateEx1(tka, ZEND_AST_INCLUDE_OR_EVAL, ZEND_REQUIRE, yyvsa[yyvsp].ast); }
	case 632:
		{ yyval.ast = astCreateEx1(tka, ZEND_AST_INCLUDE_OR_EVAL, ZEND_REQUIRE_ONCE, yyvsa[yyvsp].ast); }
	case 635:
		{ yyval.ast = astCreate1(tka, ZEND_AST_ISSET, yyvsa[yyvsp].ast); }
}
