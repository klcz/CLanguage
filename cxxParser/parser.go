package cxxParser

import (
	"fmt"
	"strings"
)

// ============================================================================
// CParser — the LALR-generated parser + hand-written implementation
// ============================================================================

type CParser struct {
	_yaccVerboseFlag int
	ErrorOutput      strings.Builder
	eofToken         int
	yyMax            int
	yyVals           []interface{}
	yyVal            interface{}

	yyStates []int

	yyExpectingState int
	useGlobalStacks  bool
	debug            yyDebug

	DebugHelper string // DebugYyN, DumpTokens

	_tu   *TranslationUnit
	lexer *ParserInput
}

func NewCParser() *CParser {
	p := &CParser{
		DebugHelper: "",
	}
	p.yyMax = 256
	p.yyVals = make([]interface{}, 0)
	p.yyVal = ""
	p._tu = NewTranslationUnit("uninitialized.c")
	p.lexer = NewParserInput(nil)
	return p
}

func (p *CParser) yyerror(message string) {
	p.yyerrorExpected(message, nil)
}

func (p *CParser) yyerrorExpected(message string, expected []string) {
	if p._yaccVerboseFlag > 0 && len(expected) > 0 {
		p.ErrorOutput.WriteString(message + ", expecting")
		for _, e := range expected {
			p.ErrorOutput.WriteString(" " + e)
		}
		p.ErrorOutput.WriteString("\n")
	} else {
		p.ErrorOutput.WriteString(message + "\n")
	}
}

func yyname(token int) string {
	if token < 0 || token >= len(yyNames) {
		return "[illegal]"
	}
	if name := yyNames[token]; name != "" {
		return name
	}
	return "[unknown]"
}

func (p *CParser) yyExpectingTokens(state int) []int {
	var ok []bool
	if //goland:noinspection GoBoolExpressions
	len(yyNames) > 0 {
		ok = make([]bool, len(yyNames))
	}
	n := yySindex[state]
	if n != 0 {
		start := n
		if start < 0 {
			start = -n
		}
		for token := start; int(token) < len(yyNames) && int(n)+int(token) < len(yyTable); token++ {
			if yyCheck[n+token] == token && !ok[token] && yyNames[token] != "" {
				ok[token] = true
			}
		}
	}
	n = yyRindex[state]
	if n != 0 {
		start := n
		if start < 0 {
			start = -n
		}
		for token := start; int(token) < len(yyNames) && int(n)+int(token) < len(yyTable); token++ {
			if yyCheck[n+token] == token && !ok[token] && yyNames[token] != "" {
				ok[token] = true
			}
		}
	}
	var result []int
	for token := 0; token < len(ok); token++ {
		if ok[token] {
			result = append(result, token)
		}
	}
	return result
}

func (p *CParser) yyExpecting(state int) []string {
	tokens := p.yyExpectingTokens(state)
	result := make([]string, len(tokens))
	for i, tok := range tokens {
		result[i] = yyNames[tok]
	}
	return result
}

func (p *CParser) yyDefault(first interface{}) interface{} {
	return first
}

/*--start@[ParseTmpl]--*/

