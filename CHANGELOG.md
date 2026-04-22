# Changelog

All notable changes to `sandermuller/laravel-fluent-validation-rector` will be documented in this file.

## 0.8.0 - 2026-04-22

Two feature bundles ship together: opt-in `messages()` migration for Livewire `#[Validate]` attributes, and full coverage of the `laravel-fluent-validation` 1.19.0 surface delta across three rectors.

### Livewire `#[Validate]` `message:` → `messages()` migration (opt-in)

New `MIGRATE_MESSAGES` config flag on `ConvertLivewireRuleAttributeRector` (default `false`). When enabled, `message:` attribute args migrate into a generated `messages(): array` method alongside `rules()`:

- String form: `#[Validate('required|email', message: 'Bad email.')]` → `'<prop>' => 'Bad email.'` (whole-attribute key per Livewire's documented behaviour — Laravel matches the attribute-only key against any rule failing on the attribute).
- Array form: `message: ['required' => 'X', 'min' => 'Y']` → `'<prop>.required' => 'X'`, `'<prop>.min' => 'Y'`. Keys already containing `.` (full-path forms used with keyed-array first-arg attributes) pass through verbatim.

Opt-in because the generated method expands the class surface and some consumers centralize messages in lang files. Default-off keeps legacy skip-log behaviour intact.

Safety: preflight check bails the whole conversion (leaving `#[Validate]` intact) when an existing non-trivial `messages()` method can't be safely merged — multi-return, conditional, builder-loop, and `return $cached` shapes all qualify. Per-property anchoring matches `extractAndStripRuleAttribute`'s first-CONVERTIBLE-wins so a non-literal first attribute can't orphan a later attribute's `message:` arg. Per-attribute migration tracking via `spl_object_id` keyset means the legacy skip-log still fires for non-migrateable shapes (non-literal values, mixed arrays).

```php
return RectorConfig::configure()
    ->withConfiguredRule(ConvertLivewireRuleAttributeRector::class, [
        ConvertLivewireRuleAttributeRector::MIGRATE_MESSAGES => true,
    ]);

```
### `laravel-fluent-validation` 1.19.0 surface support

Three rectors grow together for the upstream 1.19.0 delta (11 new `FluentRule::*` shortcut factories + new `DeclinedRule` class + 4 `NumericRule` sign helpers). Composer floor bumped to `^1.19`.

**Converters (CONVERT set)** — `ValidationStringToFluentRuleRector` / `ValidationArrayToFluentRuleRector`:

- 9 new tokens recognized as direct factory shortcuts: `'ipv4'`, `'ipv6'`, `'mac_address'`, `'json'`, `'timezone'`, `'hex_color'`, `'active_url'`, `'list'`, `'declined'`.
- Sibling-token promotion: `'string|ipv4'` → `FluentRule::ipv4()` (not the verbose `string()->ipv4()`). Converters emit the final factory form directly because `SIMPLIFY` isn't in the default `ALL` set, so users on the default pipeline don't depend on a follow-up cleanup pass. Same pattern for `'array|list'`.
- Type-source tracking prevents over-promotion: chain-derived types (`Rule::string()->alpha()`, `Password` chains, `Email` rule objects) keep their extracted chain ops in Pass 2 — only plain string-token types promote.

**Cleanup (SIMPLIFY set)** — `SimplifyFluentRuleRector`:

- Factory-shortcut collapse for the 9 new factories (zero-arg) plus `regex` and `enum` (arg-passthrough). `string()->ipv4()` → `ipv4()`, `string()->regex($p)` → `regex($p)`, `field()->enum($t)` → `enum($t)`.
- Conservative arg-passthrough gate: only fires when the source factory is arg-less AND the chain has no `label()` call (positional-slot threading is out of v1 scope; the gate prevents silent label loss).
- Label-promotion path extended to the new factories: `string()->label('Address')->ipv4()` → `ipv4('Address')` already collapses end-to-end via the existing pipeline.
- Redundant-call removal extended: `FluentRule::ipv4()->ipv4()` collapses.

**Escape-hatch rewrite (SIMPLIFY set)** — `SimplifyRuleWrappersRector`:

- `enum` rewrite: `->rule(Rule::enum(X::class))` / `->rule(['enum', X::class])` → `->enum(X::class)` on the 5 `HasEmbeddedRules` consumers (String / Numeric / Email / Date / Field). `Rule::enum(X, $cb)` multi-arg bails (callback can't be threaded through the single-method form).
- Literal-zero comparisons: `'gt:0'` → `->positive()`, `'gte:0'` → `->nonNegative()`, `'lt:0'` → `->negative()`, `'lte:0'` → `->nonPositive()` (NumericRule only). Non-zero literals (`'gt:5'`), field references (`'gt:other_field'`), and broader spellings (`'gt:00'`, `'gt:-0'`) stay as the escape hatch — exact-zero match is the documented contract.

### Refactor

`SimplifyFluentRuleRector::simplifyChain` and `ConvertsValidationRuleArrays::convertArrayToFluentRule` cognitive complexity dropped below the PHPStan cap by extracting `tryFactoryShortcuts*` / `tryRemoveRedundantTypeCalls` / `tryPromoteLabelToFactoryArg` / `tryFoldMinMaxIntoBetween` / `chainHasLabelCall` and `detectArrayRuleType` helpers. Stale baseline ignore for the old simplifyChain complexity removed.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.7.0...0.8.0

## 0.7.0 - 2026-04-21

New `SimplifyRuleWrappersRector` (under the existing opt-in `SIMPLIFY` set) rewrites escape-hatch `->rule(...)` calls into native fluent methods on the correct typed-rule subclass.

### What it rewrites

| From | To | Receivers |
|---|---|---|
| `->rule(Rule::in([…]))` / `->rule('in:a,b')` / `->rule(['in', 'a', 'b'])` | `->in([…])` | String / Numeric / Email / Field / Date |
| same shapes for `notIn` / `not_in` | `->notIn([…])` | same |
| `->rule('min:N')` / `->rule(['min', N])` | `->min(N)` | String / Numeric / Array / File / Password |
| `->rule('max:N')` / `->rule(['max', N])` | `->max(N)` | + Email |
| `->rule('between:L,U')` / `->rule(['between', L, U])` | `->between(L, U)` | String / Numeric / Array / File |
| `->rule('size:N')` / `->rule(['size', N])` | `->exactly(N)` (intentional rename per `TypedBuilderHint`) | String / Numeric / Array / File |
| `->rule('regex:/…/')` / `->rule(['regex', '/…/'])` | `->regex('/…/')` | String only |

### Set wiring

`SIMPLIFY` registers `SimplifyRuleWrappersRector` after `SimplifyFluentRuleRector` so factory shortcuts (`string()->url()` → `url()`) collapse first. `ALL` deliberately does not include `SIMPLIFY`.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.6.1...0.7.0

## 0.6.1 - 2026-04-20

A real-world dry-run of 0.6.0 against a production Laravel codebase (~43 `rules()` methods) returned 0 emits / 43 skips — every method had a pre-existing `@return array<string, DataAwareRule>` / `DataAwareRule[]` docblock that 0.6.0 treated as "user-customized — respecting". 0.6.1 recognizes Laravel validation-contract annotations as safe-to-narrow-from, plus two AST-level fixes surfaced by the same dry-run.

### Laravel validation contracts accepted as narrow-from shapes

`UpdateRulesReturnTypeDocblockRector` now narrows `@return` annotations whose body references any Laravel validation contract, in both generic-array and `T[]`-shorthand forms:

- `array<string, ValidationRule>` / `array<string, \Illuminate\Contracts\Validation\ValidationRule>`
- `array<string, DataAwareRule>` / `array<string, \Illuminate\Contracts\Validation\DataAwareRule>`
- `array<string, ValidatorAwareRule>` / `array<string, \Illuminate\Contracts\Validation\ValidatorAwareRule>`
- `array<string, ImplicitRule>` / `array<string, \Illuminate\Contracts\Validation\ImplicitRule>`
- `array<string, Rule>` / `array<string, \Illuminate\Contracts\Validation\Rule>`
- `DataAwareRule[]`, `ValidationRule[]`, etc. (the older PHPStan `T[]` shorthand)

Safety: every `*Rule` class shipped by `sandermuller/laravel-fluent-validation` implements all four Laravel contracts (`DataAwareRule, FluentRuleContract, ValidationRule, ValidatorAwareRule`), and the polish rule's condition 3 has already proven every array item is a FluentRule chain before this matcher runs. Narrowing from any of the listed Laravel contracts to `FluentRuleContract` therefore drops no valid type at the item level.

Unconditional — no opt-in flag. POLISH is itself opt-in; expanding the narrow-from set inside it is an implementation detail of "polish narrows the docblock". Users who authored one of the listed Laravel-contract annotations and did **not** want POLISH to touch it should not run POLISH.

Behavior change from 0.6.0: annotations like `@return array<string, ValidationRule>` that were previously respected as user-customized are now narrowed. Idempotency preserved — second-pass runs are no-ops.

### Bug: `Concat`-keyed array items are now recognized

Livewire nested-field idiom `'credentials.' . Class::CONST => FluentRule::...()` produces a `BinaryOp\Concat` key node rather than a single `String_`. 0.6.0 rejected these as "not a statically-known string" and skipped the method.

0.6.1 walks the `Concat` tree recursively and accepts it when every leaf is `String_` or `ClassConstFetch`, covering arbitrarily-nested concatenations like `'prefix.' . A::X . '.suffix'`.

### Bug: misleading skip-log reason for spread items

`...$foo` inside an array is represented by php-parser 5 as `ArrayItem{value, key=null, unpack=true}`. The 0.6.0 skip path checked `key === null` before `unpack === true`, emitting `"ArrayItem key at index N is not String_ / ClassConstFetch"` — technically true (null is neither), but the real disqualifier is the spread itself.

0.6.1 checks `unpack` first and logs `"encountered spread at index N — cannot determine keys statically"`. Skip behavior unchanged; only the diagnostic improves. `skip_spread_in_return.php.inc` fixture re-covers the flipped order.

### Fixtures

Added 4 fixtures: `all_fluent_laravel_data_aware_rule`, `all_fluent_laravel_data_aware_rule_shorthand`, `all_fluent_laravel_validation_rule_fqn`, `all_fluent_concat_key`. Updated `skip_user_annotation` to use a genuinely non-Laravel-contract type (`\App\Validation\DomainRuleContract`) so the "respect user-customized" coverage survives.

Matrix now at **50 fixtures** (22 emit + 28 skip). 259 tests / 312 assertions / 0 failures / PHPStan clean.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.6.0...0.6.1

## 0.6.0 - 2026-04-20

### New opt-in polish rule: `UpdateRulesReturnTypeDocblockRector`

Narrows the `@return` PHPDoc annotation on `rules()` methods from the wide `array<string, ValidationRule|string|array<mixed>>` union down to `array<string, \SanderMuller\FluentValidation\Contracts\FluentRuleContract>` when every value in the returned array is a `FluentRule::*()` call chain. Cosmetic — runtime behavior is untouched — but gives PHPStan and editors a narrower type to reason about after migration.

Rule ships behind a new `FluentValidationSetList::POLISH` set. **Not** in `ALL`; opt-in per-invocation:

```php
return RectorConfig::configure()
    ->withPaths([__DIR__ . '/app'])
    ->withSets([FluentValidationSetList::POLISH]);




```
#### What qualifies

The rule fires on a `rules()` method when **all five** hold:

1. Method is named `rules` with non-nullable `: array` return type (not `?array`, not union).
2. Body has exactly one `Return_` statement whose expression is a literal `Array_` (not a builder variable, not `RuleSet::from(...)`, not a collection pipeline — multi-return methods disqualify too).
3. Every `ArrayItem` has a string-typed key (`String_` or `ClassConstFetch`) and a value whose innermost `MethodCall->var` walk ends at `FluentRule::*()` (alias imports `use FluentRule as FR` resolve via Rector's `NodeNameResolver`).
4. Existing `@return` annotation (if any) is absent, `@return array` exactly, or the wide-union this package's converters emit — optionally followed by pure-prose description. User-authored unions/intersections/generics, and `@inheritDoc`, are respected.
5. Class context: extends `FormRequest` anywhere in the ancestor chain (aliased imports resolved) **or** uses `HasFluentRules` / `HasFluentValidation` / `HasFluentValidationForFilament` directly on the class or via any ancestor.

Skipped with a logged reason when any of the above fails.

#### Why opt-in

This is a polish rule, not a correctness migration. It runs against files that `CONVERT` / `GROUP` / `TRAITS` have already produced. Rector's multi-pass convergence handles it, but for clarity:

> Run `POLISH` as a separate invocation after `CONVERT` stabilizes. Single-pass runs that mix `CONVERT` + `POLISH` may need a second invocation if any file had string-rule items mid-convert.

40 fixtures lock the emit/skip behavior — see `tests/UpdateRulesReturnTypeDocblock/Fixture/` for the full matrix including intermediate-base-request ancestors, aliased imports, `when()` / `each()` / mid-chain-closure variants, widened unions that must be respected, collection-pipeline returns that must be skipped, and the `$this->passwordRules()` Fortify trait pattern.

### Shared-helper additions

Both helpers added to `SanderMuller\FluentValidationRector\Rector\Concerns\DetectsInheritedTraits`, designed to be consumed by future rules that need alias-safe / current-class-inclusive ancestry checks:

- **`anyAncestorExtends(Class_ $class, string $fqn): bool`** — mirrors the existing `anyAncestorUsesTrait` but for class-extension checks, and resolves `$class->extends` through `$this->getName(...)` before `class_exists`. Closes the alias-blindness gap the raw-`toString()` path has (`use FormRequest as BaseRequest; class Foo extends BaseRequest` now resolves).
- **`currentOrAncestorUsesTrait(Class_ $class, string $traitFqn): bool`** — inspects the current class's `TraitUse` nodes first before walking ancestors. Closes the parentless-class-with-direct-trait-use gap: `class Livewire { use HasFluentValidation; }` now qualifies regardless of inheritance.

The legacy `anyAncestorUsesTrait` stays as-is for backward compatibility with `AddHasFluentRulesTraitRector` and `AddHasFluentValidationTraitRector` callers; a follow-up alias-fix is a candidate for a separate release.

### Exposed constants on `NormalizesRulesDocblock`

`STANDARD_RULES_ANNOTATION_BODY` and `RETURN_TAG_PATTERN` flipped from `private` to `protected` so polish rules can consume them without duplicating the canonical wide-union literal or the PHPDoc continuation-aware regex.

New helper on the same trait:

- **`annotationBodyMatchesStandardUnionExactlyOrProse(string $body): bool`** — decides whether an extracted `@return` body is safe to narrow. Accepts the exact standard body optionally followed by whitespace-only or pure-prose tails; rejects any trailing type-syntax (`|`, `&`, `<`, `>`, `(`, `)`, `[`, `]`, `@`, `\`-FQN). Without this guard, widened unions like `STANDARD_BODY|\Illuminate\Support\Collection` would silently lose the additive `|Collection` member on narrow — a type-lie regression caught by Codex adversarial review during spec.

### Composer requirement bumped

- `sandermuller/laravel-fluent-validation`: `^1.8.1` → `^1.17`.

1.17 is the version that shipped the `FluentRuleContract` interface this rule narrows to. Keeping the floor at 1.17 matches the supported matrix. Non-POLISH rules have no direct 1.17 surface dependency, so if you're downstream on 1.8.1 and not planning to run POLISH, the old constraint would still work in practice — but the rector package no longer supports that configuration as a tested pair.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.5.3...0.6.0

## 0.5.3 - 2026-04-17

### 0.5.3

First release shipping the implementation behind the 0.5.0–0.5.2 CHANGELOG entries. The 0.5.0/0.5.1/0.5.2 tags pointed at CHANGELOG-only commits; the actual source for the skip-log flip and the attribute-context rule-object conversion landed in this commit.

#### What's in here

Everything described in the 0.5.0 entry (skip-log off by default, opt-in via `FLUENT_VALIDATION_RECTOR_VERBOSE=1`) and the 0.5.2 entry (attribute-context `new Password()` / `new Unique()` / `new Exists()` → FluentRule chains; legacy cwd log swept on every default-mode run) is now actually present in the shipped code.

Summary of new surface over 0.4.19:

- `SanderMuller\FluentValidationRector\Diagnostics` — env gate + path resolver for the skip log (verbose cwd, default tmp).
- `SanderMuller\FluentValidationRector\RunSummary::unlinkLogArtifacts()` — idempotent cleanup of both verbose and off-mode paths; called on parent init and in the shutdown closure after emit.
- `ConvertLivewireRuleAttributeRector` now lowers constructor-form rule objects in `#[Validate([...])]`:
  - `new Password($n)` → `FluentRule::password($n)`
  - `new Unique(...)` → `->unique(...)`
  - `new Exists(...)` → `->exists(...)`
    Non-attribute `rules()` arrays preserve the `->rule(new X(...))` escape hatch (scope-leak guard).
  

#### If you pinned 0.5.0, 0.5.1, or 0.5.2

Those tags shipped CHANGELOG text without the matching code — running a pinned 0.5.x composer install before 0.5.3 gives you 0.4.19-level behavior. Upgrade to `^0.5.3` to actually receive the documented changes.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.5.2...0.5.3

## 0.5.2 - 2026-04-17

Two changes on top of 0.5.0's skip-log flip: attribute-context constructor-form rule objects now lower to proper FluentRule chains, and the skip-log cleanup reaches legacy artifacts so upgrading consumers see a clean project root.

### `new Password(...)` / `new Unique(...)` / `new Exists(...)` in attribute context

Livewire `#[Validate([...])]` attribute args are const-expr, which forbids `Password::min(8)` / `Rule::unique(...)` — the shapes the rector already converts cleanly in `rules()` arrays. Consumers had to fall back to constructor form (`new Password(8)`, `new Unique('users', 'email')`), and the rector preserved them as-is on the `->rule(new X(...))` escape hatch.

0.5.2 lowers them like their static-factory counterparts:

```php
// Before
#[Validate(['required', new Password(8)])]
public string $password = '';

// After
protected function rules(): array
{
    return [
        'password' => FluentRule::password(8)->required(),
    ];
}






```
Same for `new Unique(...)` → `->unique(...)` and `new Exists(...)` → `->exists(...)` against `Illuminate\Validation\Rules\Unique` / `Exists` (matching the existing `Rule::unique(...)` / `Rule::exists(...)` conversion).

**Preserved behavior.** Constructor-form rule objects inside regular `rules()` arrays still route to the escape hatch:

```php
// FormRequest::rules() — unchanged
'password' => ['required', new Password(8)],
// → FluentRule::field()->required()->rule(new Password(8))






```
The detection is gated on a state flag set by the calling rector (`ConvertLivewireRuleAttributeRector` passes `inAttributeContext: true`; `ValidationArrayToFluentRuleRector` doesn't). This closes the original scope-leak concern that parked the feature: detecting `new Password()` globally would silently rewrite intentional constructor-form code in method arrays.

### Skip-log cleanup reaches legacy artifacts

0.5.0's parent-init cleanup only swept the current-mode path, so a `.rector-fluent-validation-skips.log` inherited from a 0.4.x install (or left behind by a verbose-mode run before flipping back to default) persisted in the project root — the exact CI-dirty-artifact problem 0.5.0 was meant to solve.

`RunSummary::unlinkLogArtifacts()` now sweeps both verbose (cwd) and off-mode (tmp) paths with their sentinels on every parent-init pass. A fresh default run on a 0.4.x-upgraded consumer now drops the legacy log automatically.

Also small polish: `RunSummary::format()` is now side-effect-free (cleanup moved to the shutdown closure after emit), the per-run cleanup helper is DRY'd into `unlinkLogArtifacts()`, and the end-of-run hint includes `--clear-cache` so the suggested re-run command is actionable as-copied (Rector caches bail results per file).

### Notes

No API changes. Configuration surface unchanged. No migration required for 0.5.x consumers; 0.4.x upgraders will see the legacy log disappear on first run.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.5.1...0.5.2

## 0.5.1 - 2026-04-17

Attribute-context `new Password(...)` / `new Rule\Unique(...)` / `new Rule\Exists(...)` now convert to proper FluentRule chains instead of the `->rule(new X(...))` escape hatch.

### Why

Livewire `#[Validate([...])]` attribute args are const-expr, which forbids the static-factory forms (`Password::min(8)`, `Rule::unique(...)`) that the rector already converts cleanly in `rules()` arrays. Consumers using attribute-form validation were stuck with the verbose `->rule()` wrapper even though the same rule in method-form arrays got the nice FluentRule lowering. The gap was parked on scope-leak concerns — detecting `new Password()` globally would rewrite intentional constructor-form code in `rules()` arrays too.

### Behavior

The converter now tracks whether it's running inside an attribute context. When yes:

```php
// Before
#[Validate(['required', new Password(8)])]
public string $password = '';

// After (0.5.1)
protected function rules(): array
{
    return [
        'password' => FluentRule::password(8)->required(),
    ];
}







```
Same shape for `new Unique(...)` and `new Exists(...)` against `Illuminate\Validation\Rules\Unique` / `Exists`, lowered to `->unique(...)` / `->exists(...)` chain methods (matching the existing `Rule::unique(...)` conversion).

### What's preserved

Non-attribute `rules()` arrays are unchanged — `new Password(8)` stays on the `->rule(new Password(8))` escape hatch. The scope-leak fix is a state flag threaded in from the calling rector (`ConvertLivewireRuleAttributeRector` passes `inAttributeContext: true`; `ValidationArrayToFluentRuleRector` doesn't). No PARENT_NODE walking; Rector 2.x no longer populates it by default, so explicit context is cheaper and more reliable.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.5.0...0.5.1

## 0.5.0 - 2026-04-17

### 0.5.0

Skip-log default flipped: **off by default**, opt-in via env. No rule-behavior changes.

#### Why

Consumer CI pipelines running auto-fix workflows (mijntp's in particular) were picking up `.rector-fluent-validation-skips.log` and `.log.session` as dirty artifacts every rector run and trying to commit+push them — blocked on protected branches, broke the pipeline. Each consumer previously had to know to gitignore both files. Flipping the default shifts that burden: fresh consumers get zero artifacts in their project root; users who actually want the per-entry breakdown opt in explicitly.

#### Behavior

Default runs still count skips and the end-of-run summary reports the total, but writes go to a cwd-hash-scoped path under `sys_get_temp_dir()` that the summary reader unlinks after emitting its hint. No file ever surfaces in the consumer's project root.

```
[fluent-validation] 42 skip entries. Re-run with FLUENT_VALIDATION_RECTOR_VERBOSE=1 and --clear-cache for details.








```
Opt in by exporting the env var before running Rector:

```bash
FLUENT_VALIDATION_RECTOR_VERBOSE=1 vendor/bin/rector process --clear-cache








```
Env-only is deliberate — the flag has to reach parallel workers (fresh PHP processes spawned via `proc_open`) and shell-exported env inherits automatically, while in-process mutation would not. With verbose on, the log lands in the project root as before and the summary references it:

```
[fluent-validation] 42 skip entries written to .rector-fluent-validation-skips.log — see for details








```
#### Migration

If you previously relied on the log appearing in your project root, export `FLUENT_VALIDATION_RECTOR_VERBOSE=1` in whichever shell runs Rector. If you had gitignore entries for `.rector-fluent-validation-skips.log*`, you can leave them in place — they only apply on verbose runs now.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.19...0.5.0

## 0.4.19 - 2026-04-15

Closes three latent correctness gaps in `ConvertLivewireRuleAttributeRector` that had been documented in the array-form spec since 0.4.17. No main-package, trait-selection, or Filament changes.

### Keyed-array `#[Validate]` now expands per-key

Livewire v3's keyed-array attribute shape used to silently produce wrong output — the converter ignored `ArrayItem` keys and collapsed all values into one chain, dropping the `.*` wildcard scoping entirely.

```php
// Before (0.4.18): silent wrong output, keys ignored
#[Validate(['todos' => 'required|array', 'todos.*' => 'string|min:3'])]
public array $todos = [];

// After (0.4.19): per-key expansion into rules()
public function rules(): array
{
    return [
        'todos' => FluentRule::array()->required(),
        'todos.*' => FluentRule::string()->min(3),
    ];
}









```
Flat `.*` entries pass through `GroupWildcardRulesToEachRector` downstream for nested `->each(...)` folding. Fails closed on unconvertible values, numeric-string keys, and mixed keyed/positional shapes with a skip-log entry.

### `#[Validate]` marker preserved to keep real-time validation

Stripping `#[Validate]` used to silently regress `wire:model.live` on-property-update validation — Livewire fires real-time only when a `#[Validate]` attribute is present on the property. 0.4.19 keeps an empty `#[Validate]` marker after conversion:

```php
// After (0.4.19): rules() generated, empty marker preserved
#[Validate]
public string $name = '';

protected function rules(): array { /* ... */ }









```
Deprecated `#[Rule]` (not `#[Validate]`) strips cleanly without a marker — the rector's scope is FluentRule migration, not the `#[Rule]` → `#[Validate]` upgrade. `#[Validate(onUpdate: false)]` also strips cleanly; if any `#[Validate]` on the property opts out of real-time, the marker is suppressed (aggregate veto, not first-wins).

**Opt out** on codebases that don't use `wire:model.live`:

```php
ConvertLivewireRuleAttributeRector::class => [
    ConvertLivewireRuleAttributeRector::PRESERVE_REALTIME_VALIDATION => false,
]









```
### `as:` / `attribute:` recognised as synonyms

Both named args map to `->label()`. Array-form (`as: [key => label]`, `attribute: [key => label]`) applies per-entry across keyed-first-arg expansions. On conflict, `attribute:` wins over `as:` — precedence is deterministic and independent of source ordering.

### Named-args surface corrected

- `messages:` (plural) no longer classified as a dropped known arg. It wasn't a Livewire-documented shape in the first place; now logged as `unrecognized arg; likely typo for message:?`.
- `translate: false` added to the dropped list (no FluentRule equivalent).
- Array-form `message: [...]` gets a dedicated "deferred to a future release (messages() method generation)" log entry.
- `onUpdate: false` now consumed as the marker-veto signal from the section above; other explicit `onUpdate` values stay on the dropped list.

### New concerns

Six shared concerns extracted along the way to keep the host rector under the PHPStan cognitive-complexity cap:

- `ExpandsKeyedAttributeArrays`, `ExtractsLivewireAttributeLabels`, `ReportsLivewireAttributeArgs`, `ResolvesRealtimeValidationMarker`, `ResolvesInheritedRulesVisibility`, `DetectsLivewireRuleAttributes`.

### Config surface

Two rules now accept configuration via `withConfiguredRule()`:

- `ConvertLivewireRuleAttributeRector::PRESERVE_REALTIME_VALIDATION` (bool, default `true`)
- `AddHasFluentRulesTraitRector::BASE_CLASSES` (list of strings) — existing, documented in the README for the first time

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.18...0.4.19

## 0.4.18 - 2026-04-15

Quality-of-life release. No trait-selection or main-package changes — 0.4.16's Filament `insteadof` emission is unchanged.

### Stale `@return` docblock on mutated `rules()` method

Body-mutation rectors used to rewrite the `rules()` array but leave the surrounding `@return` annotation untouched, producing type-lies like `@return array<string, StringRule>` above a body that returned `array<string, ArrayRule>`.

Four rectors now normalize the annotation: `ConvertLivewireRuleAttributeRector` (merge path), `ValidationArrayToFluentRuleRector`, `ValidationStringToFluentRuleRector`, `GroupWildcardRulesToEachRector`. When the `@return` body references a FluentRule-family concrete type (`StringRule`, `ArrayRule`, `NumericRule`, etc.), the whole tag — including multi-line continuations — is replaced with:

```
@return array<string, ValidationRule|string|array<mixed>>










```
This matches the annotation fresh-emitted on newly-generated `rules()` methods, so every `rules()` method this package touches now carries the same `@return` shape.

Broad or unrelated annotations are preserved (`@return array<string, mixed>`, `@return array`, `@return FooBar`). Staleness is evaluated only against the `@return` tag body — a description line elsewhere in the docblock mentioning `StringRule` does not trigger replacement.

### Hybrid-bail silent on non-candidate classes

The `ConvertLivewireRuleAttributeRector` hybrid-bail used to fire on any class with a `$this->validate([...])` call, regardless of whether the class had `#[Rule]` / `#[Validate]` attributes to migrate. On a production app that meant dozens of spurious skip-log entries on Actions, FormRequests, Controllers, and DataObjects with unrelated `validate()` methods.

Now the rector bails silently on any class without Livewire rule attributes. Genuine hybrid cases — attributes AND explicit `$this->validate([...])` — still skip-log as before.

### Removed `vendor/bin/fluent-validation-migrate`

The regex-based migrator shipped in 0.4.16 corrupted multi-trait `use X, Y, Z { … }` blocks: it renamed the top-level import but left short-name references inside the class body pointing at the old name, producing a `Trait "…HasFluentValidation" not found` fatal-at-load. Regex matching can't reliably handle the trait-use block structure.

The CLI is removed entirely. The `bin` entry in `composer.json` is dropped. A safe AST-based replacement was scoped for this release but pruned — the narrow `1.7.x → 1.8.1` upgrade window doesn't justify new migration infrastructure at this point. The edge case is documented in the README's Known Limitations section with a concise hand-fix recipe.

**If you ran 0.4.16's CLI on a codebase:** verify each converted file still loads. The single-trait-block happy path worked correctly; only multi-trait blocks with existing `insteadof` were corrupted.

### Under the hood

Three extracted concerns to keep rector class complexity under the PHPStan cognitive-complexity limit:

- `DetectsLivewireRuleAttributes` — `#[Rule]` / `#[Validate]` detection (FQN + short alias).
- `IdentifiesLivewireClasses` — parent-class or `render()`-method heuristic.
- `NormalizesRulesDocblock` — scoped `@return` rewriter with multi-line support.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.17...0.4.18

## 0.4.17 - 2026-04-15

Quality-of-life release. No trait-selection or main-package changes — 0.4.16's Filament `insteadof` emission is unchanged.

### Stale `@return` docblock on mutated `rules()` method

Body-mutation rectors used to rewrite the `rules()` array but leave the surrounding `@return` annotation untouched, producing type-lies like `@return array<string, StringRule>` above a body that returned `array<string, ArrayRule>`.

Four rectors now normalize the annotation: `ConvertLivewireRuleAttributeRector` (merge path), `ValidationArrayToFluentRuleRector`, `ValidationStringToFluentRuleRector`, `GroupWildcardRulesToEachRector`. When the `@return` body references a FluentRule-family concrete type (`StringRule`, `ArrayRule`, `NumericRule`, etc.), the whole tag — including multi-line continuations — is replaced with:

```
@return array<string, ValidationRule|string|array<mixed>>











```
This matches the annotation fresh-emitted on newly-generated `rules()` methods, so every `rules()` method this package touches now carries the same `@return` shape.

Broad or unrelated annotations are preserved (`@return array<string, mixed>`, `@return array`, `@return FooBar`). Staleness is evaluated only against the `@return` tag body — a description line elsewhere in the docblock mentioning `StringRule` does not trigger replacement.

### Hybrid-bail silent on non-candidate classes

The `ConvertLivewireRuleAttributeRector` hybrid-bail used to fire on any class with a `$this->validate([...])` call, regardless of whether the class had `#[Rule]` / `#[Validate]` attributes to migrate. On a production app that meant dozens of spurious skip-log entries on Actions, FormRequests, Controllers, and DataObjects with unrelated `validate()` methods.

Now the rector bails silently on any class without Livewire rule attributes. Genuine hybrid cases — attributes AND explicit `$this->validate([...])` — still skip-log as before.

### Removed `vendor/bin/fluent-validation-migrate`

The regex-based migrator shipped in 0.4.16 corrupted multi-trait `use X, Y, Z { … }` blocks: it renamed the top-level import but left short-name references inside the class body pointing at the old name, producing a `Trait "…HasFluentValidation" not found` fatal-at-load. Regex matching can't reliably handle the trait-use block structure.

The CLI is removed entirely. The `bin` entry in `composer.json` is dropped. A safe AST-based replacement was scoped for this release but pruned — the narrow `1.7.x → 1.8.1` upgrade window doesn't justify new migration infrastructure at this point. The edge case is documented in the README's Known Limitations section with a concise hand-fix recipe.

**If you ran 0.4.16's CLI on a codebase:** verify each converted file still loads. The single-trait-block happy path worked correctly; only multi-trait blocks with existing `insteadof` were corrupted.

### Under the hood

Three extracted concerns to keep rector class complexity under the PHPStan cognitive-complexity limit:

- `DetectsLivewireRuleAttributes` — `#[Rule]` / `#[Validate]` detection (FQN + short alias).
- `IdentifiesLivewireClasses` — parent-class or `render()`-method heuristic.
- `NormalizesRulesDocblock` — scoped `@return` rewriter with multi-line support.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.16...0.4.17

## 0.4.16 - 2026-04-15

Main package `1.8.1` reworked `HasFluentValidationForFilament` to override the standard `validate()` / `validateOnly()` / `getRules()` / `getValidationAttributes()` — the methods Livewire and Filament both rely on. Users get transparent FluentRule behaviour on standard method names; `insteadof` disambiguation is **required** alongside Filament's `InteractsWithForms` (v3/v4) or `InteractsWithSchemas` (v5). Rector now emits the correct adaptation automatically, and the release ships a standalone migration CLI for consumers upgrading out of the 1.7.x state — where the collision fatals at class-load and locks out every AST-based tool.

**Requires:** `sandermuller/laravel-fluent-validation ^1.8.1`. `^1.8.0` is not supported — that trait shape was replaced within hours of tagging.

### What changed

#### Rector emits the 4-method `insteadof` block automatically

`AddHasFluentValidationTraitRector` on a Livewire component that **directly uses** a Filament form trait now emits:

```php
use HasFluentValidationForFilament {
    HasFluentValidationForFilament::validate insteadof InteractsWithForms;
    HasFluentValidationForFilament::validateOnly insteadof InteractsWithForms;
    HasFluentValidationForFilament::getRules insteadof InteractsWithForms;
    HasFluentValidationForFilament::getValidationAttributes insteadof InteractsWithForms;
}
use InteractsWithForms;












```
`getMessages` is intentionally absent from the block — the trait defines it but Filament does not, so no collision to resolve.

The emission uses separate `use` blocks (one for `HasFluentValidationForFilament { … }`, one for the Filament trait) rather than a single combined block. Both forms are valid PHP; the separate-block form is simpler to emit and round-trips through Pint's `ordered_traits` fixer cleanly.

#### Ancestor-only Filament now skip-logs instead of auto-composing

When the Filament trait lives on a parent class and **not** directly on the subclass under conversion, the rector now skip-logs with a pointer to add `HasFluentValidationForFilament` on the concrete subclass manually. The 0.4.15 design tried to handle this shape, but PHP method resolution across trait chains + `parent::` forwarding is too fragile to guarantee — specifically, whether the subclass's `validate()` correctly forwards to the ancestor's Filament form-schema aggregation depends on details of the composition that the rector can't safely reason about.

Log message: *"parent class uses Filament trait — add HasFluentValidationForFilament with insteadof directly on this class if needed (rector cannot safely auto-compose through inheritance)"*.

#### Conflict guard widened for the Filament variant

If a class body declares `validate()` / `validateOnly()` / `getRules()` / `getValidationAttributes()` directly (i.e. a user-authored method on the class itself), the rector now skip-logs and refuses to insert `HasFluentValidationForFilament`. PHP's class-method-over-trait-method resolution would pre-empt the trait entirely, leaving the FluentRule chain inert — inserting the trait in that state is a visible no-op that also produces a confusingly "finished" migration diff. Better to skip-log and leave the user to reconcile.

The plain-Livewire variant's existing guard (blocks on `validate()` / `validateOnly()`) is unchanged.

#### Swap-on-detect preserves adaptations

When a class already has the **wrong** variant directly on it (e.g. plain `HasFluentValidation` on a Filament class), the rector still swaps to the correct variant and drops the orphaned top-level import. For the Filament branch, the insteadof adaptation is now included in the swap.

### New: `vendor/bin/fluent-validation-migrate`

A standalone source-text migrator ships in this release to handle the `1.7.x → 1.8.1` upgrade path. Operates entirely on file bytes — no class autoload, no Rector, no PhpParser.

**Why it exists.** Upgrading from `1.7.x` to `1.8.1` puts any Filament+Livewire class using `HasFluentValidation` into a fatal-at-load state. Rector (and every other AST-based tool) autoloads classes during analysis, so the fatal fires during the tool's own run, aborting it partway through with zero writes persisted. The migration CLI sidesteps this entirely by never touching the autoloader.

**What it does.** For every `.php` file under the given paths (or `app/` by default):

1. Detects BOTH an import of `HasFluentValidation` / `HasFluentValidationForFilament` AND a Filament form trait (`InteractsWithForms` / `InteractsWithSchemas`).
2. Swaps the import + in-class trait-use line to `HasFluentValidationForFilament` (if it was plain).
3. Adds the 4-method `insteadof` block if missing.
4. Leaves `$this->validate(...)` / `$this->validateOnly(...)` call sites alone — the trait overrides those standard names, so existing call sites stay correct.

**Usage:**

```bash
# preview
vendor/bin/fluent-validation-migrate --dry-run

# apply in-place (default path: app/)
vendor/bin/fluent-validation-migrate

# custom paths
vendor/bin/fluent-validation-migrate app/ src/Livewire/












```
**Idempotent:** running twice yields the same result as running once. Files that don't match (plain Livewire without Filament, or classes already carrying the correct adaptation) are untouched.

**Standard migration order** for a `1.7.x → 1.8.1` upgrade:

1. `composer require sandermuller/laravel-fluent-validation:^1.8.1`
2. `vendor/bin/fluent-validation-migrate` — fix the affected classes before the fatal blocks tooling
3. `vendor/bin/rector process` — regular rector run, now against a clean codebase

### Migration path from 0.4.15

0.4.15 inserted `HasFluentValidationForFilament` without the insteadof block (against main-package 1.8.0's shape, where no adaptation was needed). On `1.8.1`, that state is broken — class will fatal at load.

0.4.16's rector does **not** retrofit those classes in-place. Use `vendor/bin/fluent-validation-migrate` — it detects the partial-migration shape (Filament trait already swapped, insteadof missing) and adds the adaptation block. Unified path, one tool, handles the fresh-upgrade and the 0.4.15-partial cases identically.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.15...0.4.16

## 0.4.15 - 2026-04-15

### Changed

#### `AddHasFluentValidationTraitRector` picks the right trait variant for Filament components

Main package `1.8.0` ships a dedicated `HasFluentValidationForFilament` trait — additive, exposes `validateFluent()` without overriding any Livewire/Filament methods, so there is no collision with Filament's `InteractsWithForms` or v5's `InteractsWithSchemas`. The rector now selects the correct variant automatically:

- Plain Livewire component → `HasFluentValidation` (transparent: overrides `validate()` / `validateOnly()`, existing call sites keep working with FluentRule objects).
- Livewire + Filament (`InteractsWithForms` v3/v4 OR `InteractsWithSchemas` v5, detected directly or via the ancestor chain) → `HasFluentValidationForFilament`. Consumer code must call `$this->validateFluent(...)` in submit handlers; `validate()` remains Filament's and handles form-schema rules as before.

Detection walks the parent chain via `ReflectionClass`, so subclasses of a shared Filament base class pick the Filament variant without needing the Filament trait re-declared on every subclass.

**Swap-on-detect:** if a class is already tagged with the wrong variant (plain `HasFluentValidation` on a Filament class, or vice versa), the rector removes the wrong one, inserts the correct one, and drops the now-orphaned top-level `use` import. Skipping on mismatch would silently ship a runtime collision; swap is the safe default.

**Conflict guard:** the trait insertion is skipped (with a skip-log entry) when the class declares a method that would collide with the chosen trait's public surface — `validate()` / `validateOnly()` for `HasFluentValidation`, `validateFluent()` for `HasFluentValidationForFilament`. These are hard user decisions the rector never overrides.

### Upgrade

- `sandermuller/laravel-fluent-validation` constraint bumped to `^1.8`. Consumers on `1.7.x` should stay on rector `0.4.14`; there is no `1.8` fallback path inside the rector.
- No rector config changes. The 0.4.15 prerelease plan had added a `filament_conflict_resolution` option with an `insteadof` adaptation emitter; that work was scrapped before release once the main-package trait-design fix landed. If you ever saw that option in a prerelease build, it has been removed.

### New rector-side helper

- `DetectsFilamentForms` concern centralises the Filament-trait substring match (`InteractsWithForms`, `InteractsWithSchemas`) + ancestor walk. Extracted so additional trait rectors can share the detection.

### Fixtures

Added 4 new fixtures under `tests/AddHasFluentValidationTrait/Fixture/`:

- `filament_interacts_with_forms_picks_filament_variant.php.inc` — v3/v4 path.
- `filament_interacts_with_schemas_picks_filament_variant.php.inc` — v5 path.
- `ancestor_filament_picks_filament_variant.php.inc` — subclass inherits `InteractsWithForms` from a base class.
- `swap_plain_trait_on_filament_class.php.inc` — existing `HasFluentValidation` on a Filament class → replaced with `HasFluentValidationForFilament`, orphaned import dropped.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.14...0.4.15

## 0.4.14 - 2026-04-15

### Changed

#### `@return` annotation uses Laravel's `ValidationRule` interface

`ConvertLivewireRuleAttributeRector::installRulesMethod()` now emits:

```php
/**
 * @return array<string, ValidationRule|string|array<mixed>>
 */
protected function rules(): array { /* … */ }













```
The annotation imports `Illuminate\Contracts\Validation\ValidationRule` via Rector's import-names pass (the same pass that handles `FluentRule` imports), so the pre-Pint output has a proper `use` statement + short-name reference.

**Why `ValidationRule` + `string` + `array<mixed>` union:**

- `FluentRule` (the static factory class) doesn't implement any shared interface with the concrete rule classes it produces. Verified directly: `class FluentRule` has no extends/implements, while `class EmailRule implements DataAwareRule, ValidationRule, ValidatorAwareRule`. The 0.4.13 annotation `array<string, FluentRule>` was semantically wrong — PHPStan correctly flagged it as a `return.type` mismatch.
- All concrete `*Rule` classes the rector emits (`EmailRule`, `StringRule`, `FieldRule`, `IntegerRule`, etc.) already implement `Illuminate\Contracts\Validation\ValidationRule`. That's the accurate common supertype.
- `string` + `array<mixed>` cover Laravel-native rule forms a user might add via manual edit to the generated method (raw pipe-delimited strings, array-tuple rules). Future-safe annotation.

**Why not `array<string, mixed>`:**

`mixed` matches Laravel's own `rules()` convention and would also satisfy vanilla PHPStan, but strict-mode tooling (`rector/type-perfect`, `tomasvotruba/type-coverage`) flags `mixed` as "too broad — use narrower type." The `ValidationRule|string|array<mixed>` union is strictly narrower than `mixed` while still covering every shape the rector emits or a user might add.

**Why not a shared `FluentRule` interface on the main package:**

Mijntp's initial proposal was to add a shared supertype to `laravel-fluent-validation` 1.8 so the annotation could reference a package-native type. fwwl0vv3 (main-package maintainer) declined the interface on the grounds that `FluentRule` is intentionally a factory, not a value type, and Laravel's existing `ValidationRule` interface already provides the right supertype for the concrete rules. The rector's fix is standalone — no cross-package coordination or version bump required on the main package.

### Fixtures

Updated 10 fixtures under `tests/ConvertLivewireRuleAttribute/Fixture/` to match the new annotation. Each fixture now also shows the `use Illuminate\Contracts\Validation\ValidationRule;` import added to the file-level import block.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.13...0.4.14

## 0.4.13 - 2026-04-14

### Changed

#### `@return` annotation tightened to match PHPStan's inferred type

`ConvertLivewireRuleAttributeRector::installRulesMethod()` attached a three-way union docblock annotation to the generated `rules()` method since 0.4.3:

```php
/**
 * @return array<string, FluentRule|string|array<string, mixed>>
 */
protected function rules(): array { /* … */ }














```
The union was defensive — `FluentRule` for chain entries, `string` for raw rule-string fallbacks, `array<string, mixed>` for nested Livewire dotted rules. But those last two shapes only exist on the `mergeIntoExistingRulesMethod()` path, which doesn't emit a docblock. The fresh-emit path (the only path that sets the docblock) produces entries exclusively from `convertStringToFluentRule()` and `convertArrayAttributeArg()` — both return FluentRule builder expressions.

Surfaced during 0.4.11 verification: `type-perfect` + `tomasvotruba/type-coverage` flagged `return.type` errors when the actual inferred type was a specific FluentRule subclass (e.g. `array<string, EmailRule>` from `FluentRule::email()->...`) but the declared type advertised the broader three-way union. The declared-wider-than-inferred mismatch is noise for anyone running strict-mode PHPStan on converted files.

0.4.13 narrows to `array<string, FluentRule>`:

- Accurate for the fresh-emit case (all entries are FluentRule chains).
- Covariance-safe with PHPStan's narrower inferred subclass types (`FluentRule` is a supertype of `EmailRule`, `StringRule`, etc. that the specific factory methods return).
- Still pre-empts rector-preset's `DocblockReturnArrayFromDirectArrayInstanceRector` from adding the loose `array<string, mixed>`.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.12...0.4.13

## 0.4.12 - 2026-04-14

### Fixed

#### Inheritance-aware `rules()` method generation

Since 0.4.0, `ConvertLivewireRuleAttributeRector::installRulesMethod()` always emitted `protected function rules(): array` on the concrete subclass. Two PHP inheritance rules make this unsafe without a parent-class check:

1. **A subclass cannot override a `final` parent method.** If the parent declares `final public function rules()`, PHP throws `Fatal error: Cannot override final method <Parent>::rules()` at class-load time. Any consumer with a shared Livewire base that owns validation (reasonable "parent owns rules, children extend behavior" pattern) hit this the moment their codebase loaded.
2. **Visibility cannot be narrowed across inheritance.** If the parent's `rules()` is `public`, emitting `protected rules()` on the subclass is a fatal covariance violation: `Access level to <Child>::rules() must be public (as in class <Parent>)`.

Caught by mijntp during 0.4.11 verification. Their `BaseSmsTwoFactor::rules()` is `final public`, and every concrete `#[Rule]`-attributed subclass got fatal-on-load from the rector output. Earlier verification rounds ran `php -l` (parse-check, doesn't link inheritance) and fixture tests that didn't instantiate the converted classes. PHPStan-analysing the rector output against the real project autoload — which mijntp started doing this release — caught both violations immediately.

#### What 0.4.12 does

`ConvertLivewireRuleAttributeRector::resolveGeneratedRulesVisibility()` walks the parent chain via `ReflectionClass` (detected from the AST `$class->extends` node — child class doesn't need to be autoloadable). For each ancestor:

- **Ancestor has `final rules()`** → helper returns `null`. `refactor()` logs a skip entry (`parent class declares final rules() method; cannot override — skipping to avoid fatal-on-load`) and bails before any property mutation. The child class is left unchanged; `#[Rule]` attributes stay in place.
- **Ancestor has `public rules()` (non-final)** → helper returns `MODIFIER_PUBLIC`. Generated method is emitted as `public function rules(): array { … }` to satisfy visibility covariance.
- **Ancestor has `protected` or `private rules()`** → helper returns `MODIFIER_PROTECTED`. `protected` override is legal when narrowing isn't happening.
- **No ancestor has `rules()`** (the common case — Livewire `Component` has no default `rules()`) → helper returns `MODIFIER_PROTECTED`. Matches pre-0.4.12 default.

The check runs BEFORE `extractAndStripRuleAttribute()` so a bail never strips attributes the rector couldn't replace with a generated method. The visibility resolution runs twice on the happy path (once in `refactor()` as the gate, once in `installRulesMethod()` for the emit) — one extra ReflectionClass walk per converted class is trivial cost vs. the correctness gain.

#### Fixtures pinning the behavior

Two new fixtures plus two helper classes under `tests/ConvertLivewireRuleAttribute/FixtureSupport/`:

- `skip_parent_has_final_rules.php.inc` — child extends a base with `final public rules()`, has `#[Rule]` attributes, expected output: no change + specific skip-log entry.
- `generates_public_rules_when_parent_public.php.inc` — child extends a base with `public rules()` (non-final), has `#[Rule]` attributes, expected output: `rules()` method emitted as `public function rules()`.

The helper classes (`BaseWithFinalPublicRules`, `BaseWithPublicRules`) are real autoloadable PHP files so `ReflectionClass` resolves against them at test time.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.11...0.4.12

## 0.4.11 - 2026-04-14

`GroupWildcardRulesToEachRector` now applies to Livewire components. Requires `sandermuller/laravel-fluent-validation ^1.7.1`.

### Changed

#### Livewire components are no longer skipped by the wildcard grouping rector

Before 0.4.11, `GroupWildcardRulesToEachRector` skipped Livewire components (detected via direct parent match on `Livewire\Component` / `Livewire\Form`, `HasFluentValidation` trait usage, or the presence of a `render()` method) because nested `each()` / `children()` calls broke Livewire's wildcard key reading at runtime. The rule had worked correctly on FormRequests since 0.3.0 but produced runtime-broken output on Livewire.

`sandermuller/laravel-fluent-validation` 1.7.0 shipped `HasFluentValidation::getRules()`, which flattens nested `each()` / `children()` back to wildcard keys via `RuleSet::flattenRules()`. `validate()` and `validateOnly()` on a Livewire component using the trait now see the flat form Livewire expects, regardless of whether the source `rules()` method uses nested or flat notation.

With the runtime support in place, the Rector's Livewire-skip guard is obsolete:

```php
// Before 0.4.11 — skipped on Livewire
class MyComponent extends Component {
    public function rules(): array {
        return [
            'items' => FluentRule::array()->required(),
            'items.*.name' => FluentRule::string()->required(),
        ];
    }
}

// 0.4.11 — groups into nested each(), flattened back to wildcard at runtime
class MyComponent extends Component {
    public function rules(): array {
        return [
            'items' => FluentRule::array()->required()->each([
                'name' => FluentRule::string()->required(),
            ]),
        ];
    }
}
















```
Removed from `GroupWildcardRulesToEachRector`:

- `isLivewireClass()` check at the top of `refactorClass()`
- `isLivewireClass()` method (direct parent + trait + `render()` heuristic)
- `LIVEWIRE_CLASSES` constant
- `use SanderMuller\FluentValidation\HasFluentValidation;` import (no longer needed)
- Skip-log message `'detected as Livewire (nested each() breaks Livewire wildcard handling; trait added separately)'`

The associated `skip_livewire*.php.inc` fixtures were converted to `group_livewire_*.php.inc` with expected-output halves that exercise the nested-each form.

#### Main package constraint bumped to `^1.7.1`

`composer.json` now requires `sandermuller/laravel-fluent-validation: ^1.7.1`. Consumers upgrading this Rector from 0.4.10 to 0.4.11 with an older main package pinned (1.6.x or below) will get a composer conflict rather than a silent runtime break when Livewire components start using the grouped output. The conflict is the intentionally-safer failure mode.

If you don't use Livewire, nothing breaks: FormRequest and `$request->validate()` pathways were never affected by the Livewire-skip guard and work identically across 0.4.x.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.10...0.4.11

## 0.4.10 - 2026-04-14

### Fixed

#### Run summary no longer depends on rector/extension-installer

0.4.9 registered the shutdown-emit hook from `config/config.php`, which Rector's extension-installer plugin loads automatically for packages declaring `type: rector-extension`. Both collectiq and mijntp caught that the plugin isn't installed on their projects — `rector/extension-installer` ships as a namespace-isolated internal dependency inside `rector/rector` itself but doesn't scan the consumer's vendor tree. Without the plugin, `config/config.php` is dead metadata.

Result: 0.4.9 was strictly additive on most codebases — the rules converted correctly, but the new stdout summary never fired. Worse than "visibly broken" because the discoverability gap it was meant to close was now hidden behind a different form of invisibility.

0.4.10 moves the registration into each of the 7 rule constructors. When Rector's DI container instantiates any of the fluent-validation rules during config initialization, that rule's `__construct()` calls `RunSummary::registerShutdownHandler()`. The existing idempotent guard (`self::$registered`) ensures exactly-once registration per PHP process regardless of how many rules fire. Consumers using any `FluentValidationSetList::*` set or any individual rule via `->withRules([...])` get the hook automatically — no extension-installer dependency, no `require-dev` addition, no `allow-plugins` approval.

The `config/config.php` registration is retained as belt-and-suspenders: extension-installer-enabled consumers register via the config load path; others register via rule construction. The idempotent guard prevents double-registration in either case.

#### Second gate: the rule-constructor path fires outside Rector too

Rule constructors fire whenever the class is instantiated. That includes:

- Consumer test suites that happen to spin up our rector classes (e.g. Pest / PHPUnit tests for custom Rector rule configs)
- Composer post-install autoload scripts touching the class
- IDE inspection runs
- Any arbitrary PHP process that imports the class for its own reasons

Without a second gate, each of these would register a shutdown handler that emits the summary at process exit — leaking a `[fluent-validation] N skip entries written to…` line into pest/phpunit/phpstan/composer output.

0.4.10 adds `isRectorInvocation()` — a basename check against `$_SERVER['argv'][0]`. The gate matches `rector`, `rector.phar`, `vendor/bin/rector`, and any `rector`-substring script name. Rejects `pest`, `phpunit`, `phpstan`, `composer`, `php`, and anything else. Combined with the existing `--identifier` worker check, the summary fires only during `vendor/bin/rector process`-parent invocations.

### Refactor

Reorganized `tests/` into the per-rule folder convention Rector core and extensions use (`rector-phpunit`, `rector-doctrine`, etc.): `tests/<RuleName>/{<RuleName>RectorTest.php, Fixture/, config/}`. Prompted by Rector maintainer feedback. No behavior change, no consumer impact — tests aren't distributed in the `composer require --dev` artifact. Skip fixtures under `ConvertLivewireRuleAttribute/Fixture/` also renamed from `bail_*` / `*_bails` to `skip_*` to match the same convention (`skip_*.php.inc` for no-change scenarios, single-section, no `-----` separator).

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.9...0.4.10

## 0.4.9 - 2026-04-13

### Added

#### Run-summary stdout line

Users running Rector on codebases with heavy trait-hoisting (abstract bases that propagate the performance traits via `DetectsInheritedTraits`) or hybrid Livewire `validate([...])` bail conditions see `[OK] 0 files changed` from Rector itself and assume the rules didn't fire. The actual story lives in `.rector-fluent-validation-skips.log`, but until now there was no pointer to it — users had to know to look.

0.4.9 emits a single STDOUT line at the end of each Rector invocation when the skip log contains entries:

```
[fluent-validation] 42 skip entries written to .rector-fluent-validation-skips.log — see for details


















```
Implementation is a shutdown function registered from the package's `config/config.php`, which rector-extension-installer includes in consumer projects' Rector runs. The shutdown function:

- Runs on parent PHP process exit, after Rector's own output has flushed. Doesn't interleave with Rector's `[OK]` summary.
- Gates on "am I the parent?" via absence of `--identifier` in `$_SERVER['argv']`. Workers are spawned with `--identifier <uuid>`; the parent isn't. This avoids each worker emitting its own summary line.
- Only emits when the skip log exists and is non-empty. Silent when there's nothing to report — users never see a useless summary line.
- Writes to STDOUT (not STDERR). STDOUT from the parent process reaches the user's terminal directly; STDERR under `withParallel()` has the swallow problem that motivated the file sink in 0.4.2, but we're emitting from the parent here, not a worker.

Singular/plural noun matches entry count (`1 skip entry` / `N skip entries`).

The shutdown function is idempotent — if `config/config.php` gets loaded multiple times in the same process (uncommon but possible), the handler registers exactly once via a static flag.

#### Public API

`SanderMuller\FluentValidationRector\RunSummary` has two public static methods:

- `registerShutdownHandler()` — called from `config/config.php`. Idempotent, gated on parent-ness.
- `format(): ?string` — returns the summary line as a string, or null when the log is absent/empty. Exposed for unit testing without needing to trigger a PHP shutdown cycle; consumers shouldn't need to call this directly.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.8...0.4.9

## 0.4.8 - 2026-04-13

### Added

#### Validation-equivalence integration test (correctness)

Every release so far has claimed the rector's output preserves runtime validation semantics. The fixture tests verify the output *text* matches expected shape, but until now nothing empirically verified that the converted FluentRule chains produce the same Laravel-validator error messages as the string-form rules they replaced.

This mattered for modal operators — `bail`, `sometimes`, `nullable`, `required_without`, etc. — whose semantics are order-sensitive and mode-switching, not additive. The string parser emits tokens in encounter order after pulling the type token forward. If that ordering ever subtly diverged from Laravel's expected rule-sequence semantics, downstream codebases would silently get different validation behavior than they had before running the rector.

New `tests/ValidationEquivalenceTest.php` runs 16 parametrized cases covering:

- `required + email + max` (simple, two invalid shapes)
- `bail` (empty input, non-string value — verifies only-one-error semantics)
- `sometimes` (field absent, field present + invalid)
- `nullable` (null accepted, invalid still fails)
- `required_without` (one missing, both missing)
- `integer + min/max` (both boundaries)
- `in:` list (value not in list)
- `array + each()` nested (typed children via dotted key equivalence)
- `boolean` (non-boolean input)

Each case runs Laravel's validator against invalid input using both the string-form rules (what the user wrote pre-conversion) and the FluentRule builder (what the rector would emit), and asserts the error messages are identical.

Uses Orchestra Testbench for the Laravel container + facade bootstrap (FluentRule's builder touches `Validator::` and `Rule::` facades during rule materialization). First integration-level test in the package; `AbstractRectorTestCase`-based fixture tests continue to cover conversion correctness.

Caught during mijntp's 0.4.7 open-ended feedback as the highest-priority paranoia item.

#### `NEWLINED_ARRAY_PRINT` regression guard

`ConvertLivewireRuleAttributeRector::multilineArray()` attaches Rector's `AttributeKey::NEWLINED_ARRAY_PRINT` attribute to the generated `rules()` method's return array, forcing one-item-per-line emission regardless of array size. The attribute key is Rector-internal and has churned across past major versions.

New `tests/RectorInternalContractsTest.php::testNewlinedArrayPrintConstantExists` fails fast with a targeted error message if the constant vanishes in a Rector 3+ upgrade, pointing the maintainer at the replacement attribute to wire into `multilineArray()` instead of letting the absence silently collapse generated `rules()` methods to a single line.

Flagged by mijntp.

### Changed

#### `ManagesTraitInsertion` emits at alphabetically-sorted position

0.3.0's `ManagesNamespaceImports` fix taught the rector to insert top-of-file `use` imports at the alphabetically-correct position rather than prepending. The class-body trait list (`use HasFluentRules;` inside the class) kept the old behavior: append after the last existing trait.

0.4.8 extends the symmetry to class-body trait insertion. `ManagesTraitInsertion::resolveSortedTraitInsertPosition()` walks existing `TraitUse` statements and inserts the new trait at the position where it sorts alphabetically among them:

```php
// Before (append)
class MyRequest {
    use HasAuditLog;
    use HasRateLimit;
    use Sanitizes;
    use HasFluentRules;   // appended
}

// After (sorted)
class MyRequest {
    use HasAuditLog;
    use HasFluentRules;   // sorted between HasAuditLog and HasRateLimit
    use HasRateLimit;
    use Sanitizes;
}



















```
Pint's `ordered_traits` continues to resort if a consumer's existing trait list wasn't already alphabetical, but on well-ordered class bodies Pint is typically a no-op now.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.7...0.4.8

## 0.4.7 - 2026-04-13

### Fixed

#### Skip-log noise from trivial non-candidates

0.4.3 introduced the skip log as a diagnostic surface for "why wasn't my component converted" investigations. On codebases with lots of classes that *happen* to have a `rules()` method but aren't actually FormRequests or Livewire components — Actions, `Console\Kernel`, Collections, PHPUnit helpers — the trait rectors would evaluate each class, find it non-convertible, and log that decision. Those log entries are diagnostic noise: the user has no action to take; the class was never a realistic candidate.

Caught by hihaho's 0.4.5 regression-insurance verification: 2988 skip-log entries / 777KB on their 108-file corpus, dominated by two repeat messages:

- 1316 × `"no FluentRule usage in rules() method"` (from `AddHasFluentRulesTraitRector`, firing on every class with a `rules()` method that didn't use FluentRule — mostly non-FormRequest classes in hihaho's naming-convention style).
- 1255 × `"not detected as a Livewire component (no Livewire parent or render() method)"` (from `AddHasFluentValidationTraitRector`, firing on every class that wasn't Livewire — which is most of the codebase).

The remaining ~417 entries were the actually-interesting categories: abstract classes, inherited traits, hybrid `validate()` / `validateOnly()` conflicts, unsafe parent detection, FormRequest/Livewire trait mismatches.

0.4.7 silences the two noisy messages. Both rectors now treat "class doesn't look like our target" as a silent no-op:

- `AddHasFluentValidationTraitRector` now gates on `isLivewireClass()` *first* (before the abstract / already-has-trait / ancestor / validate-conflict / FluentRule-usage checks). Non-Livewire classes are silent no-ops; the other checks fire only on actual Livewire components.
- `AddHasFluentRulesTraitRector` keeps its existing check order but silences the "no FluentRule usage" bail. Classes with a `rules()` method that lacks FluentRule are silent no-ops instead of log entries.

Interesting categories stay logged: abstract classes, `alreadyHasTrait`, `anyAncestorUsesTrait`, `hasValidateMethodConflict`, `extends a configured base class`, `isLivewireClass (uses HasFluentValidation instead)` (on the FormRequest rector), `unsafe parent`, and all attribute-converter skips are untouched.

#### Tradeoff

The "not detected as a Livewire component" log used to help debug an edge case: a user's Livewire class that the rector's heuristic (Livewire parent OR `render()` method OR `HasFluentValidation` trait) fails to detect. In 0.4.7 that misdetection becomes silent. If a user reports "my Livewire class wasn't converted and there's no log," we'd add candidacy-gated logging in a follow-up — but hihaho's three-codebase data argued this is a rare case against 2571 actually-noisy entries, so the simpler filter wins for now.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.6...0.4.7

## 0.4.6 - 2026-04-13

### Added

#### Array-form Livewire attribute conversion

`ConvertLivewireRuleAttributeRector` now handles array-form attribute rules, matching the existing string-form behavior:

```php
// Before
#[Rule(['required', 'string', 'max:255'])]
public string $name = '';

#[Validate(['nullable', 'email'])]
public ?string $email = null;

// After
public string $name = '';

public ?string $email = null;

/**
 * @return array<string, FluentRule|string|array<string, mixed>>
 */
protected function rules(): array
{
    return [
        'name' => FluentRule::string()->required()->max(255),
        'email' => FluentRule::email()->nullable(),
    ];
}





















```
`as:` label mapping continues to work (`#[Rule([...], as: 'x')]` → `->label('x')`). Empty arrays (`#[Rule([])]`) now emit a specific skip-log entry and leave the attribute in place, instead of silently converting to `FluentRule::field()`.

#### Known behavior: rule-object constructors get the `->rule()` escape hatch

PHP attribute args must be const-expressions. This rules out static method calls like `Password::min(8)` and `Rule::unique('users', 'email')` inside `#[Rule([...])]` — the only legal forms are the constructor calls `new Password(8)` and `new Rule\Unique('users', 'email')`.

The array converter's type-detection layer looks specifically for the `Password::min(...)` and `Rule::factoryMethod(...)` shapes. Constructor calls fall through to the `->rule(...)` escape hatch:

- `#[Rule(['required', new Password(8)])]` → `FluentRule::field()->required()->rule(new Password(8))`
- `#[Rule(['required', 'email', new Rule\Unique('users', 'email')])]` → `FluentRule::email()->required()->rule(new Rule\Unique('users', 'email'))`

Both outputs are runtime-correct. For the richer `FluentRule::password(8)` / `->unique('users', 'email')` form, prefer `rules(): array` over attribute-form when you need rule objects. Attribute-form is at its best for pure-string rule lists; the const-expr ceiling limits what's expressible beyond that.

### Changed

#### Internal: trait split

`ConvertsValidationRules` (1061 lines) split into two composing traits:

- `ConvertsValidationRuleStrings` — the rule-string surface: type tokens, modifier dispatch, factory construction, the `$needsFluentRuleImport` state. Used directly by `ValidationStringToFluentRuleRector`.
- `ConvertsValidationRuleArrays` — array-specific helpers + the `convertArrayToFluentRule()` entry point. Composes `ConvertsValidationRuleStrings` via `use`, so any rector using the array trait also gets the string surface. Used by `ValidationArrayToFluentRuleRector` and `ConvertLivewireRuleAttributeRector`.

`$needsFluentRuleImport` stays on the string trait (single owner), so import coordination is unchanged. No user-facing behavior change; `ValidationArrayToFluentRuleRector` drops from 1009 lines to ~170 after the extraction. `detectRuleFactoryType()` got a minor refactor into an `applyFactoryChainCall()` helper during the split.

If you're consuming `ConvertsValidationRules` directly (unlikely for internal-infrastructure traits but possible): rename your import to `ConvertsValidationRuleStrings`. No compat shim in this release; if a consumer reports breakage, a shim ships in 0.4.7.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.5...0.4.6

## 0.4.5 - 2026-04-13

### Fixed

#### Skip-log `fflush` before sentinel unlock (data loss under `withParallel()`)

0.4.3 introduced a PPID-keyed session sentinel (`.rector-fluent-validation-skips.log.session`) with `flock(LOCK_EX)` to coordinate first-worker truncation across Rector's parallel workers. The mechanism had a latent bug: after writing the session marker under the lock, the code called `flock($handle, LOCK_UN)` and only flushed the buffer later via `fclose` in the `finally` block.

`flock(LOCK_UN)` is POSIX advisory-only and does not imply a buffer flush. Between unlock and `fclose`, another worker could acquire the sentinel lock, `stream_get_contents` through an empty or stale sentinel (the session marker was still sitting in PHP's userland stream buffer on the first worker's side), decide the session was fresh, and re-truncate the log — wiping any entries earlier workers had already appended via `FILE_APPEND | LOCK_EX`.

Reproduced by mijntp during 0.4.3 verification with 100% consistency on macOS/APFS:

- Baseline scenario (5 files, 3 convert + 2 array-form bail, default parallel): log had 9 entries, zero from the bail files.
- `--debug` (single-process) on the same inputs: log had all 8 expected entries.
- Parallel runs of only the 2 bail files: log file did not exist at all across 3 consecutive runs (each worker raced to truncate through the unflushed window).

Scenarios 2 (dirty-log preseed) and 3 (run-twice) passed — those exercise only the single-worker hot path, where the `fclose` flush at the end of the process handled the race by accident.

Fix: explicit `fflush($handle)` immediately before `flock($handle, LOCK_UN)` in `ensureLogSessionFreshness()`. Guarantees the session marker is written through to the OS before the next lock-holder reads it. The race window is now zero for correctly-implementing platforms.

#### `@return` docblock emits short alias pre-Pint

0.4.3 added `setDocComment` to pre-empt rector-preset's loose `@return array<string, mixed>`, but wrote the type as `\SanderMuller\FluentValidation\FluentRule` — the fully-qualified name — even though `queueFluentRuleImport()` already registers the short alias in the file's imports. Pint's `fully_qualified_strict_types` fixer cleaned it up post-rector, but the pre-Pint output was chattier than necessary.

Flagged by collectiq during 0.4.3 verification. Fix: emit `FluentRule` short name directly in the Doc string. Same class of polish as the 0.3.0 "synthesized `FluentRule::` uses short name" fix. No fixture updates needed — the test config's `->withImportNames()` was silently normalizing the FQN to short name in fixture assertions, so consumer-facing output now matches what the fixtures already expected.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.4...0.4.5

## 0.4.4 - 2026-04-13

### Fixed

#### Skip-log `fflush` before sentinel unlock (data loss under `withParallel()`)

0.4.3 introduced a PPID-keyed session sentinel (`.rector-fluent-validation-skips.log.session`) with `flock(LOCK_EX)` to coordinate first-worker truncation across Rector's parallel workers. The mechanism had a latent bug: after writing the session marker under the lock, the code called `flock($handle, LOCK_UN)` and only flushed the buffer later via `fclose` in the `finally` block.

`flock(LOCK_UN)` is POSIX advisory-only and does not imply a buffer flush. Between unlock and `fclose`, another worker could acquire the sentinel lock, `stream_get_contents` through an empty or stale sentinel (the session marker was still sitting in PHP's userland stream buffer on the first worker's side), decide the session was fresh, and re-truncate the log — wiping any entries earlier workers had already appended via `FILE_APPEND | LOCK_EX`.

Reproduced by mijntp during 0.4.3 verification with 100% consistency on macOS/APFS:

- Baseline scenario (5 files, 3 convert + 2 array-form bail, default parallel): log had 9 entries, zero from the bail files.
- `--debug` (single-process) on the same inputs: log had all 8 expected entries.
- Parallel runs of only the 2 bail files: log file did not exist at all across 3 consecutive runs (each worker raced to truncate through the unflushed window).

Scenarios 2 (dirty-log preseed) and 3 (run-twice) passed — those exercise only the single-worker hot path, where the `fclose` flush at the end of the process handled the race by accident.

Fix: explicit `fflush($handle)` immediately before `flock($handle, LOCK_UN)` in `ensureLogSessionFreshness()`. Guarantees the session marker is written through to the OS before the next lock-holder reads it. The race window is now zero for correctly-implementing platforms.

#### `@return` docblock emits short alias pre-Pint

0.4.3 added `setDocComment` to pre-empt rector-preset's loose `@return array<string, mixed>`, but wrote the type as `\SanderMuller\FluentValidation\FluentRule` — the fully-qualified name — even though `queueFluentRuleImport()` already registers the short alias in the file's imports. Pint's `fully_qualified_strict_types` fixer cleaned it up post-rector, but the pre-Pint output was chattier than necessary.

Flagged by collectiq during 0.4.3 verification. Fix: emit `FluentRule` short name directly in the Doc string. Same class of polish as the 0.3.0 "synthesized `FluentRule::` uses short name" fix. No fixture updates needed — the test config's `->withImportNames()` was silently normalizing the FQN to short name in fixture assertions, so consumer-facing output now matches what the fixtures already expected.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.3...0.4.4

## 0.4.3 - 2026-04-13

### Fixed

#### Skip-log truncation race under `withParallel()`

0.4.2 introduced the file-sink skip log (`.rector-fluent-validation-skips.log`) to survive Rector's parallel worker STDERR being swallowed. The truncation logic used a per-process `static $logFileTruncated` flag: first write per process truncated, subsequent writes appended. That flag is scoped per-worker, so under `withParallel()` every one of the 15 workers independently decided it was "first" and truncated the file — wiping any entries written by earlier workers.

Caught by mijntp with a deterministic repro:

- Run A (5 files, 3 convert + 2 array-form bail, parallel): log had 7 entries, zero from the 2 bailed files. The last-writing workers' entries survived.
- Run B (same 2 bailed files alone, with `--debug` which disables parallel): log had both bail entries.
- Run C (same 2 bailed files alone, default parallel): log file didn't exist at all.

0.4.3 replaces the per-process flag with a PPID-keyed session sentinel (`.rector-fluent-validation-skips.log.session`) coordinated via `flock(LOCK_EX)`:

- First worker in a Rector run sees a missing sentinel (or one with a stale PPID) → truncates the log, writes the new session marker.
- All subsequent workers in the same run see their PPID matches → skip truncation, append only.
- Next Rector invocation has a new PPID → first worker truncates again → fresh log per run.

Under `withParallel()` all workers share the same PPID (the main Rector process), so the check is authoritative. Each worker runs the sentinel check once per process; subsequent writes skip straight to `FILE_APPEND | LOCK_EX`.

Non-POSIX / Windows (`posix_getppid()` unavailable) falls through to an mtime-based staleness heuristic with a 300-second threshold. Workers `touch()` the sentinel on every session verification, so long-running Rector invocations keep their sentinel mtime fresh. Back-to-back runs within 300s on non-POSIX may share a log (acceptable degradation), but per-worker data loss is eliminated regardless of platform.

`.gitignore` updated to include the `.session` sentinel.

#### `validateOnly()` bypass now triggers hybrid bail

`ConvertLivewireRuleAttributeRector::hasExplicitValidateCall()` previously only matched `$this->validate([...])`. Livewire also exposes `$this->validateOnly($field, $rules = null, ...)` — when called with a second-arg rules array, that call bypasses any generated `rules(): array` method and converting the attributes produces dead code.

0.4.3 extends the check:

- `validate` → rules at arg 0 (unchanged)
- `validateOnly` → rules at arg 1 (new)

`validateOnly($field)` without a rules override keeps converting — it uses `rules()` / attribute rules, so no dead-code risk. Explicit `validateOnly($field, ['x' => '…'])` triggers the bail.

Two new fixtures:

- `bail_on_hybrid_validateOnly_with_rules.php.inc` — attribute + `validateOnly('name', [...])` → bail, attributes preserved.
- `converts_with_plain_validateOnly.php.inc` — attribute + `validateOnly('name')` → converts to `rules()`.

Theoretical today — no peer codebase has exercised the pattern — but the bail is one-line-cheap and prevents silent dead code if it ever hits.

#### Tighter `@return` annotation on generated `rules(): array`

0.4.2's appended `rules()` method had no docblock. Running rector-preset's `DocblockReturnArrayFromDirectArrayInstanceRector` (enabled by `typeDeclarationDocblocks: true` in most Rector configs) would infer and add a loose `@return array<string, mixed>` annotation.

0.4.3 pre-emptively attaches the tighter:

```php
/**
 * @return array<string, FluentRule|string|array<string, mixed>>
 */
protected function rules(): array
























```
The union accurately describes what the generated array contains:

- `FluentRule` — method-chain builders (the common case)
- `string` — raw rule strings when merged into an existing `rules()` method that used them
- `array<string, mixed>` — Livewire dotted / nested rules

The annotation uses the short name `FluentRule` since the rector already queues the `use` import via `UseNodesToAddCollector`.

Updated 6 existing fixtures to include the new docblock.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.2...0.4.3

## 0.4.2 - 2026-04-13

### Fixed

#### File-sink skip log — visible under `withParallel()`

`LogsSkipReasons` (used by every rector to explain why it skipped a class) used to emit only to STDERR, gated on `FLUENT_VALIDATION_RECTOR_VERBOSE=1`. That worked for single-process Rector runs, but Rector's parallel executor (the default in projects scaffolded by `rector init`) doesn't forward worker STDERR to the parent's STDERR. Effectively, ~100% of production usage saw zero skip-log output regardless of the env var.

The trait now writes each skip line to `.rector-fluent-validation-skips.log` in the consumer's current working directory using `FILE_APPEND | LOCK_EX`, which works correctly across worker processes. STDERR mirroring is preserved when `FLUENT_VALIDATION_RECTOR_VERBOSE=1` is set, for single-process invocations.

The log is truncated at the first write per Rector run so stale entries from previous runs don't leak in. After a Rector run finishes, `cat .rector-fluent-validation-skips.log` shows everything the rector skipped and why. `.gitignore` entry added.

Reported by collectiq's 7-file scan after observing zero skip-log output despite multiple unsupported-args cases in the file set.

#### Blank line before generated `rules(): array`

`ConvertLivewireRuleAttributeRector` used to append the synthesized `rules(): array` method directly after the last class member, leaving them flush. Pint's `class_attributes_separation` fixer would always fire on converted files. The rector now inserts a `Nop` statement between the previous member and the appended method (skipping the Nop when the previous statement is already a Nop).

Same pattern used by the trait rectors in 0.1.1 — applied here to the new attribute rector.

#### Property-type-aware type inference for untyped rule strings

When a `#[Rule]` / `#[Validate]` attribute's rule string has no type token (e.g. `#[Validate('max:2000')]`, `#[Validate('required')]`), 0.4.0 fell back to `FluentRule::field()` and emitted untyped modifiers via the `->rule('...')` escape hatch — because `FieldRule` doesn't have `max()`, `min()`, etc. methods.

0.4.1 reads the PHP property's type declaration and uses it as the factory base when the rule string doesn't specify one:

```php
// Before (0.4.0)
#[Validate('max:2000')]
public string $description = '';
// → 'description' => FluentRule::field()->rule('max:2000')

#[Validate('min:1')]
public int $count = 0;
// → 'count' => FluentRule::field()->rule('min:1')

// After (0.4.1)
#[Validate('max:2000')]
public string $description = '';
// → 'description' => FluentRule::string()->max(2000)

#[Validate('min:1')]
public int $count = 0;
// → 'count' => FluentRule::integer()->min(1)

























```
Maps:

- `string` → `FluentRule::string()`
- `int` / `integer` → `FluentRule::integer()`
- `bool` / `boolean` → `FluentRule::boolean()`
- `float` → `FluentRule::numeric()`
- `array` → `FluentRule::array()`

Nullable types unwrap (`public ?string $x` → uses `string`). Union types, intersection types, object types, and missing type declarations fall through to the prior `FluentRule::field()` + `->rule(...)` behavior — safe default when the property type doesn't map cleanly.

Inference only applies when the rule string has **no** type token. Explicit `#[Validate('string|max:50')]` continues to use the rule-string token, even on a non-`string` property — the rule string wins for clarity.

Reference fixture pinned from collectiq's `ReportContentButton`.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.1...0.4.2

## 0.4.1 - 2026-04-13

### Fixed

#### File-sink skip log — visible under `withParallel()`

`LogsSkipReasons` (used by every rector to explain why it skipped a class) used to emit only to STDERR, gated on `FLUENT_VALIDATION_RECTOR_VERBOSE=1`. That worked for single-process Rector runs, but Rector's parallel executor (the default in projects scaffolded by `rector init`) doesn't forward worker STDERR to the parent's STDERR. Effectively, ~100% of production usage saw zero skip-log output regardless of the env var.

The trait now writes each skip line to `.rector-fluent-validation-skips.log` in the consumer's current working directory using `FILE_APPEND | LOCK_EX`, which works correctly across worker processes. STDERR mirroring is preserved when `FLUENT_VALIDATION_RECTOR_VERBOSE=1` is set, for single-process invocations.

The log is truncated at the first write per Rector run so stale entries from previous runs don't leak in. After a Rector run finishes, `cat .rector-fluent-validation-skips.log` shows everything the rector skipped and why. `.gitignore` entry added.

Reported by collectiq's 7-file scan after observing zero skip-log output despite multiple unsupported-args cases in the file set.

#### Blank line before generated `rules(): array`

`ConvertLivewireRuleAttributeRector` used to append the synthesized `rules(): array` method directly after the last class member, leaving them flush. Pint's `class_attributes_separation` fixer would always fire on converted files. The rector now inserts a `Nop` statement between the previous member and the appended method (skipping the Nop when the previous statement is already a Nop).

Same pattern used by the trait rectors in 0.1.1 — applied here to the new attribute rector.

#### Property-type-aware type inference for untyped rule strings

When a `#[Rule]` / `#[Validate]` attribute's rule string has no type token (e.g. `#[Validate('max:2000')]`, `#[Validate('required')]`), 0.4.0 fell back to `FluentRule::field()` and emitted untyped modifiers via the `->rule('...')` escape hatch — because `FieldRule` doesn't have `max()`, `min()`, etc. methods.

0.4.1 reads the PHP property's type declaration and uses it as the factory base when the rule string doesn't specify one:

```php
// Before (0.4.0)
#[Validate('max:2000')]
public string $description = '';
// → 'description' => FluentRule::field()->rule('max:2000')

#[Validate('min:1')]
public int $count = 0;
// → 'count' => FluentRule::field()->rule('min:1')

// After (0.4.1)
#[Validate('max:2000')]
public string $description = '';
// → 'description' => FluentRule::string()->max(2000)

#[Validate('min:1')]
public int $count = 0;
// → 'count' => FluentRule::integer()->min(1)


























```
Maps:

- `string` → `FluentRule::string()`
- `int` / `integer` → `FluentRule::integer()`
- `bool` / `boolean` → `FluentRule::boolean()`
- `float` → `FluentRule::numeric()`
- `array` → `FluentRule::array()`

Nullable types unwrap (`public ?string $x` → uses `string`). Union types, intersection types, object types, and missing type declarations fall through to the prior `FluentRule::field()` + `->rule(...)` behavior — safe default when the property type doesn't map cleanly.

Inference only applies when the rule string has **no** type token. Explicit `#[Validate('string|max:50')]` continues to use the rule-string token, even on a non-`string` property — the rule string wins for clarity.

Reference fixture pinned from collectiq's `ReportContentButton`.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.0...0.4.1

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
  
