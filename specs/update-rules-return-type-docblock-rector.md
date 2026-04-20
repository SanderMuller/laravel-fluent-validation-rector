# UpdateRulesReturnTypeDocblockRector

## Overview

Opt-in polish rule that narrows the `@return` PHPDoc annotation on `rules()` methods to `array<string, FluentRuleContract>` when the returned array is composed entirely of `FluentRule::*()` call chains. Introduced alongside `sandermuller/laravel-fluent-validation` 1.17.0, which added the `FluentRuleContract` interface that every `*Rule` class implements. Rule is cosmetic-ergonomic, not correctness-driven — it upgrades the docblock shape after a successful conversion so downstream editors/static analyzers get a narrower type without changing runtime behavior.

---

## 1. Rule Metadata

- **Class**: `SanderMuller\FluentValidationRector\Rector\UpdateRulesReturnTypeDocblockRector`
- **Emitted annotation**: `array<string, \SanderMuller\FluentValidation\Contracts\FluentRuleContract>`
- **Native return type**: untouched (`: array` stays — generic shape is PHPDoc-only).
- **Set membership**: new `FluentValidationSetList::POLISH` constant pointing at `config/sets/polish.php`. **Not** added to `ALL` — opt-in only. Opt-in placement lets downstream run `vendor/bin/rector process --config=... POLISH` separately from the initial conversion pipeline.

## 2. Emit Conditions

All five must hold. Any failure → skip (with `logSkip` reason; see §6).

1. **Method signature**: method name is `rules`, return type is non-nullable `array` (not `?array`, not union), zero required params. Matches `FormRequest::rules()` and the `HasFluentRules` / `HasFluentValidation` / `HasFluentValidationForFilament` trait shape.
2. **Single literal-`Array_` return**: method body has exactly one `Return_` statement whose `expr` is an `Array_` node. Methods with multiple `return` stmts (early-return for admin, etc.), `return RuleSet::from(...)`, `return $rules;` (builder variant), or no return at all → skip. Multi-return is the important disqualifier — the per-branch arrays may differ.
3. **Every `ArrayItem` value is a recognized FluentRule chain**:
   - Key is non-null AND is `String_` or `ClassConstFetch` (no auto-indexed numeric keys — annotation claim is `array<string, …>`).
   - Value's innermost `->var` walk terminates at a `StaticCall` whose `class` resolves (via Rector's `NodeNameResolver::getName()`, not raw `toString()`) to `SanderMuller\FluentValidation\FluentRule`. Alias imports (`use FluentRule as FR`) must still match.
   - Disqualifying value shapes: `Array_` (conditional-tuple `[$bool, FluentRule::...]`), `Spread`, `Match_`, `Ternary`, `Variable` (assigned-then-returned), `New_`, bare `String_` rule tokens, `StaticCall` on anything other than `FluentRule`.
   - `FluentRule::anyOf(...)` and `FluentRule::when(...)` are still `StaticCall` on `FluentRule` — they pass.
