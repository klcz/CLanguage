# C# to PHP 8.5 Rewrite Rules

## Namespace Mapping
- `CLanguage` → `CLanguage`
- `CLanguage.Types` → `CLanguage\Types`
- `CLanguage.Syntax` → `CLanguage\Syntax`
- `CLanguage.Compiler` → `CLanguage\Compiler`
- `CLanguage.Interpreter` → `CLanguage\Interpreter`
- `CLanguage.Parser` → `CLanguage\Parser`

## Class/Struct/Enum Mapping

### Classes
```csharp
// C#:
public class Foo { }
```
```php
// PHP:
class Foo {}
```

### Abstract classes
```csharp
// C#:
public abstract class Foo { }
```
```php
// PHP:
abstract class Foo {}
```

### Static classes
```csharp
// C#:
public static class Foo { }
```
```php
// PHP: regular class with private constructor + static methods
class Foo {
    private function __construct() {}
}
```

### Structs → PHP classes
C# `struct` becomes a PHP `class` (no value-type semantics).

### Enums
- Pure C# enums (non-[Flags]) → PHP 8.1+ `enum`
- [Flags] enums → PHP class with constants

```csharp
// C#:
public enum Foo { A, B, C }
```
```php
// PHP:
enum Foo: int { case A; case B; case C; }
```

```csharp
// C#:
[Flags] public enum Foo { None = 0, A = 1, B = 2 }
```
```php
// PHP:
class Foo {
    const None = 0;
    const A = 1;
    const B = 2;
}
```

## Properties

### Auto-properties
```csharp
// C#: public int Foo { get; set; }
```
```php
// PHP: public int $Foo { get => $this->Foo; set => $this->Foo = $value; }
//       or simply: public int $Foo;
```

### Read-only auto-properties
```csharp
// C#: public int Foo { get; private set; }
//      public int Foo { get; }
```
```php
// PHP: public readonly int $Foo;
```

### Expression-bodied properties
```csharp
// C#: public int Foo => _bar;
```
```php
// PHP: public readonly int $Foo;
// On construct: $this->Foo = $bar;
// OR with get:
// public function __get($name) { ... }
```

### Properties with backing fields
```csharp
// C#: private int _foo; public int Foo { get => _foo; set => _foo = value; }
```
```php
// PHP: manually implement
private int $_foo;
public function getFoo(): int { return $_foo; }
public function setFoo(int $value): void { $_foo = $value; }
// Or use __get/__set
```

**IMPORTANT**: For this codebase, since most properties just expose fields, use:
- `public int $Foo;` for `{ get; set; }`
- `public readonly int $Foo;` for `{ get; private set; }` or `{ get; }`
- Constructor promotion: `public function __construct(public readonly int $Foo = 0) {}`

## Nullable Types
- `T?` → `?T` or `null|T`
- `object?` → `mixed` or `?object`

## Constructor Pattern
```csharp
// C#:
public class Foo {
    public int X { get; private set; }
    public Foo(int x) { X = x; }
}
```
```php
// PHP (promoted):
class Foo {
    public function __construct(public readonly int $X = 0) {}
}
```

## Method Patterns
- `void` return → PHP `: void`
- `virtual` → all PHP methods are virtual
- `override` → `#[\Override]` attribute (PHP 8.3+)
- `sealed` → `final`
- `abstract` → `abstract`
- `static` → `static`
- `protected` → `protected`
- `private` → `private`
- `internal` → no PHP equivalent, use `public` or omit

## LINQ → PHP
- `.Select(x => f(x))` → `array_map(fn($x) => f($x), $arr)`
- `.Where(x => cond)` → `array_filter($arr, fn($x) => cond)`
- `.FirstOrDefault()` → use loops or custom helper
- `.Any()` → use loops
- `.ToArray()` → `array_values(...)` or just cast
- `.ToList()` → `array_values(...)`
- `.Count()` → `count($arr)`
- `.Skip(n)` → `array_slice($arr, n)`
- `.Take(n)` → `array_slice($arr, 0, n)`
- `.All(x => cond)` → use loops
- `Enumerable.Range(a, b)` → `range(a, a + b - 1)`
- `.Concat()` → `array_merge()`
- `.Cast<T>()` → no-op for PHP
- `.Reverse()` → `array_reverse()`
- `.Distinct()` → `array_unique()`
- `.OrderBy(x => k)` → `usort($arr, fn($a,$b) => $a->k <=> $b->k)`
- `.First(x => cond)` → loop + find
- `.LastOrDefault()` → `end($arr)`

