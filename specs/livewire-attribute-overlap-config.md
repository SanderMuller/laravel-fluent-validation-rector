# `ConvertLivewireRuleAttributeRector` Key-Overlap Behavior Config

## Overview

Current behavior: `ConvertLivewireRuleAttributeRector` bails on an entire class when any method in the class calls `$this->validate([...])` with explicit rule arrays. The guard exists to prevent generating a `rules()` method whose shape contradicts the explicit `validate([...])` call — a real bug mode.

Reality: in collectiq (5 hits), the explicit `validate()` calls live inside action methods (`save`, `nextStep`, `create`, etc.) and contain a **mix** of keys that overlap with `#[Validate]` attrs and keys that don't. Current bail conservatively loses the non-overlapping attr conversions.

New config knob lets consumers opt into partial or forced conversion.

---

## 1. Current State

### 1a. Existing guard (rector source, locator TBD)

`ConvertLivewireRuleAttributeRector` walks the class body for any `MethodCall` matching `$this->validate(<array>)`. When found, skips the whole class with:

```
class calls $this->validate([...]) with explicit args — attribute conversion skipped to avoid generating dead-code rules()
```

Rationale: if `rules()` is generated from `#[Validate]` attrs AND `validate([...])` is called in an action method with a different rule array, Livewire runtime calls the explicit `validate([...])` for action validation but falls back to the synthesized `rules()` for real-time / `wire:model.live` validation. Divergence between the two is a real source of production bugs.

### 1b. Observed case (collectiq `ArticleEditorPage.php`)

```php
#[Validate('required|string|min:6|max:150')]
public string $title = '';

#[Validate('nullable|image|max:5120')]
public ?TemporaryUploadedFile $editorImage = null;

public function save(): void
{
    $this->validate(RuleSet::compileToArrays([
        'title'   => FluentRule::string()->required()->between(6, 150),  // <-- overlaps $title attr
        'content' => FluentRule::array()->required(),                    // <-- new key, not an attr
    ]));
}
```

Consumer intent: attrs handle real-time validation on every property update; the explicit `validate()` in `save()` is a stricter pre-save check. Partial conversion would rewrite the non-overlapping attrs into `rules()` and leave the `save()` body alone.

### 1c. Five affected files (collectiq)

`ArticleEditorPage`, `AuthorOnboardingPage`, `SeriesCreatorPage`, `EpisodeEditorPage`, `SeriesSettingsPage`. All share the same "attrs + action-method `validate()`" pattern.

---

## 2. Proposed Design

### 2a. Config key `KEY_OVERLAP_BEHAVIOR` with three modes

```php
->withConfiguredRule(ConvertLivewireRuleAttributeRector::class, [
    ConvertLivewireRuleAttributeRector::KEY_OVERLAP_BEHAVIOR => 'partial',
])
```

| Mode      | Behavior                                                                                          |
|-----------|---------------------------------------------------------------------------------------------------|
| `'bail'`  | Current behavior — skip entire class if any `$this->validate([...])` is present. **Default.**    |
| `'partial'` | Convert attrs whose key does NOT appear in any `$this->validate([...])` array. Leave overlapping attrs + the original `validate()` call untouched. |
| `'force'` | Convert all attrs → `rules()` entries. Emit a `// NOTE: also set via $this->validate() in method X — duplicate rule` comment above each overlapping key in the generated `rules()`. Consumer resolves manually. |

### 2b. Overlap analysis

**Codex-review constraint (2026-04-24).** Key extraction must be narrowly restricted. A naïve "walk any `validate(<expr>)` and find an `Array_` somewhere inside" rule would accept wrappers like `array_merge($dynamic, ['title' => ...])` or builder calls carrying only a partial key set — `partial` mode would misclassify an overlapping property as non-overlapping and reintroduce the divergence bug the original `bail` guard prevents. Restricted extraction:

Walk the class AST:

1. Collect attr-keyed properties: `$attrKeys = [propertyName => attrNode, ...]` for every `#[Validate]` / `#[Rule]` on a property.
2. Walk class methods for `$this->validate($arg)`. Accept `$arg` ONLY when it is one of these **exactly-specified** shapes:
   - A direct `Array_` literal: `$this->validate(['title' => '...'])`.
   - A `StaticCall` to `RuleSet::compileToArrays($literalArray)` where the arg is a direct `Array_` literal (collectiq's established idiom).
   
   Any other shape — `array_merge(...)`, variable arg (`$this->validate($rules)`), property fetch, method call, ternary — falls back to `bail` semantics for the whole class regardless of mode. Skip-log with reason `"$this->validate() called with extraction-unsafe arg shape: <shape> — classwide bail"`.
3. From accepted arrays, extract top-level string keys into `$explicitKeys = Set<string>`.
4. For each `$attrKeys` entry:
   - If `partial`: convert iff property name NOT in `$explicitKeys`. Otherwise leave attr in place.
   - If `force`: always convert, attach comment noting overlap when in `$explicitKeys`. Still respects the extraction-unsafe fallback — if any `validate()` arg is unsafe, revert to `bail`.
   - If `bail` (default): proceed to existing whole-class skip if `$explicitKeys` is non-empty OR any `validate()` call has extraction-unsafe args.

### 2c. Edge cases

1. **Dynamic rule keys** (`$this->validate([$key => 'required'])`): treat as "unknown keys, conservatively match any property" → falls back to `bail` semantics even in partial mode. Log-skip with reason.
2. **`$this->validate()` with no arg / with default-closure rules**: no rule array to extract keys from. Partial mode: no overlap, all attrs convert. Force mode: same. Bail: skip (preserved).
3. **Multiple `$this->validate([...])` calls in different methods**: union all key sets.
4. **Wildcard keys in attrs vs concrete in validate()** (or vice versa): conservative — treat as overlap. Don't try to resolve wildcard subsumption here.

---

## 3. Safety Analysis

### 3a. `'partial'` is the safer-than-`force` default path for consumers who want progress

Partial only converts attrs whose property name is unambiguously absent from every explicit `validate()`. No divergence risk — the converted attrs continue to drive real-time validation; the `validate()` call is untouched and continues to drive action-time validation. No shared key means no source of truth ambiguity.

Single false-negative risk: wildcard keys. A property `public array $items = []` attr `#[Validate(['items.*' => 'required'])]` + `validate(['items' => [...]])` would be treated as overlap (substring match). This under-converts rather than over-converts — safe failure mode.

### 3b. `'force'` is documented-unsafe

Comment emission warns consumers. Behavior: `rules()` may now contradict the explicit `validate()`. This is exactly the mode the existing `bail` guard was added to prevent. Ship anyway because some consumers (after review) want the `rules()` shape populated and will resolve conflicts manually. Default is not `force`.

### 3c. Idempotency

Re-running the rector on a `partial`-converted file should be a no-op for overlapping attrs (they remain as attrs). The converted non-overlapping attrs would flip back to… no, the attrs are removed on conversion. The `rules()` method exists. The `validate()` call still references the overlap keys. Second pass: attrs are gone, no `#[Validate]` to match, rector no-ops. Idempotent.

---

## 4. Fixtures

Under `tests/ConvertLivewireRuleAttribute/Fixture/` (extending existing):

- `partial_converts_non_overlapping_attrs.php.inc` — collectiq shape; `title` stays attr, `content` (unrelated attr) converts.
- `partial_preserves_all_when_all_overlap.php.inc` — every attr overlaps `validate()` → leave all attrs, skip.
- `partial_ignores_dynamic_validate_key.php.inc` — `$this->validate([$dynamic => ...])` → bail-equivalent, skip with logged reason.
- `partial_bails_on_array_merge_arg.php.inc` — `$this->validate(array_merge($dynamic, ['title' => ...]))` → classwide bail (extraction-unsafe wrapper).
- `partial_bails_on_variable_arg.php.inc` — `$this->validate($rules)` where `$rules` is assembled elsewhere → classwide bail.
- `partial_accepts_ruleset_compile_to_arrays.php.inc` — `$this->validate(RuleSet::compileToArrays(['title' => ...]))` → keys extracted, partial applies.
- `force_converts_all_with_duplicate_comment.php.inc` — `force` mode, overlapping attr generates `// NOTE:` comment.
- `bail_preserves_current_behavior.php.inc` — default config, existing skip path fires.
- `partial_multiple_validate_calls_union_keys.php.inc` — two action methods each with `validate([...])`, union of keys determines overlap.
- `partial_with_no_explicit_validate.php.inc` — `validate()` is called but with no array arg → all attrs convert cleanly.

---

## 5. Open Questions

1. **Should `'partial'` be the default instead of `'bail'`?** `bail` is stricter. `partial` is safer-than-`force` but still changes behavior for consumers on 0.12.x upgrading. Lean keep `bail` as default, document upgrade path.
2. **Key-extraction from `RuleSet::compileToArrays([...])` wrapping** (collectiq's shape). The rule array inside is still a plain `Array_` node, just wrapped in a static call. Walk unconditionally through the wrapping call — or gate on the wrapping class? Lean walk unconditionally; any `$this->validate(<expr>)` where `<expr>` eventually contains a top-level `Array_` should contribute its keys.
3. **Comment emission in `'force'` mode** — single-line `// NOTE: ...` vs PHPDoc above the key? Single-line is the idiomatic Laravel style for such hints.

---

## 6. Out of Scope

- Conflict resolution inside `force` mode (keeping attr rules vs `validate()` rules when both exist). Rector emits the attr-derived rule into `rules()`; consumer decides.
- Cross-class `validate()` calls (e.g., a trait calls `$this->validate()`). Trait-level analysis deferred.
- Livewire v2 vs v3 attr differences. Rector already handles both `#[Rule]` and `#[Validate]` uniformly; no extra surface.

---

## Implementation status (2026-04-24)

**Shipped:** `bail` (default, preserves prior behavior) + `partial`. Force mode deferred — requires comment-emission logic orthogonal to the overlap-detection core and no collectiq-side demand vs. `partial`.

- [x] `KEY_OVERLAP_BEHAVIOR` config + `OVERLAP_BEHAVIOR_BAIL` / `OVERLAP_BEHAVIOR_PARTIAL` constants on `ConvertLivewireRuleAttributeRector`.
- [x] `ExtractsExplicitValidateKeys` trait — returns `list<string>|'unsafe'`. Accepts direct `Array_` and `RuleSet::compileToArrays($literalArray)`. Any other wrapper → `'unsafe'`.
- [x] `PredictsLivewireAttributeEmitKeys` trait — emit keys for `#[Validate]` / `#[Rule]` attrs (keyed-first-arg → internal keys; single-chain → property name).
- [x] `shouldProcessClass` dispatch: `'unsafe'` always bails; `'partial'` populates `$partialOverlapSkipKeys` and `propertyHasPartialOverlap` skips individual properties.
- [x] 9 fixtures under `tests/ConvertLivewireRuleAttribute/FixturePartialOverlap/`.

### Findings

- **Extraction-unsafe wrappers (Codex HIGH).** Naïve "find any `Array_` inside `validate()`" accepts `array_merge($dynamic, ['title' => ...])` and misclassifies an overlapping property as non-overlapping. Fixed by narrowing the accepted shape list to direct `Array_` + `RuleSet::compileToArrays($literalArray)`; anything else returns `'unsafe'` → classwide bail.
- **Named `rules:` arg lookup (Codex HIGH).** `validate(messages: [...], rules: [...])` with `rules` passed by name would be read from positional slot 0 (the `messages` arg), seeding the skip set with message keys like `'title.required'`. Fixed: named-arg lookup precedes positional index in `extractRulesArgFromValidateCall`.
- **Predicted emit keys, not property name (Codex HIGH).** `#[Validate(['todos' => ..., 'todos.*' => ...])]` on `$items` has effective keys `todos` / `todos.*`, not `items`. Property-name-only overlap check would convert + strip the overlapping `todos` rule. Fixed by `PredictsLivewireAttributeEmitKeys`: keyed-first-arg attrs emit their internal keys; single-chain attrs emit the property name.
- **`preserve_realtime_validation` marker.** Partial-skipped properties keep their `#[Validate]` attrs intact, so the existing marker-emission path is untouched.
- **Idempotency.** Second pass on a partial-converted file: non-overlapping attrs are already gone; overlapping attrs remain and re-match the skip path. No-op.
- **Complexity budget.** Extracted both new responsibilities into `Concerns/*` traits to keep `ConvertLivewireRuleAttributeRector` under PHPStan's cognitive-complexity threshold.

### Tests

- 478 tests / 820 assertions / 0 failures. 60 `ConvertLivewireRuleAttribute` fixtures including 9 new partial-overlap cases.
- Pint clean. PHPStan 0 errors. `vendor/bin/rector process` 0 self-changes.
