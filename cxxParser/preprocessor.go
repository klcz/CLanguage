package cxxParser

import (
	"fmt"
	"strings"
)

// ============================================================================
// ParserInput — implements yyInput for the LALR parser
// ============================================================================

type ParserInput struct {
	Tokens   []Token
	index    int
	typedefs map[string]bool
}

func NewParserInput(tokens []Token) *ParserInput {
	if tokens == nil {
		tokens = []Token{}
	}
	return &ParserInput{
		Tokens:   tokens,
		index:    -1,
		typedefs: make(map[string]bool),
	}
}

func (pi *ParserInput) advance() bool {
	if pi.index+1 < len(pi.Tokens) {
		pi.index++
		pi.tryRegisterStructName()
		return true
	}
	return false
}

func (pi *ParserInput) tryRegisterStructName() {
	tok := pi.Tokens[pi.index]
	if tok.Kind == TokenKindSTRUCT || tok.Kind == TokenKindCLASS || tok.Kind == TokenKindUNION {
		if pi.index+2 < len(pi.Tokens) {
			nameTok := pi.Tokens[pi.index+1]
			afterName := pi.Tokens[pi.index+2]
			if nameTok.Kind == TokenKindIDENTIFIER &&
				(afterName.Kind == '{' || afterName.Kind == ':') {
				pi.typedefs[nameTok.StringValue()] = true
			}
		}
	}
}

func (pi *ParserInput) token() int {
	return int(pi.CurrentToken().Kind)
}

func (pi *ParserInput) value() interface{} {
	v := pi.CurrentToken().Value
	if v == nil {
		return ""
	}
	return v
}

func (pi *ParserInput) CurrentToken() Token {
	tok := pi.Tokens[pi.index]
	if tok.Kind == TokenKindIDENTIFIER && pi.typedefs[tok.StringValue()] {
		if pi.index+1 < len(pi.Tokens) && pi.Tokens[pi.index+1].Kind == TokenKindCOLONCOLON {
			return tok
		}
		return tok.AsKind(TokenKindTYPE_NAME)
	}
	return tok
}

func (pi *ParserInput) AddTypedef(declaredIdentifier string) {
	pi.typedefs[declaredIdentifier] = true
}

func (pi *ParserInput) DumpTokens() string {
	s := ""
	for _, token := range pi.Tokens {
		if token.Kind >= 256 {
			s += fmt.Sprintf(`{"%v": %s}`, token.Value,
				strings.ReplaceAll(token.Kind.String(), "TokenKind", "")) + "\n"
		} else {
			s += fmt.Sprintf(`{"%s"}`, string([]byte{byte(token.Kind)})) + "\n"
		}
	}
	return s
}

// ============================================================================
// Preprocessor
// ============================================================================

type PreprocessorInclude func(filePath string, relative bool) []Token

type Define struct {
	Name          string
	Parameters    []string
	HasParameters bool
	Body          []Token
}

func NewDefine(body []Token) *Define {
	return &Define{
		HasParameters: false,
		Parameters:    []string{},
		Body:          body,
	}
}

func NewDefineWithParams(name string, hasParameters bool, parameters []string, body []Token) *Define {
	return &Define{
		Name:          name,
		HasParameters: hasParameters,
		Parameters:    parameters,
		Body:          body,
	}
}

func (d *Define) String() string {
	return d.Name + ": [" + tokensJoin(d.Body) + "]"
}

func tokensJoin(tokens []Token) string {
	parts := make([]string, len(tokens))
	for i, t := range tokens {
		parts[i] = fmt.Sprint(t)
	}
	return strings.Join(parts, ", ")
}

type Preprocessor struct {
	tokens  []Token
	include PreprocessorInclude
	report  *Report
}

func NewPreprocessor(include PreprocessorInclude, report *Report, tokens []Token) *Preprocessor {
	return &Preprocessor{
		tokens:  tokens,
		include: include,
		report:  report,
	}
}

