# Changelog

All notable changes to `sandermuller/laravel-fluent-validation-rector` will be documented in this file.

## 0.4.0 - 2026-04-13

### Added

#### `ConvertLivewireRuleAttributeRector`

Livewire's validation attributes only accept const-expression arguments, so expressing FluentRule chains, closures, or custom rule objects inside them is impossible. The idiomatic migration is to move the rule into a `rules(): array` method where the full FluentRule API is available. This rector automates that migration.

```php
// Before
use Livewire\Attributes\Rule;
use Livewire\Component;

final class Settings extends Component
{
    #[Rule('nullable|email', as: 'notificatie e-mailadres')]
    public ?string $notification_email = '';
}

// After
use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;

final class Settings extends Component
{
    public ?string $notification_email = '';

    protected function rules(): array
    {
        return [
            'notification_email' => FluentRule::email()->nullable()->label('notificatie e-mailadres'),
        ];
    }
}

```
`use HasFluentValidation;` is added separately by `AddHasFluentValidationTraitRector` (the set list runs this rector before the trait rector).

**Handles:**

- Both short `#[Rule]`/`#[Validate]` and fully-qualified `#[\Livewire\Attributes\Rule]`/`#[\Livewire\Attributes\Validate]`.
- The `as:` named argument becomes `->label('...')` on the chain.
- Multiple properties in the same class collect into a single `rules(): array` method (appended in source order), emitted with one item per line via Rector's `NEWLINED_ARRAY_PRINT` attribute — readable regardless of item count.
- An existing `rules(): array` method with a simple `return [...]` is merged into (attribute rules appended); an existing `rules()` method with non-trivial control flow (conditional returns, logic) bails with a skip log — migrate manually.
- Form components (`extends \Livewire\Form`) work the same as regular components.

**Bail on hybrid classes.** Classes that declare `#[Rule]`/`#[Validate]` attributes AND call `$this->validate([...])` with an explicit array argument use the explicit args as the authoritative validation source — Livewire ignores attribute rules once `validate()` is called with explicit rules. Converting the attributes in such classes would produce a `rules(): array` method that's dead code (the explicit `validate([...])` bypasses it) and creates noisy diffs. The rector detects these classes via a `MethodCall name=validate + non-null first arg` scan and skips them with a log reason. Users can still consolidate manually.

**Dropped unsupported args.** The `message:` (singular), `messages:` (plural), and `onUpdate:` named attribute arguments have no direct FluentRule builder equivalents. The rule-string and `as:` label migrate; the unsupported args are dropped and logged via the package's skip-reason mechanism (visible with `FLUENT_VALIDATION_RECTOR_VERBOSE=1`). An in-source `// TODO:` comment beside the converted chain was planned but PhpParser's pretty-printer doesn't reliably render comments on sub-expressions inside array items — that's deferred to a follow-up release with a proper post-rector implementation.

**Array-form `#[Rule([...])]` attributes are deferred.** Array-based attribute arguments require sharing more of `ValidationArrayToFluentRuleRector`'s private helpers via the `ConvertsValidationRules` trait. Tractable; for now the rector logs a skip pointing to the property so manual migration is unambiguous.

