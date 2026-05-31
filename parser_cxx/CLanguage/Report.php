<?php declare(strict_types=1);

namespace CLanguage;

use CLanguage\Syntax\Location;

interface TextWriter
{
    public function write(string $text): void;
}

class Report
{
    private int $_reportingDisabled = 0;
    private array $_extraInformation = [];
    private ?Printer $_printer;
    private array $_previousErrors = [];

    public function __construct(?Printer $printer = null)
    {
        $this->_printer = $printer ?? new Printer();
    }

    public function getErrors(): array
    {
        return array_values($this->_previousErrors);
    }

    public function warning(int $code, ?Location $loc = null, ?Location $endLoc = null, string $warning = '', mixed ...$args): void
    {
        if ($this->_reportingDisabled > 0) return;

        $loc ??= Location::null();
        $endLoc ??= Location::null();

        if ($args !== []) {
            $warning = sprintf($warning, ...$args);
        }

        $msg = new WarningMessage($code, $loc, $endLoc, $warning, $this->_extraInformation);
        $this->_extraInformation = [];

        $hash = $msg->hash();
        if (!isset($this->_previousErrors[$hash])) {
            $this->_previousErrors[$hash] = $msg;
            $this->_printer->print($msg);
        }
    }

    public function errorCode(int $code, mixed ...$args): void
    {
        $loc = Location::null();
        $endLoc = Location::null();
        $formatArgs = $args;

        if (count($args) >= 2 && $args[0] instanceof Location && $args[1] instanceof Location) {
            $loc = $args[0];
            $endLoc = $args[1];
            $formatArgs = array_slice($args, 2);
        }

        $m = match ($code) {
            103 => "%s '%s' not found",
            default => null,
        };

        if ($m === null) {
            $this->error($code, $loc, $endLoc, implode(', ', $formatArgs));
            return;
        }

        $this->error($code, $loc, $endLoc, sprintf($m, ...$formatArgs));
    }

    public function error(int $code, ?Location $loc = null, ?Location $endLoc = null, string $error = '', mixed ...$args): void
    {
        if ($this->_reportingDisabled > 0) return;

        $loc ??= Location::null();
        $endLoc ??= Location::null();

        if ($args !== []) {
            $error = sprintf($error, ...$args);
        }

        $msg = new ErrorMessage($code, $loc, $endLoc, $error, $this->_extraInformation);
        $this->_extraInformation = [];

        $hash = $msg->hash();
        if (!isset($this->_previousErrors[$hash])) {
            $this->_previousErrors[$hash] = $msg;
            $this->_printer->print($msg);
        }
    }
}

class AbstractMessage
{
    public string $MessageType = 'Info';
    public Location $Location;
    public Location $EndLocation;
    public bool $IsWarning = false;
    public int $Code = 0;
    public string $Text = '';

    public function __construct(string $type = '', string $text = '')
    {
        if ($type !== '') {
            $this->MessageType = $type;
        }
        if ($text !== '') {
            $this->Text = $text;
        }
    }

    public function equals(?AbstractMessage $o = null): bool
    {
        return $o !== null
            && $o->Code === $this->Code
            && (string)$o->Location === (string)$this->Location
            && $o->IsWarning === $this->IsWarning
            && $o->Text === $this->Text;
    }

    public function hash(): string
    {
        return md5(implode("\x00", [
            (string)$this->Code,
            (string)$this->Location,
            (string)$this->EndLocation,
            $this->IsWarning ? '1' : '0',
            $this->Text,
        ]));
    }

    public function __toString(): string
    {
        $mt = strtolower($this->MessageType);
        if ($this->Location->isNull()) {
            return sprintf("%s C%04d: %s", $mt, $this->Code, $this->Text);
        }
        return sprintf(
            "%s(%d,%d,%d,%d): %s C%04d: %s",
            $this->Location->Document->Path,
            $this->Location->Line,
            $this->Location->Column,
            $this->EndLocation->Line,
            $this->EndLocation->Column,
            $mt,
            $this->Code,
            $this->Text
        );
    }

    public function isError(): bool
    {
        return !$this->IsWarning;
    }
}

class ErrorMessage extends AbstractMessage
{
    public function __construct(
        int      $code,
        Location $loc,
        Location $endLoc,
        string   $error,
        array    $extraInformation,
    )
    {
        parent::__construct();
        $this->MessageType = 'Error';
        $this->Code = $code;
        $this->Text = $error;
        $this->Location = $loc;
        $this->EndLocation = $endLoc;
        $this->IsWarning = false;
    }
}

class WarningMessage extends AbstractMessage
{
    public function __construct(
        int      $code,
        Location $loc,
        Location $endLoc,
        string   $error,
        array    $extraInformation,
    )
    {
        parent::__construct();
        $this->MessageType = 'Warning';
        $this->Code = $code;
        $this->Text = $error;
        $this->Location = $loc;
        $this->EndLocation = $endLoc;
        $this->IsWarning = true;
    }
}

class Printer
{
    private int $warnings = 0;
    private int $errors = 0;

    public function getWarningsCount(): int
    {
        return $this->warnings;
    }

    public function getErrorsCount(): int
    {
        return $this->errors;
    }

    public function print(AbstractMessage $msg): void
    {
        if ($msg->IsWarning) {
            ++$this->warnings;
        } else {
            ++$this->errors;
        }
    }
}

class SavedPrinter extends Printer
{
    public array $Messages = [];

    public function print(AbstractMessage $msg): void
    {
        parent::print($msg);
        $this->Messages[] = $msg;
    }
}

class TextWriterPrinter extends Printer
{
    private object $output;

    public function __construct(object $output)
    {
        $this->output = $output;
    }

    public function print(AbstractMessage $msg): void
    {
        parent::print($msg);

        if (!$msg->Location->isNull()) {
            $this->output->write($msg->Location . ' ');
        }

        $this->output->write(sprintf(
                "%s C%04d: %s",
                $msg->MessageType,
                $msg->Code,
                $msg->Text
            ) . "\n");
    }
}
