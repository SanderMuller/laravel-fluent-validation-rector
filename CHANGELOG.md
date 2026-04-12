# Changelog

All notable changes to `sandermuller/laravel-fluent-validation-rector` will be documented in this file.

## 0.3.0 - 2026-04-12

### Added

#### Array-tuple rules lower directly to fluent method calls

Previously `['max', 65535]`, `['between', 3, 100]`, `['min', 4]` were wrapped in `->rule([...])` escape-hatch form even when the rule name mapped cleanly to a fluent modifier. Laravel treats the colon form (`'max:65535'`) and the tuple form (`['max', 65535]`) as equivalent, but the rector only lowered the colon form. `ValidationArrayToFluentRuleRector` now dispatches tuples through a new `buildModifierCallFromTupleExprArgs()` helper that reuses the existing rule-name-to-fluent-method mapping.

Covers `NUMERIC_ARG_RULES`, `TWO_NUMERIC_ARG_RULES`, `STRING_ARG_RULES`, and one-arg rules like `regex`, `format`, `startsWith`. Falls back to `->rule([...])` when the rule name isn't in the dispatch table, preserving prior behavior. Per-element lowering is preserved: mixed tuples + closure keep the closure as `->rule(fn)`.

```php
// Before
'author_notes' => ['nullable', 'string', ['max', 65535]]
// After (0.1.x)
'author_notes' => FluentRule::string()->nullable()->rule(['max', 65535])
// After (0.3.0)
'author_notes' => FluentRule::string()->nullable()->max(65535)

```
#### Flat wildcard `'items.*'` entries fold into parent `->each(<scalar>)`

`GroupWildcardRulesToEachRector` previously only collapsed dot-notation groups with nested wildcard children (`items.*.field`) or fixed children (`items.field`). A standalone `'items.*' => ...` entry stayed separate, even when the idiomatic form is `FluentRule::array()->each(FluentRule::field()->...)`. The rule now folds the flat wildcard's FluentRule chain into the parent as `->each(<scalar>)` rather than `->each([key => val, …])`.

Synthesizes a bare `FluentRule::array()` parent when no explicit parent exists. Handles const-concat wildcard keys (`self::VIDEO_IDS . '.*'`) via the existing constant-resolution pathway. Parent type is still validated: `each()` only attaches to `FluentRule::array()`.

```php
// Before
'interactions' => FluentRule::array(),
'interactions.*' => FluentRule::field()->filled(),
// After
'interactions' => FluentRule::array()->each(FluentRule::field()->filled()),

```
#### Skip trait insertion when an ancestor already declares it

Both trait rectors now walk the class's ancestor chain via `ReflectionClass` and skip insertion when any parent class already uses `HasFluentRules` or `HasFluentValidation`. Complements the existing `base_classes` configuration — codebases with a shared Livewire or FormRequest base don't need to enumerate every intermediate class for the rector to avoid redundant trait additions.

The reflection walk runs against the consumer project's autoloader, so it works whenever the parent class is loadable at rector-run time (effectively always for Laravel apps). Unloadable parents silently fall through to the "add trait" path, preserving prior behavior.

### Fixed

#### Synthesized `FluentRule::` references now use the short name

`GroupWildcardRulesToEachRector` previously emitted `\SanderMuller\FluentValidation\FluentRule::array()` (fully qualified) when synthesizing a parent or nested field wrapper. Pint's `fully_qualified_strict_types` fixer would clean it up, but pre-Pint output was noisy. The rector now emits `FluentRule::array()` (short) and inserts `use SanderMuller\FluentValidation\FluentRule;` at the alphabetically-sorted position when the import isn't already present.

#### Trait `use` imports insert alphabetically instead of prepending