var preprocessorNoTokens []Token
var preprocessorNoStrings []string

func (p *Preprocessor) Preprocess() []Token {
	defines := make(map[string]*Define)
	for p.preprocessIteration(defines, p.includeBuiltins, &p.tokens, p.report) {
	}
	return p.tokens
}

func (p *Preprocessor) includeBuiltins(filePath string, relative bool) []Token {
	if filePath == "stdint.h" || filePath == "<stdint.h>" || filePath == `"stdint.h"` {
		return preprocessorNoTokens
	}
	if p.include != nil {
		return p.include(filePath, relative)
	}
	return nil
}

func (p *Preprocessor) preprocessIteration(defines map[string]*Define, include PreprocessorInclude, tokens *[]Token, report *Report) bool {
	anotherIterationNeeded := false
	i := 0
	for i < len(*tokens) {
		t := (*tokens)[i]
		if t.Kind == TokenKindEOL || t.Kind == '\\' {
			*tokens = append((*tokens)[:i], (*tokens)[i+1:]...)
		} else if t.Kind == TokenKindIDENTIFIER {
			ident := ""
			if s, ok := t.Value.(string); ok {
				ident = s
			}
			if define, ok := defines[ident]; ok {
				if define.HasParameters {
					args, argLen := readDefineArgs(i+1, *tokens)
					newDefines := copyDefines(defines)
					delete(newDefines, define.Name)
					for ai := 0; ai < minInt(len(args), len(define.Parameters)); ai++ {
						args[ai].Name = define.Parameters[ai]
						newDefines[args[ai].Name] = args[ai]
					}
					newBody := make([]Token, len(define.Body))
					copy(newBody, define.Body)
					for p.preprocessIteration(newDefines, include, &newBody, report) {
					}
					// Remove the macro invocation
					removeLen := argLen + 1
					if i+removeLen > len(*tokens) {
						removeLen = len(*tokens) - i
					}
					*tokens = append((*tokens)[:i], (*tokens)[i+removeLen:]...)
					// Insert expanded body
					*tokens = insertTokens(*tokens, i, newBody)
					anotherIterationNeeded = true
				} else {
					newBody := make([]Token, len(define.Body))
					copy(newBody, define.Body)
					newDefines := copyDefines(defines)
					delete(newDefines, define.Name)
					for p.preprocessIteration(newDefines, include, &newBody, report) {
					}
					*tokens = append((*tokens)[:i], (*tokens)[i+1:]...)
					*tokens = insertTokens(*tokens, i, newBody)
					i += len(newBody)
				}
			} else {
				i++
			}
		} else if t.Kind == '#' && i+1 < len(*tokens) &&
			((*tokens)[i+1].Kind == TokenKindIDENTIFIER || (*tokens)[i+1].Kind == TokenKindIF || (*tokens)[i+1].Kind == TokenKindELSE) {
			eol := i + 1
			for eol < len(*tokens) && (*tokens)[eol].Kind != TokenKindEOL {
				if (*tokens)[eol].Kind == '\\' && eol+1 < len(*tokens) && (*tokens)[eol+1].Kind == TokenKindEOL {
					eol++
				}
				eol++
			}
			var insertTokensSlice []Token
			tokenValueString := ""
			if v, ok := (*tokens)[i+1].Value.(string); ok {
				tokenValueString = v
			}

			switch tokenValueString {
			case "define":
				if eol-i > 2 {
					nameToken := (*tokens)[i+2]
					body := make([]Token, len((*tokens)[i+3:eol]))
					copy(body, (*tokens)[i+3:eol])
					ps := preprocessorNoStrings
					hasPs := false
					if len(body) >= 2 && body[0].Kind == '(' && body[0].Location.Index == nameToken.EndLocation.Index {
						endParam := -1
						for j := 1; j < len(body); j++ {
							if body[j].Kind == ')' {
								endParam = j
								break
							}
						}
						if endParam >= 0 && endParam+1 < len(body) {
							var paramNames []string
							for _, btok := range body[:endParam] {
								if btok.Kind == TokenKindIDENTIFIER {
									paramNames = append(paramNames, btok.StringValue())
								}
							}
							ps = paramNames
							body = body[endParam+1:]
							hasPs = true
						}
					}
					define := NewDefineWithParams(
						nameToken.StringValue(),
						hasPs,
						ps,
						body,
					)
					if strings.TrimSpace(define.Name) != "" {
						defines[define.Name] = define
					}
				} else {
					report.WarningAt(1025, (*tokens)[i].Location, (*tokens)[eol-1].EndLocation, "Incomplete #define")
				}

			case "include":
				if eol-i > 2 {
					relative := (*tokens)[i+2].Kind == TokenKindSTRING_LITERAL
					iname := ""
					for j := i + 2; j < eol; j++ {
						k := (*tokens)[j].Kind
						if k == '/' || k == '\\' || k == '.' {
							iname += string(rune(k))
						} else if k == TokenKindIDENTIFIER || k == TokenKindSTRING_LITERAL {
							iname += (*tokens)[j].StringValue()
						}
					}
					if include != nil {
						insertTokensSlice = include(iname, relative)
					}
					if insertTokensSlice == nil {
						report.WarningAt(1027, (*tokens)[i+2].Location, (*tokens)[eol-1].EndLocation, "Failed to find file")
					}
				} else {
					report.WarningAt(1026, (*tokens)[i].Location, (*tokens)[eol-1].EndLocation, "Incomplete #include")
				}

			case "endif", "else":
				report.WarningAt(1028, (*tokens)[i].Location, (*tokens)[eol-1].EndLocation, "Unexpected preprocessor directive")

			case "if", "ifdef", "ifndef":
				isTrue := true
				if tokenValueString == "if" {
					isTrue = evalIfCondition(defines, (*tokens)[i+2:eol])
				} else {
					isDefined := false
					if i+2 < len(*tokens) {
						if s, ok := (*tokens)[i+2].Value.(string); ok {
							_, isDefined = defines[s]
						}
					}
					isTrue = (tokenValueString == "ifdef") == isDefined
				}

				elseStartIndex := -1
				elseEndIndex := -1
				endifStartIndex := len(*tokens)
				endifEndIndex := len(*tokens)
				ifDepth := 1

				for j := i + 3; j < len(*tokens)-1 && endifEndIndex == len(*tokens); j++ {
					if (*tokens)[j].Kind == '#' {
						if eis, ok := (*tokens)[j+1].Value.(string); ok {
							switch eis {
							case "if", "ifdef", "ifndef":
								ifDepth++
							case "else":
								if ifDepth == 1 {
									elseStartIndex = j
									elseEndIndex = j + 2
								}
							case "endif":
								ifDepth--
								if ifDepth == 0 {
									endifStartIndex = j
									endifEndIndex = j + 2
								}
							}
						}
					}
				}

				if isTrue {
					if elseStartIndex >= eol {
						insertTokensSlice = make([]Token, elseStartIndex-eol)
						copy(insertTokensSlice, (*tokens)[eol:elseStartIndex])
					} else {
						insertTokensSlice = make([]Token, endifStartIndex-eol)
						copy(insertTokensSlice, (*tokens)[eol:endifStartIndex])
					}
				} else {
					if elseEndIndex >= eol {
						insertTokensSlice = make([]Token, endifStartIndex-elseEndIndex)
						copy(insertTokensSlice, (*tokens)[elseEndIndex:endifStartIndex])
					}
				}
				eol = endifEndIndex

			default:
				report.WarningAt(1024, (*tokens)[i].Location, (*tokens)[eol-1].EndLocation, "Cannot understand preprocessor")
			}

			if eol < len(*tokens) {
				eol++
			}
			*tokens = append((*tokens)[:i], (*tokens)[eol:]...)
			if insertTokensSlice != nil {
				*tokens = insertTokens(*tokens, i, insertTokensSlice)
			}
			anotherIterationNeeded = true
		} else {
			i++
		}
	}
	return anotherIterationNeeded
}

