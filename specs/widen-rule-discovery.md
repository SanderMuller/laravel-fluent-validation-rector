# Widen Rule Discovery

## Overview

`ValidationStringToFluentRuleRector` and `ValidationArrayToFluentRuleRector` currently look for rule arrays in three exact shapes: `rules(): array` methods on `FormRequest` subclasses, `$request->validate([...])`, and `Validator::make([...])`. Three real-world rule-definition shapes are missed: (1) `Validator::validate([...], [...])` static call, (2) global `validator(...)` helper, (3) custom-named methods like `editorRules()` / `rulesWithoutPrefix()` on a class that already qualifies as a rules-bearing class. Widen the discovery to cover all three without consumer config.

---

## 1. Current State

### 1.1 Discovery surface (as of 0.13.3)

`ValidationStringToFluentRuleRector::refactor()` and `ValidationArrayToFluentRuleRector::refactor()` both dispatch on three node types via `getNodeTypes()`:

```php
return [ClassLike::class, MethodCall::class, StaticCall::class];
```

Routing in `refactor()`:

| Node                               | Dispatched to                                                          |
|------------------------------------|------------------------------------------------------------------------|
| `ClassLike`                        | `refactorFormRequest()` (visits `rules()` method only)                 |
| `MethodCall` named `validate`      | `refactorValidateCall()` (catches `$request->validate([...])`)         |
| `MethodCall`/`StaticCall` `make`   | `refactorValidatorMake()` (catches `Validator::make([...])`)           |

`refactorFormRequest()` walks `$class->getMethods()`, filters by `$this->isName($method, 'rules')`, then converts the rules array. The method-name filter is hardcoded.

### 1.2 Three discovery gaps

#### Gap A — `Validator::validate([...], [...])` (one-shot static call)

```php
Validator::validate(
    $request->all(),
    ['email' => 'required|email|max:255'],
);
```

Missed because the dispatcher only matches `MethodCall`/`StaticCall` with `name === 'make'`. `Validator::validate()` has the same structural shape as `Validator::make()` — args 0 = data, arg 1 = rules array — but isn't routed.

#### Gap B — Global `validator(...)` helper

```php
validator($request->all(), ['email' => 'required|email|max:255'])->validate();
```

Same shape as `Validator::make()` but invoked via the framework helper function. Currently lands as a `FuncCall` node, which isn't in `getNodeTypes()`. The data + rules-array slots are at args 0 and 1 of the `FuncCall`, identical to `Validator::make`.

#### Gap C — Custom-named rules methods

```php
final class CreateOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return $this->editorRules() + $this->shippingRules();
    }

    private function editorRules(): array
    {
        return ['title' => 'required|string|max:150'];   // ← invisible to rector today
    }

    private function shippingRules(): array
    {
        return ['address' => 'required|string'];          // ← invisible to rector today
    }
}
```

Codebases routinely split rules methods for readability or per-context reuse. Today's `rules()`-only filter leaves the helper methods untouched, producing a half-converted class with `rules()` on FluentRule and helpers still on string rules.

### 1.3 Why a config knob is the wrong fix

A `RULES_METHOD_NAMES => ['rules', 'editorRules', ...]` config requires every consumer to enumerate their convention upfront. It also doesn't help with one-off helpers (`saveRules()`, `step1Rules()`) that only one or two methods in the codebase use. Auto-detection by content signature avoids the config burden entirely and trades it for a precise-enough heuristic.

---

## 2. Proposed Changes

### 2.1 Auto-detect rules-shaped methods

Add a shared concern `DetectsRulesShapedMethods` (in `src/Rector/Concerns/`) with a single predicate:

```php
private function isRulesShapedMethod(ClassMethod $method): bool;
```

A method is rules-shaped when ALL of these hold:

1. **Method body is `return [...];` only.** A single statement that returns an `Array_` literal. Multi-statement bodies, ternaries, builder pipelines, helper-method delegation — all rejected (we only convert the literal-array shape today; same constraint).
2. **Array is string-keyed.** All `ArrayItem` keys are `String_` (or `ClassConstFetch` resolving to a string). List-shape arrays (`['required', 'string']`) are values inside a rule definition, not rules definitions themselves.
3. **At least one item value is rule-shaped.** A value is rule-shaped if it matches any of:
   - **Pipe-delimited rule string** with at least one recognized Laravel rule token (`'required|string'`, `'nullable|email|max:255'`).
   - **`Rule::*()` static call** (`Rule::unique('users')`, `Rule::in([...])`).
   - **`FluentRule::*()` chain** (already converted; harmless to re-detect).
   - **Constructor-form rule object** (`new Password(8)`, `new Unique('users')`, `new Exists('roles')`).
   - **Array containing any of the above** (the `['required', 'string', Rule::unique(...)]` shape).

The predicate runs in `refactorFormRequest()` (and equivalents) when iterating methods. It REPLACES the current `$this->isName($method, 'rules')` filter.

### 2.2 Class-qualification gate (NEW — does not exist today)

**Codex review (2026-04-26) caught that the converter rectors DO NOT currently have a class-qualification gate.** Both `ValidationStringToFluentRuleRector` and `ValidationArrayToFluentRuleRector` dispatch every `ClassLike` into `refactorFormRequest()`, which iterates methods filtered only by name (`isName(method, 'rules')`). The name filter is the only thing preventing a Domain entity / Service / Action class from being walked. Swapping the name filter for a content detector without ALSO adding a class gate would expand the false-positive surface to every class in the codebase.

`GroupWildcardRulesToEachRector` has the same gap: literal `rules` name filter, no class gate.

`UpdateRulesReturnTypeDocblockRector` is the exception — it already has a `classQualifiesForPolish(Class_ $class): bool` gate. That gate operates on `Class_` (not arbitrary `ClassLike`) and silently rejects unrelated classes (no skip-log noise outside the proven model). It returns true for FormRequest ancestry OR fluent-validation trait usage. **It does NOT currently include generic Livewire ancestry** — Livewire components are handled by a separate path. The shared concern must preserve those properties.

**Phase 1 (new, prerequisite for Phase 3):** extract `classQualifiesForPolish` into a shared concern `QualifiesForRulesProcessing` exposing `qualifiesForRulesProcessing(Class_ $class): bool`. Apply it in the converter + grouping rectors BEFORE any method-level walk. Codex review (2026-04-26) caught the implementation must:

- Operate on `Class_`, not `ClassLike`. The existing converter dispatcher accepts `ClassLike`; narrow at the gate (interface/trait/enum bodies bail without log noise).
- Silently bail on non-qualifying classes — no skip-log entry. Logging would flood the actionable tier with one entry per non-qualifying class in a Laravel codebase.
- Cover FormRequest ancestry + fluent-validation trait usage. Livewire stays on its own path.

This is the same gate `UpdateRulesReturnTypeDocblockRector` uses today; extracting it gives the converter + grouping rectors a shared, proven boundary.

### 2.3 Rule-token recognition

Pipe-delimited string detection needs a known-token list to avoid false positives. There's no single canonical constant today — the converter implicitly knows rule names via `ConvertsValidationRuleStrings::TYPE_MAP` (line 100), the per-rule modifier sets, and the live `convertStringToFluentRule()` path that explodes on `|` and resolves each segment via `normalizeRuleName()`.

Two implementation options:

**Option A — declarative constant.** Add a new `KNOWN_RULE_NAMES` constant (a flat list of every Laravel rule name the converters recognize) inside `DetectsRulesShapedMethods` or a sibling concern. The detector tokenizes a candidate string and asserts at least one token resolves to a known name. Pros: cheap (no AST work, no tokenization side effects); transparent. Cons: another list to keep in sync with the converters' actual coverage.

**Option B — use the existing converter as the detector.** Call `convertStringToFluentRule()` on each candidate string and treat a non-null return as "rule-shaped". Pros: zero duplication; the source of truth IS the converter. Cons: convertStringToFluentRule does work (TYPE_MAP lookups, normalization) we'd then re-do during actual conversion; the detector becomes order-of-magnitude slower per method.

