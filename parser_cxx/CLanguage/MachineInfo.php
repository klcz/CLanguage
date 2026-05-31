<?php declare(strict_types=1);

namespace CLanguage;

use CLanguage\Compiler\EmitContext;
use CLanguage\Compiler\ResolvedVariable;
use CLanguage\Interpreter\CInterpreter;
use CLanguage\Interpreter\InternalFunction;
use Closure;
use InvalidArgumentException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionObject;
use ReflectionType;

class MachineInfo
{
    /** @--var array<string, string> */
    protected static array $CSharpOperatorNames = [
        'op_Addition' => 'operator+',
        'op_Subtraction' => 'operator-',
        'op_Multiply' => 'operator*',
        'op_Division' => 'operator/',
        'op_Modulus' => 'operator%',
        'op_Equality' => 'operator==',
        'op_Inequality' => 'operator!=',
        'op_LessThan' => 'operator<',
        'op_GreaterThan' => 'operator>',
        'op_LessThanOrEqual' => 'operator<=',
        'op_GreaterThanOrEqual' => 'operator>=',
        'op_BitwiseAnd' => 'operator&',
        'op_BitwiseOr' => 'operator|',
        'op_ExclusiveOr' => 'operator^',
        'op_LeftShift' => 'operator<<',
        'op_RightShift' => 'operator>>',
        'op_UnaryNegation' => 'operator-',
        'op_LogicalNot' => 'operator!',
    ];
    protected static ?MachineInfo $windows32 = null;
    protected static ?MachineInfo $mac64 = null;
    public int $CharSize = 1;
    public int $ShortIntSize = 2;
    public int $IntSize = 4;
    public int $LongIntSize = 4;
    public int $LongLongIntSize = 8;
    public int $FloatSize = 4;
    public int $DoubleSize = 8;
    public int $LongDoubleSize = 8;
    public int $PointerSize = 4;
    public string $HeaderCode = '';
    /** @--var BaseFunction[] */
    public array $InternalFunctions = [];
    /** @--var array<string, string> */
    public array $SystemHeadersCode = [];

    public function __construct()
    {
        $this->InternalFunctions = [];
        $this->HeaderCode = '';
        $this->SystemHeadersCode['math.h'] = SystemHeaders::MathH;
    }

    public static function Windows32(): MachineInfo
    {
        if (self::$windows32 === null) {
            self::$windows32 = self::getWindows32();
        }
        return self::$windows32;
    }

    public static function getWindows32(): self
    {
        $m = new self();
        $m->CharSize = 1;
        $m->ShortIntSize = 2;
        $m->IntSize = 4;
        $m->LongIntSize = 4;
        $m->LongLongIntSize = 8;
        $m->FloatSize = 4;
        $m->DoubleSize = 8;
        $m->LongDoubleSize = 8;
        $m->PointerSize = 4;
        return $m;
    }

    public static function Mac64(): MachineInfo
    {
        if (self::$mac64 === null) {
            self::$mac64 = self::getMac64();
        }
        return self::$mac64;
    }

    public static function getMac64(): self
    {
        $m = new self();
        $m->CharSize = 1;
        $m->ShortIntSize = 2;
        $m->IntSize = 4;
        $m->LongIntSize = 8;
        $m->LongLongIntSize = 8;
        $m->FloatSize = 4;
        $m->DoubleSize = 8;
        $m->LongDoubleSize = 8;
        $m->PointerSize = 8;
        return $m;
    }

    public function getGeneratedHeaderCode(): string
    {
        $w = new CodeWriter();
        $w->writeLine('typedef char int8_t;');
        $w->writeLine('typedef unsigned char uint8_t;');
        if ($this->ShortIntSize === 2) {
            $w->writeLine('typedef short int16_t;');
            $w->writeLine('typedef unsigned short uint16_t;');
        }
        if ($this->IntSize === 4) {
            $w->writeLine('typedef int int32_t;');
            $w->writeLine('typedef unsigned int uint32_t;');
        } elseif ($this->LongIntSize === 4) {
            $w->writeLine('typedef long int32_t;');
            $w->writeLine('typedef unsigned long uint32_t;');
        }
        $w->write($this->HeaderCode);
        return $w->getCode();
    }

    public function addGlobalMethods(object $target): void
    {
        $this->addTargetMethods(null, $target);
    }