// yyparse implements the LALR(1) parser driver.
func (p *CParser) yyparseTmpl(yyLex yyInput) interface{} {
	if p.yyMax <= 0 {
		p.yyMax = 256
	}
	yyState := 0
	p.yyVal = nil
	yyToken := -1
	yyErrorFlag := 0

	if p.useGlobalStacks && p.yyStates != nil {
		// use preallocated
	} else {
		p.yyStates = make([]int, p.yyMax)
		p.yyVals = make([]interface{}, p.yyMax)
	}

	var yyM int
	var yyV int

continueYyLoop:
	for yyTop := 0; ; yyTop++ {
		if yyTop >= len(p.yyStates) {
			newStates := make([]int, len(p.yyStates)+p.yyMax)
			copy(newStates, p.yyStates)
			p.yyStates = newStates
			newVals := make([]interface{}, len(p.yyVals)+p.yyMax)
			copy(newVals, p.yyVals)
			p.yyVals = newVals
		}
		p.yyStates[yyTop] = yyState
		p.yyVals[yyTop] = p.yyVal
		if p.debug != nil {
			p.debug.push(yyState, p.yyVal)
		}

	continueYyDiscarded:
		for { // yyDiscarded loop
			var yyN int
			if yyN = int(yyDefRed[yyState]); yyN == 0 {
				if yyToken < 0 {
					if yyLex.advance() {
						yyToken = yyLex.token()
					} else {
						yyToken = 0
					}
					if p.debug != nil {
						p.debug.lex(yyState, yyToken, yyname(yyToken), yyLex.value())
					}
				}
				yyN = int(yySindex[yyState])
				if yyN != 0 {
					yyN += yyToken
					if yyN >= 0 && yyN < len(yyTable) && int(yyCheck[yyN]) == yyToken {
						if p.debug != nil {
							p.debug.shift(yyState, int(yyTable[yyN]), yyErrorFlag-1)
						}
						yyState = int(yyTable[yyN])
						p.yyVal = yyLex.value()
						yyToken = -1
						if yyErrorFlag > 0 {
							yyErrorFlag--
						}
						continue continueYyLoop
					}
				}
				yyN = int(yyRindex[yyState])
				if yyN != 0 {
					yyN += yyToken
					if yyN >= 0 && yyN < len(yyTable) && int(yyCheck[yyN]) == yyToken {
						yyN = int(yyTable[yyN])
					} else {
						// Error handling
						switch yyErrorFlag {
						case 0:
							p.yyExpectingState = yyState
							if p.debug != nil {
								p.debug.error("syntax error")
							}
							if yyToken == 0 || yyToken == p.eofToken {
								panic(newYyUnexpectedEof())
							}
							yyErrorFlag = 1
							fallthrough
						case 1, 2:
							yyErrorFlag = 3
							for {
								yyN = int(yySindex[p.yyStates[yyTop]])
								if yyN != 0 {
									yyN += int(TokenKindYYErrorCode)
									if yyN >= 0 && yyN < len(yyTable) && int(yyCheck[yyN]) == int(TokenKindYYErrorCode) {
										if p.debug != nil {
											p.debug.shift(p.yyStates[yyTop], int(yyTable[yyN]), 3)
										}
										yyState = int(yyTable[yyN])
										p.yyVal = yyLex.value()
										continue continueYyLoop
									}
								}
								if p.debug != nil {
									p.debug.pop(p.yyStates[yyTop])
								}
								yyTop--
								if yyTop < 0 {
									break
								}
							}
							if p.debug != nil {
								p.debug.reject()
							}
							panic(yyException{message: "irrecoverable syntax error"})
						case 3:
							if yyToken == 0 {
								if p.debug != nil {
									p.debug.reject()
								}
								panic(yyException{message: "irrecoverable syntax error at end-of-file"})
							}
							if p.debug != nil {
								p.debug.discard(yyState, yyToken, yyname(yyToken), yyLex.value())
							}
							yyToken = -1
							continue continueYyDiscarded
						}
					}
				} else {
					// no Rindex entry either — error
					switch yyErrorFlag {
					case 0:
						p.yyExpectingState = yyState
						if p.debug != nil {
							p.debug.error("syntax error")
						}
						if yyToken == 0 || yyToken == p.eofToken {
							panic(newYyUnexpectedEof())
						}
						yyErrorFlag = 1
						fallthrough
					case 1, 2:
						yyErrorFlag = 3
						for {
							yyN = int(yySindex[p.yyStates[yyTop]])
							if yyN != 0 {
								yyN += int(TokenKindYYErrorCode)
								if yyN >= 0 && yyN < len(yyTable) && int(yyCheck[yyN]) == int(TokenKindYYErrorCode) {
									if p.debug != nil {
										p.debug.shift(p.yyStates[yyTop], int(yyTable[yyN]), 3)
									}
									yyState = int(yyTable[yyN])
									p.yyVal = yyLex.value()
									continue continueYyLoop
								}
							}
							if p.debug != nil {
								p.debug.pop(p.yyStates[yyTop])
							}
							yyTop--
							if yyTop < 0 {
								break
							}
						}
						if p.debug != nil {
							p.debug.reject()
						}
						panic(yyException{message: "irrecoverable syntax error"})
					case 3:
						if yyToken == 0 {
							if p.debug != nil {
								p.debug.reject()
							}
							panic(yyException{message: "irrecoverable syntax error at end-of-file"})
						}
						if p.debug != nil {
							p.debug.discard(yyState, yyToken, yyname(yyToken), yyLex.value())
						}
						yyToken = -1
						continue continueYyDiscarded
					}
				}
			}
			// Reduce
			yyV = yyTop + 1 - int(yyLen[yyN])
			if p.debug != nil {
				p.debug.reduce(yyState, p.yyStates[yyV-1], yyN, fmt.Sprintf("rule %d", yyN), int(yyLen[yyN]))
			}
			// Default: yyVal = yyDefault(yyV > yyTop ? null : yyVals[yyV])
			if yyV > yyTop {
				p.yyVal = nil
			} else {
				p.yyVal = p.yyDefault(p.yyVals[yyV])
			}

			/*--start@[ParseTmpl.codes]--*/

			// User action switch
			// (grammar actions would appear here; see full generated parser for complete list)
			// For now, only structural skeleton — real actions come from the grammar.
			switch yyN {
			case 1:
				{
					_ = p.lexer.CurrentToken
				}
			}

			/*--end@[ParseTmpl.codes]--*/

			yyTop -= int(yyLen[yyN])
			yyState = p.yyStates[yyTop]
			yyM = int(yyLhs[yyN])
			if yyState == 0 && yyM == 0 {
				if p.debug != nil {
					p.debug.shift(0, yyFinal, 0)
				}
				yyState = yyFinal
				if yyToken < 0 {
					if yyLex.advance() {
						yyToken = yyLex.token()
					} else {
						yyToken = 0
					}
					if p.debug != nil {
						p.debug.lex(yyState, yyToken, yyname(yyToken), yyLex.value())
					}
				}
				if yyToken == 0 {
					if p.debug != nil {
						p.debug.accept(p.yyVal)
					}
					return p.yyVal
				}
				continue continueYyLoop
			}
			yyN = int(yyGindex[yyM])
			if yyN != 0 {
				yyN += yyState
				if yyN >= 0 && yyN < len(yyTable) && int(yyCheck[yyN]) == yyState {
					yyState = int(yyTable[yyN])
				} else {
					yyState = int(yyDgoto[yyM])
				}
			} else {
				yyState = int(yyDgoto[yyM])
			}
			if p.debug != nil {
				p.debug.shift(p.yyStates[yyTop], yyState, 0)
			}
			continue continueYyLoop
		}
	}
}