**Recommend Option A.** The list of recognized rule names is small (~80 entries) and stable (tracks Laravel's docs); duplication cost is one PR every few Laravel minor releases. The detection-vs-conversion separation also keeps the detector predictable — no AST mutation during discovery.

For the `Rule::*()` and constructor-form checks, reuse the existing detection from `ConvertsValidationRuleArrays` (`isRuleObjectFactory()` and friends).

### 2.4 New static-call dispatchers

Add two new arms to the existing `refactor()` dispatcher in both converter rectors. These are independent of Phase 2 (no class-gate dependency).

#### `Validator::validate([...], [...])`

`StaticCall` whose class resolves (via `getName`, which walks `use` aliases) to `Illuminate\Support\Facades\Validator` or `Illuminate\Validation\Validator` AND name is `validate`. Slot positions match `Validator::make`: arg 0 = data, arg 1 = rules. Reuses `refactorValidatorMake()`.

#### `validator([...], [...])` global helper

**Caveat caught by Codex review:** matching a `FuncCall` named `validator` is NOT equivalent to `Validator::make()`. PHP's name resolution checks the current namespace BEFORE the global namespace — an unqualified `validator()` in namespace `App\Foo` resolves to `App\Foo\validator()` if such a function exists, falling back to global `validator()` only when it doesn't. Naively rewriting any `FuncCall` named `validator` could mutate non-Laravel userland helpers.

Two strategies:

- **A — Strict resolution.** Resolve the call to the global `\validator` function via available PHPStan scope / name-resolution data; if scope is absent or the name doesn't resolve to the global function, bail. The exact API (PHPStan scope helpers, name-resolver attribute, etc.) is an implementation detail — the contract is "only rewrite when resolution proves the global function is the target". Other rectors in this repo already read `AttributeKey::SCOPE` and tolerate missing scope, so the pattern is established.
- **B — `use function validator` heuristic.** Only rewrite when the file imports `use function Illuminate\Support\validator` or the call uses a leading `\`. Most Laravel apps don't import the function explicitly — the implicit "global fallback" is the dominant call shape — so this would skip most real cases.

**Recommend A**, with the caveat that the bare `validator(...)` call (no leading backslash, no `use function`) requires PHPStan scope to confirm resolution to the global function. If scope is unavailable, bail and skip-log so a pure name match doesn't fire.

Both arms reuse `refactorValidatorMake()`'s array-extraction + conversion logic; only the dispatch arm changes.

### 2.5 Affected rectors

| Rector                                  | Change                                                                                          |
|-----------------------------------------|-------------------------------------------------------------------------------------------------|
| `ValidationStringToFluentRuleRector`    | Add class-qualification gate (Phase 0). Replace `isName(method, 'rules')` filter with `isRulesShapedMethod` (Phase 2). Add `Validator::validate` arm (Phase 1a). Add `validator()` helper arm (Phase 1b, conditional on PHPStan scope resolution). |
| `ValidationArrayToFluentRuleRector`     | Same.                                                                                           |
| `GroupWildcardRulesToEachRector`        | Add class-qualification gate (Phase 0). Replace `isName(method, 'rules')` with `isRulesShapedMethod` (Phase 2). Doesn't need the static-call arms. |
| `UpdateRulesReturnTypeDocblockRector`   | Already has the gate. Replace `rules`-only method check with `isRulesShapedMethod` (Phase 3). Update skip-log message strings that hardcode the word "rules()" to be method-name-aware. |

### 2.6 Pipeline skew with trait rectors (Codex review catch)

`AddHasFluentRulesTraitRector` and `AddHasFluentValidationTraitRector` decide whether to add the trait based on FluentRule usage detected inside `rules()` only. After Phase 2, a class might have `rules()` empty (delegating to helpers) and `editorRules()` containing the FluentRule chain — the trait rector wouldn't detect the FluentRule usage, would skip trait insertion, and the converted helper methods would have no trait support.

**Phase 2.5 (new):** widen the FluentRule-usage detector inside the trait rectors to walk ANY rules-shaped method (or, more conservatively, any method on a qualifying class — same gate). Otherwise auto-detection in the converter rectors creates a half-converted output where helpers have FluentRule chains but the class lacks the runtime trait.

### 2.7 Conservative defaults

- The detection is **strictly content-based** — no class-name pattern matching, no convention guessing.
- The class-qualification gate is the primary safety boundary. Method-content detection only runs INSIDE qualifying classes.
- A method that doesn't match the rule-shape signature stays untouched. The rector's behavior on existing `rules()` methods is unchanged because `rules()` already matches the signature trivially.
- Rule strings without recognized tokens (`'foo|bar'`) don't trigger detection. Pure escape-hatch code (e.g. `return ['flag' => 'on'];`) doesn't accidentally trigger conversion attempts.

---

## 3. Safety Analysis

### 3.1 False-positive surfaces

| Method shape                                                  | Rule-shaped? | Reasoning                                                                         |
|---------------------------------------------------------------|:------------:|-----------------------------------------------------------------------------------|
| `getEmailConfig(): array { return ['from' => 'foo@bar.com']; }` | ❌            | `'foo@bar.com'` isn't a pipe-delimited rule string with known tokens.            |
| `getDefaults(): array { return ['name' => 'Anonymous']; }`    | ❌            | `'Anonymous'` doesn't match the rule-token signature.                             |
| `getMessages(): array { return ['title.required' => 'X']; }`  | ❌            | `'X'` is a plain string, not a rule string.                                       |
| `getValidationContext(): array { return ['rules' => [...]]; }` | ❌            | Outer key's value is an `Array_`, not a rule string. Nested detection out of scope. |
| `editorRules(): array { return ['title' => 'required|string']; }` | ✅            | Pipe-delimited rule string with two known tokens.                                |
| `rulesWithoutPrefix(): array { return ['x' => Rule::in([...])]; }` | ✅            | `Rule::in()` static call.                                                         |

False-positive risk is dominated by methods that return a string-keyed array of strings where at least one string happens to match a pipe-delimited rule token. In practice this is rare — `'required|string'` style strings show up almost exclusively in validation contexts.

### 3.2 False-negative surfaces

- Methods whose return is `return $rules;` (variable, not literal). Existing `rules()` detection has the same blind spot; no regression.
- Methods that build the array via `collect()->put()->merge()->all()`. Out of scope (intractable; was already a Known Limitation).
- Multi-statement method bodies (`$x = ...; return [...];`). Codex review (2026-04-26) flagged this isn't a fringe case — `$rules = [...]; return $rules;` and conditionally-assembled `rules()` methods are common in larger codebases. Accepted limitation for v1 of this feature; could be loosened via the `InlineResolvableParentRulesRector` pattern (top-level-assign + return) in a follow-up if real cases surface.

### 3.3 Performance considerations

The class-qualification gate (Phase 0) is the load-bearing performance guard. Without it, Phase 2 would be `O(all classes × all methods × items)` and inappropriate for large codebases. WITH the gate, the cost reduces to `O(qualifying classes × their methods × items)` — typically a small minority of any Laravel codebase (FormRequests + Livewire components + classes using the package's traits). At that scope the cost matches what `UpdateRulesReturnTypeDocblockRector` already pays today.

No concrete benchmark numbers in this spec — the implementation is structurally bounded by the gate, and the rector ecosystem's per-file cost is dominated by Rector's parser and visitors, not our per-class predicate.

### 3.3 Backward compatibility

- Default behavior on `rules()` methods is unchanged — they still match the signature, still get processed.
- No new config knobs. No version-pinning concerns.
- Composer floor stays at fluent-validation ^1.20.

---

## 4. Tests

Per phase. Fixtures live under each rector's `Fixture/` directory.

---

## Implementation

Phases run in numeric order. Phase 1 is a hard prerequisite for Phase 3. Phase 3 and Phase 4 must ship together (same PR, same release) — splitting them produces half-converted classes per §2.6.

### Phase 1: Class-qualification gate (Priority: HIGH — prerequisite for Phase 3+)

Codex review (2026-04-26) caught the converter / grouping rectors lack a class gate. Without it, Phase 3 would scan every `ClassLike` for rule-shaped methods, producing massive false-positive surface (Domain entities / Services / Actions returning string-keyed arrays of strings).

- [x] Extract the class-qualification check from `UpdateRulesReturnTypeDocblockRector::classQualifiesForPolish()` into a new `src/Rector/Concerns/QualifiesForRulesProcessing.php` concern. Provides `qualifiesForRulesProcessing(Class_ $class): bool`. Covers FormRequest ancestry + `HasFluentRules` / `HasFluentValidation` / `HasFluentValidationForFilament` direct or ancestor usage + Livewire ancestry + classes with any `#[FluentRules]` attributed method. (Findings: needed Livewire + FluentRules-attribute branches discovered during test runs — see Findings.)
- [x] Apply the gate in `ValidationStringToFluentRuleRector::refactor()` and `ValidationArrayToFluentRuleRector::refactor()` BEFORE dispatching to `refactorFormRequest()`. Bails are silent (no skip-log entry).
- [x] Apply the gate in `GroupWildcardRulesToEachRector::refactorClass()`.
- [x] Refactor `UpdateRulesReturnTypeDocblockRector` to use the shared concern (replaces its inline qualifier; existing behavior preserved — 55 tests still green).
- [x] Tests — `skip_non_qualifying_class.php.inc` per rector (3 fixtures); 144 tests across all 4 affected rectors green.

### Phase 2a: `Validator::validate(...)` static call (Priority: HIGH — independent)

Smallest, fully independent of the gate / detector work. No class gate needed — the dispatcher handles the call shape directly.

- [x] Add `StaticCall` arm matching `Validator::validate(<data>, <rules>)` to `ValidationStringToFluentRuleRector::refactor()`. Resolution check happens inside the reused `refactorValidatorMake()` (already gates on `Illuminate\Support\Facades\Validator` via `isObjectType`).
- [x] Same for `ValidationArrayToFluentRuleRector::refactor()`.
- [x] Tests — `validator_validate_static_call.php.inc` per rector. 62 tests across both rectors green.

### Phase 2b: Global `validator()` helper (Priority: MEDIUM — independent)

Implement strict-resolution per §2.4. Can ship after 2a if needed.

- [x] Add `FuncCall::class` to both rectors' `getNodeTypes()`.
- [x] Add `FuncCall` arm matching `validator(<data>, <rules>)`. Conservative resolution via PhpParser's `namespacedName` attribute on the call's Name node — when set to a non-`validator` value, the call is in a non-empty namespace without a leading backslash and could shadow, so bail. When null (global namespace OR leading-backslash form), the call unambiguously targets the global `\validator` function.
- [x] Tests — positive: leading-backslash form (`\validator(...)`) per rector. Negative: namespaced bare-call (`validator(...)` inside `namespace App\Foo`) bails. 65 tests across both rectors green.

### Phase 3 + 4 (must ship together): auto-detection of rules-shaped methods + trait-rector pipeline catch-up (Priority: HIGH)

**Hard same-PR / same-release coupling.** In the `ALL` set, the order is `convert → group → traits`. Once Phase 3 starts converting helper methods, the trait rectors must already know to walk them — otherwise a single Rector run produces classes where helpers contain FluentRule chains but the runtime trait was skipped because the trait rector only inspected `rules()`. Splitting Phase 3 from Phase 4 across releases ships a half-converted state to consumers.

- [x] New `src/Rector/Concerns/DetectsRulesShapedMethods.php` trait with `isRulesShapedMethod(ClassMethod $method): bool` predicate per §2.1.
- [x] New `KNOWN_RULE_NAMES` constant inside the concern (Option A from §2.3) — flat list of ~100 Laravel rule names the converters recognize.
- [x] Reuse `Rule::*()` / constructor-form detection inline (Rule + Password/Unique/Exists/In/NotIn/Enum/Dimensions/File/ImageFile constructors).
- [x] Replace `$this->isName($method, 'rules')` with `$this->isRulesShapedMethod($method)` inside `ConvertsValidationRuleStrings::refactorFormRequest()` (the shared walker both converters use). Kept the existing `'rules'` and `hasFluentRulesAttribute` checks as short-circuits.
- [x] Replace the same check in `GroupWildcardRulesToEachRector::refactorClass()`.
- [x] **Same PR**: widen the FluentRule-usage detector in `AddHasFluentRulesTraitRector::usesFluentRule()` and `AddHasFluentValidationTraitRector::usesFluentRule()` to walk ANY method on the qualifying class.
- [x] Tests — positive (auto-detection): `auto_detect_editor_rules.php.inc` (string converter), `auto_detect_rule_object.php.inc` (array converter).
- [x] Tests — positive (trait pipeline): `auto_detect_trait_added_via_helper.php.inc`.
- [x] Tests — negative: `skip_messages_method.php.inc`, `skip_multi_statement_helper.php.inc`. 123 tests across 5 rectors green.

### Phase 5: Auto-detection in `UpdateRulesReturnTypeDocblockRector` (Priority: MEDIUM — depends on Phase 3+4)

- [x] Replace the `isName(method, 'rules')` filter with `isRulesShapedMethod`. Kept the `'rules'` short-circuit for perf.
- [x] Update skip-log messages that hardcoded `rules()` (3 sites: mixed-allowlist warning + nullable-return + multi-stmt-return) to use `getName($method)` so they reference the actual method name.
- [x] Tests — positive: `narrow_editor_rules_return.php.inc`. 56 docblock tests green; existing fixtures unchanged.

### Phase 6: Documentation (Priority: HIGH — same release as Phase 3+4)

- [x] Update README's "Known limitations" — replaced the conflated `Rules built outside rules(): array` entry with three precise entries (`withValidator()` callbacks, Collection pipelines, multi-statement helpers) plus an explicit "already covered" callout listing the now-supported cases (`Validator::validate`, global helper, custom-named methods).

---

## Open Questions

1. **Should the rule-shape signature treat a single Rule::*() call (no string keys) as rule-shaped?** Today's converters require a string-keyed array. Users who write `return [Rule::in([...]), Rule::dimensions(...)]` (list-shape, used inside a rule's value slot) almost never do that at the method's top level. Recommend: keep string-keyed-only. Surfaces a falsepositive guard naturally.

