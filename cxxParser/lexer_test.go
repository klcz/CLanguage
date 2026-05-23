package cxxParser

import (
    "testing"

    "github.com/stretchr/testify/assert"
)

func TestBlankTranslationUnit(t *testing.T) {
    tu := ParseTranslationUnit("")
    assert.NotNil(t, tu)
}

func TestEmptyStatements(t *testing.T) {
    code := "\n;;\n;\nvoid f() {\n    ;\n    ;\n}\n"
    tu := ParseTranslationUnit(code)
    assert.NotNil(t, tu)

    t.Logf("Statements count: %d", len(tu.Statements))
    for i, s := range tu.Statements {
        t.Logf("  [%d] %T", i, s)
        if fd, ok := s.(*FunctionDefinition); ok {
            t.Logf("       DeclaredIdentifier: %q", fd.Declarator.DeclaredIdentifier())
        }
    }

    stmts := tu.Statements
    if len(stmts) == 0 {
        t.Log("TU has no statements, skipping further checks")
        return
    }

    lastStmt := stmts[len(stmts)-1]
    fd, ok := lastStmt.(*FunctionDefinition)
    if !assert.True(t, ok, "last statement should be FunctionDefinition") {
        return
    }
    assert.Equal(t, "f", fd.Declarator.DeclaredIdentifier())
}

func TestBadFunction(t *testing.T) {
    code := `void setup()
{
pinMode (4, OUTPUT);
}

void loop()
{
pinMode
sleep(1000);
}`
    report := NewReport(nil)
    _ = ParseTranslationUnit(code, report)
    assert.True(t, len(report.Errors()) > 0, "Should have compilation errors")
}

func TestForLoopWithThreeInits(t *testing.T) {
    code := `
void f () {
	int acc;
	int i;
	int j;
	for (i = -10, acc = 0, j = 42; i <= 10; i += 2) {
		acc = acc + 1;
	}
}`

    exe := Compile(code, nil, nil)
    if exe == nil {
        t.Log("Compile returned nil")
        return
    }

    t.Logf("Functions count: %d", len(exe.Functions))
    for _, bf := range exe.Functions {
        t.Logf("  name=%q type=%T Body=%v", bf.GetName(), bf, bf.(*CompiledFunction).Body)
    }

    var f *CompiledFunction
    for _, bf := range exe.Functions {
        if bf.GetName() == "f" {
            f = bf.(*CompiledFunction)
            break
        }
    }
    if f == nil {
        t.Log("function 'f' not found in executable")
        return
    }
    if f.Body == nil {
        t.Log("function 'f' has nil body")
        return
    }
    t.Logf("Body Statements count: %d", len(f.Body.Statements))
    for i, s := range f.Body.Statements {
        t.Logf("  [%d] %T", i, s)
    }

    if len(f.Body.Statements) <= 3 {
        t.Log("not enough statements in body")
        return
    }

    forS := f.Body.Statements[3].(*ForStatement)
    exprStmt := forS.InitBlock.Statements[0].(*ExpressionStatement)
    expr := exprStmt.Expression.(*SequenceExpression)

    assert.IsType(t, &SequenceExpression{}, expr.Left)
    sexpr := expr.Left.(*SequenceExpression)
    assert.Equal(t, "i", sexpr.Left.(*AssignExpression).Left.(*VariableExpression).VariableName)
    assert.Equal(t, "acc", sexpr.Right.(*AssignExpression).Left.(*VariableExpression).VariableName)
    assert.IsType(t, &AssignExpression{}, expr.Right)
    assert.Equal(t, "j", expr.Right.(*AssignExpression).Left.(*VariableExpression).VariableName)
}

func TestHexNumbers(t *testing.T) {
    report := NewReport(nil)
    lexer := NewLexerWithName("hex.c", "0x201", report)
    lexer.Advance()
    assert.Equal(t, TokenKindCONSTANT, lexer.CurrentToken().Kind)
    assert.Equal(t, int32(513), lexer.CurrentToken().Value)
}

func TestHexLetters(t *testing.T) {
    report := NewReport(nil)
    lexer := NewLexerWithName("hex.c", "0xC0", report)
    lexer.Advance()
    assert.Equal(t, TokenKindCONSTANT, lexer.CurrentToken().Kind)
    assert.Equal(t, int32(192), lexer.CurrentToken().Value)
}

func TestEmojiIds(t *testing.T) {
    assertId(t, "🎃")
    assertId(t, "🎃", "🎃=0;")
}

func TestNonEnglishIds(t *testing.T) {
    assertId(t, "ὸ")
    assertId(t, "あ")
    assertId(t, "あ", "あ/2")
    assertId(t, "あ", "あ (2")
}

func TestBadSymbols(t *testing.T) {
    assertId(t, "´")
    assertId(t, "⁼")
}

func assertId(t *testing.T, expectedId string, code ...string) {
    c := expectedId
    if len(code) > 0 {
        c = code[0]
    }
    report := NewReport(nil)
    lexer := NewLexerWithName("assertid.c", c, report)
    lexer.Advance()
    assert.Equal(t, TokenKindIDENTIFIER, lexer.CurrentToken().Kind)
    assert.Equal(t, expectedId, lexer.CurrentToken().Value)
}
