<?php
namespace parse;

class AbstractMessage
{
    public $MessageType = 'Info';
    public $Location;
    public $EndLocation;
    public $IsWarning = false;
    public $Code = 0;
    public $Text = '';

    public function __construct(string $type = 'Info', string $text = '')
    {
        $this->MessageType = $type;
        $this->Text = $text;
        $this->Location = Location::getNull();
        $this->EndLocation = Location::getNull();
    }

    public function getIsError(): bool
    {
        return !$this->IsWarning;
    }

    public function __toString(): string
    {
        if ($this->Location->getIsNull()) {
            return sprintf('%s C%04d: %s', strtolower($this->MessageType), $this->Code, $this->Text);
        }
        return sprintf(
            '%s(%d,%d,%d,%d): %s C%04d: %s',
            $this->Location->Document->Path,
            $this->Location->Line,
            $this->Location->Column,
            $this->EndLocation->Line,
            $this->EndLocation->Column,
            strtolower($this->MessageType),
            $this->Code,
            $this->Text
        );
    }
}

class ErrorMessage extends AbstractMessage
{
    public function __construct(int $code, Location $loc, Location $endLoc, string $error, array $extraInformation)
    {
        parent::__construct('Error', $error);
        $this->Code = $code;
        $this->Location = $loc;
        $this->EndLocation = $endLoc;
        $this->IsWarning = false;
    }
}

class WarningMessage extends AbstractMessage
{
    public function __construct(int $code, Location $loc, Location $endLoc, string $error, array $extraInformation)
    {
        parent::__construct('Warning', $error);
        $this->Code = $code;
        $this->Location = $loc;
        $this->EndLocation = $endLoc;
        $this->IsWarning = true;
    }
}

class Printer
{
    public $WarningsCount = 0;
    public $ErrorsCount = 0;

    public function print(AbstractMessage $msg): void
    {
        if ($msg->IsWarning) {
            $this->WarningsCount++;
        } else {
            $this->ErrorsCount++;
        }
    }
}

class SavedPrinter extends Printer
{
    public $Messages = [];

    public function print(AbstractMessage $msg): void
    {
        parent::print($msg);
        $this->Messages[] = $msg;
    }
}

class TextWriterPrinter extends Printer
{
    private $output;

    public function __construct($output)
    {
        $this->output = $output;
    }

    public function print(AbstractMessage $msg): void
    {
        parent::print($msg);
        if (!$msg->Location->getIsNull()) {
            fwrite($this->output, $msg->Location . ' ');
        }
        fwrite($this->output, sprintf("%s C%04d: %s\n", $msg->MessageType, $msg->Code, $msg->Text));
    }
}

class Report
{
    private $reportingDisabled = 0;
    private $extraInformation = [];
    private $printer;
    private $previousErrors = [];

    public function __construct($printer = null)
    {
        $this->printer = $printer ?? new Printer();
    }

    public function getErrors(): array
    {
        return array_keys($this->previousErrors);
    }

    public function error(int $code, $loc, $endLoc, string $error): void
    {
        if ($this->reportingDisabled > 0) {
            return;
        }
        if (!($loc instanceof Location)) {
            $loc = Location::getNull();
        }
        if (!($endLoc instanceof Location)) {
            $endLoc = Location::getNull();
        }
        $msg = new ErrorMessage($code, $loc, $endLoc, $error, $this->extraInformation);
        $this->extraInformation = [];
        $key = $this->messageKey($msg);
        if (!isset($this->previousErrors[$key])) {
            $this->previousErrors[$key] = true;
            $this->printer->print($msg);
        }
    }

    public function warning(int $code, $loc, $endLoc, string $warning): void
    {
        if ($this->reportingDisabled > 0) {
            return;
        }
        if (!($loc instanceof Location)) {
            $loc = Location::getNull();
        }
        if (!($endLoc instanceof Location)) {
            $endLoc = Location::getNull();
        }
        $msg = new WarningMessage($code, $loc, $endLoc, $warning, $this->extraInformation);
        $this->extraInformation = [];
        $key = $this->messageKey($msg);
        if (!isset($this->previousErrors[$key])) {
            $this->previousErrors[$key] = true;
            $this->printer->print($msg);
        }
    }

    private function messageKey(AbstractMessage $msg): string
    {
        return $msg->Code . '|' . ($msg->Location->getIsNull() ? 'null' : $msg->Location->Line . ',' . $msg->Location->Column) . '|' . ($msg->IsWarning ? '1' : '0') . '|' . $msg->Text;
    }
}