/*--end@[ParseTmpl]--*/

// ── CParserImpl methods ──────────────────────────────────────────────────

//goland:noinspection GoUnusedGlobalVariable
var noTokens []Token

//goland:noinspection GoUnusedGlobalVariable
var noObjects []interface{}

func (p *CParser) ParseTranslationUnitFromCode(name, code string, include PreprocessorInclude, report *Report) *TranslationUnit {
	doc := NewDocument(name, code)
	lexed := NewLexedDocument(doc, report)
	// Use path.GetFileNameWithoutExtension equivalent
	baseName := name
	if idx := strings.LastIndexByte(name, '.'); idx >= 0 {
		baseName = name[:idx]
	}
	if idx := strings.LastIndexByte(baseName, '\\'); idx >= 0 {
		baseName = baseName[idx+1:]
	}
	if idx := strings.LastIndexByte(baseName, '/'); idx >= 0 {
		baseName = baseName[idx+1:]
	}
	return p.ParseTranslationUnit(report, baseName, include, lexed.Tokens)
}

func (p *CParser) ParseTranslationUnit(report *Report, name string, include PreprocessorInclude, tokens ...[]Token) *TranslationUnit {
	// Flatten token arrays
	var flatTokens []Token
	for _, t := range tokens {
		flatTokens = append(flatTokens, t...)
	}
	preprocessor := NewPreprocessor(include, report, flatTokens)
	p.lexer = NewParserInput(preprocessor.Preprocess())

	p._tu = NewTranslationUnit(name)

	if len(p.lexer.Tokens) == 0 {
		return p._tu
	}

	func() {
		defer func() {
			if r := recover(); r != nil {
				if len(p.DebugHelper) > 0 && strings.Contains(p.DebugHelper, "+DumpTokens") {
					tokenDump := p.lexer.DumpTokens()
					println(tokenDump)
				}

				switch v := r.(type) {
				case NotImplementedException:
					report.ErrorAt(9999, p.lexer.CurrentToken().Location, p.lexer.CurrentToken().EndLocation,
						"Not Supported: "+v.message)
				case *NotSupportedException:
					report.ErrorAt(9001, p.lexer.CurrentToken().Location, p.lexer.CurrentToken().EndLocation,
						"Not Supported: "+v.message)
				case yyUnexpectedEof:
					report.ErrorAt(1513, p.lexer.CurrentToken().Location, p.lexer.CurrentToken().EndLocation, "Incomplete")
				case yyException:
					if v.message == "irrecoverable syntax error" {
						report.ErrorAt(1001, p.lexer.CurrentToken().Location, p.lexer.CurrentToken().EndLocation, "Syntax error")
					} else {
						report.ErrorAt(9000, p.lexer.CurrentToken().Location, p.lexer.CurrentToken().EndLocation, "Parser Error: "+v.message)
					}
				default:
					report.ErrorAt(9000, p.lexer.CurrentToken().Location, p.lexer.CurrentToken().EndLocation,
						fmt.Sprintf("Parser Error: %v", r))
				}
			}
		}()
		p.yyparse(p.lexer)
	}()

	return p._tu
}

