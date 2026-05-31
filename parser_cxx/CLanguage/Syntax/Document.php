<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use InvalidArgumentException;

class Document
{
    private const array COMPILABLE_EXTENSIONS = ['.c', '.cpp', '.cxx', '.m', '.mpp', '.ino'];
    public readonly string $Path;
    public readonly string $Content;
    public readonly ?string $Encoding;
    public bool $IsCompilable {
        get => $this->isCompilable();
    }

    public function __construct(string $path, string $content, ?string $encoding = null)
    {
        if (trim($path) === '') {
            throw new InvalidArgumentException('Document path must be specified');
        }

        $this->Path = $path;
        $this->Content = $content;
        $this->Encoding = $encoding ?? 'UTF-8';
    }

    public function isCompilable(): bool
    {
        $ext = strtolower(pathinfo($this->Path, PATHINFO_EXTENSION));
        return in_array('.' . $ext, self::COMPILABLE_EXTENSIONS, true);
    }

    public function __toString(): string
    {
        return $this->Path;
    }
}