2. **Should the detector consider methods with `protected` / `private` visibility, or only `public`?** Helper methods like `editorRules()` are commonly `private`. Recommend: visit any visibility — the class-qualification gate is the real safety boundary. Visibility doesn't change rule semantics.

---

<!-- ## Resolved Questions
1. **{Original question?}** **Decision:** {What was decided.} **Rationale:** {Why.}
-->

## Findings

<!-- Notes added during implementation. Do not remove this section. -->

### Phase 1 implementation notes (2026-04-26)

- **Gate needs Livewire + FluentRules-attribute branches the spec didn't anticipate.** The original `classQualifiesForPolish()` covered FormRequest + traits only. Tests immediately surfaced two more qualifying signals:
  - **Livewire ancestry.** `CONVERT` runs BEFORE `TRAITS` in the `ALL` set, so Livewire components don't yet have `HasFluentValidation` when conversion fires. Adding Livewire detection to the shared gate (via the existing `IdentifiesLivewireClasses` concern) covers them. No-op for the docblock rector — its downstream `singleLiteralArrayReturn` / `allItemsAreFluentChains` checks reject non-FluentRule bodies regardless.
  - **`#[FluentRules]` attribute marker.** Custom validator classes (`SubtitleImportValidator extends FluentValidator`) opt into rule-bearing detection by attributing a method with `#[FluentRules]`. The shared concern checks for any method carrying this attribute as a fourth qualifying condition.
