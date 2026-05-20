<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$base = __DIR__;
require "$base/TokenKind.php";
require "$base/Report.php";
require "$base/Types.class.php";
require "$base/Syntax.class.php";
require "$base/LexedDocument.php";
require "$base/Lexer.php";
require "$base/Preprocessor.php";
require "$base/CParser.php";
require "$base/ParserInput.php";

echo "Loaded\n";
$code = 'int main() { return 0; }';
$doc = new parse\Document("test.c", $code);
$lexed = new parse\LexedDocument($doc, new parse\Report());

$pp = new parse\Preprocessor(function($a, $b) { return null; }, new parse\Report(), $lexed->Tokens);
$tokens = $pp->preprocess();
$input = new parse\ParserInput($tokens);

$parser = new parse\CParser();
$parser->_tu = new parse\TranslationUnit("test");
echo "Calling yyparse...\n";
try {
    $result = $parser->yyparse($input);
    echo "yyparse returned\n";
} catch (parse\yyUnexpectedEof $e) {
    echo "Unexpected EOF\n";
} catch (parse\yyException $e) {
    echo "yyException: " . $e->getMessage() . "\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
echo "Done\n";
