package cxxParser

import (
	"fmt"
	"strconv"
	"unicode"
)

// ============================================================================
// Lexer
// ============================================================================

type Lexer struct {
	_token TokenKind
	_value interface{}

	_lastR    int
	_chbuf    []byte
	_chbuflen int

	location    Location
	endLocation Location
	line        int
	column      int

	Report    *Report
	Document  *Document
	IsTypedef func(string) bool

	nextPosition int
}

func NewLexer(doc *Document, report *Report) *Lexer {
	l := &Lexer{
		Report:   report,
		Document: doc,
		location: NewLocation(doc, 0, 1, 1),
		line:     1,
		column:   1,
		_lastR:   -2,
		_chbuf:   make([]byte, 4*1024),
	}
	l.endLocation = l.location
	l.IsTypedef = func(s string) bool { return false }
	return l
}

func NewLexerWithName(name, code string, report *Report) *Lexer {
	return NewLexer(NewDocument(name, code), report)
}

func (l *Lexer) CurrentToken() Token {
	if l._value == nil {
		text := ""
		// Location.IsNull || EndLocation.IsNull || Location.Document.Path != EndLocation.Document.Path ? "" :
		// Location.Document.Content.Substring (Location.Index, EndLocation.Index - Location.Index);
		if l.location.Document != nil && l.location.Document == l.location.Document {
			text = l.location.Document.Content[l.location.Index:l.endLocation.Index]
		}
		return NewToken(l._token, text, l.location, l.endLocation)
	}
	return NewToken(l._token, l._value, l.location, l.endLocation)
}

func (l *Lexer) Eof() bool {
	l._value = nil
	l._token = -1
	return false
}

var kwTokens = map[string]TokenKind{
	"auto":      TokenKindAUTO,
	"bool":      TokenKindBOOL,
	"break":     TokenKindBREAK,
	"case":      TokenKindCASE,
	"char":      TokenKindCHAR,
	"class":     TokenKindCLASS,
	"const":     TokenKindCONST,
	"continue":  TokenKindCONTINUE,
	"default":   TokenKindDEFAULT,
	"do":        TokenKindDO,
	"double":    TokenKindDOUBLE,
	"else":      TokenKindELSE,
	"enum":      TokenKindENUM,
	"extern":    TokenKindEXTERN,
	"false":     TokenKindFALSE,
	"float":     TokenKindFLOAT,
	"for":       TokenKindFOR,
	"goto":      TokenKindGOTO,
	"if":        TokenKindIF,
	"inline":    TokenKindINLINE,
	"int":       TokenKindINT,
	"long":      TokenKindLONG,
	"public":    TokenKindPUBLIC,
	"private":   TokenKindPRIVATE,
	"protected": TokenKindPROTECTED,
	"register":  TokenKindREGISTER,
	"restrict":  TokenKindRESTRICT,
	"return":    TokenKindRETURN,
	"short":     TokenKindSHORT,
	"signed":    TokenKindSIGNED,
	"sizeof":    TokenKindSIZEOF,
	"static":    TokenKindSTATIC,
	"struct":    TokenKindSTRUCT,
	"switch":    TokenKindSWITCH,
	"true":      TokenKindTRUE,
	"typedef":   TokenKindTYPEDEF,
	"union":     TokenKindUNION,
	"unsigned":  TokenKindUNSIGNED,
	"virtual":   TokenKindVIRTUAL,
	"void":      TokenKindVOID,
	"volatile":  TokenKindVOLATILE,
	"while":     TokenKindWHILE,
	"override":  TokenKindOVERRIDE,
	"operator":  TokenKindOPERATOR,
}

//goland:noinspection GoUnusedGlobalVariable
var KeywordTokens = func() map[TokenKind]bool {
	m := make(map[TokenKind]bool)
	for _, v := range kwTokens {
		m[v] = true
	}
	return m
}()

//goland:noinspection GoUnusedGlobalVariable
var OperatorTokens = map[TokenKind]bool{
	TokenKindEQ_OP:    true,
	TokenKindGE_OP:    true,
	TokenKindLE_OP:    true,
	TokenKindNE_OP:    true,
	TokenKindOR_OP:    true,
	TokenKindAND_OP:   true,
	TokenKindDEC_OP:   true,
	TokenKindINC_OP:   true,
	TokenKindPTR_OP:   true,
	TokenKindLEFT_OP:  true,
	TokenKindRIGHT_OP: true,
}