- **Indirect-Livewire fixture limitation.** The `group_livewire_indirect_component.php.inc` fixture extends `SomeAppBaseComponent` which would itself extend `Livewire\Component`. In the test env Livewire isn't a dev dependency, so `class_exists('Livewire\Component')` returns false and `isLivewireClass`'s ancestry walk can't reach it. Pre-Phase-1 the fixture passed because the rector had NO class gate; post-Phase-1 it silently skips. Converted the fixture to a no-change form documenting the gate's behavior on unloadable ancestries — in real consumer codebases (where Livewire IS installed) this case works correctly.
- **Const + import cleanup on UpdateRulesReturnTypeDocblockRector.** Removed the now-unused `QUALIFYING_TRAIT_FQNS` const + `HasFluentRules` / `HasFluentValidation` / `HasFluentValidationForFilament` / `FormRequest` imports.

### Codex review-pass findings (2026-04-26)

Three HIGH findings from Codex on the implemented branch. Resolutions:

- **Eloquent-shape false positive on `casts()` (HIGH).** `casts(): array` returning `['active' => 'boolean', 'meta' => 'array']` matches the rules-shape signature because `'boolean'`/`'array'` are in `KNOWN_RULE_NAMES`. Without a guard the converter would rewrite cast declarations as FluentRule chains and corrupt model behavior. **Fix:** added `NON_RULES_METHOD_NAMES_DENYLIST` in `DetectsRulesShapedMethods` (14 names: `casts`, `getCasts`, `getDates`, `attributes`, `validationAttributes`, `messages`, `validationMessages`, `middleware`, `getRouteKeyName`, `broadcastOn`, `broadcastWith`, `toArray`, `toJson`, `jsonSerialize`) — short-circuits at the top of `isRulesShapedMethod()`. Regression: `skip_casts_method.php.inc`.

