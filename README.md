# Laravel Fluent Validation Rector

Rector rules for migrating Laravel validation to [sandermuller/laravel-fluent-validation](https://github.com/sandermuller/laravel-fluent-validation).

Automates the bulk of a migration from native Laravel validation (pipe-delimited strings, array-based rules, `Rule::` objects) to FluentRule method chains. In real-world testing against a production Laravel codebase, the rules converted **448 files across 3469 tests with zero regressions**.

## Installation

```bash
composer require --dev sandermuller/laravel-fluent-validation-rector
```

## Usage

### Quick start — run all rules

```php
// rector.php
use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Set\FluentValidationSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/app'])
    ->withSets([FluentValidationSetList::ALL]);
```

```bash
vendor/bin/rector process --dry-run   # preview changes
vendor/bin/rector process             # apply them
vendor/bin/pint                       # fix code style after
```

### Available sets

| Set                                 | Rules                                                                                                                                 | Description                                                                   |
|-------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------|
| `FluentValidationSetList::ALL`      | Convert + Group + Traits                                                                                                              | Full migration pipeline (excludes Simplify)                                   |
| `FluentValidationSetList::CONVERT`  | `ValidationStringToFluentRuleRector`, `ValidationArrayToFluentRuleRector`, `ConvertLivewireRuleAttributeRector`                       | Convert pipe-delimited, array-based, and `#[Rule]`-attribute rules to FluentRule chains |
| `FluentValidationSetList::GROUP`    | `GroupWildcardRulesToEachRector`                                                                                                      | Group flat wildcard keys into nested `each()`/`children()` calls              |
| `FluentValidationSetList::TRAITS`   | `AddHasFluentRulesTraitRector`, `AddHasFluentValidationTraitRector`                                                                   | Add performance traits to FormRequest and Livewire classes                    |
| `FluentValidationSetList::SIMPLIFY` | `SimplifyFluentRuleRector`                                                                                                            | Post-migration cleanup: factory shortcuts, min/max→between, label→factory arg |

### Granular usage

```php
// Just conversion, no grouping or traits:
->withSets([FluentValidationSetList::CONVERT])

// Conversion + traits, skip grouping:
->withSets([
    FluentValidationSetList::CONVERT,
    FluentValidationSetList::TRAITS,
])

// Post-migration simplification (run separately after verifying conversion):
->withSets([FluentValidationSetList::SIMPLIFY])
```

### Individual rules

```php
use SanderMuller\FluentValidationRector\Rector\ValidationStringToFluentRuleRector;
use SanderMuller\FluentValidationRector\Rector\ValidationArrayToFluentRuleRector;

return RectorConfig::configure()
    ->withRules([
        ValidationStringToFluentRuleRector::class,
        ValidationArrayToFluentRuleRector::class,
    ]);
```

## Rules reference

| Rule                                 | What it does                                                                                                                                                                       |
|--------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `ValidationStringToFluentRuleRector` | Pipe-delimited string rules (`'required\|string\|max:255'`) into fluent chains. Context-aware: works in FormRequest `rules()`, `$request->validate()`, and `Validator::make()`.    |
| `ValidationArrayToFluentRuleRector`  | Array-based rules (`['required', 'string', Rule::unique(...)]`), including `Rule::` objects, `Password::min()` chains, conditional tuples, closures, and custom rule objects.      |
| `ConvertLivewireRuleAttributeRector` | Livewire `#[Rule('...')]` / `#[Validate('...')]` property attributes into a generated `rules(): array` method. Strips the attribute, preserves the property, maps `as:` to `->label()`. Bails on non-trivial existing `rules()` methods. |
| `GroupWildcardRulesToEachRector`     | Flat wildcard/dotted keys into nested `each()`/`children()` calls. Skips Livewire classes. Synthesizes a bare `FluentRule::array()` parent for dot-notation keys without an explicit parent rule, so nested `required` children still fire when the parent is missing. |
| `AddHasFluentRulesTraitRector`       | Adds `use HasFluentRules;` to FormRequest classes that use FluentRule (enables optimized validation).                                                                              |
| `AddHasFluentValidationTraitRector`  | Adds `use HasFluentValidation;` to Livewire components that use FluentRule.                                                                                                        |
| `SimplifyFluentRuleRector`           | Simplifies FluentRule chains: factory shortcuts (`string()->url()` → `url()`), label as factory arg, min/max → between, redundant type removal.                                    |

## Tips

### Hoist the trait to a shared base class

If your codebase has a shared FormRequest / Livewire base (e.g. `app/Http/Requests/FormRequest.php` extending `Illuminate\Foundation\Http\FormRequest`), declaring `use HasFluentRules;` on that base once lets every subclass inherit it. The rector's ancestor-chain detection (via `ReflectionClass`) will skip re-adding the trait to subclasses automatically — no `base_classes` configuration needed. This was the idiomatic outcome on the codebases where it was tested: one place to declare, quiet subclass runs.

### Long fluent chains on one line

The rector doesn't insert line breaks between method calls — `FluentRule::string()->required()->max(255)` is valid PHP on a single line and keeps diffs minimal. If you prefer multi-line chains, Pint's [`method_chaining_indentation`](https://mlocati.github.io/php-cs-fixer-configurator/#version:3.0|fixer:method_chaining_indentation) fixer (or php-cs-fixer's equivalent) reflows them after Rector runs.

## Known limitations

- **Namespace-less files** — classes declared at the file root (no `namespace`) are silently skipped by `GroupWildcardRulesToEachRector` and the two trait rectors. Laravel projects always use namespaces, so this is not a real-world concern.
- **Rules built outside `rules(): array`** — the rector looks for `rules(): array` methods, `$request->validate([...])` calls, and `Validator::make([...])` calls. Rules built inside `withValidator()` callbacks, Action classes using `Collection::put()->merge()`, or abstract base classes with custom `rulesWithoutPrefix()` conventions are left alone; migrate those manually.
- **Ternary rule strings** — `['nullable', $flag ? 'email' : 'url']` is left alone. The `->when(cond, thenFn, elseFn)` conversion is tractable but not yet implemented.
- **`#[Rule(..., messages: [...])]` / `#[Rule(..., onUpdate: ...)]`** — the `messages:` and `onUpdate:` attribute args have no FluentRule builder equivalents. The rector migrates the rule string + `as:` label and emits a `// TODO:` comment beside the converted chain listing the dropped args verbatim, so you can migrate them manually to Livewire's `messages(): array` hook.

## Requirements

- PHP 8.2+
- Rector 2.4+
- [sandermuller/laravel-fluent-validation](https://github.com/sandermuller/laravel-fluent-validation) ^1.0

## License

MIT