func (l *Lexer) Read() int {
	if l.nextPosition < len(l.Document.Content) {
		r := l.Document.Content[l.nextPosition]
		l.nextPosition++
		l.column++
		return int(r)
	}
	return -1
}

func (l *Lexer) Peek() int {
	if l.nextPosition < len(l.Document.Content) {
		return int(l.Document.Content[l.nextPosition])
	}
	return -1
}

func (l *Lexer) SkipWhiteSpace() {
	r := l._lastR
	if r == -2 {
		r = l.Read()
	}
	skippedComment := true
	for skippedComment {
		for r >= 0 && r <= ' ' {
			if r == '\n' || r == 0x2028 {
				break
			}
			r = l.Read()
		}
		skippedComment = false
		if r == '/' && l.Peek() == '/' {
			nr := l.Read()
			for nr > 0 && nr != '\n' && nr != 0x2028 {
				nr = l.Read()
			}
			r = nr
		} else if r == '/' && l.Peek() == '*' {
			nr := l.Read()
			for nr > 0 && !(nr == '*' && l.Peek() == '/') {
				if nr == '\n' || nr == 0x2028 {
					l.line++
					l.column = 1
				}
				nr = l.Read()
			}
			l.Read() // consume ending /
			r = l.Read()
			skippedComment = true
		}
	}
	l._lastR = r
}