0.1.1 routed the top-of-file trait import through Rector's `UseNodesToAddCollector`, whose `UseAddingPostRector` always prepends new imports regardless of alphabetical order. Pre-Pint output was worse than 0.1.0's (which inserted adjacent to existing `SanderMuller\…` imports). Both trait rectors now insert the `use` statement manually at the alphabetically-sorted position, falling back to "append after the last use" when the existing imports aren't already sorted (preserving intentional user ordering). Shared AST logic lives in a new `Concerns\ManagesNamespaceImports` trait consumed by all three rectors that synthesize imports.

#### PHPStan no longer fails on the `#[FluentRules]` attribute reference

The rector references `SanderMuller\FluentValidation\FluentRules` as a forward-compatible attribute class — it ships in newer `laravel-fluent-validation` releases but isn't present in every version satisfying the `^1.0` constraint. Switched from `FluentRules::class` to a string literal so static analysis doesn't trip on the optional reference. CI-only regression; no runtime behavior change.

### Regression tests locked in

#### `Rule::unique(Model::class)->withoutTrashed()` → fluent `->unique()` callback

The existing `convertChainedDatabaseRule()` pathway already converts `Rule::unique(...)->method()` and `Rule::exists(...)->method()` chains to the fluent callback form (`->unique($table, $column, fn ($rule) => $rule->method())`). An earlier report suggested this pattern wasn't working; verified it does. Added a fixture exercising the exact `Rule::unique(User::class)->withoutTrashed()` shape to prevent regression.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.1.1...0.3.0

## 0.2.0 - 2026-04-12

### Added

#### Array-tuple rules lower directly to fluent method calls

Previously `['max', 65535]`, `['between', 3, 100]`, `['min', 4]` were wrapped in `->rule([...])` escape-hatch form even when the rule name mapped cleanly to a fluent modifier. Laravel treats the colon form (`'max:65535'`) and the tuple form (`['max', 65535]`) as equivalent, but the rector only lowered the colon form. `ValidationArrayToFluentRuleRector` now dispatches tuples through a new `buildModifierCallFromTupleExprArgs()` helper that reuses the existing rule-name-to-fluent-method mapping.

Covers `NUMERIC_ARG_RULES`, `TWO_NUMERIC_ARG_RULES`, `STRING_ARG_RULES`, and one-arg rules like `regex`, `format`, `startsWith`. Falls back to `->rule([...])` when the rule name isn't in the dispatch table, preserving prior behavior. Per-element lowering is preserved: mixed tuples + closure keep the closure as `->rule(fn)`.

```php
// Before
'author_notes' => ['nullable', 'string', ['max', 65535]]
// After (0.1.x)
'author_notes' => FluentRule::string()->nullable()->rule(['max', 65535])
// After (0.2.0)
'author_notes' => FluentRule::string()->nullable()->max(65535)


```
Reported from a run against the hihaho codebase (20+ files).

#### Flat wildcard `'items.*'` entries fold into parent `->each(<scalar>)`

`GroupWildcardRulesToEachRector` previously only collapsed dot-notation groups with nested wildcard children (`items.*.field`) or fixed children (`items.field`). A standalone `'items.*' => ...` entry stayed separate, even when the idiomatic form is `FluentRule::array()->each(FluentRule::field()->...)`. The rule now folds the flat wildcard's FluentRule chain into the parent as `->each(<scalar>)` rather than `->each([key => val, …])`.

Synthesizes a bare `FluentRule::array()` parent when no explicit parent exists. Handles const-concat wildcard keys (`self::VIDEO_IDS . '.*'`) via the existing constant-resolution pathway. Parent type is still validated: `each()` only attaches to `FluentRule::array()`.

```php
// Before
'interactions' => FluentRule::array(),
'interactions.*' => FluentRule::field()->filled(),
// After
'interactions' => FluentRule::array()->each(FluentRule::field()->filled()),


```
Reported from a run against the hihaho codebase (15+ files).

### Fixed

#### Trait `use` imports insert alphabetically instead of prepending

