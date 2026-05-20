<?php
namespace parse;

class LexedDocument
{
    public $Document;
    public $Tokens;

    public function __construct(Document $document, Report $report)
    {
        $this->Document = $document;
        $tokens = [];
        $lexer = new Lexer($document, $report);
        try {
            while ($lexer->advance()) {
                $tokens[] = $lexer->CurrentToken;
            }
        } catch (\NotSupportedException $err) {
            $t = $lexer->CurrentToken;
            $report->error(9000, $t->Location, $t->EndLocation, 'Not Supported: ' . $err->getMessage());
        } catch (\Exception $ex) {
            $t = $lexer->CurrentToken;
            $report->error(9000, $t->Location, $t->EndLocation, 'Internal Error: ' . $ex->getMessage());
        }
        $this->Tokens = $tokens;
    }
}