    /**
     * Registers methods from an object as C-callable internal functions.
     * Generates C struct header code and creates marshal wrappers.
     */
    protected function addTargetMethods(?string $name, object $target): void
    {
        if ($target === null) {
            throw new InvalidArgumentException('Target must be specified');
        }

        $isRef = $name !== null;

        //
        // Find the methods to marshal
        //
        $refl = new ReflectionObject($target);
        $allMethods = $refl->getMethods(ReflectionMethod::IS_PUBLIC);
        $methods = [];
        foreach ($allMethods as $m) {
            if ($m->isStatic() || $m->isConstructor() || $m->getNumberOfParameters() !== $m->getNumberOfRequiredParameters()) {
                continue;
            }
            $rtype = $m->getReturnType();
            if ($rtype instanceof ReflectionNamedType && $rtype->getName() === 'string') {
                continue;
            }
            $name2 = $m->getName();
            if (in_array($name2, ['GetType', 'Equals', 'GetHashCode', 'ToString'], true)) {
                continue;
            }
            $methods[] = $m;
        }

        //
        // Generate the type code for the reference
        //
        $code = new CodeWriter();
        $typeName = '_' . $name . '_t';
        $code->writeLine('');
        if ($isRef) {
            $code->writeLine('struct ' . $typeName . ' {')->indent();
        }

        /** @--var array<array{method:\ReflectionMethod, returnType:?string, prototype:string}> */
        $wmethods = [];

        foreach ($methods as $m) {
            $mrt = self::clrTypeToCode($m->getReturnType());
            if ($mrt === null) {
                continue;
            }
            $ps = [];
            foreach ($m->getParameters() as $p) {
                $pt = self::clrTypeToCode($p->getType());
                if ($pt === null) {
                    continue 2;
                }
                $ps[] = [$pt, $p->getName()];
            }
            $code->write($mrt);
            $code->write(' ');
            $pcode = new CodeWriter();
            $mname = $m->getName();
            if (str_starts_with($mname, 'set_')) {
                $mname = 'set' . substr($mname, 4);
            } elseif (str_starts_with($mname, 'get_')) {
                $mname = 'get' . substr($mname, 4);
            } elseif (ctype_upper($mname[0])) {
                $mname = lcfirst($mname);
            }
            $pcode->write($mname . '(');
            $head = '';
            foreach ($ps as [$t, $n]) {
                $pcode->write($head);
                $pcode->write($t . ' ' . $n);
                $head = ', ';
            }
            $pcode->write(')');
            $code->write($pcode->getCode());
            $code->writeLine(';');
            $wmethods[] = ['method' => $m, 'returnType' => $mrt, 'prototype' => $pcode->getCode()];
        }

        //
        // Detect static C# operator methods and register them as member operators
        //
        /** @--var array<array{method:\ReflectionMethod, opName:string, returnType:?string, prototype:string}> */
        $operatorMethods = [];
        if ($isRef) {
            $staticMethods = $refl->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC);
            foreach ($staticMethods as $m) {
                $opName = self::$CSharpOperatorNames[$m->getName()] ?? null;
                if ($opName === null) {
                    continue;
                }
                $mrt = self::clrTypeToCode($m->getReturnType());
                if ($mrt === null) {
                    continue;
                }
                $ps = $m->getParameters();
                if (count($ps) < 1) {
                    continue;
                }
                // First parameter must be the declaring type (becomes implicit this)
                $firstType = $ps[0]->getType();
                if ($firstType instanceof ReflectionNamedType && $firstType->getName() !== $refl->getName()) {
                    continue;
                }
                $remainingParams = [];
                $ok = true;
                for ($i = 1; $i < count($ps); $i++) {
                    $pt = self::clrTypeToCode($ps[$i]->getType());
                    if ($pt === null) {
                        $ok = false;
                        break;
                    }
                    $remainingParams[] = [$pt, $ps[$i]->getName() ?: 'arg'];
                }
                if (!$ok) {
                    continue;
                }
                $code->write($mrt);
                $code->write(' ');
                $pcode = new CodeWriter();
                $pcode->write($opName . '(');
                $head = '';
                foreach ($remainingParams as [$t, $n]) {
                    $pcode->write($head);
                    $pcode->write($t . ' ' . $n);
                    $head = ', ';
                }
                $pcode->write(')');
                $code->write($pcode->getCode());
                $code->writeLine(';');
                $operatorMethods[] = ['method' => $m, 'opName' => $opName, 'returnType' => $mrt, 'prototype' => $pcode->getCode()];
            }
        }

        if ($isRef) {
            $code->outdent()->writeLine('};');
            $code->writeLine('struct ' . $typeName . ' ' . $name . ';');
            $this->HeaderCode .= $code->getCode();
        }

