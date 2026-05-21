<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require __DIR__ . '/TokenKind.php';
require __DIR__ . '/Types.class.php';
require __DIR__ . '/Syntax.class.php';
require __DIR__ . '/Preprocessor.php';
require __DIR__ . '/Lexer.php';
require __DIR__ . '/cparser.php';
use parse\CParser;
$p = new CParser();
echo "OK\n";
