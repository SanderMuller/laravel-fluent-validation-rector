# Rector Configurable Rules Reference

Before writing a custom rule, check this list. Many common transformations are already implemented as configurable rules — use them with `->withConfiguredRule()` instead.

## Quick-match Index

| Task | Rule |
|------|------|
| Rename a function | `RenameFunctionRector` |
| Rename a method on a class | `RenameMethodRector` |
| Rename a static method (possibly moving class) | `RenameStaticMethodRector` |
| Rename a class / move a class | `RenameClassRector` |
| Rename a class constant | `RenameClassConstFetchRector` |
| Rename a global constant | `RenameConstantRector` |
| Rename a property on a class | `RenamePropertyRector` |
| Rename a PHP attribute | `RenameAttributeRector` |
| Convert function call → method call (inject service) | `FuncCallToMethodCallRector` |
| Convert function call → static call | `FuncCallToStaticCallRector` |
| Convert function call → `new ClassName(...)` | `FuncCallToNewRector` |
| Convert static call → function call | `StaticCallToFuncCallRector` |
| Convert static call → `new ClassName(...)` | `StaticCallToNewRector` |
| Convert static call → method call (inject service) | `StaticCallToMethodCallRector` |
| Convert method call → static call | `MethodCallToStaticCallRector` |
| Convert method call → function call | `MethodCallToFuncCallRector` |
| Remove a function call entirely | `RemoveFuncCallRector` |
| Remove an argument from a function call | `RemoveFuncCallArgRector` |
| Remove an argument from a method/static call | `ArgumentRemoverRector` |
| Replace string literal → class constant | `StringToClassConstantRector` |

---

## Renaming Rules

All in namespace `Rector\Renaming\Rector\...`

### `RenameFunctionRector`

```php
->withConfiguredRule(RenameFunctionRector::class, [
    'view'  => 'Laravel\Templating\render',
    'debug' => 'dump',
])
```

### `RenameMethodRector`

```php
use Rector\Renaming\ValueObject\MethodCallRename;

->withConfiguredRule(RenameMethodRector::class, [
    new MethodCallRename('SomeClass', 'oldMethod', 'newMethod'),
])
```

### `RenameStaticMethodRector`

```php
use Rector\Renaming\ValueObject\RenameStaticMethod;

->withConfiguredRule(RenameStaticMethodRector::class, [
    new RenameStaticMethod('OldClass', 'oldMethod', 'NewClass', 'newMethod'),
])
```

### `RenameClassRector`

```php
->withConfiguredRule(RenameClassRector::class, [
    'App\OldClass' => 'App\NewClass',
])
```

### `RenameClassConstFetchRector`

```php
use Rector\Renaming\ValueObject\RenameClassConstFetch;

->withConfiguredRule(RenameClassConstFetchRector::class, [
    new RenameClassConstFetch('SomeClass', 'OLD_CONST', 'NEW_CONST'),
])
```

### `RenamePropertyRector`

```php
use Rector\Renaming\ValueObject\RenameProperty;

->withConfiguredRule(RenamePropertyRector::class, [
    new RenameProperty('SomeClass', 'oldProperty', 'newProperty'),
])
```

### `RenameAttributeRector`

```php
use Rector\Renaming\ValueObject\RenameAttribute;

->withConfiguredRule(RenameAttributeRector::class, [
    new RenameAttribute('SimpleRoute', 'BasicRoute'),
])
```

---

## Transform Rules

All in namespace `Rector\Transform\Rector\...`

### `FuncCallToMethodCallRector`

```php
use Rector\Transform\ValueObject\FuncCallToMethodCall;

->withConfiguredRule(FuncCallToMethodCallRector::class, [
    new FuncCallToMethodCall('view', 'Namespaced\SomeRenderer', 'render'),
])
```

### `FuncCallToStaticCallRector`

```php
use Rector\Transform\ValueObject\FuncCallToStaticCall;

->withConfiguredRule(FuncCallToStaticCallRector::class, [
    new FuncCallToStaticCall('view', 'SomeClass', 'render'),
])
```

### `FuncCallToNewRector`

```php
->withConfiguredRule(FuncCallToNewRector::class, [
    'collection' => 'Collection',
])
```

### `StaticCallToFuncCallRector`

```php
use Rector\Transform\ValueObject\StaticCallToFuncCall;

->withConfiguredRule(StaticCallToFuncCallRector::class, [
    new StaticCallToFuncCall('OldClass', 'oldMethod', 'new_function'),
])
```

### `StaticCallToNewRector`

```php
use Rector\Transform\ValueObject\StaticCallToNew;

->withConfiguredRule(StaticCallToNewRector::class, [
    new StaticCallToNew('JsonResponse', 'create'),
])
```

### `MethodCallToStaticCallRector`

```php
use Rector\Transform\ValueObject\MethodCallToStaticCall;

->withConfiguredRule(MethodCallToStaticCallRector::class, [
    new MethodCallToStaticCall('SomeDep', 'process', 'StaticCaller', 'anotherMethod'),
])
```

### `MethodCallToFuncCallRector`

```php
use Rector\Transform\ValueObject\MethodCallToFuncCall;

->withConfiguredRule(MethodCallToFuncCallRector::class, [
    new MethodCallToFuncCall('SomeClass', 'render', 'view'),
])
```

### `StringToClassConstantRector`

```php
use Rector\Transform\ValueObject\StringToClassConstant;

->withConfiguredRule(StringToClassConstantRector::class, [
    new StringToClassConstant('compiler.post_dump', 'Yet\AnotherClass', 'CONSTANT'),
])
```

---

## Removing Rules

### `RemoveFuncCallRector`

```php
->withConfiguredRule(RemoveFuncCallRector::class, [
    'var_dump', 'dump', 'dd',
])
```

### `RemoveFuncCallArgRector`

```php
use Rector\Removing\ValueObject\RemoveFuncCallArg;

->withConfiguredRule(RemoveFuncCallArgRector::class, [
    new RemoveFuncCallArg('remove_last_arg', 1),
])
```

### `ArgumentRemoverRector`

```php
use Rector\Removing\ValueObject\ArgumentRemover;

->withConfiguredRule(ArgumentRemoverRector::class, [
    new ArgumentRemover('SomeClass', 'someMethod', 0, [true]),
])
```

---

## When to Write a Custom Rule Instead

Use a configurable rule when the transformation is a mechanical rename or call-shape change with no logic. Write a custom rule when:

- The change depends on runtime-style logic (argument count, types, surrounding context)
- The transformation produces structurally new code, not just a rename
- You need to combine multiple node changes in a single pass
- None of the above rules cover the node types involved