        foreach ($wmethods as $wm) {
            $proto = $isRef
                ? $wm['returnType'] . ' ' . $typeName . '::' . $wm['prototype']
                : $wm['returnType'] . ' ' . $wm['prototype'];
            $this->addInternalFunction($proto, $this->marshalMethod($target, $wm['method']));
        }

        foreach ($operatorMethods as $om) {
            $proto = $om['returnType'] . ' ' . $typeName . '::' . $om['prototype'];
            $this->addInternalFunction($proto, $this->marshalStaticOperator($target, $om['method']));
        }
    }

    /**
     * Maps a PHP ReflectionType to a C type string.
     */
    public static function clrTypeToCode(?ReflectionType $type): ?string
    {
        if ($type === null) {
            return null;
        }
        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            return self::typeNameToCode($name);
        }
        return null;
    }

    /**
     * Maps a PHP type name string to a C type code string.
     */
    public static function typeNameToCode(string $name): ?string
    {
        if ($name === 'void') {
            return 'void';
        }
        if ($name === 'int') {
            return 'int';
        }
        if ($name === 'float') {
            return 'float';
        }
        if ($name === 'double') {
            return 'double';
        }
        if ($name === 'string') {
            return 'const char *';
        }
        if ($name === 'bool') {
            return 'int';
        }
        return null;
    }

    public function addInternalFunction(string $prototype, ?callable $action = null): void
    {
        $this->InternalFunctions[] = new InternalFunction($this, $prototype, $action);
    }

    /**
     * Creates a callable that marshals an instance method call from C stack arguments.
     */
    protected function marshalMethod(object $target, ReflectionMethod $method): Closure
    {
        $ps = $method->getParameters();
        $nargs = count($ps);

        return function (CInterpreter $interpreter) use ($target, $method, $ps, $nargs): void {
            $args = [];
            for ($i = 0; $i < $nargs; $i++) {
                $pt = $ps[$i]->getType();
                $varg = $interpreter->readArg($i);
                $args[] = self::unmarshalValue($varg, $pt);
            }
            $result = $method->invoke($target, ...$args);
            $rt = $method->getReturnType();
            if ($rt instanceof ReflectionNamedType && $rt->getName() !== 'void') {
                $interpreter->push(self::marshalValue($result, $rt));
            }
        };
    }

    /**
     * Converts a Value from the C stack to a PHP value.
     */
    protected static function unmarshalValue(Value $value, ?ReflectionType $type): string|int|bool|float
    {
        if ($type === null) {
            return $value->Int32Value;
        }
        if ($type instanceof ReflectionNamedType) {
            return match ($type->getName()) {
                'int' => $value->Int32Value,
                'float' => $value->Float32Value,
                'double' => $value->Float64Value,
                'string' => '',
                'bool' => $value->Int32Value !== 0,
                default => $value->Int32Value,
            };
        }
        return $value->Int32Value;
    }

    /**
     * Converts a PHP value to a Value for the C stack.
     */
    protected static function marshalValue(mixed $value, ReflectionType $type): Value
    {
        if ($type instanceof ReflectionNamedType) {
            return match ($type->getName()) {
                'float' => Value::fromFloat((float)$value),
                'double' => Value::fromDouble((float)$value),
                'bool' => Value::fromBool((bool)$value),
                'string' => Value::fromString((string)$value),
                default => Value::fromInt((int)$value),
            };
        }
        return Value::fromInt((int)$value);
    }

    /**
     * Marshals a static C# operator method as a member operator internal function.
     */
    protected function marshalStaticOperator(object $target, ReflectionMethod $method): Closure
    {
        $ps = $method->getParameters();

        return function (CInterpreter $interpreter) use ($target, $method, $ps): void {
            $args = [];
            for ($i = 0; $i < count($ps); $i++) {
                $pt = $ps[$i]->getType();
                if ($i === 0) {
                    // First C# parameter is the declaring type (maps to implicit this).
                    $args[] = $target;
                } else {
                    $varg = $interpreter->readArg($i - 1);
                    $args[] = self::unmarshalValue($varg, $pt);
                }
            }
            $result = $method->invoke(null, ...$args);
            $rt = $method->getReturnType();
            if ($rt instanceof ReflectionNamedType && $rt->getName() !== 'void') {
                $interpreter->push(self::marshalValue($result, $rt));
            }
        };
    }

    public function addGlobalReference(string $name, object $target): void
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Name must be specified');
        }
        $this->addTargetMethods(trim($name), $target);
    }

    public function getUnresolvedVariable(string $name, ?array $argTypes, EmitContext $context): ?ResolvedVariable
    {
        return null;
    }
}
