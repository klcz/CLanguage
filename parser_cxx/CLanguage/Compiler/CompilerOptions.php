<?php declare(strict_types=1);

namespace CLanguage\Compiler;

use CLanguage\MachineInfo;
use CLanguage\Report;

readonly class CompilerOptions
{
    public MachineInfo $MachineInfo;
    public Report $Report;
    public array $Documents;

    public function __construct(?MachineInfo $machineInfo = null, ?Report $report = null, array $documents = [])
    {
        $this->MachineInfo = $machineInfo ?? new MachineInfo();
        $this->Report = $report ?? new Report();
        $this->Documents = $documents;
    }
}
