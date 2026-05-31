<?php declare(strict_types=1);

namespace CLanguage\Interpreter;

use CLanguage\MachineInfo;
use CLanguage\Types\CArrayType;
use CLanguage\Types\CBasicType;
use CLanguage\Types\CType;
use CLanguage\Types\TypeHierarchyEntry;
use CLanguage\Value;

class Executable
{
    public readonly MachineInfo $MachineInfo;
    public array $Functions = [];
    public array $Globals = [];
    private array $typeHierarchy = [];

    public function __construct(MachineInfo $machineInfo)
    {
        $this->MachineInfo = $machineInfo;
        $this->Functions = [];
        foreach ($machineInfo->InternalFunctions as $f) {
            $this->Functions[] = $f;
        }
    }

    public function addTypeHierarchyEntry(TypeHierarchyEntry $entry): void
    {
        $this->typeHierarchy[] = $entry;
    }

    public function getConstantMemory(string $stringConstant): Value
    {
        $index = count($this->Globals);
        $bytes = $stringConstant;
        $len = strlen($bytes) + 1;
        $type = new CArrayType(CBasicType::signedChar(), $len);
        $v = $this->addGlobal('__c' . count($this->Globals), $type);
        $values = [];
        for ($i = 0; $i < strlen($bytes); $i++) {
            $values[] = Value::fromByte(ord($bytes[$i]));
        }
        $values[] = Value::fromInt(0);
        $v->InitialValue = $values;
        return Value::pointer($v->StackOffset);
    }

    public function addGlobal(string $name, CType $type): CompiledVariable
    {
        $last = empty($this->Globals) ? null : $this->Globals[count($this->Globals) - 1];
        $offset = $last === null ? 0 : $last->stackOffset + $last->variableType->numValues;
        $v = new CompiledVariable($name, $offset, $type);
        $this->Globals[] = $v;
        return $v;
    }

    public function dumpOp(bool $internalFunction = false): string
    {
        $s = '';
        $m = $this->MachineInfo;
        $g = $this->Globals;
        $t = $this->typeHierarchy;
        $f = $this->Functions;

        $s .= "MachineInfo: {IntSize={$m->IntSize}, PointerSize={$m->PointerSize}, LongIntSize={$m->LongIntSize}, DoubleSize={$m->DoubleSize}}\n";

        if (count($g) > 0) {
            $s .= "Globals:\n";
            foreach ($g as $i => $global) {
                $vv = $global->initialValue === null ? 'null' : '[...]';
                if ($global->initialValue !== null && count($global->initialValue) > 0) {
                    $parts = array_map(fn($v) => (string)$v, $global->initialValue);
                    $vv = '[' . implode(', ', $parts) . ']';
                }
                $s .= "\t{$global->Name} @" . sprintf('%02d', $i) . " <{$global->variableType}> = {$vv}\n";
            }
        }

        if (count($t) > 0) {
            $s .= "Types:\n";
            foreach ($t as $typ) {
                $s .= "\t{$typ->typeName} <{$typ}>\n";
            }
        }

        if (count($f) > 0) {
            $s .= "Functions:\n";

            if ($internalFunction) {
                foreach ($f as $i => $ifun) {
                    if ($ifun instanceof InternalFunction) {
                        $nc = self::ifAppend($ifun->NameContext, '::');
                        $s .= "\t{$nc}{$ifun->Name} #" . sprintf('%02d', $i) . " `{$ifun->Action}` {$ifun->FunctionType}\n";
                    }
                }
            }

            foreach ($f as $i => $cfun) {
                if ($cfun instanceof CompiledFunction) {
                    $nc = self::ifAppend($cfun->NameContext, '::');
                    $s .= "\t{$nc}{$cfun->Name} #" . sprintf('%02d', $i) . " {$cfun->FunctionType}\n";
                    foreach ($cfun->Instructions as $ins) {
                        $op = str_replace('OpCode', '', $ins->op->Name);
                        $s .= "\t\t{$op} {$ins->x}\n";
                    }
                }
            }
        }

        return $s;
    }

    public static function ifAppend(string $s, string $append): string
    {
        if (strlen($s) === 0) return '';
        return $s . $append;
    }
}
