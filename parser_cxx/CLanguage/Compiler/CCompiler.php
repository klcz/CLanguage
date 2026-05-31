<?php declare(strict_types=1);

namespace CLanguage\Compiler;

use CLanguage\Interpreter\BaseFunction;
use CLanguage\Interpreter\CInterpreter;
use CLanguage\Interpreter\CompiledFunction;
use CLanguage\Interpreter\CompiledVariable;
use CLanguage\Interpreter\Executable;
use CLanguage\Interpreter\ExecutionException;
use CLanguage\Interpreter\InternalFunction;
use CLanguage\Interpreter\OpCode;
use CLanguage\MachineInfo;
use CLanguage\Parser\CParser;
use CLanguage\Parser\LexedDocument;
use CLanguage\Report;
use CLanguage\Syntax\Block;
use CLanguage\Syntax\Declarator;
use CLanguage\Syntax\Document;
use CLanguage\Syntax\ExpressionStatement;
use CLanguage\Syntax\FuncallExpression;
use CLanguage\Syntax\FunctionDeclarator;
use CLanguage\Syntax\Location;
use CLanguage\Syntax\TranslationUnit;
use CLanguage\Syntax\VariableExpression;
use CLanguage\Types\CArrayType;
use CLanguage\Types\CBasicType;
use CLanguage\Types\CFunctionType;
use CLanguage\Types\CStructType;
use CLanguage\Types\TypeHierarchyEntry;
use CLanguage\Value;
use InvalidArgumentException;
use Throwable;

/**
 * Type IDs start at 1 so that 0 can be reserved as "no type" / invalid.
 */
class CCompiler
{
    private const int FirstTypeId = 1;

    private CompilerOptions $options;

    /** @--var array<string, LexedDocument> */
    private array $lexedDocuments = [];

    /** @--var TranslationUnit[] */
    private array $tus;

    public function __construct(?CompilerOptions $options = null, ?MachineInfo $mi = null, ?Report $report = null)
    {
        if ($options === null) {
            if ($mi !== null && $report !== null) {
                $options = new CompilerOptions($mi, $report, []);
            } else {
                $options = new CompilerOptions();
            }
        }
        $this->options = $options;
        $this->tus = [];

        $this->processDocument(new Document('_machine.h', $options->MachineInfo->getGeneratedHeaderCode()));
        foreach ($options->MachineInfo->SystemHeadersCode as $key => $value) {
            $this->processDocument(new Document($key, $value));
        }
        foreach ($options->Documents as $d) {
            $this->processDocument($d);
        }
    }

    private function processDocument(Document $document): void
    {
        $lexed = new LexedDocument($document, $this->options->Report);
        $this->lexedDocuments[$document->Path] = $lexed;

        if ($document->IsCompilable) {
            $parser = new CParser();
            $name = pathinfo($document->Path, PATHINFO_FILENAME);

            $this->add(
                $parser->parseTranslationUnit(
                    $this->options->Report,
                    $name,
                    [$this, 'resolveInclude'],
                    ...$this->lexedDocuments['_machine.h']->Tokens,
                    ...$lexed->Tokens
                )
            );
        }
    }

    public function add(TranslationUnit $translationUnit): void
    {
        $this->tus[] = $translationUnit;
    }

    public static function compileFromString(string $code): Executable
    {
        $compiler = new self();
        $compiler->addCode('main.c', $code);
        $exe = $compiler->compile();
        $errorMessages = [];
        foreach ($compiler->options->Report->getErrors() as $err) {
            if ($err->isError) {
                $errorMessages[] = (string)$err;
            }
        }
        if ($errorMessages !== []) {
            throw new InvalidArgumentException(implode("\n", $errorMessages));
        }
        return $exe;
    }

    public function addCode(string $name, string $code): void
    {
        $this->addDocument(new Document($name, $code));
    }

    public function addDocument(Document $document): void
    {
        $this->processDocument($document);
    }

    public function compile(): Executable
    {
        try {
            return $this->compileExecutable();
        } catch (Throwable $ex) {
            error_log((string)$ex);
            $this->options->Report->error(9000, error: 'Compiler error: ' . $ex->getMessage());
            return new Executable($this->options->MachineInfo);
        }
    }