4. **Existing `@return` tag is one of**:
   - Absent entirely.
   - Exactly `array` (no generic).
   - **Canonical-wide-union plus optional trailing prose only**. The annotation body (after `RETURN_TAG_PATTERN` extraction + continuation concat + trim) must satisfy both:
     1. Starts with `NormalizesRulesDocblock::STANDARD_RULES_ANNOTATION_BODY` verbatim — `array<string, \Illuminate\Contracts\Validation\ValidationRule|string|array<mixed>>`.
     2. The remainder (everything after the standard body) is empty / whitespace-only OR pure prose matching `/^\s+[A-Za-z][A-Za-z0-9 ,.'\-]*$/`.
     3. **Reject** any remainder containing type-syntax characters `|`, `&`, `<`, `>`, `(`, `)`, `[`, `]`, `@`, or a `\`-prefixed FQN token. This guards against false-positive narrowing of user-widened types like `array<string, \Illuminate\Contracts\Validation\ValidationRule|string|array<mixed>>|\Illuminate\Support\Collection` — those start with the standard body, but the trailing `|Collection` is a genuine additive union the user authored. Silently stripping it would be a type-lie regression. Codex-review flagged this exact case.
     4. Multi-line continuation concatenation happens before the remainder check — a 2-line wrapped standard-union must be parsed fully before deciding prose vs type-suffix.
   - **Never** when docblock contains `@inheritDoc` — respect inherited contract from parent's `rules()`.
   - Any user-customized annotation (`@return array<string, ValidationRule>`, `@return array<string, mixed>`, widened union, intersection, nested generic, etc.) → skip.
5. **Class context** (any one):
   - **Current class OR any ancestor extends FormRequest.** Pattern-match on `$class->extends->toString()` is **not enough** on two axes:
     - **Alias resolution**: `use App\Http\Requests\BaseRequest as BaseRequestAlias; class Foo extends BaseRequestAlias` — `toString()` returns the raw `BaseRequestAlias` literal, `class_exists` fails, reflection walk aborts. Must resolve via `$this->getName($class->extends)` (Rector's `NodeNameResolver`) before feeding to `ReflectionClass`.
     - **Intermediate hierarchy**: `extends BaseAdminRequest extends FormRequest`. Walk `ReflectionClass::getParentClass()` to the root.
     - New helper `anyAncestorExtends(Class_ $class, string $fqn): bool` — takes the resolved parent FQN, not the raw name. Returns false on reflection failure (parent not autoloadable).
   - **Current class directly uses OR any ancestor uses** one of `[HasFluentRules, HasFluentValidation, HasFluentValidationForFilament]`.
     - `DetectsInheritedTraits::anyAncestorUsesTrait(...)` currently only inspects **parents** (it early-returns when `$class->extends` is not a `Name`). A class with no parent but `use HasFluentRules;` directly would silently fail condition 5 today. This breaks the `uses_has_fluent_rules_trait.php.inc` emit fixture.
     - Fix: add new helper `currentOrAncestorUsesTrait(Class_ $class, string $traitFqn): bool` that first scans `$class->getTraitUses()` (AST-level — each `TraitUse` node has `->traits` as `Name[]`, resolve each via `$this->getName(...)`) before falling back to the ancestor walk. Use this new helper in condition 5 instead of `anyAncestorUsesTrait`.

## 3. AST Entry Point

`getNodeTypes(): array` returns `[Class_::class]`. Refactor at class level, not method level:

1. Run condition 5 (class context) once against the `Class_` node. Short-circuit return `null` if neither ancestor-extends nor trait check passes — **do not log** (see §6).
2. Iterate `$class->stmts` for `ClassMethod` nodes matching condition 1 (`name === 'rules'`, non-nullable `array` return type).
3. For each matching method, run conditions 2–4 and rewrite its docblock if they pass. Log skip reasons per §6 when a method fails 1–4 inside a class that passed 5.
4. If any method was rewritten, return the mutated `Class_`. Otherwise return `null` (idempotency guard — see §8).

Class-level entry runs the reflection walk in condition 5 once per class rather than once per method. Also keeps skip-log scope tight: non-qualifying classes produce no log noise.

## 4. Shared Helpers

### Reuse (do not duplicate)

- `LogsSkipReasons` trait — skip-reason logging via `logSkip($class, $reason)`. Writes to tmp-scoped log file; verbose mode mirrors to STDERR. Works under `withParallel()`.
- `DetectsInheritedTraits::anyAncestorUsesTrait(...)` — for condition 5 trait check.
- `NormalizesRulesDocblock::RETURN_TAG_PATTERN` regex + `STANDARD_RULES_ANNOTATION_BODY` constant — for condition 4 prefix match. Either extract both to a shared constant holder or require the new trait `use NormalizesRulesDocblock;` purely for the constant access (acceptable — no behavior reuse needed).

### New

- `anyAncestorExtends(Class_ $class, string $fqn): bool` — new method on a new trait `DetectsAncestorClass` OR added to `DetectsInheritedTraits`. Reflection-based, walks `ReflectionClass::getParentClass()`. Returns false on reflection failure (parent not autoloadable). Same degradation behavior as existing trait methods.
- `isFluentRuleChainValue(Expr $value): bool` — private method on the new rector. Walks `MethodCall->var` until non-`MethodCall`, asserts `StaticCall` with resolved name `FluentRule::class`. Must use `$this->getName($staticCall->class)` for alias-safe FQN resolution.

## 5. Set File + Set Registration

New file `config/sets/polish.php`:

```php
<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\UpdateRulesReturnTypeDocblockRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(UpdateRulesReturnTypeDocblockRector::class);
};
```

New constant in `src/Set/FluentValidationSetList.php`:

```php
public const string POLISH = self::SETS_DIR . 'polish.php';
```

`SetListTest` will need a fixture asserting the set file exists and loads. `config/sets/all.php` stays untouched — POLISH is explicitly opt-in.

## 6. Skip-Reason Logging

Reuse `LogsSkipReasons::logSkip(Class_ $class, string $reason)`. **Scope**: only log skips when the class-context check (condition 5) passes but a downstream condition (1–4) fails. Do not log for classes that fail condition 5 — that would spam the log with every unrelated class in the codebase, defeating the signal-to-noise purpose of the skip log. Condition 5 is the qualifying gate; failures inside a qualifying class are the interesting signal.

Reasons to emit (one per skip path, all fired only after condition 5 passes):

- `"method rules() has multi-stmt return — cannot narrow contract"`
- `"value at key '{key}' is not a FluentRule chain (shape: {Node short-name})"`
- `"existing @return tag '{truncated body}' is user-customized — respecting"`
- `"docblock contains @inheritDoc — respecting parent contract"`
- `"method rules() has nullable array return — cannot narrow contract"`
- `"return expression is {Node short-name}, not Array_ literal"`
- `"ArrayItem key at index {n} is not String_ / ClassConstFetch"`

Per `LogsSkipReasons` dedupe: `(rule, file, class, reason)` tuple is unique per process. Verbose-mode STDERR mirror helps users running single-file rector runs locally.

## 7. Tests

Layout mirrors existing rector tests: `tests/UpdateRulesReturnTypeDocblock/` with `Fixture/`, `config/configured_update_rules_return_type_docblock_rule.php`, and `UpdateRulesReturnTypeDocblockRectorTest.php`.

### Fixture matrix

Every fixture is a `.php.inc` before/after pair (Rector test convention: source + `-----` separator + expected output; no separator = no-op).

**Emit**:

- `all_fluent_no_annotation.php.inc` — pure-fluent return, no docblock → emit contract annotation.
- `all_fluent_wide_union_annotation.php.inc` — pure-fluent return + existing `STANDARD_RULES_ANNOTATION_BODY` → narrow to contract. **Load-bearing case** on freshly-converted codebases.
- `all_fluent_wide_union_with_trailing_prose.php.inc` — wide union + trailing description → narrow (prefix-match coverage).
- `all_fluent_wide_union_multiline.php.inc` — wide union wrapped across two lines → narrow (continuation-regex coverage).
- `all_fluent_plain_array_annotation.php.inc` — existing `@return array` (no generic) → narrow to contract.
- `fluent_anyof_and_when.php.inc` — return uses `FluentRule::anyOf(...)` + `FluentRule::when(...)` → still chains, emit.
- `fluent_alias_import.php.inc` — `use FluentRule as FR; FR::string()->...` → emit (alias resolution).
- `extends_intermediate_base_request.php.inc` — `extends BaseAdminRequest extends FormRequest`, pure-fluent → emit (ancestor-chain coverage).
- `uses_has_fluent_rules_trait.php.inc` — non-FormRequest class with direct `use HasFluentRules;` on the class itself (no parent class) → emit. Verifies current-class-trait detection (Codex finding #1).
- `uses_has_fluent_validation_trait.php.inc` — non-FormRequest class with direct `use HasFluentValidation;` → emit.
- `livewire_with_rules_method.php.inc` — Livewire component with both `#[Validate]` attributes AND a `rules()` method (hybrid) → emit on the method path.
- `extends_aliased_form_request.php.inc` — `use Illuminate\Foundation\Http\FormRequest as BaseRequest; class Foo extends BaseRequest` → emit. Verifies `$this->getName()` alias resolution before reflection (Codex finding #2).
- `extends_aliased_intermediate_base.php.inc` — `use App\Http\Requests\BaseAdminRequest as Base; class Foo extends Base` where `BaseAdminRequest extends FormRequest` → emit. Combines alias + ancestor walk.

**Skip** (per repo convention, all skip fixtures use `skip_` prefix — confirmed via `tests/AddHasFluentValidationTrait/Fixture/`):

- `skip_already_contract.php.inc` — annotation already `array<string, FluentRuleContract>` → no diff (idempotency guard).
- `skip_user_annotation.php.inc` — user wrote `@return array<string, ValidationRule>` → respect.
- `skip_inherit_doc.php.inc` — pure-fluent + `@inheritDoc` → respect inherited contract.
- `skip_mixed_rule_object.php.inc` — contains `Rule::in(...)` → skip.
- `skip_mixed_string_rule.php.inc` — contains `'name' => 'required|string'` → skip.
- `skip_mixed_new_custom_rule.php.inc` — contains `new CustomRule()` → skip.
- `skip_conditional_tuple_value.php.inc` — `'field' => [$cond, FluentRule::...]` → skip.
- `skip_spread_in_return.php.inc` — `return [...$shared, 'field' => FluentRule::...]` → skip.
- `skip_match_at_value.php.inc` — `'field' => match ($foo) { ... }` → skip.
- `skip_ternary_at_value.php.inc` — `'field' => $foo ? FluentRule::... : FluentRule::...` → skip (both arms fluent but analysis deferred).
- `skip_rules_builder_variant.php.inc` — `$rules = []; $rules[...] = ...; return $rules;` → skip (not direct literal).
- `skip_ruleset_from_return.php.inc` — `return RuleSet::from([...]);` → skip (not `Array_`).
- `skip_multi_return_method.php.inc` — `if ($this->isAdmin()) return [...]; return [...];` → skip.
- `skip_numeric_key_item.php.inc` — `return [FluentRule::string(), FluentRule::numeric()];` (unkeyed) → skip.
- `skip_nullable_array_return.php.inc` — `): ?array` → skip.
- `skip_livewire_property_rules.php.inc` — rules-on-property (`#[Validate]`), no `rules()` method → skip.
- `skip_unrelated_class_with_rules_method.php.inc` — random class named `rules()` returning an array, not a FormRequest, no trait → skip (condition 5).
- `skip_widened_union_annotation.php.inc` — existing `@return array<string, \Illuminate\Contracts\Validation\ValidationRule|string|array<mixed>>|\Illuminate\Support\Collection` → skip (Codex finding #3: remainder contains `|\`, type-suffix reject). The union is user-authored refinement, not prose.
- `skip_widened_intersection_annotation.php.inc` — existing `@return array<string, \Illuminate\Contracts\Validation\ValidationRule|string|array<mixed>>&\Countable` → skip (remainder contains `&`).
- `skip_nested_generic_after_standard.php.inc` — existing `@return array<string, \Illuminate\Contracts\Validation\ValidationRule|string|array<mixed>><int>` (invalid shape but possible authorship error) → skip (remainder contains `<`).

### Integration tests

- `tests/FullPipeline/` gets a new fixture asserting POLISH set, run after CONVERT, narrows a realistic fixture. No POLISH-in-ALL regression (assert `all.php` does not register the new rule).
- `SetListTest` covers the new constant loading.
- `RectorInternalContractsTest` — if it enforces rule discovery or `DocumentedRuleInterface`, add the new rule to the expected set.
- `ValidationEquivalenceTest` — N/A, no runtime behavior change.

### Skip-log assertion

Add a test under `tests/UpdateRulesReturnTypeDocblock/` that runs the rule over a mixed fixture, captures the tmp-scoped skip log, and asserts the expected reasons appear. Mirror the pattern used by existing skip-log tests (check `AddHasFluentValidationTrait` or similar).

## 8. Rector API Usage

Summary of `AbstractRector` protected methods consumed by this rule (confirmed via `rector-developer` skill reference):

- `$this->getName(Node $node): ?string` — alias-tolerant FQN resolution for the `StaticCall->class` `Name` node. Returns `null` on dynamic names; treat as skip.
- `$this->isName(Node $node, string $name): bool` — convenience check for `$method->name` (`'rules'`) and `$arrayItem->key` when it's a `String_`.
- **Do not use** `$this->isObjectType(...)` on the `StaticCall->class` — it's a `Name` node, not a typed expression; `getName()` is the right tool. `isObjectType` would be required only if resolving via method-call target type.
- Class-name AST nodes (none created here — docblock rewrite is string-based via `RETURN_TAG_PATTERN`). If future iterations emit a class-reference AST node, use `Node\Name\FullyQualified` with no leading backslash.

**Return value from `refactor(Class_ $class)`**:

- Return the modified `Class_` when any method's docblock was rewritten.
- Return `null` when no method qualified — critical for idempotency. Rector re-runs rules until stable; returning a same-shape `Class_` unchanged still counts as "no change" but skipping the return path is cleaner.
- Never return empty array (`ShouldNotHappenException`).

**Idempotency**: second pass over an already-narrowed file must emit nothing. The "exactly `array<string, FluentRuleContract>`" fixture (`all_fluent_already_contract.php.inc`) is the primary guard — condition 4's prefix match against `STANDARD_RULES_ANNOTATION_BODY` won't match the narrowed form, so the rule naturally skips. Verify with a dedicated second-pass test that runs the rule twice and asserts byte-identical output on pass 2.

**Test fixture mechanics** (Rector convention):

- Emit fixtures: input + `-----` separator + expected output. Run with empty expected block and `FixtureFileUpdater` auto-fills on first test run.
- Skip fixtures: single section, **no** `-----` separator. The `skip_*.php.inc` naming convention applies.
- Config at `tests/UpdateRulesReturnTypeDocblock/config/configured_update_rules_return_type_docblock_rule.php` registers only `UpdateRulesReturnTypeDocblockRector::class`.

## 9. Documentation

- New entry in `README.md` rules table with code sample (before/after).
- `DocumentedRuleInterface::getRuleDefinition()` on the rule class, following the `SimplifyFluentRuleRector::getRuleDefinition()` shape. Code samples must show the wide-union-to-contract narrowing, not just the no-annotation emit case — narrowing is the headline behavior.

## 10. Configurability — deferred

Rule is **not** `ConfigurableRectorInterface` in v1. Downstream forks of `sandermuller/laravel-fluent-validation` that wrap or subclass `FluentRule` under a different FQN would need to override the hard-coded `FluentRule::class` and `FluentRuleContract::class` references. Adding configurability is mechanical (`->withConfiguredRule(UpdateRulesReturnTypeDocblockRector::class, ['fluent_rule_class' => '...', 'contract_fqn' => '...'])`) but speculative — no known downstream does this today. Parked until asked. See Open Question §3.

## Implementation

### Phase 1: Helpers + Detection (Priority: HIGH)

- [x] Add `anyAncestorExtends(Class_ $class, string $fqn): bool` to `DetectsInheritedTraits` trait (or new `DetectsAncestorClass` trait). **Must resolve `$class->extends` via `$this->getName(...)` before reflection** — raw `toString()` fails on aliased imports (Codex finding #2). Reflection walk mirroring existing trait method.
- [x] Add `currentOrAncestorUsesTrait(Class_ $class, string $traitFqn): bool` to `DetectsInheritedTraits`. First scans `$class->getTraitUses()` (AST `TraitUse` nodes, resolve each `Name` via `$this->getName(...)`), then falls back to existing `anyAncestorUsesTrait` ancestor walk. Replaces direct use of `anyAncestorUsesTrait` in condition 5 (Codex finding #1 — existing helper skips classes with direct trait use but no parent).
- [x] Add `annotationBodyMatchesStandardUnionExactlyOrProse(string $body): bool` helper to `NormalizesRulesDocblock` (or new trait `MatchesFluentDocblock`). Logic per condition 4: starts-with check + remainder prose-only regex + type-syntax-char reject list (Codex finding #3).
- [x] Extract or expose `STANDARD_RULES_ANNOTATION_BODY` + `RETURN_TAG_PATTERN` so the new rule can consume them without duplicating. Option A: make them `protected` (currently `private`) and use the trait. Option B: move to a shared `Concerns\FluentDocblockConstants` readonly class. Prefer A — minimal change.
- [x] Tests — unit tests for: `anyAncestorExtends` with aliased parent, `currentOrAncestorUsesTrait` with direct use on parentless class, `annotationBodyMatchesStandardUnionExactlyOrProse` with prose-tail (accept), union-tail (reject), intersection-tail (reject), plain-text-only (accept).

### Phase 2: Rector Rule (Priority: HIGH)

- [x] Create `src/Rector/UpdateRulesReturnTypeDocblockRector.php`. Class-level `getNodeTypes` returning `[Class_::class]`. Implement `DocumentedRuleInterface`.
- [x] Implement `refactor(Class_ $class)`: class-context check → iterate `ClassMethod` nodes → per method, run emit conditions 1–4.
- [x] Implement `isFluentRuleChainValue(Expr): bool` — alias-safe FQN resolution via `$this->getName()`.
- [x] Implement `emitContractAnnotation(ClassMethod $method): void` — add new docblock OR overwrite existing-wide-union via `RETURN_TAG_PATTERN` rewrite, mirroring `NormalizesRulesDocblock::normalizeRulesDocblockIfStale` replacement mechanics.
- [x] Wire `LogsSkipReasons` trait; emit skip reasons per §6.
- [x] `getRuleDefinition()` with the narrowing code sample.
- [x] Tests — full fixture matrix from §7 (Fixture dir + test file + configured set). **40 fixtures total** (15 emit + 25 skip) — exceeds the 10+17 baseline. Peer contributions from mijntp/hihaho added 8 real-world shapes (see Phase 2 findings).

### Phase 3: Set Registration (Priority: HIGH)

- [x] Add `POLISH` constant to `src/Set/FluentValidationSetList.php`.
- [x] Create `config/sets/polish.php` registering only this rule.
- [x] Do **not** add to `config/sets/all.php`. Confirm with a test assertion.
- [x] Tests — update `SetListTest` to cover the new constant; add assertion that POLISH is not a subset of ALL.
- [x] Bump composer requirement: `sandermuller/laravel-fluent-validation: ^1.17` (package peer confirmed Option A — opt-in consumers should upgrade in tandem since narrowed annotation is meaningless without the contract resolving).

### Phase 4: Documentation + Release Notes (Priority: MEDIUM)

- [x] Add README rule entry with before/after narrowing sample. Link the `FluentRuleContract` interface from the `laravel-fluent-validation` 1.17.0 release.
- [x] Write `RELEASE_NOTES_<version>.md` describing the new opt-in rule and the POLISH set. CI promotes this into `CHANGELOG.md` on tag — do not hand-edit `CHANGELOG.md`.
- [x] Tests — N/A (doc-only).

### Phase 5: Verification (Priority: HIGH)

- [x] `vendor/bin/pint --dirty --format agent || true` — code style clean.
- [x] `vendor/bin/pest --filter=UpdateRulesReturnTypeDocblock || true` — new rule tests pass (40 fixtures).
- [x] `vendor/bin/rector process || true` — 0 changes against self (rector's own import/type fixes applied during pass; re-run clean).
- [x] `vendor/bin/phpstan analyse --memory-limit=2G || true` — 0 errors.
- [x] `vendor/bin/pest || true` — full suite, 0 failures (249 passed, 302 assertions, 1 upstream-deprecated note).

---

## Open Questions

1. **Option B follow-up timing.** Should `NormalizesRulesDocblock::STANDARD_RULES_ANNOTATION_BODY` itself be narrowed to `FluentRuleContract` on the fresh-emit path when the rector's output is pure-fluent? Parked for post-1.0 of this polish rule. Risk flagged during design: `NormalizesRulesDocblock` runs mid-conversion when arrays may still contain non-fluent values being converted in the same pass — narrowing there could emit `FluentRuleContract` on partially-converted arrays where one item remained a string rule Rector couldn't handle. Polish-rule-at-end-of-pipeline is the safer surface. Revisit after downstream adoption confirms stability.
2. **Ternary/match with all-fluent arms.** Both branches tracing to `FluentRule::*` chains are technically contract-compatible. Current spec skips. Worth a follow-up rule that handles this case, or leave as permanent skip? Tradeoff: correctness confidence vs. coverage on real codebases (mijntp's FormRequests rarely use ternary rules; hihaho unknown).
3. **Rule key expression types beyond `String_` / `ClassConstFetch`.** Enum-valued keys (`FormField::Name->value => FluentRule::...`) resolve to strings at runtime but the AST sees a `MethodCall`/`PropertyFetch` on an enum. Claim `array<string, …>` is still correct. Include or exclude? Exclude in v1, revisit if downstream requests.
4. **Make rule `ConfigurableRectorInterface`.** Would allow downstream forks to override `FluentRule::class` / `FluentRuleContract::class` FQNs. Speculative — no known downstream wraps these today. Defer until first request; when implemented follow the `->withConfiguredRule(..., ['fluent_rule_class' => ..., 'contract_fqn' => ...])` shape from rector-developer's configurable-rules reference.

---

## Resolved Questions

1. **How aggressive should `@return` prefix matching be?** **Decision:** exact-body + prose-only-tail. Reject any remainder containing type-syntax (`|`, `&`, `<`, `>`, `(`, `)`, `[`, `]`, `@`, `\`-FQN). **Rationale:** naïve prefix match false-positive narrows user-widened unions like `STANDARD_BODY|\Illuminate\Support\Collection`, erasing additive type members the user authored. Pure-prose tails (`List of rules keyed by field`) are still tolerated because they carry no type semantics.
2. **Does `anyAncestorUsesTrait` cover classes with direct trait use but no parent?** **Decision:** no — existing helper returns false when `$class->extends` is not a `Name`. Add new `currentOrAncestorUsesTrait` that first scans `$class->getTraitUses()` at AST level before delegating to the ancestor walk. **Rationale:** condition 5's `HasFluentRules` / `HasFluentValidation` emit path targets non-FormRequest classes that directly use the trait — the bulk of Livewire and standalone-service cases. Reflecting only parents skips the entire motivating population.
3. **Should `anyAncestorExtends` consume `$class->extends->toString()` or the resolved name?** **Decision:** resolved name via `$this->getName(...)`. **Rationale:** aliased imports (`use FormRequest as BaseRequest`) return the alias literal from `toString()`, which `class_exists` cannot load, aborting the reflection walk. `NodeNameResolver::getName()` resolves through `use` statements to the FQN.
<!--
4. **{Original question?}** **Decision:** {What was decided.} **Rationale:** {Why.}
-->

## Findings

### Phase 1 (2026-04-20)

- Chose **Option A** for constant exposure: flipped `STANDARD_RULES_ANNOTATION_BODY` and `RETURN_TAG_PATTERN` from `private` to `protected` in `NormalizesRulesDocblock`. No new shared-class needed; Phase 2's rector can `use NormalizesRulesDocblock` for constant access.
- Added `@phpstan-require-extends \Rector\Rector\AbstractRector` to `DetectsInheritedTraits`. Used inline FQN in the phpdoc rather than a `use` import to avoid intelephense's P1003 unused-import warning (phpstan annotations aren't seen as usage by intelephense).
- Kept existing `anyAncestorUsesTrait` untouched. It has the same alias-blindness bug (`toString()` not `getName()`) but is consumed by `AddHasFluentRulesTraitRector` + `AddHasFluentValidationTraitRector`. Fixing it changes behavior of unrelated rectors, widening Phase 1 scope. Logged as pre-existing issue — follow-up candidate for a separate alias-fix spec. The new `anyAncestorExtends` + `currentOrAncestorUsesTrait` go through `getName()` correctly.
- Extracted pure reflection walk as `reflectedAncestryExtends(string, string): bool` so unit tests drive it with already-resolved FQNs without needing Rector's container. `anyAncestorExtends` is the thin `getName()`-resolving wrapper.
- Unit-test harness: trait `@phpstan-require-extends` is a static-analysis contract only; at runtime a stub class providing a `getName()` method satisfies the helpers. `DetectsInheritedTraitsHarness` mimics `NodeNameResolver::getName()` semantics for the node shapes under test (`FullyQualified` → FQN, `Name` literal → raw token, `Name 'UnresolvableAlias'` → null sentinel for resolver-miss scenarios). Same approach for `NormalizesRulesDocblockHarness`.
- Fixture classes live under `tests/Concerns/Fixture/`. `NestedFormRequestChild extends IntermediateBaseRequest extends FormRequest` exercises the multi-step `getParentClass()` walk without needing to mock reflection.
- Tests pass: 22 assertions green via `vendor/bin/pest tests/Concerns/`.

### Phase 2 (2026-04-20)

- **Multi-return detection must be recursive.** Initial implementation counted top-level `Return_` stmts in `$method->stmts` — missed returns nested in `if`/`else`/`try` blocks, which is exactly the canonical multi-return pattern (`if (admin) return [...]; return [...];`). Fixed by switching to `$this->traverseNodesWithCallable` with `NodeVisitor::DONT_TRAVERSE_CURRENT_AND_CHILDREN` guard for nested `FunctionLike` nodes (so closures/arrow-fns inside the method body don't pollute the count with their own returns). Caught by `skip_multi_return_method.php.inc`.
- **FluentRuleContract FQN hard-coded as string, not `::class`.** Installed version is 1.8.1; the `FluentRuleContract` interface shipped in 1.17.0 isn't resolvable via composer yet. Using a string literal in `CONTRACT_ANNOTATION_BODY` lets the rector run unchanged regardless of consumer's installed version — the FQN is emitted as text into docblocks, not resolved as a class at rector-time. Flagged as deliberate and low-risk.
- **Peer contributions from mijntp (ruy3zc8x) + hihaho (e0cp6lq3).** Eight real-world shapes surfaced via peer grep that hadn't been in my draft matrix:
  - `skip_method_call_at_value.php.inc` — `$this->passwordRules()` (Fortify trait pattern).
  - `skip_collection_pipeline_return.php.inc` — `return collect(parent::rules())->mergeRecursive(...)->all();`.
  - `skip_array_rule_list_value.php.inc` — array mixing `['exclude_if', ...]`, strings, FluentRule at value position.
  - `skip_closure_at_value.php.inc` — inline `fn()`/closure validator at value position.
  - `fluent_each_with_inner_array.php.inc` — `FluentRule::array()->each([...])` with inner-array-of-fluent-rules argument. Must narrow outer but not confuse inner.
  - `fluent_chain_with_embedded_closures.php.inc` — chain with mid-chain `->rule(Closure)`, doesn't break `MethodCall->var` walk.
  - `fluent_when_with_typed_closure_return.php.inc` — `when()` with closure returning a typed `StringRule`. Outer chain still qualifies.
  - `skip_livewire_property_rules.php.inc` + `livewire_with_rules_method.php.inc` — Livewire classes with `#[Validate]` + our traits.
- **Intermediate-base-request fixtures share a single PHP class declaration.** `tests/Concerns/Fixture/IntermediateBaseRequest.php` was created in Phase 1 for unit-test use; Phase 2 fixtures reference it via `use ...\IntermediateBaseRequest;` (direct + aliased). Avoids redeclaring the same base class across fixtures.
- Tests pass: 40 fixtures green, 41 assertions via `vendor/bin/pest tests/UpdateRulesReturnTypeDocblock/`.

### Phase 3 (2026-04-20)

- Package peer (wr018nqu) confirmed: FluentRuleContract FQN is `SanderMuller\FluentValidation\Contracts\FluentRuleContract` (nested under `Contracts/`, not flat); composer dep bumped to `^1.17` per Option A.
- Peer also recommended a README note for Phase 4: "POLISH narrows @return after CONVERT stabilizes. Single-pass rector runs may require a second invocation if the file had string rules mid-convert." No explicit ordering constraint needed — Rector's multi-pass convergence handles it.
- `composer update sandermuller/laravel-fluent-validation` pulled 1.17.0; `vendor/sandermuller/laravel-fluent-validation/src/Contracts/FluentRuleContract.php` present.
- 65 assertions green across `tests/Concerns/`, `tests/UpdateRulesReturnTypeDocblock/`, `tests/SetListTest.php`.

### Phase 5 (2026-04-20)

- **PHPStan initially surfaced 3 issues**, all fixed without suppressions:
  - `$remainder === false` dead branch on `NormalizesRulesDocblock::annotationBodyMatchesStandardUnionExactlyOrProse` — PHP 8+ `substr` never returns false. Removed.
  - Unused `CONTRACT_FQN` class constant on the new rector — folded its doc text into the surviving `CONTRACT_ANNOTATION_BODY` const and deleted.
  - `processRulesMethod()` cognitive complexity 24 (limit 20). Extracted four helpers: `hasQualifyingReturnType`, `singleLiteralArrayReturn`, `allItemsAreFluentChains`, `docblockIsNarrowable`. Each is single-responsibility and keeps the main orchestrator at complexity ~5.
  - Follow-up round surfaced a `ReflectionClass($string)` call without a class-string guard in `reflectedAncestryExtends`. Added `class_exists || interface_exists` gate; paired with inline `@var class-string` narrowing.
- **Rector self-run applied 3 cosmetic import/type fixes** on its own rules (extracting nested `Node\NullableType` / `Node\FunctionLike` to direct imports, hoisting `\ReflectionClass` to imported form, promoting `!== null` to `instanceof Expr`). Applied, re-ran: clean.
- **Final verification**: pint clean, phpstan 0 errors, rector 0 changes against self, pest 249 passed / 302 assertions / 0 failures.

### Evaluate pass (2026-04-20)

Self-review + Codex adversarial review surfaced 5 issues, all fixed in-place:

**Self-review** (2 fixes):
- `@inheritDoc` detection was case-sensitive (`str_contains`). PHPDoc spec accepts `@inheritdoc` lowercase variant too — switched to `stripos`. Added `skip_inherit_doc_lowercase.php.inc`.
- Docblock-inject path corrupted single-line docblocks: `/** @see X */` → `/** @see X * @return ...\n*/` (new tag inline with existing content). Fixed by expanding single-line to multi-line before injection. Added `all_fluent_docblock_no_return_single_line.php.inc` + `all_fluent_docblock_no_return_multiline.php.inc`.

**Codex review** (3 fixes):
- `currentOrAncestorUsesTrait` fell back to legacy `anyAncestorUsesTrait` for the ancestor leg, which still uses raw `$class->extends->toString()` — silently missed aliased parent classes that inherit a qualifying trait (`use App\BaseComponent as Base; class Foo extends Base` where `BaseComponent` uses `HasFluentValidation`). Extracted new `aliasAwareAncestorUsesTrait` that resolves via `$this->getName()` before reflection. Added `extends_aliased_base_with_trait.php.inc` + test fixture class `tests/Concerns/Fixture/IntermediateLivewireBase.php`.
- `RETURN_TAG_PATTERN` captured through `[^\r\n]*` which greedily included `*/` on single-line docblocks. `/** @return array */` extracted body as `array */`, failed `canNarrowExistingBody`, silently skipped. Fixed pattern to use negative lookahead `(?!\s*\*\/)` so the trailing terminator is left in place and the space before it is preserved. Added `all_fluent_single_line_return_tag.php.inc`.
- Wrapped `@return\n * type<...>` (type on continuation line) left the body with a leading `* ` prefix that `canNarrowExistingBody` rejected. Added post-extraction normalization regex `/^\s*\*\s*/` to strip the continuation-asterisk. Added `all_fluent_return_type_next_line.php.inc`.

Final tally: **46 fixtures** (18 emit + 28 skip). Full suite: 255 passed / 308 assertions / 0 failures. PHPStan clean.

<!-- Notes added during implementation. Do not remove this section. -->