- **`ClassConstFetch` keys accepted as string-like (HIGH).** `[Status::ACTIVE => 'required|string']` would pass the shape check even though `Status::ACTIVE` may resolve to int / enum at runtime, mis-classifying status-message lookup tables as rules arrays. **Fix:** dropped `ClassConstFetch` from `isStringLikeKey` — accept only literal `String_`. Methods whose rules array uses class-const keys still convert via the literal `rules()` name path or the `#[FluentRules]` opt-in. Regression: `skip_class_const_keyed_map.php.inc`.

- **Parent-safety still `parent::rules()`-only (HIGH).** Auto-detect now widens to methods like `editorRules()`, but `collectUnsafeParentClass()` and the filesystem fallback still watched only `parent::rules()` calls — a child doing `array_merge(parent::editorRules(), […])` could let the converter rewrite the parent's `editorRules()` while the child still treated it as a plain array. **Fix:** widened `collectUnsafeParentClass()` to scan every child method (was: only `rules()`) for any `parent::*()` call combined with array manipulation in the same body, and updated the filesystem-scan regex from `parent::rules()` to `parent::\w+\s*\(`. Regression: `skip_parent_auto_detected_method_manipulation.php.inc`.

- **Imported `use function ... validator` (Codex MEDIUM, accepted).** Bare `validator(...)` after `use function Foo\validator` resolves to `Foo\validator`, which PhpParser's NameResolver attaches as `namespacedName` on the FuncCall name. The current conservative check (`namespacedName !== 'validator'`) bails on this case — the call is left untouched. Accepted as a limitation; users can prefix with `\` or move to `Validator::make(…)` / `Validator::validate(…)` to opt in. Documented behavior, not a bug.