    private function compileExecutable(): Executable
    {
        $exe = new Executable($this->options->MachineInfo);
        $exeContext = new ExecutableContext($exe, $this->options->Report);

        // Put something at the zero address so we don't get 0 addresses of globals
        $exe->addGlobal('__zero__', CBasicType::SignedInt());

        //
        // Find Variables, Functions, Types
        //
        $exeInitBody = new Block(VariableScope::Local);
        $tucs = [];
        foreach ($this->tus as $tu) {
            $tucs[] = new TranslationUnitContext($tu, $exeContext);
        }
        $tuInits = [];

        foreach ($tucs as $tuc) {
            $tu = $tuc->TranslationUnit;
            $this->addStatementDeclarations($tuc);
            if (count($tu->InitStatements) > 0) {
                $tuInitBody = new Block(VariableScope::Local);
                $tuInitBody->addStatements($tu->InitStatements);
                $tuInit = new CompiledFunction("__{$tu->Name}__cinit", '', CFunctionType::$VoidProcedure, $tuInitBody);
                $exeInitBody->addStatement(
                    new ExpressionStatement(
                        new FuncallExpression(
                            new VariableExpression($tuInit->Name, Location::null(), Location::null())
                        )
                    )
                );
                $tuInits[] = new FunctionToCompile($tuInit, $tuc);
                $exe->Functions[] = $tuInit;
            }
        }

        //
        // Generate a function to init globals
        //
        $exeInit = new CompiledFunction('__cinit', '', CFunctionType::$VoidProcedure, $exeInitBody);
        $exe->Functions[] = $exeInit;

        //
        // Allocate vtable globals for polymorphic types
        // This must happen before globals are added so vptr InitialValues can reference vtable addresses
        //
        $polymorphicTypes = [];
        $vtableVars = [];
        foreach ($tucs as $tuc) {
            $this->collectPolymorphicTypes($tuc->TranslationUnit, $polymorphicTypes);
        }
        $pureVirtualTrap = null;
        if (count($polymorphicTypes) > 0) {
            // Add the pure-virtual trap function (only when needed)
            $pureVirtualTrap = new InternalFunction('__pure_virtual_called', '', CFunctionType::$VoidProcedure);
            $pureVirtualTrap->Action = function (CInterpreter $state) {
                throw new ExecutionException('Pure virtual function called');
            };
            $exe->Functions[] = $pureVirtualTrap;
        }
        $nextTypeId = self::FirstTypeId;
        foreach ($polymorphicTypes as $st) {
            // Assign unique type ID for RTTI
            $st->VTable->TypeId = $nextTypeId++;
            // Vtable runtime layout: [type_id, method0, method1, ...]
            $vtableSize = $st->VTable->runtimeSlotCount;
            $vtableType = new CArrayType(CBasicType::SignedInt(), $vtableSize);
            $vtableVar = $exe->addGlobal("__vtable_{$st->Name}", $vtableType);
            $st->VTableGlobalAddress = $vtableVar->StackOffset;
            $vtableVars[spl_object_id($st)] = $vtableVar;
        }

        //
        // Link everything together
        // This is done before compilation to make sure everything is visible (for recursion)
        //
        $functionsToCompile = [new FunctionToCompile($exeInit, $exeContext)];
        array_push($functionsToCompile, ...$tuInits);

        foreach ($tucs as $tuc) {
            $tu = $tuc->TranslationUnit;
            foreach ($tu->Variables as $g) {
                $v = $exe->addGlobal($g->Name, $g->VariableType);
                $v->InitialValue = $g->InitialValue;
                // Set vptr for polymorphic global variables
                $gst = $g->VariableType;
                if ($gst instanceof CStructType && $gst->isPolymorphic() && $gst->VTableGlobalAddress !== null) {
                    $numValues = $gst->numValues();
                    if ($v->InitialValue === null || count($v->InitialValue) < $numValues) {
                        $v->InitialValue = array_fill(0, $numValues, new Value(0));
                    }
                    $v->InitialValue[0] = Value::pointer($gst->VTableGlobalAddress);
                }
            }

            $funcs = [];
            foreach ($tu->Functions as $f) {
                if ($f->Body !== null) {
                    $funcs[] = $f;
                }
            }
            foreach ($funcs as $f) {
                $exe->Functions[] = $f;
            }
            foreach ($funcs as $f) {
                $functionsToCompile[] = new FunctionToCompile($f, $tuc);
            }
        }

        //
        // Populate vtable initial values with function pointers
        // Now that all functions have their indices, we can resolve them
        //
        $funcIndex = self::buildFunctionIndex($exe);
        foreach ($polymorphicTypes as $st) {
            $key = spl_object_id($st);
            if (isset($vtableVars[$key])) {
                $this->populateVTable($exe, $st, $vtableVars[$key], $funcIndex, $pureVirtualTrap);
            }
        }

        //
        // Build compile-time type hierarchy table for RTTI
        //
        foreach ($polymorphicTypes as $st) {
            $baseTypeId = -1;
            if ($st->BaseType?->VTable !== null) {
                $baseTypeId = $st->BaseType->VTable->TypeId;
            }
            $exe->addTypeHierarchyEntry(new TypeHierarchyEntry($st->VTable->TypeId, $baseTypeId, $st->Name));
        }

        //
        // Compile functions
        //
        foreach ($functionsToCompile as $fAndPC) {
            $f = $fAndPC->Function;
            $pc = $fAndPC->Context;
            $body = $f->Body;
            if ($body === null) {
                continue;
            }
            $fc = new FunctionContext($exe, $f, $pc);
            $this->addStatementDeclarations($fc);
            $body->emit($fc);
            $fc->checkLabels();
            array_push($f->LocalVariables, ...$fc->LocalVariables);

            // Make sure it returns
            if (count($body->Statements) === 0 || !$body->alwaysReturns()) {
                if ($f->FunctionType->ReturnType->isVoid()) {
                    $fc->emit(OpCode::Return);
                } else {
                    $this->options->Report->error(161, error: "'{$f->Name}' not all code paths return a value");
                }
            }
        }

        return $exe;
    }

