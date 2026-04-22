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
- [Rules shipped](#rules-shipped) — what gets converted and what stays

**Usage**
- [Sets](#sets) — mix and match subsets of the migration pipeline
- [Individual rules](#individual-rules) — when you need one specific conversion

**Output**
- [Formatter integration](#formatter-integration) — what the rector emits and how Pint / PHP-CS-Fixer finish the job
- [Diagnostics](#diagnostics) — skip log, cache interactions, manual spot-checks

**Reference**
- [Known limitations](#known-limitations)
- [License](#license)

## Installation

```bash
composer require --dev sandermuller/laravel-fluent-validation-rector
```

Requires PHP 8.2+, Rector 2.4+, and [sandermuller/laravel-fluent-validation](https://github.com/sandermuller/laravel-fluent-validation) ^1.20. The floor bumped from ^1.19 to ^1.20 in 0.9.0 to unlock the `InlineMessageParamRector` surface (factory + rule-method `?string $message = null` params). 1.17 shipped `FluentRuleContract`, 1.19 added the `FluentRule::ipv4/ipv6/json/timezone/hex_color/active_url/list/declined/regex/enum` factory surface + `NumericRule` sign helpers, and 1.20 added inline `message:` parameters. Consumers on 1.17–1.19 should pin rector to 0.8.x; 0.9.0+ requires 1.20 even if you only use CONVERT/GROUP/TRAITS (composer floor is enforced at install time). The 1.8.1+ constraint also matters if you have Filament+Livewire components: the `HasFluentValidationForFilament` trait overrides four methods that also exist on Filament's `InteractsWithForms` / `InteractsWithSchemas`, so an `insteadof` adaptation is required. Rector emits the adaptation for you on direct Filament compositions.

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

## Rules shipped

Grouped by the set that includes them. `FluentValidationSetList::ALL` runs everything in Converters + Grouping + Traits; `SIMPLIFY` is a separate post-migration cleanup set you opt into after verifying the initial conversion.

### Converters (set `CONVERT`)

- **`ValidationStringToFluentRuleRector`** converts pipe-delimited rule strings (`'required|string|max:255'`) to fluent chains. Works in FormRequest `rules()`, `$request->validate()`, and `Validator::make()`.
- **`ValidationArrayToFluentRuleRector`** converts array-based rules (`['required', 'string', Rule::unique(...)]`), including `Rule::` objects, `Password::min()` chains, conditional tuples, closures, and custom rule objects.
- **`ConvertLivewireRuleAttributeRector`** strips Livewire `#[Rule('...')]` / `#[Validate('...')]` property attributes and generates a `rules(): array` method. Handles string, list-array, and keyed-array shapes; `#[Validate(['todos' => 'required', 'todos.*' => '...'])]` expands into one `rules()` entry per key. Constructor-form rule objects (`new Password(8)`, `new Unique('users')`, `new Exists('roles')`) — which attribute const-expr forces you into instead of the usual `Password::min(8)` / `Rule::unique(...)` shape — lower to `FluentRule::password(8)` / `->unique(...)` / `->exists(...)` the same as their static-factory counterparts. Maps `as:` / `attribute:` to `->label()` in both string and array forms (when both are present, `attribute:` wins on conflict). For `#[Validate]` properties, it keeps an empty `#[Validate]` marker on the property so `wire:model.live` real-time validation survives conversion. Opt out via the `preserve_realtime_validation => false` config. Bails on edge cases (hybrid `$this->validate([...])` calls, final parent `rules()` methods, unsupported attribute args, numeric keyed-array keys) and logs each one to the skip file — see [Diagnostics](#diagnostics).

### Grouping (set `GROUP`)

- **`GroupWildcardRulesToEachRector`** folds flat wildcard and dotted keys into nested `each()` / `children()` calls. Applies to FormRequests and Livewire components alike. On Livewire, the `HasFluentValidation` trait's `getRules()` override flattens the nested form back to wildcard keys at runtime, so the grouping is safe. When a dot-notation key has no explicit parent rule, the rector synthesizes a bare `FluentRule::array()` parent so nested `required` children still fire.

### Traits (set `TRAITS`)

- **`AddHasFluentRulesTraitRector`** adds `use HasFluentRules;` to FormRequests that use FluentRule.
- **`AddHasFluentValidationTraitRector`** adds the fluent-validation trait to Livewire components that use FluentRule. Picks `HasFluentValidation` for plain Livewire components, or `HasFluentValidationForFilament` + a 4-method `insteadof` block when Filament's `InteractsWithForms` (v3/v4) or `InteractsWithSchemas` (v5) is used **directly** on the class. Ancestor-only Filament usage is skip-logged (PHP method resolution through inheritance is fragile; user must add the trait on the concrete subclass). If the wrong variant is already directly on a class, the rector swaps it to the right one and drops the orphaned import.

> [!TIP]
> If your codebase has a shared FormRequest or Livewire base, declare `use HasFluentRules;` (or `HasFluentValidation`) on the base once and every subclass inherits it. The trait rectors walk the ancestor chain via `ReflectionClass` and won't re-add the trait on subclasses, so no `base_classes` configuration is needed.

### Post-migration (set `SIMPLIFY`)

- **`SimplifyFluentRuleRector`** cleans up FluentRule chains after migration: factory shortcuts (`string()->url()` → `url()`), `->label()` folded into the factory arg, `min()` + `max()` → `between()`, redundant type removal. Run it as a separate pass after you've verified the initial conversion. It's not included in `ALL` by default.
- **`SimplifyRuleWrappersRector`** rewrites escape-hatch `->rule(...)` calls into native typed-rule methods. Covers `in`/`notIn` (on `String`/`Numeric`/`Email`/`Field`/`Date` rules — the `HasEmbeddedRules` consumers), `min`/`max`/`between` (per-class allowlist; `EmailRule` has only `max`), `regex` (StringRule only), `size` → `exactly` (Laravel's `size:` rule renamed in fluent-validation per `TypedBuilderHint`; rewrites on `String`/`Numeric`/`Array`/`File`), `enum` (on `HasEmbeddedRules` consumers — typed-rule allowlist), and the literal-zero comparison helpers (`gt:0` → `->positive()`, `gte:0` → `->nonNegative()`, `lt:0` → `->negative()`, `lte:0` → `->nonPositive()` on `NumericRule`; non-zero literals and field references stay as escape hatch). Receiver-type inference walks the chain back to the `FluentRule::*()` factory; bails on variable receivers, `Conditionable` proxy hops (`->when(...)`/`->unless(...)`/`->whenInput(...)`), and methods absent from the resolved typed-rule class. Runs after `SimplifyFluentRuleRector` so factory shortcuts apply first.

  **Plus 1.19.0 surface in CONVERT + SIMPLIFY:** `ValidationStringToFluentRuleRector` / `ValidationArrayToFluentRuleRector` recognize `'ipv4'`/`'ipv6'`/`'mac_address'`/`'json'`/`'timezone'`/`'hex_color'`/`'active_url'`/`'list'`/`'declined'` as direct factory tokens, with sibling-token promotion (`'string|ipv4'` → `FluentRule::ipv4()` instead of the verbose `string()->ipv4()` detour). `SimplifyFluentRuleRector` also collapses already-fluent chains for these factories plus `regex`/`enum` arg-passthrough (`string()->regex($p)` → `regex($p)`, `field()->enum($t)` → `enum($t)`). Requires `sandermuller/laravel-fluent-validation` ^1.19.

- **`InlineMessageParamRector`** collapses `->message('...')` / `->messageFor('key', '...')` chain calls into the inline `message:` named parameter on FluentRule factories and rule methods. Three rewrite predicates: factory-direct (`FluentRule::email()->message('Bad')` → `FluentRule::email(message: 'Bad')` when `->message()` is immediately on the factory with no intervening rule method or Conditionable hop), rule-method matched-key (`->min(3)->messageFor('min', 'Too short.')` → `->min(3, message: 'Too short.')`), and rule-object (`->rule(new In([...]))->messageFor('in', 'Pick one.')` → `->rule(new In([...]), message: 'Pick one.')`). Six skip categories with user-facing log entries: variadic-trailing (`requiredWith`/`contains`/etc.), composite (`digitsBetween`/`DateRule::between`/`ImageRule::dimensions` — inline binds to last sub-rule), mode-modifier (`EmailRule::strict`/`PasswordRule::letters` — don't call `addRule`), deferred-key factories (`date`/`dateTime`), L11/L12-divergent `Password` (`getFromLocalArray` shortRule lookup is L12+ only — template explicitly lists `password.letters`/`password.mixed` sub-key alternatives for L11 consumers), no-implicit-constraint factories (`field`/`anyOf`). Pre-existing user misbindings (`->min(3)->messageFor('max', ...)`) stay chained silently — not rector's job to fix. Requires `sandermuller/laravel-fluent-validation` ^1.20 (earlier floors get zero rewrites via the reflection-time surface probe).

### Docblock polish (set `POLISH`)

- **`UpdateRulesReturnTypeDocblockRector`** narrows the `@return` PHPDoc annotation on `rules()` methods from the wide `array<string, ValidationRule|string|array<mixed>>` union down to `array<string, \SanderMuller\FluentValidation\Contracts\FluentRuleContract>` when every value in the returned array is a `FluentRule::*()` call chain. Cosmetic — runtime behavior is untouched — but gives PHPStan and editors a narrower type to reason about. Opt-in via the `POLISH` set; not in `ALL`.
  - **Qualifying classes**: `FormRequest` subclasses (anywhere in the ancestor chain, aliased imports included) and classes using `HasFluentRules` / `HasFluentValidation` / `HasFluentValidationForFilament` directly or via ancestors.
  - **Narrowed only**: methods with no existing `@return`, `@return array`, or the wide-union annotation this package's converters emit. User-customized annotations, `@inheritDoc`, widened unions/intersections, and any non-prose suffix are respected.
  - **Skipped** when the returned array isn't a single literal `Array_` (multi-return, builder variants, `RuleSet::from(...)`, collection pipelines), when any value isn't a FluentRule chain (`Rule::in(...)`, `new Custom()`, closures, string rules, ternary/match), or when the method has `): ?array` / unkeyed items.
  - Run it as a **separate pass after CONVERT stabilizes**. Rector's multi-pass convergence means it eventually fires on the final shape, but a single-invocation rector run that mixes CONVERT + POLISH may require a second invocation if any file had string-rule items mid-convert.

## Sets

| Set        | Includes                                                 |
|------------|----------------------------------------------------------|
| `ALL`      | Convert + Group + Traits (full migration pipeline)       |
| `CONVERT`  | String, array, and `#[Rule]` attribute converters        |
| `GROUP`    | Wildcard/dotted-key grouping into `each()`               |
| `TRAITS`   | Performance trait insertion for FormRequest and Livewire |
| `SIMPLIFY` | Post-migration chain cleanup                             |
| `POLISH`   | Narrow `@return` docblocks to `FluentRuleContract`       |

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

The full rule list — any of these can be registered individually without pulling the whole set:

| Rule                                   | Set (opt-in)                          | Purpose                                                                 |
|----------------------------------------|---------------------------------------|-------------------------------------------------------------------------|
| `ValidationStringToFluentRuleRector`   | `CONVERT` (included in `ALL`)         | pipe-delimited rule strings → FluentRule chains                         |
| `ValidationArrayToFluentRuleRector`    | `CONVERT` (included in `ALL`)         | array-based rules + `Rule::`/`Password::` objects → FluentRule chains   |
| `ConvertLivewireRuleAttributeRector`   | `CONVERT` (included in `ALL`)         | Livewire `#[Rule]` / `#[Validate]` → generated `rules()` method         |
| `GroupWildcardRulesToEachRector`       | `GROUP` (included in `ALL`)           | flat wildcard/dotted keys → nested `each()` / `children()`              |
| `AddHasFluentRulesTraitRector`         | `TRAITS` (included in `ALL`)          | adds `use HasFluentRules;` to FormRequests that use FluentRule          |
| `AddHasFluentValidationTraitRector`    | `TRAITS` (included in `ALL`)          | adds Livewire trait (plain or Filament variant) to Livewire components  |
| `SimplifyFluentRuleRector`             | `SIMPLIFY` (**not** in `ALL`)         | factory shortcuts, `->between()`, redundant-type cleanup                |
| `SimplifyRuleWrappersRector`           | `SIMPLIFY` (**not** in `ALL`)         | `->rule('in:a,b')` / `->rule(Rule::in([...]))` / `->rule('size:N')` → native typed-rule methods (`->in([...])`, `->exactly(N)`, etc.) |
| `InlineMessageParamRector`             | `SIMPLIFY` (**not** in `ALL`)         | `->message('x')` / `->messageFor('key', 'x')` on factories + rule methods → inline `message:` named param (requires fluent-validation ^1.20) |
| `UpdateRulesReturnTypeDocblockRector`  | `POLISH` (**not** in `ALL`)           | narrow `@return` on pure-fluent `rules()` to `FluentRuleContract`       |

### Configurable rules

Two rules accept configuration via `withConfiguredRule()`:

- **`ConvertLivewireRuleAttributeRector`**
  - `PRESERVE_REALTIME_VALIDATION` (bool, default `true`). When true, converted `#[Validate]` properties retain an empty `#[Validate]` marker so `wire:model.live` real-time validation survives conversion. Opt out with `false` on codebases that don't use `wire:model.live` and find the marker noisy in converted diffs.
  - `MIGRATE_MESSAGES` (bool, default `false`). When true, `message:` attribute args migrate into a generated `messages(): array` method alongside `rules()`. String `message: 'X'` becomes `'<prop>' => 'X'`; array `message: ['rule' => 'X']` becomes `'<prop>.<rule>' => 'X'` (or full-path keys passthrough verbatim for keyed-array first-arg attributes). Opt-in because the generated method expands the class surface and some consumers centralize messages in lang files. The whole conversion bails (leaving `#[Validate]` intact) when an existing non-trivial `messages()` method can't be safely merged — preflight check prevents silent message loss.
- **`AddHasFluentRulesTraitRector`** — `BASE_CLASSES` (list of strings). Opt-in list of FormRequest base class names that should receive the trait. Leave empty to skip the trait-insertion path entirely; set to a class list to target shared bases.

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
[fluent-validation] 42 skip entries. Re-run with FLUENT_VALIDATION_RECTOR_VERBOSE=1 and --clear-cache for details.
```

Opt in when you actually want the per-entry breakdown:

```bash
FLUENT_VALIDATION_RECTOR_VERBOSE=1 vendor/bin/rector process --clear-cache
```

Env-only is deliberate. The flag has to reach parallel workers (fresh PHP processes spawned via `proc_open`), and shell-exported env inherits automatically — an in-process `putenv()` wrapper would not. Exporting the variable one step above the rector invocation keeps a single source of truth that every worker sees.

Verbose mode writes `.rector-fluent-validation-skips.log` to your project root (plus a `.session` sentinel used to coordinate truncation across parallel workers) and the end-of-run line points at it:

```
[fluent-validation] 42 skip entries written to .rector-fluent-validation-skips.log — see for details
```

Gitignore both files if you enable verbose persistently — CI auto-fix workflows that commit dirty artifacts will otherwise pick them up on every run.

The log is a file sink because Rector's `withParallel(...)` executor doesn't forward worker STDERR to the parent. A diagnostic line written via `fwrite(STDERR, ...)` from a worker would vanish on parallel runs (Rector's default). A file sink survives worker death and you can inspect it from the project root after the run finishes. If you're writing your own Rector rule and want similar diagnostics, the same gotcha applies: `withParallel()` + STDERR means silent data loss.

> [!TIP]
> Rector caches per-file results. Files that hit a bail produce no transformation, so the skip entry is written once and the rule is not re-invoked on cached runs. To force every file to be revisited and every bail to be re-logged, run `vendor/bin/rector process --clear-cache` (or delete `.cache/rector*`).

> [!NOTE]
> `ConvertLivewireRuleAttributeRector` verifies the generated `rules(): array` is syntactically correct, but it can't prove the converted rule is behaviorally equivalent to the source attribute. If a converted Livewire component has no feature test covering validation, review the diff by hand and watch for dropped `message:` / explicit `onUpdate:` / `translate: false` args (logged to the skip file) that need manual migration to Livewire's `messages(): array` hook or project config. `messages:` (plural, not a Livewire-documented arg) surfaces its own "unrecognized, likely typo for `message:`?" log entry.

## Known limitations

- **Namespace-less files.** Classes at the file root without a `namespace` are silently skipped by the grouping and trait rectors. Laravel projects always use namespaces, so this rarely comes up in practice.
- **Rules built outside `rules(): array`.** The rector looks for `rules(): array`, `$request->validate([...])`, and `Validator::make([...])`. Rules built inside `withValidator()` callbacks, custom `rulesWithoutPrefix()` conventions, or Action-class `Collection::put()->merge()` chains are left alone.
- **Ternary rule strings.** `['nullable', $flag ? 'email' : 'url']` is left alone. A `->when(cond, thenFn, elseFn)` conversion is tractable in principle but wasn't worth it: three separate codebase audits turned up near-zero usage (single digits across a 100+ FormRequest corpus), and the closure-based fluent form loses the terseness users reach for ternaries to preserve. Use `Rule::when(...)` or branch the rules array outside the ternary instead.
- **`#[Validate(..., message: '...')]` / explicit `onUpdate: true` / `translate: false`.** These attribute args have no FluentRule builder equivalent. The rule string, `as:`/`attribute:` label, and `onUpdate: false` (consumed as a real-time-validation opt-out marker) are migrated; the remaining args are written to the skip log so you can migrate them to Livewire's `messages(): array` hook or project config manually. Array-form `message: [...]` is deferred to a future release.

## License

MIT