- **`#[FluentRules]` attribute bypasses shape check (Codex HIGH, accepted).** A `#[FluentRules]`-attributed multi-statement method has all nested `Return_ + Array_` walked by the converter, so a stray inner array could be rewritten. This matches pre-existing `rules()` behavior — both are explicit opt-ins (a method named `rules()` on a qualifying class signals "convert me"; `#[FluentRules]` is the same signal under another name). The auto-detect path (Phase 3) keeps the single-literal-return safety boundary; the opt-in paths trust the user. No fix.

### Codex re-review (2026-04-26, second pass)

After the three fixes above landed, a second Codex pass surfaced two more HIGH:

- **Case-sensitive denylist (HIGH).** PHP method names are case-insensitive at runtime — `Casts()` IS Eloquent's `casts()`. The original denylist lookup used the source-cased `getName()` value, so a literal-cased `Casts()` slipped past the guard and reopened the cast-corruption surface. **Fix:** `strtolower()` the source-cased name before the `isset()` lookup, and stored the `NON_RULES_METHOD_NAMES_DENYLIST` keys as lowercase. Regression: `skip_casts_method_mixed_case.php.inc`.

- **Filesystem-fallback regex missed `collect()` (HIGH).** The in-AST scan recognises `collect(parent::editorRules())->merge(…)` as array manipulation, but the filesystem fallback's `ARRAY_MANIPULATION_PATTERN` only matched `array_*` / `in_array` / bracket assignment. A parent-first or parallel run could convert an unsafe parent before any worker had AST-scanned the child. **Fix:** added `\bcollect\s*\(` to `ARRAY_MANIPULATION_PATTERN` so the layer-3 fallback recognises the same shape the AST scan does.