func minInt(a, b int) int {
	if a < b {
		return a
	}
	return b
}

func copyDefines(src map[string]*Define) map[string]*Define {
	dst := make(map[string]*Define, len(src))
	for k, v := range src {
		dst[k] = v
	}
	return dst
}

func insertTokens(slice []Token, index int, inserts []Token) []Token {
	if len(inserts) == 0 {
		return slice
	}
	newSlice := make([]Token, 0, len(slice)+len(inserts))
	newSlice = append(newSlice, slice[:index]...)
	newSlice = append(newSlice, inserts...)
	newSlice = append(newSlice, slice[index:]...)
	return newSlice
}

func readDefineArgs(startIndex int, tokens []Token) ([]*Define, int) {
	var defines []*Define
	if startIndex < 0 || startIndex >= len(tokens) || tokens[startIndex].Kind != '(' {
		return defines, 0
	}
	parenDepth := 0
	i := startIndex
	startArgIndex := startIndex + 1
	for ; i < len(tokens) && startArgIndex > startIndex && tokens[i].Kind != TokenKindEOL; i++ {
		switch tokens[i].Kind {
		case '(':
			parenDepth++
		case ',':
			if parenDepth == 1 {
				body := make([]Token, i-startArgIndex)
				copy(body, tokens[startArgIndex:i])
				defines = append(defines, NewDefine(body))
				startArgIndex = i + 1
			}
		case ')':
			parenDepth--
			if parenDepth == 0 {
				body := make([]Token, i-startArgIndex)
				copy(body, tokens[startArgIndex:i])
				defines = append(defines, NewDefine(body))
				startArgIndex = -1
			}
		}
	}
	return defines, i - startIndex
}