    private function addStatementDeclarations(BlockContext $context): void
    {
        $block = $context->Block;
        foreach ($block->Statements as $s) {
            $s->addDeclarationToBlock($context);
        }
    }

    private function collectPolymorphicTypes(Block $block, array &$result): void
    {
        foreach ($block->Structures as $kv) {
            if ($kv->isPolymorphic() && $kv->VTable !== null) {
                $found = false;
                foreach ($result as $existing) {
                    if ($existing === $kv) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $result[] = $kv;
                }
            }
        }
    }

    /** @return array<string, array<int, array{index: int, func: BaseFunction}>> */
    private static function buildFunctionIndex(Executable $exe): array
    {
        $index = [];
        foreach ($exe->Functions as $i => $f) {
            $key = $f->NameContext . "\0" . $f->Name;
            $index[$key][] = ['index' => $i, 'func' => $f];
        }
        return $index;
    }

    private function populateVTable(
        Executable       $exe,
        CStructType      $st,
        CompiledVariable $vtableVar,
        array            $funcIndex,
        BaseFunction     $pureVirtualTrap
    ): void
    {
        if ($st->VTable === null) {
            return;
        }

        // Runtime layout: [type_id, method0, method1, ...]
        $initialValues = array_fill(0, $st->VTable->RuntimeSlotCount, new Value(0));
        $initialValues[0] = $st->VTable->TypeId;
        $trapIndex = array_search($pureVirtualTrap, $exe->Functions, true);
        for ($i = 0; $i < $st->VTable->count(); $i++) {
            $entry = $st->VTable[$i];
            $idx = self::findFunctionInIndex(
                $funcIndex,
                $entry->DeclaringType->Name,
                $entry->MethodName,
                $entry->Signature
            );
            if ($idx >= 0) {
                $initialValues[$i + 1] = Value::pointer($idx);
            } else {
                // Use pure virtual trap for unresolved methods
                $initialValues[$i + 1] = Value::pointer($trapIndex !== false ? $trapIndex : 0);
            }
        }
        $vtableVar->InitialValue = $initialValues;
    }

    private static function findFunctionInIndex(
        array         $funcIndex,
        string        $nameContext,
        string        $methodName,
        CFunctionType $signature
    ): int
    {
        $key = $nameContext . "\0" . $methodName;
        if (isset($funcIndex[$key])) {
            foreach ($funcIndex[$key] as $candidate) {
                if ($candidate['func']->FunctionType->parameterTypesEqual($signature)) {
                    return $candidate['index'];
                }
            }
        }
        return -1;
    }

    private function resolveInclude(string $path, bool $relative): ?array
    {
        if (isset($this->lexedDocuments[$path])) {
            return $this->lexedDocuments[$path]->Tokens;
        }
        return null;
    }

    private function getFunctionDeclarator(?Declarator $d): ?FunctionDeclarator
    {
        if ($d === null) return null;
        if ($d instanceof FunctionDeclarator) return $d;
        return $this->getFunctionDeclarator($d->InnerDeclarator);
    }
}

readonly class FunctionToCompile
{
    public CompiledFunction $Function;
    public EmitContext $Context;

    public function __construct(CompiledFunction $function, EmitContext $context)
    {
        $this->Function = $function;
        $this->Context = $context;
    }
}
