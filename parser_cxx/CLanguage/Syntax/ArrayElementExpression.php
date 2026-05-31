<?php declare(strict_types=1);

namespace CLanguage\Syntax;

use CLanguage\Compiler\EmitContext;
use CLanguage\Interpreter\OpCode;
use CLanguage\Types\CArrayType;
use CLanguage\Types\CPointerType;
use CLanguage\Types\CStructType;
use CLanguage\Types\CType;
use CLanguage\Value;
use Override;

class ArrayElementExpression extends Expression
{
    public Expression $Array;
    public Expression $ElementIndex;

    public function __construct(Expression $array, Expression $elementIndex)
    {
        $this->Array = $array;
        $this->ElementIndex = $elementIndex;
    }

    #[Override]
    public function __toString(): string
    {
        return "{$this->Array}[{$this->ElementIndex}]";
    }

    /** @noinspection PhpStatementHasEmptyBodyInspection */

    #[Override]
    public function canEmitPointer(): bool
    {
        return $this->Array->canEmitPointer();
    }

    #[Override]
    protected function doEmit(EmitContext $ec): void
    {
        $t = $this->Array->getEvaluatedCType($ec);
        if ($t instanceof CStructType) {
            $indexType = $this->ElementIndex->getEvaluatedCType($ec);
            if (self::tryEmitBinaryOperatorCall($ec, $t, $indexType, $this->Array, $this->ElementIndex, 'operator[]'))
                return;
            $ec->Report->error(601, error: 'Left hand side of [ must be an array or pointer');
            return;
        }
        $this->doEmitPointer($ec);
        /** @noinspection PhpStatementHasEmptyBodyInspection */
        if ($this->getEvaluatedCType($ec) instanceof CArrayType) {
            // Element is itself an array: return pointer (array-to-pointer decay)
        } else {
            $ec->emit(OpCode::LoadPointer);
        }
    }

    #[Override]
    public function getEvaluatedCType(EmitContext $ec): CType
    {
        $t = $this->Array->getEvaluatedCType($ec);
        if ($t instanceof CArrayType) {
            return $t->ElementType;
        } elseif ($t instanceof CPointerType) {
            return $t->InnerType;
        } elseif ($t instanceof CStructType) {
            $indexType = $this->ElementIndex->getEvaluatedCType($ec);
            $ft = self::tryResolveBinaryOperatorType($ec, $t, $indexType, 'operator[]');
            if ($ft !== null) {
                return $ft->ReturnType;
            }
            $ec->Report->error(601, error: 'Left hand side of [ must be an array or pointer');
            return CType::voidType();
        } else {
            $ec->Report->error(601, error: 'Left hand side of [ must be an array or pointer');
            return CType::voidType();
        }
    }

    #[Override]
    protected function doEmitPointer(EmitContext $ec): void
    {
        $this->Array->emit($ec);
        $this->ElementIndex->emit($ec);
        $ec->emit(OpCode::LoadConstant, Value::fromInt($this->getEvaluatedCType($ec)->getNumValues()));
        $ec->emit(OpCode::MultiplyInt32);
        $ec->emit(OpCode::OffsetPointer);
    }
}
