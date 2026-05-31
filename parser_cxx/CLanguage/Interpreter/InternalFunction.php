<?php declare(strict_types=1);

namespace CLanguage\Interpreter;

use CLanguage\Compiler\CCompiler;
use CLanguage\MachineInfo;
use CLanguage\Parser\CParser;
use CLanguage\Parser\LexedDocument;
use CLanguage\Report;
use CLanguage\Syntax\Document;
use CLanguage\Types\CFunctionType;
use Closure;
use RuntimeException;

class InternalFunction extends BaseFunction
{
    public ?Closure $Action = null;

    public function __construct(
        MachineInfo|string         $machineInfoOrName,
        ?string                    $prototypeOrNameContext = null,
        CFunctionType|Closure|null $functionTypeOrAction = null,
        ?Closure                   $action = null,
    )
    {
        parent::__construct();
        // Normalize arguments for two overloads.
        // Overload 1: InternalFunction(MachineInfo, string, Closure)
        // Overload 2: InternalFunction(string, string, CFunctionType)
        if ($machineInfoOrName instanceof MachineInfo) {
            $machineInfo = $machineInfoOrName;
            $prototype = $prototypeOrNameContext ?? '';
            $action = $functionTypeOrAction instanceof Closure ? $functionTypeOrAction : ($action ?? null);
            $this->initFromPrototype($machineInfo, $prototype, $action);
        } else {
            $name = $machineInfoOrName;
            $nameContext = $prototypeOrNameContext ?? '';
            $functionType = $functionTypeOrAction instanceof CFunctionType ? $functionTypeOrAction : CFunctionType::$VoidProcedure;
            $this->Name = $name;
            $this->NameContext = $nameContext;
            $this->FunctionType = $functionType;
            $this->Action = fn() => null;
        }
    }

    private function initFromPrototype(MachineInfo $machineInfo, string $prototype, ?Closure $action): void
    {
        $report = new Report();
        $parser = new CParser();
        $tu = $parser->parseTranslationUnit('_internal.h', $prototype . ';', fn($x, $y) => null, $report);
        $compiler = new CCompiler(null, $machineInfo, $report);
        $compiler->add($tu);
        $exe = $compiler->compile();

        if (count($tu->Functions) === 0) {
            $report = new Report();
            $parser = new CParser();
            $headerDoc = new LexedDocument(new Document('_machine.h', $machineInfo->getGeneratedHeaderCode()), $report);
            $protoDoc = new LexedDocument(new Document('_internal.h', $prototype . ';'), $report);
            $tu = $parser->parseTranslationUnit($report, '_internal',
                fn($x, $y) => null, ...$headerDoc->Tokens, ...$protoDoc->Tokens);
            $compiler = new CCompiler(null, $machineInfo, $report);
            $compiler->add($tu);
            $exe = $compiler->compile();
            if (count($tu->Functions) === 0) {
                throw new RuntimeException('Failed to parse function prototype: ' . $prototype);
            }
        }

        $f = $tu->Functions[count($tu->Functions) - 1];
        $this->Name = $f->Name;
        $this->NameContext = $f->NameContext;
        $this->FunctionType = $f->functionType;

        if ($action !== null) {
            $this->Action = $action;
        } else {
            $this->Action = fn() => null;
        }
    }

    public function step(CInterpreter $state, ExecutionFrame $frame): void
    {
        $a = $this->Action;
        if ($a !== null) {
            $a($state);
        }
        if ($state->YieldedValue === 0) {
            $state->return();
        }
    }
}
