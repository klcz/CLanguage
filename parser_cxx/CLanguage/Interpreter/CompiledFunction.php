<?php declare(strict_types=1);

namespace CLanguage\Interpreter;

use CLanguage\Syntax\Block;
use CLanguage\Types\CFunctionType;
use CLanguage\Value;
use RuntimeException;

class CompiledFunction extends BaseFunction
{
    public readonly ?Block $Body;
    public array $LocalVariables = [];
    public array $Instructions = [];

    public function __construct(string $name, string $nameContext, CFunctionType $functionType, ?Block $body = null)
    {
        parent::__construct();
        $this->Name = $name;
        $this->NameContext = $nameContext;
        $this->FunctionType = $functionType;
        $this->Body = $body;
        $this->LocalVariables = [];
        $this->Instructions = [];
    }

    public function __toString(): string
    {
        return $this->Name;
    }

    public function getAssembler(): string
    {
        $w = '';
        for ($i = 0; $i < count($this->Instructions); $i++) {
            $w .= "{$i}: {$this->Instructions[$i]}\n";
        }
        return $w;
    }

    public function init(CInterpreter $state): void
    {
        $last = count($this->LocalVariables) === 0 ? null : $this->LocalVariables[count($this->LocalVariables) - 1];
        if ($last !== null) {
            $state->SP += $last->stackOffset + $last->variableType->numValues;
        }
    }

    public function step(CInterpreter $state, ExecutionFrame $frame): void
    {
        $ip = $frame->IP;
        $done = false;

        while (!$done && $ip < count($this->Instructions) && $state->RemainingTime > 0) {

            $i = $this->Instructions[$ip];

            if ($state->SP < $frame->FP) {
                $prev = ($ip - 1 >= 0) ? $this->Instructions[$ip - 1] : null;
                throw new RuntimeException("{$prev} {$this->Name}@{$ip} stack underflow");
            }

            switch ($i->op) {

                case OpCode::Dup:
                    $state->Stack[$state->SP] = clone $state->Stack[$state->SP - 1];
                    $state->SP++;
                    $ip++;
                    break;

                case OpCode::Pop:
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::Jump:
                    if ($i->label !== null) {
                        $ip = $i->label->index;
                    } else {
                        throw new RuntimeException("Jump label not set");
                    }
                    break;

                case OpCode::BranchIfFalse:
                    $a = $state->Stack[$state->SP - 1];
                    $state->SP--;
                    if ($a->uint8() === 0) {
                        if ($i->label !== null) {
                            $ip = $i->label->index;
                        } else {
                            throw new RuntimeException("BranchIfFalse label not set");
                        }
                    } else {
                        $ip++;
                    }
                    break;

                case OpCode::BranchIfTrue:
                    $a = $state->Stack[$state->SP - 1];
                    $state->SP--;
                    if ($a->uint8() !== 0) {
                        if ($i->label !== null) {
                            $ip = $i->label->index;
                        } else {
                            throw new RuntimeException("BranchIfTrue label not set");
                        }
                    } else {
                        $ip++;
                    }
                    break;

                case OpCode::Call:
                    $a = $state->Stack[$state->SP - 1];
                    $state->SP--;
                    $ip++;
                    $state->call($a);
                    $done = true;
                    break;

                case OpCode::CallVirtual:
                    $vtableSlot = $i->x->int32();
                    $thisAddr = $state->Stack[$state->SP - 1]->pointer();
                    $vptr = $state->Stack[$thisAddr]->pointer();
                    $funcPtr = $state->Stack[$vptr + 1 + $vtableSlot]->pointer();
                    $ip++;
                    $state->call($state->exe->Functions[$funcPtr]);
                    $done = true;
                    break;

                case OpCode::Return:
                    $state->return();
                    $done = true;
                    break;

                case OpCode::LoadConstant:
                    $state->Stack[$state->SP] = $i->x;
                    $state->SP++;
                    $ip++;
                    break;

                case OpCode::LoadFramePointer:
                    $state->Stack[$state->SP] = new Value($frame->FP);
                    $state->SP++;
                    $ip++;
                    break;

                case OpCode::LoadPointer:
                    $a = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 1] = clone $state->Stack[$a->pointer()];
                    $ip++;
                    break;

                case OpCode::StorePointer:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$b->pointer()] = $a;
                    $state->SP -= 2;
                    $ip++;
                    break;

                case OpCode::OffsetPointer:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value($a->pointer() + $b->int32());
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::LoadGlobal:
                    $state->Stack[$state->SP] = clone $state->Stack[$i->x->int32()];
                    $state->SP++;
                    $ip++;
                    break;

                case OpCode::StoreGlobal:
                    $state->Stack[$i->x->int32()] = $state->Stack[$state->SP - 1];
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::LoadLocal:
                case OpCode::LoadArg:
                    $state->Stack[$state->SP] = clone $state->Stack[$frame->FP + $i->x->int32()];
                    $state->SP++;
                    $ip++;
                    break;

                case OpCode::StoreLocal:
                case OpCode::StoreArg:
                    $state->Stack[$frame->FP + $i->x->int32()] = $state->Stack[$state->SP - 1];
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::AddInt8:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value(self::truncSByte($a->int32()) + self::truncSByte($b->int32()));
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::AddUInt8:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value(($a->int32() & 0xFF) + ($b->int32() & 0xFF));
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::AddInt16:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value(self::truncInt16($a->int32()) + self::truncInt16($b->int32()));
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::AddUInt16:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value(($a->int32() & 0xFFFF) + ($b->int32() & 0xFFFF));
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::AddUInt64:
                case OpCode::AddInt64:
                case OpCode::AddInt32:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value($a->int32() + $b->int32());
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::AddUInt32:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value(self::truncUInt32($a->int32()) + self::truncUInt32($b->int32()));
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::AddFloat32:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value($a->float32() + $b->float32());
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::AddFloat64:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value($a->float64() + $b->float64());
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::SubtractInt32:
                case OpCode::SubtractUInt32:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value($a->int32() - $b->int32());
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::SubtractFloat64:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value($a->float64() - $b->float64());
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::MultiplyInt32:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value($a->int32() * $b->int32());
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::MultiplyFloat64:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value($a->float64() * $b->float64());
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::DivideInt32:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value((int)($a->int32() / $b->int32()));
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::DivideFloat64:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value($a->float64() / $b->float64());
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::ModuloInt32:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value($a->int32() % $b->int32());
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::EqualToInt32:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value($a->int32() === $b->int32() ? 1 : 0);
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::EqualToFloat64:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value($a->float64() === $b->float64() ? 1 : 0);
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::LessThanInt32:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value($a->int32() < $b->int32() ? 1 : 0);
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::GreaterThanInt32:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value($a->int32() > $b->int32() ? 1 : 0);
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::BinaryAndInt32:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value($a->int32() & $b->int32());
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::BinaryOrInt32:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value($a->int32() | $b->int32());
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::BinaryXorInt32:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value($a->int32() ^ $b->int32());
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::ShiftLeftInt32:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value($a->int32() << $b->int32());
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::ShiftRightInt32:
                    $a = $state->Stack[$state->SP - 2];
                    $b = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 2] = new Value($a->int32() >> $b->int32());
                    $state->SP--;
                    $ip++;
                    break;