func (l *Lexer) Advance() bool {
	l.SkipWhiteSpace()
	r := l._lastR

	if r == -1 {
		return l.Eof()
	}

	l.location = NewLocation(l.location.Document, l.nextPosition-1, l.line, l.column)
	ch := byte(r)

	if ch == '\n' || r == 0x2028 {
		l._token = TokenKindEOL
		l._value = nil
		l._lastR = l.Read()
		l.line++
		l.column = 1
	} else if unicode.IsDigit(rune(ch)) {
		onlyDigits := true
		isLong := false
		isUnsigned := false
		isFloat := false
		isHex := false
		l._chbuf[0] = ch
		l._chbuflen = 0

		for ch == '.' || unicode.IsDigit(rune(ch)) || ch == 'E' || ch == 'e' || ch == 'f' || ch == 'F' || ch == 'u' || ch == 'U' || ch == 'l' || ch == 'L' || (!isHex && ch == 'x') || (isHex && isHexChar(ch)) {
			if ch == 'l' || ch == 'L' {
				isLong = true
			} else if ch == 'u' || ch == 'U' {
				isUnsigned = true
			} else if !isHex && (ch == 'f' || ch == 'F') {
				isFloat = true
			} else if ch == 'x' && l._chbuflen == 1 && l._chbuf[0] == '0' {
				isHex = true
			} else {
				onlyDigits = onlyDigits && unicode.IsDigit(rune(ch))
				l._chbuf[l._chbuflen] = ch
				l._chbuflen++
			}
			r = l.Read()
			ch = byte(r)
		}
		l._lastR = r

		vals := string(l._chbuf[:l._chbuflen])
		lastPos := l.nextPosition - 1
		if l._lastR < 0 {
			lastPos = len(l.Document.Content)
		}
		l.endLocation = NewLocation(l.location.Document, lastPos, l.line, l.column)

		if onlyDigits || isHex {
			base := 10
			if isHex {
				base = 16
			}
			if isLong {
				if isUnsigned {
					v, err := strconv.ParseUint(vals, base, 64)
					if err != nil {
						l._value = uint64(0)
						l.Report.ErrorAt(1021, l.location, l.endLocation, "Integral constant is too large")
					} else {
						l._value = v
					}
				} else {
					v, err := strconv.ParseInt(vals, base, 64)
					if err != nil {
						l._value = int64(0)
						l.Report.ErrorAt(1021, l.location, l.endLocation, "Integral constant is too large")
					} else {
						l._value = v
					}
				}
			} else {
				if isUnsigned {
					uiv, err := strconv.ParseUint(vals, base, 32)
					if err != nil {
						uv, err2 := strconv.ParseUint(vals, base, 64)
						if err2 != nil {
							l._value = uint64(0)
							l.Report.ErrorAt(1021, l.location, l.endLocation, "Integral constant is too large")
						} else {
							l._value = uv
						}
					} else {
						l._value = uint32(uiv)
					}
				} else {
					iv, err := strconv.ParseInt(vals, base, 32)
					if err != nil {
						iv2, err2 := strconv.ParseInt(vals, base, 64)
						if err2 != nil {
							l._value = int64(0)
							l.Report.ErrorAt(1021, l.location, l.endLocation, "Integral constant is too large")
						} else {
							l._value = iv2
						}
					} else {
						l._value = int32(iv)
					}
				}
			}
		} else {
			if isFloat {
				v, err := strconv.ParseFloat(vals, 32)
				if err != nil {
					l._value = float32(0)
				} else {
					l._value = float32(v)
				}
			} else {
				v, err := strconv.ParseFloat(vals, 64)
				if err != nil {
					l._value = float64(0)
				} else {
					l._value = v
				}
			}
		}
		l._token = TokenKindCONSTANT

	} else if r == '=' {
		r = l.Read()
		if r == '=' {
			l._token = TokenKindEQ_OP
			l._value = nil
			l._lastR = l.Read()
		} else {
			l._token = '='
			l._value = nil
			l._lastR = r
		}
	} else if r == '!' {
		r = l.Read()
		if r == '=' {
			l._token = TokenKindNE_OP
			l._value = nil
			l._lastR = l.Read()
		} else {
			l._token = '!'
			l._value = nil
			l._lastR = r
		}
	} else if r == ':' {
		r = l.Read()
		if r == ':' {
			l._token = TokenKindCOLONCOLON
			l._value = nil
			l._lastR = l.Read()
		} else {
			l._token = ':'
			l._value = nil
			l._lastR = r
		}
	} else if r == ',' || r == ';' || r == '?' || r == '(' || r == ')' || r == '{' || r == '}' || r == '[' || r == ']' || r == '~' || r == '%' || r == '#' || r == '\\' {
		l._token = TokenKind(r)
		l._value = nil
		l._lastR = l.Read()
	} else if r == '.' {
		nr := l.Read()
		if nr == '.' && l.Peek() == '.' {
			r3 := l.Read()
			if r3 == '.' {
				l._token = TokenKindELLIPSIS
				l._value = nil
				l._lastR = l.Read()
			} else {
				l._token = '.'
				l._value = nil
				l._lastR = r3
				l.Report.ErrorAt(1001, l.location.Add(1), l.location.Add(2), "Identifier expected")
			}
		} else {
			l._token = TokenKind(r)
			l._value = nil
			l._lastR = nr
		}
	} else if r == '*' || r == '/' {
		nr := l.Read()
		if nr == '=' {
			nr = l.Read()
			if r == '*' {
				l._token = TokenKindMUL_ASSIGN
			} else {
				l._token = TokenKindDIV_ASSIGN
			}
			l._value = nil
			l._lastR = nr
		} else {
			l._token = TokenKind(r)
			l._value = nil
			l._lastR = nr
		}
	} else if r == '^' {
		nr := l.Read()
		if nr == '=' {
			nr = l.Read()
			l._token = TokenKindBINARY_XOR_ASSIGN
			l._value = nil
			l._lastR = nr
		} else {
			l._token = TokenKind(r)
			l._value = nil
			l._lastR = nr
		}
	} else if r == '&' {
		nr := l.Read()
		if nr == '&' {
			nr = l.Read()
			if nr == '=' {
				nr = l.Read()
				l._token = TokenKindAND_ASSIGN
				l._value = nil
				l._lastR = nr
			} else {
				l._token = TokenKindAND_OP
				l._value = nil
				l._lastR = nr
			}
		} else if nr == '=' {
			nr = l.Read()
			l._token = TokenKindBINARY_AND_ASSIGN
			l._value = nil
			l._lastR = nr
		} else {
			l._token = TokenKind(r)
			l._value = nil
			l._lastR = nr
		}
	} else if r == '|' {
		nr := l.Read()
		if nr == '|' {
			nr = l.Read()
			if nr == '=' {
				nr = l.Read()
				l._token = TokenKindOR_ASSIGN
				l._value = nil
				l._lastR = nr
			} else {
				l._token = TokenKindOR_OP
				l._value = nil
				l._lastR = nr
			}
		} else if nr == '=' {
			nr = l.Read()
			l._token = TokenKindBINARY_OR_ASSIGN
			l._value = nil
			l._lastR = nr
		} else {
			l._token = TokenKind(r)
			l._value = nil
			l._lastR = nr
		}
	} else if r == '+' {
		nr := l.Read()
		if nr == '=' {
			l._token = TokenKindADD_ASSIGN
			l._value = nil
			l._lastR = l.Read()
		} else if nr == '+' {
			l._token = TokenKindINC_OP
			l._value = nil
			l._lastR = l.Read()
		} else {
			l._token = TokenKind(r)
			l._value = nil
			l._lastR = nr
		}
	} else if r == '-' {
		nr := l.Read()
		if nr == '=' {
			l._token = TokenKindSUB_ASSIGN
			l._value = nil
			l._lastR = l.Read()
		} else if nr == '-' {
			l._token = TokenKindDEC_OP
			l._value = nil
			l._lastR = l.Read()
		} else if nr == '>' {
			l._token = TokenKindPTR_OP
			l._value = nil
			l._lastR = l.Read()
		} else {
			l._token = TokenKind(r)
			l._value = nil
			l._lastR = nr
		}
	} else if r == '<' {
		nr := l.Read()
		if nr == '=' {
			l._token = TokenKindLE_OP
			l._value = nil
			l._lastR = l.Read()
		} else if nr == '<' {
			l._token = TokenKindLEFT_OP
			l._value = nil
			l._lastR = l.Read()
		} else {
			l._token = TokenKind(r)
			l._value = nil
			l._lastR = nr
		}
	} else if r == '>' {
		nr := l.Read()
		if nr == '=' {
			l._token = TokenKindGE_OP
			l._value = nil
			l._lastR = l.Read()
		} else if nr == '>' {
			l._token = TokenKindRIGHT_OP
			l._value = nil
			l._lastR = l.Read()
		} else {
			l._token = TokenKind(r)
			l._value = nil
			l._lastR = nr
		}
	} else if r == '"' {
		l._chbuflen = 0
		r = l.Read()
		ch = byte(r)
		done := r < 0 || ch == '"'
		for !done && l._chbuflen+1 < len(l._chbuf) {
			if ch == '\\' {
				r = l.Read()
				ch = byte(r)
				if r >= 0 {
					advanceAfterEscape := true
					switch ch {
					case '\\':
						l._chbuf[l._chbuflen] = '\\'
						l._chbuflen++
					case 'r':
						l._chbuf[l._chbuflen] = '\r'
						l._chbuflen++
					case 'n':
						l._chbuf[l._chbuflen] = '\n'
						l._chbuflen++
					case 't':
						l._chbuf[l._chbuflen] = '\t'
						l._chbuflen++
					case '\'':
						l._chbuf[l._chbuflen] = '\''
						l._chbuflen++
					case '"':
						l._chbuf[l._chbuflen] = '"'
						l._chbuflen++
					case '0':
						l._chbuf[l._chbuflen] = 0
						l._chbuflen++
					case 'x':
						hex := 0
						r = l.Read()
						ch = byte(r)
						for r >= 0 && (unicode.IsDigit(rune(ch)) || isHexChar(ch)) {
							hex = hex*16 + hexVal(ch)
							r = l.Read()
							ch = byte(r)
						}
						l._chbuf[l._chbuflen] = byte(hex)
						l._chbuflen++
						advanceAfterEscape = false
					default:
						if ch == ' ' || ch == '\t' || ch == '\n' || r == 0x2028 {
							for r > 0 && r != '\n' && r != 0x2028 {
								r = l.Read()
							}
						} else {
							panic(NewNotImplementedException("Unrecognized string escape sequence"))
						}
					}
					if advanceAfterEscape {
						r = l.Read()
						ch = byte(r)
					}
				}
			} else if r == 0x2028 || ch == '\n' {
				lastPos := l.nextPosition - 1
				if l._lastR >= 0 {
					lastPos = l.nextPosition - 1
				} else {
					lastPos = len(l.Document.Content)
				}
				l.endLocation = NewLocation(l.location.Document, lastPos, l.line, l.column)
				l.Report.ErrorAt(1010, l.location, l.endLocation, "Newline in constant")
				done = true
			} else {
				l._chbuf[l._chbuflen] = ch
				l._chbuflen++
				r = l.Read()
				ch = byte(r)
			}
			if r < 0 || ch == '"' {
				done = true
			}
		}
		l._lastR = l.Read()
		l._token = TokenKindSTRING_LITERAL
		l._value = string(l._chbuf[:l._chbuflen])

	} else if r == '\'' {
		l._chbuflen = 0
		r = l.Read()
		ch = byte(r)
		done := r < 0 || ch == '\''
		for !done && l._chbuflen+1 < len(l._chbuf) {
			if ch == '\\' {
				r = l.Read()
				ch = byte(r)
				if r >= 0 {
					advanceAfterEscape := true
					switch ch {
					case '\\':
						l._chbuf[l._chbuflen] = '\\'
						l._chbuflen++
					case 'r':
						l._chbuf[l._chbuflen] = '\r'
						l._chbuflen++
					case 'n':
						l._chbuf[l._chbuflen] = '\n'
						l._chbuflen++
					case 't':
						l._chbuf[l._chbuflen] = '\t'
						l._chbuflen++
					case '\'':
						l._chbuf[l._chbuflen] = '\''
						l._chbuflen++
					case '"':
						l._chbuf[l._chbuflen] = '"'
						l._chbuflen++
					case '0':
						l._chbuf[l._chbuflen] = 0
						l._chbuflen++
					case 'x':
						hex := 0
						r = l.Read()
						ch = byte(r)
						for r >= 0 && (unicode.IsDigit(rune(ch)) || isHexChar(ch)) {
							hex = hex*16 + hexVal(ch)
							r = l.Read()
							ch = byte(r)
						}
						l._chbuf[l._chbuflen] = byte(hex)
						l._chbuflen++
						advanceAfterEscape = false
					default:
						panic(NewNotImplementedException("Unrecognized char escape sequence"))
					}
					if advanceAfterEscape {
						r = l.Read()
						ch = byte(r)
					}
				}
			} else {
				l._chbuf[l._chbuflen] = ch
				l._chbuflen++
				r = l.Read()
				ch = byte(r)
			}
			if r < 0 || ch == '\'' {
				done = true
			}
		}
		l._lastR = l.Read()
		l._token = TokenKindCONSTANT
		if l._chbuflen > 0 {
			l._value = l._chbuf[0]
		} else {
			l._value = byte(0)
		}

	} else {
		l._chbuf[0] = ch
		l._chbuflen = 0
		for r >= 0 && l._chbuflen < len(l._chbuf) && (ch == '_' || unicode.IsLetter(rune(ch)) || unicode.IsDigit(rune(ch)) || r > 127) {
			l._chbuf[l._chbuflen] = ch
			l._chbuflen++
			r = l.Read()
			ch = byte(r)
		}
		if l._chbuflen == 0 {
			panic(fmt.Errorf("character '%c' is not parsable", rune(r)))
		}
		l._lastR = r
		id := string(l._chbuf[:l._chbuflen])
		l._value = id

		if tok, ok := kwTokens[id]; ok {
			l._token = tok
		} else {
			if l.IsTypedef != nil && l.IsTypedef(id) {
				l._token = TokenKindTYPE_NAME
			} else {
				l._token = TokenKindIDENTIFIER
			}
		}
	}

	lastPos := l.nextPosition - 1
	if l._lastR < 0 {
		lastPos = len(l.Document.Content)
	}
	l.endLocation = NewLocation(l.location.Document, lastPos, l.line, l.column)
	return true
}

