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

| Set | Rules | Description |
|-----|-------|-------------|
| `FluentValidationSetList::ALL` | Convert + Group + Traits | Full migration pipeline (excludes Simplify) |
| `FluentValidationSetList::CONVERT` | `ValidationStringToFluentRuleRector`, `ValidationArrayToFluentRuleRector` | Convert pipe-delimited and array-based rules to FluentRule chains |
| `FluentValidationSetList::GROUP` | `GroupWildcardRulesToEachRector` | Group flat wildcard keys into nested `each()`/`children()` calls |
| `FluentValidationSetList::TRAITS` | `AddHasFluentRulesTraitRector`, `AddHasFluentValidationTraitRector` | Add performance traits to FormRequest and Livewire classes |
| `FluentValidationSetList::SIMPLIFY` | `SimplifyFluentRuleRector` | Post-migration cleanup: factory shortcuts, min/max→between, label→factory arg |

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

| Rule | What it does |
|------|-------------|
| `ValidationStringToFluentRuleRector` | Pipe-delimited string rules (`'required\|string\|max:255'`) into fluent chains. Context-aware: works in FormRequest `rules()`, `$request->validate()`, and `Validator::make()`. |
| `ValidationArrayToFluentRuleRector` | Array-based rules (`['required', 'string', Rule::unique(...)]`), including `Rule::` objects, `Password::min()` chains, conditional tuples, closures, and custom rule objects. |
| `GroupWildcardRulesToEachRector` | Flat wildcard/dotted keys into nested `each()`/`children()` calls. Skips Livewire classes. Synthesizes `FluentRule::array()->nullable()` for parent keys without an explicit rule. |
| `AddHasFluentRulesTraitRector` | Adds `use HasFluentRules;` to FormRequest classes that use FluentRule (enables optimized validation). |
| `AddHasFluentValidationTraitRector` | Adds `use HasFluentValidation;` to Livewire components that use FluentRule. |
| `SimplifyFluentRuleRector` | Simplifies FluentRule chains: factory shortcuts (`string()->url()` → `url()`), label as factory arg, min/max → between, redundant type removal. |

## Requirements

- PHP 8.2+
- Rector 2.4+
- [sandermuller/laravel-fluent-validation](https://github.com/sandermuller/laravel-fluent-validation) ^1.0

## License

MIT