func (p *CParser) TryParseExpression(report *Report, tokens []Token) Expression {
	cp := NewCParser()
	prefix := []Token{
		NewTokenSimple(TokenKindAUTO, "auto"),
		NewTokenSimple(TokenKindIDENTIFIER, "_"),
		NewTokenSimple('=', nil),
	}
	suffix := []Token{NewTokenSimple(';', nil)}
	tu := cp.ParseTranslationUnit(report, DefaultCodePath, func(string, bool) []Token { return nil }, prefix, tokens, suffix)
	if len(tu.Statements) > 0 {
		if mds, ok := tu.Statements[0].(*MultiDeclaratorStatement); ok && len(mds.InitDeclarators) == 1 && mds.InitDeclarators[0].Initializer != nil {
			if ei, ok := mds.InitDeclarators[0].Initializer.(*ExpressionInitializer); ok {
				return ei.Expression
			}
		}
	}
	return nil
}

func (p *CParser) AddDeclaration(a interface{}) {
	if stmt, ok := a.(Statement); ok {
		p._tu.AddStatement(stmt)
		if mds, ok := stmt.(*MultiDeclaratorStatement); ok {
			if mds.Specifiers.StorageClassSpecifier == StorageClassSpecifierTypedef && mds.InitDeclarators != nil {
				for _, id := range mds.InitDeclarators {
					p.lexer.AddTypedef(id.Declarator.DeclaredIdentifier())
				}
			} else if mds.Specifiers.StorageClassSpecifier == StorageClassSpecifierNone && len(mds.Specifiers.TypeSpecifiers) > 0 {
				for _, ts := range mds.Specifiers.TypeSpecifiers {
					if ts.Kind == TypeSpecifierKindClass || ts.Kind == TypeSpecifierKindStruct ||
						ts.Kind == TypeSpecifierKindUnion || ts.Kind == TypeSpecifierKindEnum {
						p.lexer.AddTypedef(ts.Name)
					}
				}
			}
		}
	}
}

func (p *CParser) FixPointerAndArrayPrecedence(d Declarator) Declarator {
	if pd, ok := d.(*PointerDeclarator); ok && pd.GetInnerDeclarator() != nil {
		if _, ok2 := pd.GetInnerDeclarator().(*ArrayDeclarator); ok2 {
			a := pd.GetInnerDeclarator()
			i := a.GetInnerDeclarator()
			a.SetInnerDeclarator(d)
			pd.SetInnerDeclarator(i)
			return a
		}
	}
	return nil
}

//goland:noinspection GoUnusedParameter
func (p *CParser) MakeArrayDeclarator(left Declarator, tq TypeQualifiers, lenExpr Expression, isStatic bool) Declarator {
	if left != nil && left.GetStrongBinding() {
		i := left.GetInnerDeclarator()
		a := NewArrayDeclaratorSimple(i, lenExpr)
		left.SetInnerDeclarator(a)
		return left
	}
	return NewArrayDeclaratorSimple(left, lenExpr)
}

//goland:noinspection GoUnusedParameter
func (p *CParser) GetLocation(obj interface{}) Location {
	return NullLocation
}

// ── LALR parser tables ────────────────────────────────────────────────────
// These are stripped-down examples; a full generated parser would have much
// larger tables filled from the grammar.

const yyFinal = 29
