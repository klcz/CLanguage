<?php declare(strict_types=1);

namespace CLanguage\Interpreter;

use CLanguage\Compiler\CCompiler;
use CLanguage\Types\CFunctionType;
use CLanguage\Value;
use InvalidArgumentException;
use OutOfRangeException;
use RuntimeException;
use Throwable;

class CInterpreter
{
    private static ?BaseFunction $unusedStackFrameFunction = null;
    public Executable $exe;
    public array $Stack;
    public int $SP = 0;
    public array $Frames;
    public int $YieldedValue = 0;
    public int $SleepTime = 0;
    public int $RemainingTime = 0;
    public int $CpuSpeed = 1000;
    private ?BaseFunction $entrypoint = null;
    private int $FI = -1;

    public function __construct(Executable $exe, int $maxStack = 1024, int $maxFrames = 24)
    {
        if (self::$unusedStackFrameFunction === null) {
            self::$unusedStackFrameFunction = new InternalFunction('unused', '', CFunctionType::$VoidProcedure);
        }
        $this->exe = $exe;
        $this->Stack = [];
        for ($i = 0; $i < $maxStack; $i++) {
            $this->Stack[$i] = new Value(0);
        }
        $this->Frames = [];
        for ($i = 0; $i < $maxFrames; $i++) {
            $this->Frames[$i] = new ExecutionFrame(self::$unusedStackFrameFunction);
        }
    }

    public static function runString(string $code): void
    {
        $exe = CCompiler::compileFromString($code);
        $interpreter = new self($exe);
        $interpreter->reset('main');
        $interpreter->run();
    }

    public function reset(string $entrypoint): void
    {
        $this->entrypoint = null;
        foreach ($this->exe->Functions as $f) {
            if ($f->Name === $entrypoint) {
                $this->entrypoint = $f;
                break;
            }
        }
        $this->resetInternal();
    }

    private function resetInternal(): void
    {
        $this->FI = -1;
        $this->SP = 0;
        foreach ($this->exe->Globals as $g) {
            if ($g->InitialValue !== null) {
                for ($i = 0; $i < count($g->InitialValue); $i++) {
                    $this->Stack[$g->StackOffset + $i] = clone $g->InitialValue[$i];
                }
            }
            $this->SP += $g->VariableType->getNumValues();
        }
        $this->SleepTime = 0;
        if ($this->entrypoint !== null) {
            $this->call($this->entrypoint);
        }
    }

    public function call(mixed $fn): void
    {
        if ($fn instanceof Value) {
            $this->call($this->exe->Functions[$fn->PointerValue]);
            return;
        }
        if ($fn instanceof BaseFunction) {
            if ($this->FI + 1 >= count($this->Frames)) {
                $name = $fn->Name;
                $cname = $this->getActiveFrame()?->Function->Name ?? '?';
                $this->resetInternal();
                throw new ExecutionException("Stack overflow while calling '" . $name . "' from '" . $cname . "'");
            }
            $this->FI++;
            $frame = $this->Frames[$this->FI];
            $frame->Function = $fn;
            $frame->FP = $this->SP;
            $frame->IP = 0;
            $fn->init($this);
            return;
        }
        throw new InvalidArgumentException('Expected Value or BaseFunction');
    }

    public function getActiveFrame(): ?ExecutionFrame
    {
        if (0 <= $this->FI && $this->FI < count($this->Frames)) {
            return $this->Frames[$this->FI];
        }
        return null;
    }

    public function run(): void
    {
        $this->step(1000000);
    }

    public function step(int $microseconds): void
    {
        if ($this->getActiveFrame() === null) {
            return;
        }

        if ($microseconds <= $this->SleepTime) {
            $this->SleepTime -= $microseconds;
        } else {
            $this->RemainingTime = $microseconds - $this->SleepTime;
            $this->SleepTime = 0;

            try {
                $a = $this->getActiveFrame();
                while ($a !== null && $this->RemainingTime > 0) {
                    $this->RemainingTime -= $this->CpuSpeed;
                    $a->Function->step($this, $a);
                    $a = $this->getActiveFrame();
                    if ($this->YieldedValue !== 0) {
                        break;
                    }
                }
            } catch (Throwable $e) {
                $this->resetInternal();
                throw $e;
            }
        }
    }

    public function getExecutable(): Executable
    {
        return $this->exe;
    }

    public function getCallStackDepth(): int
    {
        return $this->FI;
    }

    public function readMemory(int $address): Value
    {
        return $this->Stack[$address];
    }

    public function writeMemory(int $address, Value $value): Value
    {
        $this->Stack[$address] = $value;
        return $value;
    }

    public function readString(int $address): string
    {
        return $this->readStringWithEncoding($address);
    }

    public function readStringWithEncoding(int $address, string $encoding = 'UTF-8'): string
    {
        $b = $this->Stack[$address]->UInt8Value;
        $bytes = [];
        while ($b !== 0) {
            $bytes[] = chr($b);
            $address++;
            $b = $this->Stack[$address]->UInt8Value;
        }
        $str = implode('', $bytes);
        if ($encoding !== 'UTF-8') {
            $str = mb_convert_encoding($str, 'UTF-8', $encoding);
        }
        return $str;
    }

