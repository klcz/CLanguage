<?php
namespace parse;

class Document
{
    public $Path;
    public $Content;
    public $Encoding;

    public function __construct(string $path, string $content, $encoding = null)
    {
        if (trim($path) === '') {
            throw new \InvalidArgumentException('Document path must be specified');
        }
        $this->Path = $path;
        $this->Content = $content;
        $this->Encoding = $encoding ?? 'UTF-8';
    }

    public function getIsCompilable(): bool
    {
        $ext = strtolower(pathinfo($this->Path, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'c':
            case 'cpp':
            case 'cxx':
            case 'm':
            case 'mpp':
            case 'ino':
                return true;
        }
        return false;
    }

    public function __toString(): string
    {
        return $this->Path;
    }
}
