<?php declare(strict_types=1);

namespace CLanguage\Compiler;

use CLanguage\Interpreter\BaseFunction;
use CLanguage\Interpreter\OpCode;
use CLanguage\Types\CType;
use CLanguage\Value;
use RuntimeException;

class ResolvedVariable
{
    public VariableScope $Scope;
    public int $Address;
    public CType $VariableType;
    public ?BaseFunction $Function;
    public Value $Constant;

    private function __construct()
    {
        $this->Function = null;
        $this->Constant = new Value(0);
    }

    public static function fromScope(VariableScope $scope, int $address, CType $type): self
    {
        $v = new self;
        $v->Scope = $scope;
        $v->Address = $address;
        $v->VariableType = $type;
        return $v;
    }

    public static function fromFunction(BaseFunction $function, int $address): self
    {
        $v = new self;
        $v->Scope = VariableScope::Function;
        $v->Address = $address;
        $v->VariableType = $function->FunctionType;
        $v->Function = $function;
        return $v;
    }

    public static function fromConstant(Value $constant, CType $type): self
    {
        $v = new self;
        $v->Scope = VariableScope::Constant;
        $v->Address = 0;
        $v->VariableType = $type;
        $v->Constant = $constant;
        return $v;
    }

    /** @noinspection PhpUnusedSwitchBranchInspection */

    public function emitPointer(EmitContext $ec): void
    {
        switch ($this->Scope) {
            case VariableScope::Function:
            case VariableScope::Global:
                $ec->emit(OpCode::LoadConstant, Value::Pointer($this->Address));
                break;
            case VariableScope::Arg:
            case VariableScope::Local:
                $ec->emit(OpCode::LoadConstant, Value::Pointer($this->Address));
                $ec->emit(OpCode::LoadFramePointer);
                $ec->emit(OpCode::OffsetPointer);
                break;
            case VariableScope::Constant:
                $ec->emit(OpCode::LoadConstant, new Value(0));
                break;
            default:
                throw new RuntimeException("Cannot get address of variable scope '" . $this->Scope->name . "'");
        }
    }

    /** @noinspection PhpUnusedSwitchBranchInspection */

    public function emit(EmitContext $ec): void
    {
        switch ($this->Scope) {
            case VariableScope::Function:
                $ec->emit(OpCode::LoadConstant, Value::Pointer($this->Address));
                break;
            case VariableScope::Global:
                $ec->emit(OpCode::LoadGlobal, Value::Pointer($this->Address));
                break;
            case VariableScope::Arg:
                $ec->emit(OpCode::LoadArg, Value::Pointer($this->Address));
                break;
            case VariableScope::Local:
                $ec->emit(OpCode::LoadLocal, Value::Pointer($this->Address));
                break;
            case VariableScope::Constant:
                $ec->emit(OpCode::LoadConstant, new Value(0));
                break;
            default:
                throw new RuntimeException("Cannot get value of variable scope '" . $this->Scope->name . "'");
        }
    }
}