                case OpCode::NotInt32:
                    $a = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 1] = new Value($a->int32() === 0 ? 1 : 0);
                    $ip++;
                    break;

                case OpCode::NotFloat64:
                    $a = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 1] = new Value(abs($a->float64()) < PHP_FLOAT_EPSILON ? 1 : 0);
                    $ip++;
                    break;

                case OpCode::BinaryNotInt32:
                    $a = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 1] = new Value(~$a->int32());
                    $ip++;
                    break;

                case OpCode::NegateInt32:
                    $a = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 1] = new Value(-$a->int32());
                    $ip++;
                    break;

                case OpCode::NegateFloat64:
                    $a = $state->Stack[$state->SP - 1];
                    $state->Stack[$state->SP - 1] = new Value(-$a->float64());
                    $ip++;
                    break;

                default:
                    $result = $this->convert($state->Stack[$state->SP - 1], $i->op);
                    $state->Stack[$state->SP - 1] = $result;
                    $ip++;
                    break;
            }

            $state->RemainingTime -= $state->CpuSpeed;
        }

        $frame->IP = $ip;

        if ($ip >= count($this->Instructions)) {
            throw new ExecutionException("Function '{$this->Name}' never returned.");
        }
    }

    private static function truncSByte(int $v): int
    {
        $v = $v & 0xFF;
        return $v > 127 ? $v - 256 : $v;
    }

    private static function truncInt16(int $v): int
    {
        $v = $v & 0xFFFF;
        return $v > 32767 ? $v - 65536 : $v;
    }

    private static function truncUInt32(int $v): int
    {
        return $v & 0xFFFFFFFF;
    }

    private function convert(Value $x, OpCode $op): Value
    {
        /** @noinspection PhpDuplicateMatchArmBodyInspection */
        return match ($op) {
            OpCode::ConvertInt8Int8 => new Value($x->int32()),
            OpCode::ConvertInt8UInt8 => new Value($x->int32() & 0xFF),
            OpCode::ConvertInt8Int16 => new Value($x->int32()),
            OpCode::ConvertInt8UInt16 => new Value($x->int32() & 0xFFFF),
            OpCode::ConvertInt8Int32 => new Value($x->int32()),
            OpCode::ConvertInt8UInt32 => new Value(self::truncUInt32($x->int32())),
            OpCode::ConvertInt8Int64 => new Value($x->int32()),
            OpCode::ConvertInt8UInt64 => new Value($x->int32()),
            OpCode::ConvertInt8Float32 => new Value((float)$x->int32()),
            OpCode::ConvertInt8Float64 => new Value((float)$x->int32()),

            OpCode::ConvertUInt8Int8 => new Value(self::truncSByte($x->int32())),
            OpCode::ConvertUInt8UInt8 => new Value($x->int32() & 0xFF),
            OpCode::ConvertUInt8Int16 => new Value($x->int32()),
            OpCode::ConvertUInt8UInt16 => new Value($x->int32() & 0xFFFF),
            OpCode::ConvertUInt8Int32 => new Value($x->int32()),
            OpCode::ConvertUInt8UInt32 => new Value(self::truncUInt32($x->int32())),
            OpCode::ConvertUInt8Int64 => new Value($x->int32()),
            OpCode::ConvertUInt8UInt64 => new Value($x->int32()),
            OpCode::ConvertUInt8Float32 => new Value((float)$x->int32()),
            OpCode::ConvertUInt8Float64 => new Value((float)$x->int32()),

            OpCode::ConvertInt32Int8 => new Value(self::truncSByte($x->int32())),
            OpCode::ConvertInt32UInt8 => new Value($x->int32() & 0xFF),
            OpCode::ConvertInt32Int16 => new Value(self::truncInt16($x->int32())),
            OpCode::ConvertInt32UInt16 => new Value($x->int32() & 0xFFFF),
            OpCode::ConvertInt32Int32 => new Value($x->int32()),
            OpCode::ConvertInt32UInt32 => new Value(self::truncUInt32($x->int32())),
            OpCode::ConvertInt32Int64 => new Value($x->int32()),
            OpCode::ConvertInt32UInt64 => new Value($x->int32()),
            OpCode::ConvertInt32Float32 => new Value((float)$x->int32()),
            OpCode::ConvertInt32Float64 => new Value((float)$x->int32()),

            OpCode::ConvertUInt32Int8 => new Value(self::truncSByte($x->int32())),
            OpCode::ConvertUInt32UInt8 => new Value($x->int32() & 0xFF),
            OpCode::ConvertUInt32Int16 => new Value(self::truncInt16($x->int32())),
            OpCode::ConvertUInt32UInt16 => new Value($x->int32() & 0xFFFF),
            OpCode::ConvertUInt32Int32 => new Value($x->int32()),
            OpCode::ConvertUInt32UInt32 => new Value(self::truncUInt32($x->int32())),
            OpCode::ConvertUInt32Int64 => new Value($x->int32()),
            OpCode::ConvertUInt32UInt64 => new Value($x->int32()),
            OpCode::ConvertUInt32Float32 => new Value((float)$x->int32()),
            OpCode::ConvertUInt32Float64 => new Value((float)$x->int32()),

            OpCode::ConvertFloat64Int8 => new Value(self::truncSByte((int)$x->float64())),
            OpCode::ConvertFloat64UInt8 => new Value((int)$x->float64() & 0xFF),
            OpCode::ConvertFloat64Int16 => new Value(self::truncInt16((int)$x->float64())),
            OpCode::ConvertFloat64UInt16 => new Value((int)$x->float64() & 0xFFFF),
            OpCode::ConvertFloat64Int32 => new Value((int)$x->float64()),
            OpCode::ConvertFloat64UInt32 => new Value(self::truncUInt32((int)$x->float64())),
            OpCode::ConvertFloat64Int64 => new Value((int)$x->float64()),
            OpCode::ConvertFloat64UInt64 => new Value((int)$x->float64()),
            OpCode::ConvertFloat64Float32 => new Value($x->float64()),
            OpCode::ConvertFloat64Float64 => new Value($x->float64()),

            OpCode::ConvertFloat32Int8 => new Value(self::truncSByte((int)$x->float32())),
            OpCode::ConvertFloat32UInt8 => new Value((int)$x->float32() & 0xFF),
            OpCode::ConvertFloat32Int16 => new Value(self::truncInt16((int)$x->float32())),
            OpCode::ConvertFloat32UInt16 => new Value((int)$x->float32() & 0xFFFF),
            OpCode::ConvertFloat32Int32 => new Value((int)$x->float32()),
            OpCode::ConvertFloat32UInt32 => new Value(self::truncUInt32((int)$x->float32())),
            OpCode::ConvertFloat32Int64 => new Value((int)$x->float32()),
            OpCode::ConvertFloat32UInt64 => new Value((int)$x->float32()),
            OpCode::ConvertFloat32Float32 => new Value($x->float32()),
            OpCode::ConvertFloat32Float64 => new Value($x->float32()),

            default => throw new RuntimeException("Op code '{$op->name}' is not supported"),
        };
    }
}