## `yield return` → PHP generator
```csharp
// C#:
IEnumerable<T> GetItems() { yield return x; }
```
```php
// PHP:
function getItems(): \Generator { yield $x; }
```

## Pattern Matching
- `x is Type y` → `$x instanceof Type`
- `x as Type` → `($x instanceof Type) ? $x : null`
- `switch(x) { case A: ... }` → `match($x) { A => ..., default => ... }` or PHP `switch`
- `x switch { A => ..., B => ... }` → `match(true) { $x instanceof A => ..., $x instanceof B => ... }`
- `(x, y) is (A, B)` → `$x instanceof A && $y instanceof B`

## Null Handling
- `x ?? y` → `$x ?? $y`
- `x?.y` → `$x?->y`
- `x ??= y` → `$x ??= $y`
- `x?.y?.z` → `$x?->y?->z`
- `x ?? throw new E()` → `$x ?? throw new E()`

## String Handling
- `$"Hello {name}"` → `"Hello {$name}"`
- `string.Format("{0} {1}", a, b)` → `sprintf("%s %s", $a, $b)` or `"{$a} {$b}"`
- `nameof(Foo)` → `"Foo"` (string literal)
- `String.Join(", ", items)` → `implode(", ", $items)`
- `String.Concat(a, b)` → `$a . $b`
- `sb.ToString()` → `(string)$sb`
- `s.StartsWith(x)` → `str_starts_with($s, $x)`
- `s.EndsWith(x)` → `str_ends_with($s, $x)`
- `s.Contains(x)` → `str_contains($s, $x)`
- `s.Substring(i, l)` → `substr($s, $i, $l)`
- `s.ToLowerInvariant()` → `strtolower($s)`
- `s.Trim()` → `trim($s)`
- `s.TrimEnd()` → `rtrim($s)`
- `s.Split(c)` → `explode($c, $s)`
- `s.Length` → `strlen($s)`
- `s[i]` → `$s[$i]`

## Type Operations
- `typeof(T)` → `T::class`
- `x.GetType()` → `$x::class` or `get_class($x)`
- `x is T` → `$x instanceof T`
- `x as T` → `($x instanceof T) ? $x : null`
- `default(T)` → depends on T: `0`, `""`, `null`, `[]`
- `Convert.ToInt32(x)` → `(int)$x`
- `Convert.ToUInt64(x)` → use explicit cast or custom
- `Convert.ToDouble(x)` → `(float)$x`

## Exceptions
- C# `throw new Exception("msg")` → PHP `throw new \Exception("msg")`
- C# `catch(Exception ex)` → PHP `catch (\Exception $ex)`
- C# `finally { }` → PHP `finally { }`
- C# `throw new ArgumentNullException(nameof(x))` → PHP `throw new \InvalidArgumentException("x is null")`
- C# `throw new NotSupportedException(...)` → PHP `throw new \RuntimeException(...)` or custom exception
- C# `InvalidOperationException` → PHP `\RuntimeException`
- C# `ArgumentOutOfRangeException` → PHP `\OutOfRangeException`

## Attributes
- C# `[Obsolete]` → PHP `#[\Deprecated]` or just skip
- C# `[Flags]` → use class constants
- C# `[StructLayout(LayoutKind.Explicit)]` → not applicable, skip
- C# `[FieldOffset(0)]` → not applicable, skip

## Generic Collections
- `List<T>` → PHP `array`
- `Dictionary<K,V>` → PHP `array`
- `HashSet<T>` → PHP uses array with `array_key_exists` or a set class
- `IEnumerable<T>` → PHP `iterable` or `array`
- `IReadOnlyList<T>` → PHP `array`
- `Collection<T>` → PHP `array`
- `Stack<T>` → `array` with `array_push`/`array_pop`

