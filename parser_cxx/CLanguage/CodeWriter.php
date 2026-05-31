<?php declare(strict_types=1);

namespace CLanguage;

class CodeWriter
{
    private bool $needsIndent = true;
    private int $indent = 0;
    private string $eol;
    private string $code = '';

    public function __construct()
    {
        $this->eol = "\r\n";
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }

    public function writeLine(string $code): self
    {
        $lines = explode("\n", $code);
        for ($i = 0; $i < count($lines); $i++) {
            $this->writeIndent();
            $this->code .= rtrim($lines[$i]);
            $this->code .= $this->eol;
            $this->needsIndent = true;
        }
        return $this;
    }

    private function writeIndent(): void
    {
        if ($this->needsIndent) {
            $this->needsIndent = false;
            $this->code .= str_repeat(' ', $this->indent * 4);
        }
    }

    public function indent(): self
    {
        $this->indent++;
        return $this;
    }

    public function outdent(): self
    {
        $this->indent--;
        return $this;
    }

    public function comment(string $comment): self
    {
        return $this->write("/* " . $comment . " */");
    }

    public function write(string $code): self
    {
        $lines = explode("\n", $code);
        for ($i = 0; $i < count($lines) - 1; $i++) {
            $this->writeIndent();
            $this->code .= rtrim($lines[$i]);
            $this->code .= $this->eol;
            $this->needsIndent = true;
        }
        $this->writeIndent();
        $this->code .= $lines[count($lines) - 1];
        return $this;
    }
}
