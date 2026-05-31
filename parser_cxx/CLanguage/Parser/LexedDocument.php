<?php declare(strict_types=1);

namespace CLanguage\Parser;

use CLanguage\Report;
use CLanguage\Syntax\Document;
use Exception;
use RuntimeException;

readonly class LexedDocument
{
    public Document $Document;
    public array $Tokens;

    public function __construct(Document $document, Report $report)
    {
        $this->Document = $document;

        $tokens = [];
        $lexer = new Lexer($document, $report);
        try {
            while ($lexer->advance()) {
                $tokens[] = $lexer->getCurrentToken();
            }
        } catch (RuntimeException $err) {
            $t = $lexer->getCurrentToken();
            $report->error(9000, $t->Location, $t->EndLocation, 'Not Supported: ' . $err->getMessage());
        } catch (Exception $ex) {
            $t = $lexer->getCurrentToken();
            $report->error(9000, $t->Location, $t->EndLocation, 'Internal Error: ' . $ex->getMessage());
        }
        $this->Tokens = $tokens;
    }
}