## Special C# Features → PHP equivalents

### `using` directive → PHP `use` / `use function` / `use const`
```php
use CLanguage\Types\CType;
use CLanguage\Syntax\Block;
use CLanguage\Compiler\EmitContext;
```

### `nameof` → use string literal

### `??` and `?.` → PHP `??` and `?->`

### `is` pattern → PHP `instanceof`

### `as` cast → check with `instanceof`

### `switch` expression → PHP `match`

### `with` expression → not available, manually copy

### `record` → PHP class with readonly properties

### `init` setter → PHP readonly + constructor

### `required` → not available

## File Structure
Each `.cs` file becomes a `.php` file in the same relative directory under `parser_cxx/`.

- `CLanguage/Value.cs` → `parser_cxx/CLanguage/Value.php`
- `CLanguage/Types/CType.cs` → `parser_cxx/CLanguage/Types/CType.php`
- `CLanguage/Syntax/Expression.cs` → `parser_cxx/CLanguage/Syntax/Expression.php`
- etc.

## Import Pattern
```php
// C#:
using System;
using System.Collections.Generic;
using System.Linq;
using CLanguage.Types;
```
```php
// PHP:
use CLanguage\Types\CType;
use CLanguage\Types\CBasicType;
// ... only import what's actually used
```

## Autoloader
Each namespace maps to a directory. The autoloader at `parser_cxx/autoload.php` handles PSR-4 style:
```php
spl_autoload_register(function (string $class) {
    $file = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) require $file;
});
```

## Project-wide conventions
- File header: `<?php` (no closing `?>`)
- Namespace declaration first
- `use` statements second
- Class declaration third
- Use strict typing: `declare(strict_types=1);`
- Keep original comments (translate XML doc comments `///` to `//` or `/** */`)
- Keep original code structure, variable names, method names
- Use `int` not `integer`, `bool` not `boolean`, `float` not `double`
- Method visibility: default to `public` for all public C# members
- Use `#[...]` PHP attributes when translating C# attributes
- Keep line spacing, structure, and organization as close to original as possible

## Specific C# Patterns Translation

### C# `using System;`
→ No PHP equivalent needed; use `use` for specific classes

### C# `namespace CLanguage.Types { ... }`
```php
namespace CLanguage\Types;
```

### C# `readonly struct` / `readonly record struct`
→ Regular PHP class (no value type needed)

### C# `is null` / `is not null`
→ `=== null` / `!== null`

### C# `not` pattern
→ PHP `!` operator

### C# `or` / `and` patterns in switches
→ PHP `||` / `&&`

### C# `..` range operator
→ Not available in PHP

### C# with-expressions
→ Not available, manual copy

### C# index from end `^1`
→ Not available

### C# `=>` for expression-bodied members
→ PHP uses regular method body or arrow functions

### C# `get => expr; set => field = value;`
→ PHP: implement as methods or properties

### C# protected set → PHP: `protected` setter method

### C# init-only setter → PHP: constructor parameter

### C# `new() {}` object initializer
```php
$obj = new Foo();
$obj->X = 1;
$obj->Y = 2;
```

### C# collection initializer
```php
// C#: new List<int> { 1, 2, 3 }
// PHP: [1, 2, 3]
```

### C# anonymous types
→ PHP anonymous class or array

### C# tuples
→ PHP array with named keys

## Testing
Run: `php -l filename.php` to lint check
Use `php -r "require 'autoload.php'; echo 'OK';"` to test

## Example Conversions

### Value.cs (struct with explicit layout)
The C# `Value` struct is a discriminated union. In PHP, use a class with all numeric fields and a `$Kind` discriminator.

### MachineInfo (class with auto-properties)
Use PHP typed properties and constructor parameter promotion.

### Report (class with nested classes)
Keep nested class pattern using PHP inner classes (in separate files or same file).

### CParserImpl (partial class)
PHP only needs one file since partial classes don't exist in PHP.