Reported by [@kb7vilgo](https://github.com/) (mijntp) with a reference before/after from `InventorizationAdminSettings`. Pre-tag verification on collectiq's 7 `#[Rule]`-using files surfaced the hybrid-class pattern (5 of the 7) and the `message:` singular vs `messages:` plural Livewire-attribute-arg naming, both fixed before tagging.

### Changed

#### `FluentValidationSetList::CONVERT` now includes the new rector

Running `FluentValidationSetList::ALL` (or `CONVERT` alone) picks up attribute conversion automatically. No config change required.

#### Shared trait improvements

- `convertStringToFluentRule()` moved from `ValidationStringToFluentRuleRector` to the `ConvertsValidationRules` trait, so all three converters (string-form, array-form, attribute) share one implementation.
- `wrapInRuleEscapeHatch()` factored out; both callers (string converter + the new attribute path) reuse it.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.3.2...0.4.0

## 0.3.2 - 2026-04-12

### Fixed

#### `FluentValidationSetList::ALL` now actually applies all three rules

0.3.1 moved `ValidationStringToFluentRuleRector` and `ValidationArrayToFluentRuleRector` to register `Namespace_` as their single node type (so they could insert `use FluentRule;` at the alphabetically-sorted position). Unintended consequence: the converters, the grouping rector, and the trait rectors all competed for the same `Namespace_` instance on the same traversal pass, and Rector's format-preserving printer couldn't reconcile their concurrent mutations. Users running `FluentValidationSetList::ALL` saw only the converter's output — `GroupWildcardRulesToEachRector` silently no-op'd (flat wildcards stayed unfolded), and `AddHasFluentRulesTraitRector` silently no-op'd (no `use HasFluentRules;` added).

There was also a second failure mode: the converters emit a short `new Name('FluentRule')` reference, so the grouping rector's `getFluentRuleFactory()` matcher (checking against the fully-qualified `FluentRule::class`) and the trait rectors' `usesFluentRule()` detection both failed to recognize the converted chains.

The fix has three parts:

1. **Revert the converter node types** to the pre-0.3.1 set (`[ClassLike, MethodCall, StaticCall]`). The `use FluentRule;` import is now queued via Rector's `UseNodesToAddCollector` / `UseAddingPostRector` post-rector pipeline instead of sorted insertion. Consumers running Pint's `ordered_imports` fixer see the same final state as 0.3.0 (pre-Pint output is slightly less polished than 0.3.1, but no longer silently broken).
   
2. **Short-name tolerance in downstream rectors.** `GroupWildcardRulesToEachRector::getFluentRuleFactory()` and the trait rectors' `usesFluentRule()` now match both `SanderMuller\FluentValidation\FluentRule` (FQN) and `'FluentRule'` (short), so they recognize converter output within the same traversal pass.
   
3. **Full-pipeline regression test.** New `FullPipelineRectorTest` runs `FluentValidationSetList::ALL` end-to-end against a fixture that exercises the string → FluentRule → wildcard-fold → trait-insertion chain. This is the test that would have caught 0.3.1 before shipping — the existing per-rector configs only exercise one rule at a time and miss cross-rule interaction.
   

Reported by hihaho during 0.3.1 re-verification.

### Trade-off vs 0.3.1

0.3.1 emitted sorted imports from the converters (Pint was a no-op on converter-touched files). 0.3.2 prepends the import via `UseNodesToAddCollector` (Pint's `ordered_imports` fires once per converter-touched file). The trait rectors still use the sorted-insertion path from 0.3.0 / 0.3.1 (Pint no-op on trait-inserted imports).

The trade-off is: correct pipeline behavior (critical) vs Pint being a no-op on the converter pathway (nice-to-have). Since all consumers run Pint or `php-cs-fixer` in practice, the final output is unchanged. A future release may bring the "Pint no-op" property back once the Rector-framework interaction can be revisited without sacrificing pipeline correctness.

## 0.3.1 - 2026-04-12

### Fixed

#### Converter-pathway `FluentRule::` references now use the short name

`ConvertsValidationRules::buildFluentRuleFactory()` (shared by `ValidationStringToFluentRuleRector` and `ValidationArrayToFluentRuleRector`) used to emit `new FullyQualified(FluentRule::class)` for the initial factory call. Pint's `fully_qualified_strict_types` fixer cleaned it up, but pre-Pint output was noisy. The helper now emits `new Name('FluentRule')` and auto-inserts `use SanderMuller\FluentValidation\FluentRule;` at the alphabetically-sorted position when the import isn't already present.

Mirrors the 0.3.0 fix on `GroupWildcardRulesToEachRector`. Now every rector in this package emits consistent, Pint-free output:

- String/array converters (this release)
- Grouping rector's synthesized parent/field (0.3.0)
- Trait rectors' inserted `use` import (0.3.0 via `ManagesNamespaceImports`)

```php
// Before (0.3.0 output, pre-Pint)
'author_notes' => \SanderMuller\FluentValidation\FluentRule::string()->nullable()->max(65535),

// After (0.3.1 output, pre-Pint)
'author_notes' => FluentRule::string()->nullable()->max(65535),



```
Reported by hihaho (gap note during 0.3.0 re-verification) and collectiq (Nit A).

### Changed

#### `ValidationStringToFluentRuleRector` and `ValidationArrayToFluentRuleRector` now register `Namespace_` as their node type

Previously they registered `[ClassLike, MethodCall, StaticCall]` — three separate entry points for `rules()` methods, `$request->validate([...])` calls, and `Validator::make([...])` calls. Now they register `[Namespace_]` and traverse the subtree internally, which lets them insert the `FluentRule` import once per namespace at the correct position.

Test configs for both rectors no longer use `withImportNames()` — the rectors produce sorted output on their own.

No behavior change for end users: the same three call patterns are detected and converted. Classes without a namespace (rare in Laravel projects) are now skipped; document as a known limitation.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.3.0...0.3.1

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
  