### Codex re-review (2026-04-26, third pass)

- **Multi-class file resolution (HIGH).** `resolveParentFqcnFromSource()` used `preg_match` (single match), so in a file with two `class X extends Y` declarations only the FIRST parent was extracted — a later child manipulating its own parent's auto-detected method could leave that parent unmarked. Pre-existing bug, but the widening surfaces it more often. **Fix:** renamed to `resolveParentFqcnsFromSource()`, switched to `preg_match_all`, returns the de-duplicated list of every parent FQCN in the file. Caller marks all of them unsafe. Over-marks slightly when only one of two children does the manipulation, but the regex pre-filter already proved the file has both a `parent::*()` call AND an array-manipulation primitive, so the over-mark is on a candidate plausibly the unsafe parent — corruption-safe trade-off.

### Codex re-review (2026-04-26, fourth pass)

- **Aliased `use ... as` parents (HIGH).** `resolveParentFqcnsFromSource()` matched only direct imports (`use Foo\Bar;`) — `use Foo\Bar as BaseRequest; class Child extends BaseRequest` left the real parent unmarked in the cross-file fallback. **Fix:** added an alias-aware regex (`use\s+([\w\\]+)\s+as\s+<parentRef>`) that runs BEFORE the direct-import regex; resolves the aliased FQCN. Pre-existing gap exposed by the widening's broader parent-call surface.

- **Bracketed / multi-namespace files (Codex HIGH, accepted).** `resolveParentFqcnsFromSource()` recognises only the `namespace Foo;` line form. PHP also supports the bracketed form (`namespace Foo { ... }`) and multiple namespace blocks per file. PSR-4 (which Laravel apps + this package follow) effectively forbids both — one namespace per file, no brackets — so the chance of encountering a real consumer codebase that exercises the gap is negligible. Documented limitation; no fix.

### Codex re-review (2026-04-26, fifth pass — codex-plugin)

- **`unset()` is a `Stmt\Unset_`, not `FuncCall` (HIGH).** The legacy `'unset'` string in the `FuncCall` in_array list was dead code — `unset(...)` is a language construct that PhpParser models as `Stmt\Unset_`. A child mutating `parent::editorRules()` via `unset(...)` slipped past the parent-safety guard. **Fix:** explicit `Stmt\Unset_` check in the AST walk + `\bunset\s*\(` added to the filesystem-fallback regex. Removed the dead `'unset'` from the FuncCall list. Regression: `skip_parent_auto_detected_method_unset.php.inc`.

- **One `#[FluentRules]` method authorized class-wide auto-detection (HIGH).** A class qualifying ONLY via the per-method `#[FluentRules]` attribute used to enable auto-detect of every sibling rules-shaped method, turning a per-method opt-in into a class-wide trust boundary bypass. **Fix:** introduced `qualifiesForRulesProcessingClassWide()` on `QualifiesForRulesProcessing` (FormRequest / fluent trait / Livewire only — NOT attribute). Each consumer rector (`ConvertsValidationRuleStrings::refactorFormRequest`, `GroupWildcardRulesToEachRector`, `UpdateRulesReturnTypeDocblockRector`) gates the auto-detect path on this stricter predicate. Attributed methods themselves still process via the `hasFluentRulesAttribute` branch in each gate. Promoted `hasFluentRulesAttribute()` from `ConvertsValidationRuleStrings` (private) into `DetectsRulesShapedMethods` (shared), so all three consumer paths use one definition. `ConvertLivewireRuleAttributeRector` gained `QualifiesForRulesProcessing` + its `DetectsInheritedTraits` / `IdentifiesLivewireClasses` dependencies (PHPStan analyses the trait body in the consumer's context, not just at runtime). Regression: `attribute_only_class_skips_sibling_helper.php.inc`.

- Cognitive-complexity baseline bumped 179 → 181 (`GroupWildcardRulesToEachRector`).