// evalIfCondition evaluates a preprocessor #if expression.
// It uses the C parser to compile the expression as C code and evaluates it.
func evalIfCondition(defines map[string]*Define, tokens []Token) bool {
	defer func() {
		recover()
	}()
	report := NewReport(nil)
	expressions := make(map[string]Expression)
	for _, d := range defines {
		if len(d.Body) == 0 {
			continue
		}
		cp := NewCParser()
		e := cp.TryParseExpression(report, d.Body)
		if e != nil {
			expressions[d.Name] = e
		}
	}
	cp := NewCParser()
	expression := cp.TryParseExpression(report, tokens)
	if expression == nil {
		return false
	}
	context := NewPreprocessorContext(report, defines, expressions)
	value := expression.EvalConstant(context.EmitContext)
	return value.Int32Value() != 0
}

// PreprocessorContext — minimal EmitContext override for #if evaluation
type PreprocessorContext struct {
	*EmitContext
	defines     map[string]*Define
	expressions map[string]Expression
}

func NewPreprocessorContext(report *Report, defines map[string]*Define, expressions map[string]Expression) *PreprocessorContext {
	mi := NewMachineInfo()
	ec := NewEmitContext(mi, report, nil, nil)
	pc := &PreprocessorContext{
		EmitContext: ec,
		defines:     defines,
		expressions: expressions,
	}
	ec.self = pc
	return pc
}

func (pc *PreprocessorContext) TryResolveVariable(name string, argTypes []CType) *ResolvedVariable {
	if expr, ok := pc.expressions[name]; ok {
		nex := make(map[string]Expression)
		for k, v := range pc.expressions {
			nex[k] = v
		}
		nctx := NewPreprocessorContext(pc.GetReport(), pc.defines, nex)
		value := expr.EvalConstant(nctx.EmitContext)
		return &ResolvedVariable{
			Scope:        VariableScopeConstant,
			Constant:     value,
			VariableType: CBasicTypeSignedInt,
		}
	}
	return pc.EmitContext.TryResolveVariable(name, argTypes)
}
