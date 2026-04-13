# Laravel Fluent Validation Rector

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

Tested on a production codebase: 448 files converted, 3469 tests still passing.

## Installation

```bash
composer require --dev sandermuller/laravel-fluent-validation-rector
```

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

## Usage

### Sets

| Set        | Includes                                           |
|------------|----------------------------------------------------|
| `ALL`      | Convert + Group + Traits (full migration pipeline) |
| `CONVERT`  | String, array, and `#[Rule]` attribute converters  |
| `GROUP`    | Wildcard/dotted-key grouping into `each()`         |
| `TRAITS`   | Performance trait insertion for FormRequest and Livewire |
| `SIMPLIFY` | Post-migration chain cleanup                       |

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

## Rules

- **`ValidationStringToFluentRuleRector`** converts pipe-delimited rule strings (`'required|string|max:255'`) to fluent chains. Works in FormRequest `rules()`, `$request->validate()`, and `Validator::make()`.
- **`ValidationArrayToFluentRuleRector`** converts array-based rules (`['required', 'string', Rule::unique(...)]`), including `Rule::` objects, `Password::min()` chains, conditional tuples, closures, and custom rule objects.
- **`ConvertLivewireRuleAttributeRector`** strips Livewire `#[Rule('...')]` / `#[Validate('...')]` property attributes and generates a `rules(): array` method. Preserves the property, maps `as:` to `->label()`, and bails on non-trivial existing `rules()` methods.
- **`GroupWildcardRulesToEachRector`** folds flat wildcard and dotted keys into nested `each()` / `children()` calls. Skips Livewire classes. When a dot-notation key has no explicit parent rule, the rector synthesizes a bare `FluentRule::array()` parent so nested `required` children still fire.
- **`AddHasFluentRulesTraitRector`** adds `use HasFluentRules;` to FormRequests that use FluentRule.
- **`AddHasFluentValidationTraitRector`** adds `use HasFluentValidation;` to Livewire components that use FluentRule.
- **`SimplifyFluentRuleRector`** cleans up FluentRule chains after migration: factory shortcuts (`string()->url()` → `url()`), `->label()` folded into the factory arg, `min()` + `max()` → `between()`, redundant type removal.

## Diagnostics

If a file you expected to convert wasn't touched, check `.rector-fluent-validation-skips.log` in your project root. Every bail-capable rule writes a one-line reason there: unsupported attribute args, a hybrid `$this->validate([...])` call, a trait already present on an ancestor class, and so on.

### Pass `--clear-cache` when investigating skips

Rector caches per-file results. Files that hit a bail produce no transformation, so the skip entry is written once and the rule is not re-invoked on cached runs. To force every file to be revisited and every bail to be re-logged, run `vendor/bin/rector process --clear-cache` (or delete `.cache/rector*`).

### Spot-check `#[Rule]` conversions without feature tests

`ConvertLivewireRuleAttributeRector` verifies the generated `rules(): array` is syntactically correct. It cannot prove the converted rule is behaviorally equivalent to the source attribute, and PHPStan and the test suite validate structure, not runtime validation outcomes. If a converted Livewire component has no feature test covering validation, review the diff by hand and watch for dropped `message:` / `messages:` / `onUpdate:` args (logged to the skip file) that you need to migrate to Livewire's `messages(): array` hook manually.

## Tips

### Hoist the trait to a shared base class

If your codebase has a shared FormRequest or Livewire base, declare `use HasFluentRules;` (or `HasFluentValidation`) on the base once and every subclass inherits it. The trait rectors walk the ancestor chain via `ReflectionClass` and won't re-add the trait on subclasses, so no `base_classes` configuration is needed.

### Long fluent chains on one line

The rector does not insert line breaks between method calls. `FluentRule::string()->required()->max(255)` is valid PHP on a single line and keeps diffs minimal. If you prefer multi-line chains, Pint's [`method_chaining_indentation`](https://mlocati.github.io/php-cs-fixer-configurator/#version:3.0|fixer:method_chaining_indentation) fixer reflows them after Rector runs.

## Known limitations

- **Namespace-less files.** Classes at the file root without a `namespace` are silently skipped by the grouping and trait rectors. Laravel projects always use namespaces, so this rarely comes up in practice.
- **Rules built outside `rules(): array`.** The rector looks for `rules(): array`, `$request->validate([...])`, and `Validator::make([...])`. Rules built inside `withValidator()` callbacks, custom `rulesWithoutPrefix()` conventions, or Action-class `Collection::put()->merge()` chains are left alone.
- **Ternary rule strings.** `['nullable', $flag ? 'email' : 'url']` is left alone. A `->when(cond, thenFn, elseFn)` conversion is possible but not implemented yet.
- **`#[Rule(..., messages: [...])]` / `onUpdate:`.** These attribute args have no FluentRule builder equivalent. The rule string and `as:` label are migrated; the dropped args are written to the skip log so you can migrate them to Livewire's `messages(): array` hook manually.

## Requirements

- PHP 8.2+
- Rector 2.4+
- [sandermuller/laravel-fluent-validation](https://github.com/sandermuller/laravel-fluent-validation) ^1.0

## License

MIT