    public function readThis(): Value
    {
        $frame = $this->getActiveFrame();
        if ($frame === null) {
            return new Value(0);
        }
        $functionType = $frame->Function->FunctionType;
        if ($functionType->IsInstance) {
            $frameOffset = -1;
        } else {
            return new Value(0);
        }
        $address = $frame->FP + $frameOffset;
        return $this->Stack[$address];
    }

    public function readArg(int $index): Value
    {
        $frame = $this->getActiveFrame();
        if ($frame === null) {
            return new Value(0);
        }
        $functionType = $frame->Function->FunctionType;
        $params = $functionType->Parameters;
        if ($index < count($params)) {
            $frameOffset = $params[$index]->Offset;
        } elseif ($index === count($params) && $functionType->IsInstance) {
            $frameOffset = -1;
        } else {
            throw new OutOfRangeException("Cannot read argument #" . $index);
        }
        $address = $frame->FP + $frameOffset;
        return $this->Stack[$address];
    }

    public function yield(int $yieldedValue): void
    {
        $this->YieldedValue = $yieldedValue;
    }

    public function runFunction(Value $functionAddress, int $microseconds, Value ...$args): Value
    {
        foreach ($args as $arg) {
            $this->push($arg);
        }
        $this->call($functionAddress);
        return $this->stepFunction($microseconds, count($args));
    }

    public function push(Value $value): void
    {
        $this->Stack[$this->SP++] = $value;
    }

    private function stepFunction(int $microseconds, int $argCount): Value
    {
        $af = $this->getActiveFrame();
        if ($af === null) {
            return new Value(0);
        }

        $parametersCount = count($af->Function->FunctionType->Parameters);
        if ($argCount !== $parametersCount) {
            throw new RuntimeException(
                "Expected {$parametersCount} arguments, got {$argCount} for function {$af->Function->Name}"
            );
        }

        $startFI = $this->FI;
        $startReturnType = $af->Function->FunctionType->ReturnType;

        if ($microseconds <= $this->SleepTime) {
            $this->SleepTime -= $microseconds;
        } else {
            $this->RemainingTime = $microseconds - $this->SleepTime;
            $this->SleepTime = 0;

            try {
                $a = $this->getActiveFrame();
                while ($a !== null && $this->FI >= $startFI && $this->RemainingTime > 0) {
                    $this->RemainingTime -= $this->CpuSpeed;
                    $a->Function->step($this, $a);
                    $a = $this->getActiveFrame();
                    if ($this->YieldedValue !== 0) {
                        break;
                    }
                }
            } catch (Throwable $e) {
                $this->resetInternal();
                throw $e;
            }
        }

        while ($this->FI >= $startFI) {
            $af2 = $this->getActiveFrame();
            if ($af2 !== null) {
                $rt = $af2->Function->FunctionType->ReturnType;
                if (!$rt->isVoid()) {
                    $n = $rt->getNumValues();
                    for ($i = 0; $i < $n; $i++) {
                        $this->Stack[$this->SP++] = new Value(0);
                    }
                }
                $this->return();
            } else {
                break;
            }
        }

        $returnValue = new Value(0);
        $numReturnValues = $startReturnType !== null ? $startReturnType->getNumValues() : 0;
        for ($i = 0; $i < $numReturnValues; $i++) {
            $returnValue = $this->Stack[--$this->SP];
        }
        return $returnValue;
    }

    public function return(): void
    {
        $frame = $this->getActiveFrame();
        if ($frame === null) {
            throw new RuntimeException("Cannot call Return with no ActiveFrame");
        }
        $ftype = $frame->Function->FunctionType;
        $numArgsAndLocals = 0;
        foreach ($ftype->Parameters as $p) {
            $numArgsAndLocals += $p->ParameterType->getNumValues();
        }
        if ($ftype->IsInstance) {
            $numArgsAndLocals++;
        }
        if ($frame->Function instanceof CompiledFunction) {
            $cf = $frame->Function;
            foreach ($cf->LocalVariables as $v) {
                $numArgsAndLocals += $v->VariableType->getNumValues();
            }
        }

        $numReturnVals = $ftype->ReturnType->getNumValues();
        $newSP = $this->SP - $numArgsAndLocals;
        $retSP = $newSP - $numReturnVals;
        for ($i = 0; $i < $numReturnVals; $i++) {
            $this->Stack[$retSP + $i] = clone $this->Stack[$this->SP - $numReturnVals + $i];
        }
        $this->SP = $newSP;

        $this->FI--;
    }

    /**
     * @throws Throwable
     */
    public function runFunction0(Value $functionAddress, int $microseconds): Value
    {
        $this->call($functionAddress);
        return $this->stepFunction($microseconds, 0);
    }

    /**
     * @throws Throwable
     */
    public function runFunction1(Value $functionAddress, Value $arg0, int $microseconds): Value
    {
        $this->push($arg0);
        $this->call($functionAddress);
        return $this->stepFunction($microseconds, 1);
    }

    /**
     * @throws Throwable
     */
    public function runFunction2(Value $functionAddress, Value $arg0, Value $arg1, int $microseconds): Value
    {
        $this->push($arg0);
        $this->push($arg1);
        $this->call($functionAddress);
        return $this->stepFunction($microseconds, 2);
    }

    public function runFunction3(Value $functionAddress, Value $arg0, Value $arg1, Value $arg2, int $microseconds): Value
    {
        $this->push($arg0);
        $this->push($arg1);
        $this->push($arg2);
        $this->call($functionAddress);
        return $this->stepFunction($microseconds, 3);
    }
}
