# Laravel Fluent Validation Rector

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sandermuller/laravel-fluent-validation-rector.svg?style=flat-square)](https://packagist.org/packages/sandermuller/laravel-fluent-validation-rector)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/sandermuller/laravel-fluent-validation-rector/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/sandermuller/laravel-fluent-validation-rector/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub PHPStan Action Status](https://img.shields.io/github/actions/workflow/status/sandermuller/laravel-fluent-validation-rector/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/sandermuller/laravel-fluent-validation-rector/actions?query=workflow%3Aphpstan+branch%3Amain)
[![License](https://img.shields.io/github/license/sandermuller/laravel-fluent-validation-rector.svg?style=flat-square)](LICENSE)

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
- [Versioning policy](#versioning-policy) — what's covered by SemVer
- [Compatibility](#compatibility) — supported runtime + integration versions
- [Rules shipped](#rules-shipped) — what gets converted and what stays

**Usage**
- [Sets](#sets) — mix and match subsets of the migration pipeline
- [Individual rules](#individual-rules) — when you need one specific conversion

**Operation**
- [Formatter integration](#formatter-integration) — what the rector emits and how Pint / PHP-CS-Fixer finish the job
- [Diagnostics](#diagnostics) — skip log + verbosity tiers
- [Parity](#parity) — runtime-equivalence harness for semantics-changing rectors

**Reference**
- [Public API](PUBLIC_API.md) — frozen surface (symbols, wire keys, behavior)
- [Known limitations](#known-limitations)
- [License](#license)

## Installation

```bash
composer require --dev sandermuller/laravel-fluent-validation-rector
```

**Requirements**: PHP 8.2+, Rector 2.4+, [`sandermuller/laravel-fluent-validation`](https://github.com/sandermuller/laravel-fluent-validation) ^1.20.

If you're on an older fluent-validation:

| fluent-validation | Pin rector to    |
|-------------------|------------------|
| 1.17 – 1.19       | `^0.8`           |
| 1.20+             | `^0.13` (latest) |

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

The `ALL` set runs the full migration pipeline (converters + grouping + trait insertion) on every file under `app/`. For most codebases that's enough; the output is ready to commit after Pint runs. If you want finer control, pick subsets via [Sets](#sets) or register [individual rules](#individual-rules).

## Versioning policy

This package follows [SemVer 2.0](https://semver.org).

**MAJOR (X.y.z)** — breaking changes to the public API:
- Rename / remove a rector class
- Rename / remove a `FluentValidationSetList` constant
- Rename / remove / change-string-value of a rector configuration constant
- Change the skip-log line prefix or the per-run header structure
- Rename / remove the verbose-mode env var or its accepted values
- Change either documented skip-log path

**MINOR (x.Y.z)** — additive, non-breaking:
- New rector class
- New configuration constant on an existing rector
- New skip-log diagnostic emit site
- New configuration option value (additive enum extension)
- Wider match conditions on an existing rector (transformations now apply to shapes that previously fell through)

**PATCH (x.y.Z)** — bug fixes:
- Correctness fixes to existing transformations
- Diagnostic message text changes (the *content*, not the line format)
- Internal refactors with no observable effect on output
- Documentation updates

The package's "public API" is the explicit list in [PUBLIC_API.md](PUBLIC_API.md). Symbols not on that list are `@internal` and may change in any release without a MAJOR bump.

PATCH-level rector changes must not introduce parity violations against existing fixtures (see [Parity](#parity) below). Behavioral drift in semantics-changing rectors is a MINOR or MAJOR bump depending on whether existing consumers can opt in/out.

## Compatibility

### Tested matrix (CI)

Every push runs the full cross-product below under both `prefer-lowest` and `prefer-stable` Composer resolutions:

| OS              | PHP      | Laravel    |
|-----------------|----------|------------|
| ubuntu-latest   | 8.3, 8.4 | 11, 12, 13 |
| windows-latest  | 8.3, 8.4 | 11, 12, 13 |

Each Laravel-major leg pins the matching `orchestra/testbench` major (9.x / 10.x / 11.x). Underlying floor: PHP 8.2+ per `require.php`; Laravel range tracks `orchestra/testbench` constraint in `composer.json`.

### Runtime-detected integrations (no direct dependency)

| Integration | Detection mechanism                                                                  | Versions handled |
|-------------|--------------------------------------------------------------------------------------|------------------|
| Livewire    | `extends Livewire\Component` ancestry                                                | v3 + v4          |
| Filament    | `InteractsWithForms` (v3/v4) / `InteractsWithSchemas` (v5) trait presence            | v3 + v4 + v5     |
| Nova        | `extends Laravel\Nova\Resource` ancestry                                             | v4 + v5          |

These integrations are **detected at rector-time**, not depended upon. The rector handles them when the consumer's project includes them; absent the host packages, the corresponding code paths are dormant.

## Rules shipped

Grouped by the set that includes them. `FluentValidationSetList::ALL` runs everything in Converters + Grouping + Traits; `SIMPLIFY` is a separate post-migration cleanup set you opt into after verifying the initial conversion.

### Converters (set `CONVERT`)

#### `ValidationStringToFluentRuleRector`

Converts pipe-delimited rule strings (`'required|string|max:255'`) to fluent chains.

- **Where it fires**: FormRequest `rules()`, `$request->validate()`, `Validator::make()`.

#### `ValidationArrayToFluentRuleRector`

Converts array-based rules (`['required', 'string', Rule::unique(...)]`), including `Rule::` objects, `Password::min()` chains, conditional tuples, closures, and custom rule objects.

- **Conditional tuples accept**:
  - Explicit enum-value args: `['exclude_unless', 'type', Enum::CASE->value]`
  - In-tuple variadic spread on variadic fluent signatures: `['required_unless', $field, ...Enum::list()]` → `->requiredUnless($field, ...Enum::list())`
- **Conditional tuples bail**: spread targeting non-variadic methods (`excludeWith`, `requiredIfAccepted`), or placed on the rule-name / field position. Array form preserved.
- **Non-conditional tuples accept dynamic expressions**: `['max', $this->limit ?? 10]`, `['between', config('a'), config('b')]`, `['max', match($x) { ... }]`, via a permissive emittable-arg check on the fluent-lowering and `->rule([...])` escape-hatch paths.
- **Non-conditional tuples bail on**: object/callable/array producers (`new Obj()`, `fn() => 5`, `[1, 2]`) and side-effectful mutators (`$x = 5`, `$i++`). Preserves the original failure mode.
- **COMMA_SEPARATED conditional rules** keep strict string-like args to avoid `Closure|bool|string $field` overload ambiguity.

#### `InlineResolvableParentRulesRector`

Inlines `parent::rules()` when it appears as a spread at index 0 of a child `rules()`. Unblocks the converter rectors, which otherwise bail on spread items.

- **Handles**:
  - `...parent::rules()` when the parent is a plain `return [...];`
  - `...$base` when `$base` is the method's only top-level assignment and its RHS is a literal array or `parent::rules()`. Covers the `$base = parent::rules(); return [...$base, 'new' => '...'];` idiom.
- **Bails on**: parents that merge, concatenate, or call methods over their return; methods with peer top-level assignments, nested-scope assignments (`if` / `foreach` / `try`), or multi-use variables.
- **Runs first in `CONVERT`** so the flattened shape reaches `ValidationString/ArrayToFluentRuleRector`.

#### `ConvertLivewireRuleAttributeRector`

Strips Livewire `#[Rule('...')]` / `#[Validate('...')]` property attributes and generates a `rules(): array` method.

- **Handles**:
  - String, list-array, and keyed-array shapes. `#[Validate(['todos' => 'required', 'todos.*' => '...'])]` expands into one `rules()` entry per key.
  - Constructor-form rule objects (`new Password(8)`, `new Unique('users')`, `new Exists('roles')`) lower to `FluentRule::password(8)` / `->unique(...)` / `->exists(...)` the same as their static-factory counterparts.
  - Maps `as:` / `attribute:` to `->label()` in both string and array forms. When both are present, `attribute:` wins on conflict.
  - Keeps an empty `#[Validate]` marker on converted properties so `wire:model.live` real-time validation survives conversion. Opt out via [`PRESERVE_REALTIME_VALIDATION => false`](#convertlivewireruleattributerector-config).
- **Bails on**: hybrid `$this->validate([...])` calls (softenable, see config below), final parent `rules()` methods, unsupported attribute args, numeric keyed-array keys. Each bail logged to the skip file (see [Diagnostics](#diagnostics)).
- **Config**: `KEY_OVERLAP_BEHAVIOR => 'partial'` softens the classwide bail on explicit `$this->validate([...])` to a per-property overlap check. Converts non-overlapping attrs, leaves overlapping ones plus the explicit call alone. See [config keys](#convertlivewireruleattributerector-config).

### Grouping (set `GROUP`)

#### `GroupWildcardRulesToEachRector`

Folds flat wildcard and dotted keys into nested `each()` / `children()` calls. Applies to FormRequests and Livewire components alike.

- **Bails on** (each emits a specific skip-log entry under [`=actionable`](#diagnostics)):
  - Wildcard group has non-FluentRule entries — `'items' => ['required', ...]` next to `'items.*' => FluentRule::...`.
  - Parent rule's factory doesn't support `each()` / `children()` — only `FluentRule::array()` and `FluentRule::field()` do.
  - Wildcard parent (`items.*`) has type-specific rules that grouping would silently drop.
  - Double wildcard (`**`) or non-first `*` in a key suffix.
  - Concat-keyed wildcard (`$prefix . '.*.foo'`) where the prefix isn't a static class constant.
- **Notes**:
  - On Livewire, the `HasFluentValidation` trait's `getRules()` override flattens the nested form back to wildcard keys at runtime, so grouping is safe.
  - When a dot-notation key has no explicit parent rule, synthesizes a bare `FluentRule::array()` parent so nested `required` children still fire.

### Traits (set `TRAITS`)

#### `AddHasFluentRulesTraitRector`

Adds `use HasFluentRules;` to FormRequests that use FluentRule.

#### `AddHasFluentValidationTraitRector`

Adds the fluent-validation trait to Livewire components that use FluentRule.

- **Variant picking**:
  - Plain Livewire component → `HasFluentValidation`.
  - Filament's `InteractsWithForms` (v3/v4) or `InteractsWithSchemas` (v5) used **directly** on the class → `HasFluentValidationForFilament` + a 4-method `insteadof` block.
  - Wrong variant already directly on a class → swaps to the right one and drops the orphaned import.
- **Bails on**: ancestor-only Filament usage. PHP method resolution through inheritance is fragile, so the user must add the trait on the concrete subclass. Skip-logged.

> [!TIP]
> If your codebase has a shared FormRequest or Livewire base, declare `use HasFluentRules;` (or `HasFluentValidation`) on the base once and every subclass inherits it. The trait rectors walk the ancestor chain via `ReflectionClass` and won't re-add the trait on subclasses, so no `base_classes` configuration is needed.

### Post-migration (set `SIMPLIFY`)

`SIMPLIFY` is **opt-in**, not bundled into `ALL`. Run it as a separate pass after you've verified the initial conversion.

#### `PromoteFieldFactoryRector`

Promotes `FluentRule::field()` to a typed factory (`::string()`, `::numeric()`, etc.) when every `->rule(...)` wrapper in the chain resolves to a v1-scope rule whose target method lives on exactly one typed FluentRule subclass.

- **Why**: unblocks `SimplifyRuleWrappersRector`'s next pass. `FluentRule::field()->rule('max:61')` becomes `FluentRule::string()->max(61)` instead of staying on the escape hatch.
- **Also promotes**: `FluentRule::string()->rule(Password::default())` / `->rule(Email::default())` → `FluentRule::password()` / `::email()` (same zero-arg source, single Password/Email match, no Conditionable hops).
- **Bails on**:
  - Conditionable hops in the chain.
  - Chains whose compatible-class intersection isn't a singleton.
  - `field()->rule('accepted')` / `field()->rule('declined')`. The would-be `boolean()` factory's seed constraint rejects `"yes"` / `"on"` / `"true"` (`accepted`) and `"no"` / `"off"` / `"false"` (`declined`), inputs the original Laravel rule permits (including HTML checkbox defaults). The post-bail skip-log line names the blocked promotion target so consumers can decide between keeping the escape hatch or explicitly using `FluentRule::boolean()->accepted()`.
- **Semantic note**: `StringRule` adds Laravel's implicit `string` rule (likewise `numeric` for `NumericRule`); `FieldRule` adds neither. Promoting therefore changes validation behavior on non-string inputs. Intent matches in nearly all `max(N)` cases, but review the diff.
- **Runs first in `SIMPLIFY`** so the promoted factory reaches the wrapper rewrites.

#### `SimplifyFluentRuleRector`

Cleans up FluentRule chains after migration.

- **Handles**: factory shortcuts (`string()->url()` → `url()`), `->label()` folded into the factory arg, `min()` + `max()` → `between()`, redundant type removal.
- **Bails on**:
  - `min()` + `max()` fold when either method carries `messageFor('min'/'max')` or a positional `message()`. Would silently drop the message binding.
  - Factory-shortcut promotion when the chain has a `label()` call OR the shortcut method isn't adjacent to the factory (preserves user intent and message-binding slots).

#### `SimplifyRuleWrappersRector`

Rewrites escape-hatch `->rule(...)` calls into native typed-rule methods.

- **Handles**:

  | Rule family                     | Receivers                                  | Notes                                                                                                                                                  |
  |---------------------------------|--------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------|
  | `in` / `notIn`                  | `String`/`Numeric`/`Email`/`Field`/`Date`  | `HasEmbeddedRules` consumers                                                                                                                           |
  | `min` / `max` / `between`       | per-class allowlist                        | `EmailRule` has only `max`                                                                                                                             |
  | `regex`                         | `StringRule` only                          |                                                                                                                                                        |
  | `size` → `exactly`              | `String`/`Numeric`/`Array`/`File`          | Laravel's `size:` renamed in fluent-validation per `TypedBuilderHint`                                                                                  |
  | `enum`                          | `HasEmbeddedRules` consumers               | typed-rule allowlist                                                                                                                                   |
  | Literal-zero comparison helpers | `NumericRule`                              | `gt:0` → `->positive()`, `gte:0` → `->nonNegative()`, `lt:0` → `->negative()`, `lte:0` → `->nonPositive()`. Non-zero literals + field refs stay escape. |
  | Zero-arg string tokens          | typed receivers with matching method       | `'accepted'`, `'declined'`, `'present'`, `'prohibited'`, `'nullable'`, `'sometimes'`, `'required'`, `'filled'`                                         |

- **Array-form COMMA_SEPARATED conditional rules**: `->rule(['required_if', 'field', 'value'])` → `->requiredIf('field', 'value')`. Covers field-plus-variadic-values rules (`required_if` / `exclude_unless`) and pure variadic-fields rules (`required_with` / `prohibits`). BackedEnum cases in tail positions auto-wrap with `->value`. Category C `required_if_accepted` and Category D `exclude_with` stay as escape hatch.

- **Receiver-type inference**: walks the chain back to the `FluentRule::*()` factory. Steps through `Conditionable` proxy hops (`->when(...)` / `->unless(...)` / `->whenInput(...)`) when the closure body is a bare-return / no-return / `fn ($r) => $r` identity. Proxy hops with other closure shapes bail.

- **Bails on**: variable receivers, methods absent from the resolved typed-rule class.

- **Runs after `SimplifyFluentRuleRector`** so factory shortcuts apply first.

#### `InlineMessageParamRector`

Collapses `->message('...')` / `->messageFor('key', '...')` chain calls into the inline `message:` named parameter on FluentRule factories and rule methods. Requires `sandermuller/laravel-fluent-validation` ^1.20 (earlier floors get zero rewrites via the reflection-time surface probe).

- **Three rewrite predicates**:
  - **Factory-direct**: `FluentRule::email()->message('Bad')` → `FluentRule::email(message: 'Bad')`. Requires `->message()` immediately on the factory with no intervening rule method or Conditionable hop.
  - **Rule-method matched-key**: `->min(3)->messageFor('min', 'Too short.')` → `->min(3, message: 'Too short.')`.
  - **Rule-object**: `->rule(new In([...]))->messageFor('in', 'Pick one.')` → `->rule(new In([...]), message: 'Pick one.')`.

- **Skip categories** (each emits a user-facing log entry):

  | Category                       | Examples                                                              | Why                                                                                                                |
  |--------------------------------|-----------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------|
  | Variadic-trailing              | `requiredWith` / `contains`                                           | inline binds to wrong slot                                                                                         |
  | Composite                      | `digitsBetween` / `DateRule::between` / `ImageRule::dimensions`       | inline binds to last sub-rule                                                                                      |
  | Mode-modifier                  | `EmailRule::strict` / `PasswordRule::letters`                         | don't call `addRule`                                                                                               |
  | Deferred-key factories         | `date` / `dateTime`                                                   |                                                                                                                    |
  | L11/L12-divergent `Password`   | `getFromLocalArray` shortRule lookup is L12+ only                     | template lists `password.letters` / `password.mixed` sub-key alternatives for L11 consumers                        |
  | No-implicit-constraint factories | `field` / `anyOf`                                                   |                                                                                                                    |

- **Pre-existing user misbindings** (`->min(3)->messageFor('max', ...)`) stay chained silently. Not rector's job to fix.

### Docblock polish (set `POLISH`)

`POLISH` is **opt-in**, not bundled into `ALL`. Run it as a separate pass after `CONVERT` stabilizes (multi-pass convergence requires the final shape).

#### `UpdateRulesReturnTypeDocblockRector`

Narrows the `@return` PHPDoc annotation on `rules()` methods from the wide `array<string, ValidationRule|string|array<mixed>>` union down to `array<string, \SanderMuller\FluentValidation\Contracts\FluentRuleContract>` when every value in the returned array is a `FluentRule::*()` call chain. Cosmetic (runtime behavior untouched), but gives PHPStan and editors a narrower type to reason about.

- **Qualifying classes**: `FormRequest` subclasses (anywhere in the ancestor chain, aliased imports included) and classes using `HasFluentRules` / `HasFluentValidation` / `HasFluentValidationForFilament` directly or via ancestors.
- **Narrowed only**: methods with no existing `@return`, `@return array`, or the wide-union annotation this package's converters emit.
- **Respected (left untouched)**: user-customized annotations, `@inheritDoc`, widened unions/intersections, any non-prose suffix.
- **Skipped when**:
  - The returned array isn't a single literal `Array_` (multi-return, builder variants, `RuleSet::from(...)`, collection pipelines).
  - Any value isn't a FluentRule chain (`Rule::in(...)`, `new Custom()`, closures, string rules, ternary / match).
  - The method has `): ?array` or unkeyed items.
- **Run as a separate pass after `CONVERT` stabilizes**. Rector's multi-pass convergence means it eventually fires on the final shape, but a single-invocation rector run that mixes `CONVERT` + `POLISH` may require a second invocation if any file had string-rule items mid-convert.

## Sets

| Set        | Rules                                                                                                                                                                                                                                                                                                                          |
|------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `ALL`      | `CONVERT` + `GROUP` + `TRAITS` (the full migration pipeline)                                                                                                                                                                                                                                                                   |
| `CONVERT`  | [`InlineResolvableParentRulesRector`](#inlineresolvableparentrulesrector), [`ValidationStringToFluentRuleRector`](#validationstringtofluentrulerector), [`ValidationArrayToFluentRuleRector`](#validationarraytofluentrulerector), [`ConvertLivewireRuleAttributeRector`](#convertlivewireruleattributerector)                  |
| `GROUP`    | [`GroupWildcardRulesToEachRector`](#groupwildcardrulestoeachrector)                                                                                                                                                                                                                                                            |
| `TRAITS`   | [`AddHasFluentRulesTraitRector`](#addhasfluentrulestraitrector), [`AddHasFluentValidationTraitRector`](#addhasfluentvalidationtraitrector)                                                                                                                                                                                     |
| `SIMPLIFY` | [`PromoteFieldFactoryRector`](#promotefieldfactoryrector), [`SimplifyFluentRuleRector`](#simplifyfluentrulerector), [`SimplifyRuleWrappersRector`](#simplifyrulewrappersrector), [`InlineMessageParamRector`](#inlinemessageparamrector) — post-migration cleanup, run as a separate pass after verifying the initial conversion |
| `POLISH`   | [`UpdateRulesReturnTypeDocblockRector`](#updaterulesreturntypedocblockrector) — narrow `@return` docblocks to `FluentRuleContract`                                                                                                                                                                                              |

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

// Docblock polish (run separately after CONVERT stabilizes)
->withSets([FluentValidationSetList::POLISH])
```

> [!NOTE]
> Don't bundle `ALL` + `SIMPLIFY` + `POLISH` into a single config call. `SIMPLIFY` runs after manual diff review of the initial conversion; `POLISH` needs `CONVERT`'s multi-pass output to stabilize. Each is a separate `vendor/bin/rector process` invocation against its own `withSets([...])` block.

## Individual rules

When you need a single conversion (a one-off migration of a specific codebase path, or running just the array-based converter on a subset of files), import and register the rule class directly:

```php
use SanderMuller\FluentValidationRector\Rector\ValidationStringToFluentRuleRector;
use SanderMuller\FluentValidationRector\Rector\ValidationArrayToFluentRuleRector;

return RectorConfig::configure()
    ->withRules([
        ValidationStringToFluentRuleRector::class,
        ValidationArrayToFluentRuleRector::class,
    ]);
```

The full rule list (any of these can be registered individually without pulling the whole set):

| Rule                                                                                  | Set (opt-in)                  | Purpose                                                                                                                                      |
|---------------------------------------------------------------------------------------|-------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------|
| [`InlineResolvableParentRulesRector`](#inlineresolvableparentrulesrector)             | `CONVERT` (included in `ALL`) | inline `...parent::rules()` spread when parent is plain `return [...]`                                                                       |
| [`ValidationStringToFluentRuleRector`](#validationstringtofluentrulerector)           | `CONVERT` (included in `ALL`) | pipe-delimited rule strings → FluentRule chains                                                                                              |
| [`ValidationArrayToFluentRuleRector`](#validationarraytofluentrulerector)             | `CONVERT` (included in `ALL`) | array-based rules + `Rule::`/`Password::` objects → FluentRule chains                                                                        |
| [`ConvertLivewireRuleAttributeRector`](#convertlivewireruleattributerector)           | `CONVERT` (included in `ALL`) | Livewire `#[Rule]` / `#[Validate]` → generated `rules()` method                                                                              |
| [`GroupWildcardRulesToEachRector`](#groupwildcardrulestoeachrector)                   | `GROUP` (included in `ALL`)   | flat wildcard/dotted keys → nested `each()` / `children()`                                                                                   |
| [`AddHasFluentRulesTraitRector`](#addhasfluentrulestraitrector)                       | `TRAITS` (included in `ALL`)  | adds `use HasFluentRules;` to FormRequests that use FluentRule                                                                               |
| [`AddHasFluentValidationTraitRector`](#addhasfluentvalidationtraitrector)             | `TRAITS` (included in `ALL`)  | adds Livewire trait (plain or Filament variant) to Livewire components                                                                       |
| [`PromoteFieldFactoryRector`](#promotefieldfactoryrector)                             | `SIMPLIFY` (**not** in `ALL`) | `FluentRule::field()->rule('max:61')` → `FluentRule::string()` when wrappers narrow to one typed subclass                                    |
| [`SimplifyFluentRuleRector`](#simplifyfluentrulerector)                               | `SIMPLIFY` (**not** in `ALL`) | factory shortcuts, `->between()`, redundant-type cleanup                                                                                     |
| [`SimplifyRuleWrappersRector`](#simplifyrulewrappersrector)                           | `SIMPLIFY` (**not** in `ALL`) | `->rule('in:a,b')` / `->rule(Rule::in([...]))` / `->rule('size:N')` → native typed-rule methods (`->in([...])`, `->exactly(N)`, etc.)        |
| [`InlineMessageParamRector`](#inlinemessageparamrector)                               | `SIMPLIFY` (**not** in `ALL`) | `->message('x')` / `->messageFor('key', 'x')` on factories + rule methods → inline `message:` named param (requires fluent-validation ^1.20) |
| [`UpdateRulesReturnTypeDocblockRector`](#updaterulesreturntypedocblockrector)         | `POLISH` (**not** in `ALL`)   | narrow `@return` on pure-fluent `rules()` to `FluentRuleContract`                                                                            |

### Configurable rules

Four rules accept configuration via `withConfiguredRule()`.

#### `ConvertLivewireRuleAttributeRector` config

| Key                            | Type                  | Default  | What it does                                                                                                                                                                                                                                                                                                                                                                                                  |
|--------------------------------|-----------------------|----------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `PRESERVE_REALTIME_VALIDATION` | `bool`                | `true`   | When true, converted `#[Validate]` properties retain an empty `#[Validate]` marker so `wire:model.live` real-time validation survives conversion. Opt out with `false` on codebases that don't use `wire:model.live` and find the marker noisy in converted diffs.                                                                                                                                            |
| `MIGRATE_MESSAGES`             | `bool`                | `false`  | When true, `message:` attribute args migrate into a generated `messages(): array` method alongside `rules()`. String `message: 'X'` → `'<prop>' => 'X'`; array `message: ['rule' => 'X']` → `'<prop>.<rule>' => 'X'` (full-path keys passthrough verbatim for keyed-array first-arg attributes). Opt-in: expands class surface; some consumers centralize messages in lang files. Bails on unmergeable existing `messages()`.    |
| `KEY_OVERLAP_BEHAVIOR`         | `'bail'` \| `'partial'` | `'bail'` | Controls what happens when a class has `#[Validate]` attrs AND an explicit `$this->validate([...])` call. `'bail'` preserves 0.12 semantics — classwide skip. `'partial'` converts attrs whose predicted emit keys don't appear in any explicit `validate([...])` array; overlapping attrs + the explicit call stay intact. Only direct `Array_` / `RuleSet::compileToArrays(<literal>)` accepted; anything else forces classwide bail. |

#### `SimplifyRuleWrappersRector` config

| Key                              | Type    | Default | What it does                                                                                                                                                                                                                |
|----------------------------------|---------|---------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `TREAT_AS_FLUENT_COMPATIBLE`     | `list<string>` | `[]`    | Consumer-declared allowlist of rule-factory FQCNs whose output is FluentRule-compatible. Patterns support `*` (single namespace segment) and `**` (recursive). Silences "rule payload not statically resolvable" skip log on shapes rector can't introspect — e.g. `->rule(App\Rules\Domain\DutchPostcodeRule::create())`. |
| `ALLOW_CHAIN_TAIL_ON_ALLOWLISTED` | `bool`  | `false` | When a chain ends in `->someMethod()` after an allowlisted factory call, default preserves the tail. Flip on if your allowlist covers factories whose tails always return another FluentRule-compatible node.               |

#### `UpdateRulesReturnTypeDocblockRector` config

Same two keys as `SimplifyRuleWrappersRector` (`TREAT_AS_FLUENT_COMPATIBLE`, `ALLOW_CHAIN_TAIL_ON_ALLOWLISTED`). Allowlisted items count as FluentRule for the narrow-`@return`-tag decision. Mixed arrays (allowlisted items + string/array entries) with an existing narrow `FluentRuleContract` tag emit a stale-narrow skip-log warning.

#### `AddHasFluentRulesTraitRector` config

| Key            | Type           | Default | What it does                                                                                                                                                                          |
|----------------|----------------|---------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `BASE_CLASSES` | `list<string>` | `[]`    | Opt-in list of FormRequest **base** class names that should also receive the trait. Default is auto-detection on concrete FormRequests that use `FluentRule` — this list adds named shared bases on top of that path. Leave empty to use auto-detection only.            |

```php
use SanderMuller\FluentValidationRector\Rector\ConvertLivewireRuleAttributeRector;

return RectorConfig::configure()
    ->withConfiguredRule(ConvertLivewireRuleAttributeRector::class, [
        ConvertLivewireRuleAttributeRector::PRESERVE_REALTIME_VALIDATION => false,
    ]);
```

## Formatter integration

The rector's output is valid PHP but has three cosmetic seams that a formatter resolves automatically. The fixer names below are from PHP-CS-Fixer; Pint ships the same set under the same names as part of its default Laravel preset.

1. Imports are inserted at prepend position (not alphabetical). The `ordered_imports` fixer resolves.
2. Unused imports may be left in place (e.g. a `Livewire\Attributes\Rule` import after the attribute is stripped). The `no_unused_imports` fixer resolves.
3. Generated `@return` docblocks emit `Illuminate\Contracts\Validation\ValidationRule` as a fully-qualified reference. The `fully_qualified_strict_types` fixer hoists it to a `use` statement + short-name reference.

All three are in Pint's default Laravel preset, so most Laravel consumers have them without explicit configuration. PHP-CS-Fixer users on a custom ruleset should verify the three fixers are enabled. Without any formatter you'll see rougher-than-example output, but the code is still valid PHP.

> [!TIP]
> For the cleanest pre-formatter output, enable `->withImportNames()->withRemovingUnusedImports()` in your `rector.php`:
>
> ```php
> return RectorConfig::configure()
>     ->withImportNames()
>     ->withRemovingUnusedImports()
>     ->withSets([FluentValidationSetList::ALL]);
> ```

> [!NOTE]
> The rector doesn't insert line breaks between method calls. `FluentRule::string()->required()->max(255)` is valid PHP on a single line and keeps diffs minimal. If you prefer multi-line chains, the [`method_chaining_indentation`](https://mlocati.github.io/php-cs-fixer-configurator/#version:3.0|fixer:method_chaining_indentation) fixer (Pint / PHP-CS-Fixer) reflows them after Rector runs.

## Diagnostics

The skip log is **opt-in** as of 0.5.0. In default runs, bail-capable rules still count skips and the end-of-run summary reports the total, but no file is written to your project root:

```
[fluent-validation] 42 skip entries. Re-run with FLUENT_VALIDATION_RECTOR_VERBOSE=actionable and --clear-cache for details.
```

`FLUENT_VALIDATION_RECTOR_VERBOSE` accepts three values (case-insensitive), introduced in 0.13:

| Value | Surfaces |
|-------|---------|
| unset / empty | **off** — only always-actionable entries get counted; no file is written. |
| `actionable` | **recommended** — adds verbose entries labeled actionable (payloads that need manual migration, stale `@return` docblocks, etc.); suppresses structural noise like "trait already present" / "class is Livewire, routed to the other rector". |
| `1` / `true` / `all` | **everything** — legacy behavior, includes the structural noise. `=1` kept as alias so existing CI scripts keep working. |

```bash
# Recommended entry point — signal only, no noise
FLUENT_VALIDATION_RECTOR_VERBOSE=actionable vendor/bin/rector process --clear-cache

# Full firehose (legacy, still supported)
FLUENT_VALIDATION_RECTOR_VERBOSE=1 vendor/bin/rector process --clear-cache
```

Env-only is deliberate. The flag has to reach parallel workers (fresh PHP processes spawned via `proc_open`), and shell-exported env inherits automatically; an in-process `putenv()` wrapper would not. Exporting the variable one step above the rector invocation keeps a single source of truth that every worker sees.

Any opt-in tier writes `.cache/rector-fluent-validation-skips.log` (plus a `.session` sentinel used to coordinate truncation across parallel workers) and the end-of-run line points at it:

```
[fluent-validation] 42 skip entries written to .cache/rector-fluent-validation-skips.log — see for details
```

The `.cache/` subdir matches Rector's own cache directory convention — most projects already gitignore it. The first line of the log is a per-run header recording the package version, ISO-8601 UTC timestamp, and verbose tier, useful for cross-release diff stability in CI:

```
# laravel-fluent-validation-rector 0.14.1 — generated 2026-04-26T11:47:12Z
# verbose tier: actionable

[fluent-validation:skip] ...
```

The header is always emitted when verbose mode is on, even on zero-entry runs, so the file's existence stays stable across runs. Pre-0.14.1 the log lived at `<cwd>/.rector-fluent-validation-skips.log`; the package automatically cleans up that legacy path on first run after the upgrade.

The log is a file sink because Rector's `withParallel(...)` executor doesn't forward worker STDERR to the parent. A diagnostic line written via `fwrite(STDERR, ...)` from a worker would vanish on parallel runs (Rector's default). A file sink survives worker death and you can inspect it from the project root after the run finishes. If you're writing your own Rector rule and want similar diagnostics, the same gotcha applies: `withParallel()` + STDERR means silent data loss.

> [!TIP]
> Rector caches per-file results. Files that hit a bail produce no transformation, so the skip entry is written once and the rule is not re-invoked on cached runs. To force every file to be revisited and every bail to be re-logged, run `vendor/bin/rector process --clear-cache` (or delete `.cache/rector*`).

> [!NOTE]
> `ConvertLivewireRuleAttributeRector` verifies the generated `rules(): array` is syntactically correct, but it can't prove the converted rule is behaviorally equivalent to the source attribute. If a converted Livewire component has no feature test covering validation, review the diff by hand and watch for dropped `message:` (use [`MIGRATE_MESSAGES`](#convertlivewireruleattributerector-config) to opt in), explicit `onUpdate:`, or `translate: false` args (logged to the skip file) that need manual migration to Livewire's `messages(): array` hook or project config. `messages:` (plural, not a Livewire-documented arg) surfaces its own "unrecognized, likely typo for `message:`?" log entry.

## Parity

A small subset of rectors changes which Laravel rule object handles validation at runtime. The functional test suite proves source→source AST shape; the **parity harness** under `tests/Parity/` proves the resulting rule sets produce equivalent error bags when Laravel runs them. Together they cover both structural and behavioral correctness.

**In-scope rectors** (semantics may change):

- `SimplifyRuleWrappersRector` — promotes `field()->rule('accepted')` to typed factory chains.
- `GroupWildcardRulesToEachRector` — folds wildcard sibling keys into `each(...)`.
- `PromoteFieldFactoryRector` — rewrites `field()->required()->rule('string')` to `string()->required()`.

Pure-refactor rectors (`Validation*ToFluentRule`, `AddHasFluent*Trait`, `ConvertLivewireRuleAttribute`, `Inline*`, `UpdateRulesReturnTypeDocblock`, `SimplifyFluentRule`) ship with structural coverage only — their transformations don't change which rule class handles validation.

**Authoring a fixture.** Each fixture lives at `tests/Parity/Fixture/<RectorName>/<case>.php` and returns:

```php
return [
    'rules_before' => ['field' => 'pre-rector-rule-shape'],
    'rules_after'  => ['field' => FluentRule::typed()->...],
    'payloads' => [
        'descriptive name' => ['field' => 'value-to-test'],
    ],
    // optional, only when the divergence is intentional:
    'allowed_divergences' => [
        'descriptive name' => [
            'category'  => DivergenceCategory::ImplicitTypeConstraint,
            'rationale' => 'free-text explanation that lives next to the divergence',
        ],
    ],
];
```

The harness runs `Validator::make($payload, $rules_before)` and `Validator::make($payload, $rules_after)`, then diffs the resulting error bags. Outcomes: `MATCH`, `BEFORE_REJECTS_AFTER_PASSES`, `AFTER_REJECTS_BEFORE_PASSES`, `BOTH_REJECT_DIFFERENT_MESSAGES`, `BOTH_REJECT_DIFFERENT_ORDER`, or `SKIPPED` (DB / closure denylist).

**Allowed divergences.** Some transformations legitimately change behavior — e.g. `boolean()->accepted()` rejects `'yes' / 'on'` strings that bare `accepted` accepts because of `boolean()`'s implicit type pre-check. Categorize via `DivergenceCategory` enum:

- `ImplicitTypeConstraint` — typed rule attaches an implicit constraint absent from the pre-rector form.
- `MessageKeyDrift` — same fail outcome, different underlying message-key path.
- `AttributeLabelDrift` — same fail, `:attribute` substitution renders differently.
- `OrderDependentPipeline` — same messages, different per-field order.

The category constrains the allowed runtime outcome; mismatched category fails the test. The free-text rationale lives next to the divergence so future readers see *why* it's acceptable.

**Coverage gate.** `tests/Parity/CoverageTest.php` asserts every in-scope rector has ≥1 fixture. New semantics-changing rectors must extend the in-scope list AND ship at least one fixture before merging.

## Known limitations

- **Namespace-less files.** Classes at the file root without a `namespace` are silently skipped by the grouping and trait rectors. Laravel projects always use namespaces, so this rarely comes up in practice.
- **Rules built inside `withValidator()` callbacks.** `withValidator()` is a post-validation hook for adding custom errors via `$validator->after(...)`, not a rules definition. No FluentRule equivalent — imperative code stays.
- **Rules built via `Collection::put()->merge()->all()` chains.** Runtime-resolved collection pipelines aren't statically determinable. Out of scope unless a narrow shape (pure literal `put()` chain ending in `->all()`) gathers consumer demand.
- **Multi-statement helper bodies.** Auto-detection requires a single-statement `return [...];` shape. Helpers like `private function buildRules() { $rules = [...]; return $rules; }` stay untouched. Inline the return or convert by hand.

**Already covered (not limitations)**: `Validator::validate(...)`, the global `validator(...)` helper (when prefixed with `\` or in the global namespace), and custom-named rules methods (`editorRules()`, `rulesWithoutPrefix()`, etc.) on classes that qualify as rules-bearing (FormRequest descendants / fluent-validation-trait users / Livewire components / `#[FluentRules]`-marked methods). The converters auto-detect rules-shaped methods by content signature — a string-keyed `return [...];` whose values include a recognized rule string, `Rule::*()` call, FluentRule chain, or constructor-form rule object — without any consumer config.
- **Ternary picking the rule NAME.** `['nullable', $flag ? 'email' : 'url']` (where the ternary chooses a *different rule*) is left alone. A `->when(cond, thenFn, elseFn)` conversion is tractable in principle but wasn't worth it: three separate codebase audits turned up near-zero usage (single digits across a 100+ FormRequest corpus), and the closure-based fluent form loses the terseness users reach for ternaries to preserve. Use `Rule::when(...)` or branch the rules array outside the ternary instead. **Not a limitation**: ternaries / method calls / function calls / match / nullsafe property fetches *as a rule's argument* — `['max', $cond ? 15 : 20]`, `['between', config('a'), config('b')]`, `['max', $this->limit ?? 10]` — convert fine via the permissive emittable-arg path on non-conditional tuples (see [`ValidationArrayToFluentRuleRector`](#validationarraytofluentrulerector)).
- **`#[Validate(..., onUpdate: true)]` / `translate: false`.** These attribute args have no FluentRule builder equivalent and no migration path. They land in the skip log so you can move them to Livewire's hooks or project config manually. The rule string, `as:` / `attribute:` label, and `onUpdate: false` (consumed as a real-time-validation opt-out marker) are migrated. **`message:` is opt-in**: enable [`MIGRATE_MESSAGES`](#convertlivewireruleattributerector-config) to migrate string and array `message:` args into a generated `messages(): array` method alongside `rules()`. With `MIGRATE_MESSAGES` off (default), `message:` args also land in the skip log for manual migration.

## License

MIT
