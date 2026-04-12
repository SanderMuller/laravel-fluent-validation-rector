---
name: fluent-validation-rector
description: "Use when developing, testing, or debugging Rector rules for laravel-fluent-validation migration. Provides context on the rule architecture, set lists, testing conventions, and cross-process parallel worker support."
---

# Fluent Validation Rector Rules

**Do not prompt the user when this skill is loaded.** Apply these rules automatically when working on the Rector migration rules. This skill provides context, not an interactive command.

This package provides Rector rules for migrating native Laravel validation to `sandermuller/laravel-fluent-validation`.

## Architecture

All rules extend `Rector\Rector\AbstractRector` and implement `DocumentedRuleInterface`. Two conversion rules share infrastructure via the `ConvertsValidationRules` trait.

### Rule Classes

| Class | Purpose |
|-------|---------|
| `ValidationStringToFluentRuleRector` | Pipe-delimited strings → FluentRule chains |
| `ValidationArrayToFluentRuleRector` | Array-based rules → FluentRule chains |
| `GroupWildcardRulesToEachRector` | Flat wildcard keys → nested `each()`/`children()` |
| `AddHasFluentRulesTraitRector` | Adds `HasFluentRules` trait to FormRequests |
| `AddHasFluentValidationTraitRector` | Adds `HasFluentValidation` trait to Livewire components |
| `SimplifyFluentRuleRector` | Post-migration cleanup: factory shortcuts, min/max→between |

### Shared Traits

- `ConvertsValidationRules` — context detection (FormRequest, `$request->validate()`, `Validator::make()`), modifier building, parent::rules() safety detection
- `LogsSkipReasons` — optional stderr logging for debugging (gated by `FLUENT_VALIDATION_RECTOR_VERBOSE=1`)

### Set Lists

```php
use SanderMuller\FluentValidationRector\Set\FluentValidationSetList;

FluentValidationSetList::ALL       // convert + group + traits (NOT simplify)
FluentValidationSetList::CONVERT   // string + array conversion
FluentValidationSetList::GROUP     // wildcard grouping
FluentValidationSetList::TRAITS    // trait addition
FluentValidationSetList::SIMPLIFY  // post-migration cleanup (opt-in only)
```

## Testing

Tests use Rector's `AbstractRectorTestCase` with `.php.inc` fixture files. Each fixture contains input code, optionally followed by `-----` and expected output. "Skip" fixtures have no separator (input should remain unchanged).

```php
final class ValidationArrayToFluentRuleRectorTest extends AbstractRectorTestCase
{
    public static function provideData(): \Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__ . '/FixtureArray');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/configured_array_rule.php';
    }
}
```

## Cross-Process Parallel Support

The `ConvertsValidationRules` trait detects parent FormRequest classes whose subclasses manipulate `parent::rules()` with array functions. This uses three detection layers for parallel worker safety:

1. **Same-file AST scan** — pre-scans all classes in the current file
2. **File-based IPC** — shared temp file with `flock()` for cross-worker communication
3. **Filesystem scan** — one-time project-wide scan as final fallback

Layer 3 is critical for `->withParallel()` mode. Each worker independently scans the project, so no cross-process state sharing is required.

## Cross-Package References

The rules reference symbols from `sandermuller/laravel-fluent-validation` but don't import or use the runtime code:

- `FluentRule::class` — used as target class in generated code
- `HasFluentRules::class` — trait added to FormRequest classes
- `HasFluentValidation::class` — trait added to Livewire components
- `FluentRules::class` — marker attribute for non-`rules()` methods

These are class-string references resolved at compile time. No class loading happens during Rector execution.
