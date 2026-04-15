# Laravel Fluent Validation Rector

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sandermuller/laravel-fluent-validation-rector.svg?style=flat-square)](https://packagist.org/packages/sandermuller/laravel-fluent-validation-rector)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/sandermuller/laravel-fluent-validation-rector/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/sandermuller/laravel-fluent-validation-rector/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/sandermuller/laravel-fluent-validation-rector/pint-check.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/sandermuller/laravel-fluent-validation-rector/actions?query=workflow%3Apint-check+branch%3Amain)
[![GitHub PHPStan Action Status](https://img.shields.io/github/actions/workflow/status/sandermuller/laravel-fluent-validation-rector/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/sandermuller/laravel-fluent-validation-rector/actions?query=workflow%3Aphpstan+branch%3Amain)

Rector rules for migrating Laravel validation to [sandermuller/laravel-fluent-validation](https://github.com/sandermuller/laravel-fluent-validation). Pipe-delimited strings, array-based rules, `Rule::` objects, and Livewire `#[Rule]` attributes all convert to FluentRule method chains.

```php
// Before
public function rules(): array
{
    return [
        'email' => 'required|email|max:255',
        'tags'  => ['nullable', 'array'],
        'tags.*' => 'string|max:50',
    ];
}

// After
public function rules(): array
{
    return [
        'email' => FluentRule::email()->required()->max(255),
        'tags'  => FluentRule::array()->nullable()->each(
            FluentRule::string()->max(50),
        ),
    ];
}
```

> Tested on a production codebase: **448 files converted, 3469 tests still passing**.

## Contents

**Getting started**
- [Installation](#installation)
- [Quick start](#quick-start)
- [Rules shipped](#rules-shipped) — what gets converted and what stays

**Usage**
- [Sets](#sets) — mix and match subsets of the migration pipeline
- [Individual rules](#individual-rules) — when you need one specific conversion

**Output**
- [Pint integration](#pint-integration) — what the rector emits and how Pint finishes the job
- [Diagnostics](#diagnostics) — skip log, cache interactions, manual spot-checks

**Reference**
- [Known limitations](#known-limitations)
- [License](#license)

## Installation

```bash
composer require --dev sandermuller/laravel-fluent-validation-rector
```

Requires PHP 8.2+, Rector 2.4+, and [sandermuller/laravel-fluent-validation](https://github.com/sandermuller/laravel-fluent-validation) ^1.7.1. The main-package constraint is important: 1.7.1 introduced the `HasFluentValidation::getRules()` override that flattens nested `each()`/`children()` back to wildcard keys at runtime, which the grouping rector relies on for Livewire safety. Older main-package versions would break at runtime on Livewire components with grouped rules.

## Quick start

```php
// rector.php
use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Set\FluentValidationSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/app'])
    ->withSets([FluentValidationSetList::ALL]);
```

```bash
vendor/bin/rector process --dry-run   # preview
vendor/bin/rector process             # apply
vendor/bin/pint                       # format
```

The `ALL` set runs the full migration pipeline (converters + grouping + trait insertion) on every file under `app/`. Most codebases can stop here — the output is ready to commit after Pint runs. Cherry-pick subsets via [Sets](#sets) or [Individual rules](#individual-rules) if you want finer control.

## Rules shipped

Grouped by the set that includes them. `FluentValidationSetList::ALL` runs everything in Converters + Grouping + Traits; `SIMPLIFY` is a separate post-migration cleanup set you opt into after verifying the initial conversion.

### Converters (set `CONVERT`)

- **`ValidationStringToFluentRuleRector`** converts pipe-delimited rule strings (`'required|string|max:255'`) to fluent chains. Works in FormRequest `rules()`, `$request->validate()`, and `Validator::make()`.
- **`ValidationArrayToFluentRuleRector`** converts array-based rules (`['required', 'string', Rule::unique(...)]`), including `Rule::` objects, `Password::min()` chains, conditional tuples, closures, and custom rule objects.
- **`ConvertLivewireRuleAttributeRector`** strips Livewire `#[Rule('...')]` / `#[Validate('...')]` property attributes and generates a `rules(): array` method. Preserves the property, maps `as:` to `->label()`, and bails safely on a handful of edge cases (hybrid `$this->validate([...])` calls, final parent `rules()` methods, unsupported attribute args). All bails are logged to the skip file — see [Diagnostics](#diagnostics).

### Grouping (set `GROUP`)

- **`GroupWildcardRulesToEachRector`** folds flat wildcard and dotted keys into nested `each()` / `children()` calls. Applies to FormRequests and Livewire components alike — on Livewire, the `HasFluentValidation` trait's `getRules()` override flattens the nested form back to wildcard keys at runtime. When a dot-notation key has no explicit parent rule, the rector synthesizes a bare `FluentRule::array()` parent so nested `required` children still fire.

### Traits (set `TRAITS`)

- **`AddHasFluentRulesTraitRector`** adds `use HasFluentRules;` to FormRequests that use FluentRule.
- **`AddHasFluentValidationTraitRector`** adds `use HasFluentValidation;` to Livewire components that use FluentRule.

> [!TIP]
> If your codebase has a shared FormRequest or Livewire base, declare `use HasFluentRules;` (or `HasFluentValidation`) on the base once and every subclass inherits it. The trait rectors walk the ancestor chain via `ReflectionClass` and won't re-add the trait on subclasses, so no `base_classes` configuration is needed.

### Post-migration (set `SIMPLIFY`)

- **`SimplifyFluentRuleRector`** cleans up FluentRule chains after migration: factory shortcuts (`string()->url()` → `url()`), `->label()` folded into the factory arg, `min()` + `max()` → `between()`, redundant type removal. Run this as a separate pass after you've verified the initial conversion — it's not included in `ALL` by default.

## Sets

| Set        | Includes                                                 |
|------------|----------------------------------------------------------|
| `ALL`      | Convert + Group + Traits (full migration pipeline)       |
| `CONVERT`  | String, array, and `#[Rule]` attribute converters        |
| `GROUP`    | Wildcard/dotted-key grouping into `each()`               |
| `TRAITS`   | Performance trait insertion for FormRequest and Livewire |
| `SIMPLIFY` | Post-migration chain cleanup                             |

```php
// Just conversion, no grouping or traits
->withSets([FluentValidationSetList::CONVERT])

// Conversion + traits, skip grouping
->withSets([
    FluentValidationSetList::CONVERT,
    FluentValidationSetList::TRAITS,
])

// Post-migration cleanup (run separately after verifying)
->withSets([FluentValidationSetList::SIMPLIFY])
```

## Individual rules

When you need a single conversion — e.g. a one-off migration of a specific codebase path, or running just the array-based converter on a subset of files — import and register the rule class directly:

```php
use SanderMuller\FluentValidationRector\Rector\ValidationStringToFluentRuleRector;
use SanderMuller\FluentValidationRector\Rector\ValidationArrayToFluentRuleRector;

return RectorConfig::configure()
    ->withRules([
        ValidationStringToFluentRuleRector::class,
        ValidationArrayToFluentRuleRector::class,
    ]);
```

## Pint integration

The rector's output is valid PHP but has three cosmetic seams that a formatter resolves automatically:

1. Imports are inserted at prepend position (not alphabetical). Pint's `ordered_imports` fixer resolves.
2. Unused imports may be left in place (e.g. a `Livewire\Attributes\Rule` import after the attribute is stripped). Pint's `no_unused_imports` fixer resolves.
3. Generated `@return` docblocks emit `Illuminate\Contracts\Validation\ValidationRule` as a fully-qualified reference. Pint's `fully_qualified_strict_types` fixer hoists it to a `use` statement + short-name reference.

All three fixers are in Pint's default Laravel preset; most consumers have them without explicit configuration. php-cs-fixer has equivalents. Without a formatter you'll see rougher-than-example output, but the code is still valid PHP.

> [!TIP]
> For the cleanest pre-Pint output, enable `->withImportNames()->withRemovingUnusedImports()` in your `rector.php`:
>
> ```php
> return RectorConfig::configure()
>     ->withImportNames()
>     ->withRemovingUnusedImports()
>     ->withSets([FluentValidationSetList::ALL]);
> ```

> [!NOTE]
> The rector doesn't insert line breaks between method calls — `FluentRule::string()->required()->max(255)` is valid PHP on a single line and keeps diffs minimal. If you prefer multi-line chains, Pint's [`method_chaining_indentation`](https://mlocati.github.io/php-cs-fixer-configurator/#version:3.0|fixer:method_chaining_indentation) fixer reflows them after Rector runs.

## Diagnostics

If a file you expected to convert wasn't touched, check `.rector-fluent-validation-skips.log` in your project root. Every bail-capable rule writes a one-line reason there: unsupported attribute args, a hybrid `$this->validate([...])` call, a trait already present on an ancestor class, and so on.

The log is a file sink because Rector's `withParallel(...)` executor doesn't forward worker STDERR to the parent — a diagnostic-line-per-worker-fwrite would vanish on parallel runs (Rector's default). The file sink survives worker death and is inspectable post-run from the project root. If a Rector rule you're writing yourself needs similar diagnostics, the same gotcha applies: `withParallel()` + STDERR means silent data loss.

At the end of each Rector invocation, a single STDOUT line surfaces the log's existence:

```
[fluent-validation] 42 skip entries written to .rector-fluent-validation-skips.log — see for details
```

> [!TIP]
> Rector caches per-file results. Files that hit a bail produce no transformation, so the skip entry is written once and the rule is not re-invoked on cached runs. To force every file to be revisited and every bail to be re-logged, run `vendor/bin/rector process --clear-cache` (or delete `.cache/rector*`).

> [!NOTE]
> `ConvertLivewireRuleAttributeRector` verifies the generated `rules(): array` is syntactically correct, but it can't prove the converted rule is behaviorally equivalent to the source attribute. If a converted Livewire component has no feature test covering validation, review the diff by hand and watch for dropped `message:` / `messages:` / `onUpdate:` args (logged to the skip file) that need manual migration to Livewire's `messages(): array` hook.

## Known limitations

- **Namespace-less files.** Classes at the file root without a `namespace` are silently skipped by the grouping and trait rectors. Laravel projects always use namespaces, so this rarely comes up in practice.
- **Rules built outside `rules(): array`.** The rector looks for `rules(): array`, `$request->validate([...])`, and `Validator::make([...])`. Rules built inside `withValidator()` callbacks, custom `rulesWithoutPrefix()` conventions, or Action-class `Collection::put()->merge()` chains are left alone.
- **Ternary rule strings.** `['nullable', $flag ? 'email' : 'url']` is left alone. A `->when(cond, thenFn, elseFn)` conversion is technically tractable but declined — three separate codebase audits turned up near-zero usage in the wild (single-digit across a 100+ FormRequest corpus), and the closure-based fluent form loses the terseness users reach for ternaries to preserve. Use `Rule::when(...)` or branch the rules array outside the ternary instead.
- **`#[Rule(..., messages: [...])]` / `onUpdate:`.** These attribute args have no FluentRule builder equivalent. The rule string and `as:` label are migrated; the dropped args are written to the skip log so you can migrate them to Livewire's `messages(): array` hook manually.

## License

MIT