func isHexChar(c byte) bool {
	switch c {
	case 'a', 'b', 'c', 'd', 'e', 'f', 'A', 'B', 'C', 'D', 'E', 'F':
		return true
	}
	return false
}

func hexVal(c byte) int {
	if c >= '0' && c <= '9' {
		return int(c - '0')
	}
	if c >= 'a' && c <= 'f' {
		return int(c - 'a' + 10)
	}
	if c >= 'A' && c <= 'F' {
		return int(c - 'A' + 10)
	}
	return 0
}

// ============================================================================
// LexedDocument
// ============================================================================

type LexedDocument struct {
	Document *Document
	Tokens   []Token
}

func NewLexedDocument(doc *Document, report *Report) *LexedDocument {
	ld := &LexedDocument{Document: doc}
	tokens := make([]Token, 0)
	lexer := NewLexer(doc, report)
	func() {
		defer func() {
			if r := recover(); r != nil {
				switch v := r.(type) {
				case *NotImplementedException:
					t := lexer.CurrentToken()
					report.ErrorAt(9000, t.Location, t.EndLocation, "Not Supported: "+v.message)
				default:
					t := lexer.CurrentToken()
					report.ErrorAt(9000, t.Location, t.EndLocation, fmt.Sprintf("Internal Error: %v", r))
				}
			}
		}()
		for lexer.Advance() {
			tokens = append(tokens, lexer.CurrentToken())
		}
	}()
	ld.Tokens = tokens
	return ld
}