0.1.1 routed the top-of-file trait import through Rector's `UseNodesToAddCollector`, whose `UseAddingPostRector` always prepends new imports regardless of alphabetical order. Pre-Pint output was worse than 0.1.0's (which inserted adjacent to existing `SanderMuller\…` imports). Both trait rectors now insert the `use` statement manually at the alphabetically-sorted position, falling back to "append after the last use" when the existing imports aren't already sorted (preserving intentional user ordering). The shared AST manipulation logic moves to a new `Concerns\ManagesTraitInsertion` trait consumed by both rectors.

Reported from runs against the mijntp and hihaho codebases.

#### PHPStan no longer fails on the `#[FluentRules]` attribute reference

The rector references `SanderMuller\FluentValidation\FluentRules` as a forward-compatible attribute class — it ships in newer `laravel-fluent-validation` releases but isn't present in every version satisfying the `^1.0` constraint. Switched from `FluentRules::class` to a string literal so static analysis doesn't trip on the optional reference. CI-only regression; no runtime behavior change.

## 0.1.1 - 2026-04-12

### Fixed

- `GroupWildcardRulesToEachRector` no longer injects `->nullable()` on a synthesized parent. Before, a rules array like `'keys.p256dh' => ...->required(), 'keys.auth' => ...->required()` was rewritten to `'keys' => FluentRule::array()->nullable()->children([...])`, which silently accepted payloads without `keys` at all — the `nullable()` short-circuited evaluation so the nested `required()` children never fired. The synthesized parent is now bare (`FluentRule::array()->children([...])`), restoring the original dot-notation semantics where missing `keys` triggers the nested `required` rules. Reported by a peer running 0.1.0 against the collectiq codebase.
- `children()` and `each()` arrays are now always printed one-key-per-line. Before, synthesized nested arrays collapsed onto a single line, producing 200+ character entries when child values contained further arrays (e.g. `->in([...])`) that Pint couldn't reflow. Multi-line printing is now forced via Rector's `NEWLINED_ARRAY_PRINT` attribute regardless of child complexity.
- `AddHasFluentRulesTraitRector` and `AddHasFluentValidationTraitRector` now emit a proper top-of-file `use` import for the trait and reference the short name inside the class body. Before, the rule emitted `use \SanderMuller\FluentValidation\HasFluentRules;` inline, relying on the consumer's `rector.php` to enable `withImportNames()` (or on Pint) to clean it up. The rule now queues the import via Rector's `UseNodesToAddCollector` directly, so out-of-the-box output is polished regardless of downstream formatter configuration.
- `AddHasFluentRulesTraitRector` and `AddHasFluentValidationTraitRector` now emit a blank line between the inserted trait and the next class member. Before, Livewire components whose first member was a docblocked property (`/** @var ... */\npublic array $foo = ...;`) had the trait glued directly to the docblock without separation. Pint doesn't rescue this unless the consumer opts into `class_attributes_separation.trait_import`, so the rule inserts a `Nop` statement to produce the blank line itself. Reported by a peer running 0.1.0 against the mijntp codebase.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.1.0...0.1.1

## 0.1.0 - 2026-04-12

Initial release.

### Added

- Rector rules for migrating Laravel validation to [sandermuller/laravel-fluent-validation](https://github.com/sandermuller/laravel-fluent-validation):
  
  - `ValidationStringToFluentRuleRector` — converts string-based rules (`'required|email|max:255'`) to the fluent API.
  - `ValidationArrayToFluentRuleRector` — converts array-based rules (`['required', 'email']`) to the fluent API.
  - `SimplifyFluentRuleRector` — collapses redundant or verbose fluent chains.
  - `GroupWildcardRulesToEachRector` — groups wildcard (`items.*`) rules into `FluentRule::each()` blocks.
  - `AddHasFluentRulesTraitRector` and `AddHasFluentValidationTraitRector` — adds the required traits to FormRequests, Livewire components, and custom validators.
  
- Set lists in `FluentValidationSetList` for applying rules individually or as a full migration pipeline.
  
- Covers `Validator::make()`, FormRequest `rules()`, Livewire `$rules` properties, and inline validator calls.
  
