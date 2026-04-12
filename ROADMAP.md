# Roadmap

Tracks peer feedback, release status, and planned work.

## Released

### 0.1.0 — 2026-04-12
Initial release. Core rectors for string-form rules, array-form rules, trait insertion, grouping, and simplification. Set lists in `FluentValidationSetList`.

### 0.1.1 — 2026-04-12
Three fixes from real-world runs:
- **Synthesized parent no longer nullable** — `GroupWildcardRulesToEachRector` was emitting `FluentRule::array()->nullable()->children([...])` for dot-notation-only rule groups. Nested `->required()` children silently never fired. Fixed to emit a bare `FluentRule::array()`.
- **`children()` / `each()` always multi-line** — synthesized nested arrays used to collapse to one line when children contained further arrays (`->in([...])`). Fixed via `AttributeKey::NEWLINED_ARRAY_PRINT`.
- **Trait insertion emits proper `use` import + blank line** — `AddHasFluentRulesTraitRector` and `AddHasFluentValidationTraitRector` used to emit FQN-inline trait use. Now queue a top-of-file `use` via `UseNodesToAddCollector` and insert a `Nop` for a blank line before the next class member (gated on existing-gap check).

Plus CI fix (`fileinfo` extension for Windows), CHANGELOG seed, stale phpstan-baseline cleanup (FluentRules string literal lookup).

### 0.2.0 — broken tag (immutable), superseded by 0.3.0

The `0.2.0` Packagist tag points at `ebd1204` (a pre-feature commit), so `^0.2.0` ships none of the features its CHANGELOG describes. Since tags are immutable, 0.3.0 ships the full 0.2.0 scope plus the 0.2.x polish items that landed during re-verification.

## Released — 0.3.1

Finishes the pre-Pint output polish from 0.3.0 by sweeping the converter-pathway `FluentRule::` FQN. After this release, Pint is a no-op on rector's output across all rule types.

- **Converter `FluentRule::` uses short name** — `ConvertsValidationRules::buildFluentRuleFactory()` now emits `new Name('FluentRule')` and auto-inserts the import via `ManagesNamespaceImports`.
- Both string/array converters registered `Namespace_` as their single node type.
- 5 new fixtures covering import-insertion, dedup, and sorted-position.

## Released — 0.3.0

Theme: **broader coverage of existing rule input forms + polish on trait-insertion output + ancestor-aware trait detection.**

| # | Item | Source | Status |
|---|---|---|---|
| 1 | `['max', 65535]` tuple → `->max(65535)` | `ValidationArrayToFluentRuleRector` | **Done** |
| 2 | Flat `'items.*' => [...]` → parent `->each(<scalar>)` | `GroupWildcardRulesToEachRector` | **Done** |
| 4 | `Rule::unique()->withoutTrashed()` → `->unique(Model, null, fn)` | `ValidationArrayToFluentRuleRector::convertChainedDatabaseRule` | **Done** (pre-existing; regression fixture added) |
| — | Trait `use` import inserted alphabetically | Trait rectors | **Done** |
| — | Synthesized `FluentRule::` uses short name | `GroupWildcardRulesToEachRector` | **Done** |
| — | Skip trait insertion when ancestor already uses it | Trait rectors via `DetectsInheritedTraits` | **Done** |

Docs bundle:
- README "Known limitations" section (non-`rules()` conventions, `Collection::put()` in Actions, rules from traits/helpers)
- Long-chain formatting note (point at Pint's `method_chaining_indentation`)
- Trait insertion ordering note ("after the last existing trait")

## Peer feedback log

### collectiq — `8qylh9ys` (Pieter)
Ran 0.1.0 on 8 FormRequests. Reported bugs 1 & 2 above — both fixed in 0.1.1 and verified end-to-end with a gold-standard HTTP-layer test (`validation_rejects_missing_fields`).

Remaining nits (0.2.0 backlog):
- **Nit A — synthesized parent emits `\SanderMuller\FluentValidation\FluentRule::array()` as FQN** even when `FluentRule` is already imported. Rolling into the `UseNodesToAddCollector` touch-up.
- **Nit B — trait `use` import prepends to top of use block** instead of alphabetically. Pint's `ordered_imports` fixes it. Low priority.

### hihaho — `y0vob4dg`
Ran 0.1.0 on 108 files vs. manual PR #9345. Coverage was 2.8× human output, all 2,098 tests pass, idempotent. Gap-ranked feedback by frequency:

- **Gap 1 — `['max', N]` tuple unwrapping** → 0.2.0 in progress
- **Gap 2 — flat wildcard folding** → 0.2.0 pending
- **Gap 3 — ternary rule → `->when()`** → deferred to 0.3.0 / exploratory
- **Gap 4 — `Rule::unique()->withoutTrashed()` unwrap** → 0.2.0 pending
- **Gap 5 — long chains on one line** → README docs for 0.1.x

Files rector can't reach (documenting, not fixing): custom validator bases with `rulesWithoutPrefix()`, rules built via `Collection::put()`, rules from traits/helpers.

Style nit: FQN-inline trait (gap 6) — **fixed in 0.1.1.**

Bad report owned: "phantom FQN-not-found fatal" — peer couldn't reproduce on retry. Likely a parallel-worker + stale-container-cache interaction. No action.

Fixtures pasted for 0.2.0: `UpdateVideoRequest` (gap 1 + gap 2 combined), `CopyInteractionsRequest` (parallel flat-wildcard pairs), `DuplicateVideosRequest` (const-concat wildcard key edge). Four more pending on request: `RequestSubscriptionModal`, `GeneralSettings`, `CreateApiToken`, full `CopyInteractionsRequest`.

### mijntp — `kb7vilgo`
Ran 0.1.0 on 23 files. 900 tests pass, PHPStan clean.

- **Issue 1 — missing blank line after trait in Livewire** → **fixed in 0.1.1**, re-verified on all 6 Livewire files.
- **Issue 2 — `#[Rule(...)]` Livewire attribute syntax not converted** → feature gap, distinct entry point, deferred to 0.3.0.
- **Issue 3 — trait ordering alphabetical vs after-last-trait** → Pint's `ordered_traits` absorbs it. Cosmetic.

Re-verification of 0.1.1 surfaced three nits (all cleanly absorbed by Pint, none blocking):
- **Finding A — import ordering regression** — 0.1.0 inserted `use` alphabetically adjacent; 0.1.1 prepends to top of use block. Same root cause as collectiq Nit B. **Folded into 0.3.0.**
- **Finding B — redundant trait on inheriting subclasses** — parent class carrying the trait caused subclasses to still get it re-added. **Folded into 0.3.0** via `DetectsInheritedTraits` reflection walk.
- **Finding B — redundant trait on inheriting subclasses** — 6 Livewire subclasses get `use HasFluentValidation;` re-added even though their parent already has it. Pre-existing from 0.1.0. Would need reflection / parent-chain walk. 0.2.x nice-to-have.
- **Finding C — trait member ordering** — `Pint ordered_traits` fixes it. Cosmetic.

## Deferred

- **0.3.0 — ternary rules → `->when()`** (hihaho gap 3)
- **0.3.0 — `#[Rule(...)]` Livewire attribute conversion** (mijntp issue 2)
- **0.2.x — redundant trait on subclasses skip via reflection** (mijntp finding B)
- **0.2.x — configurable trait insertion ordering** (mijntp issue 3)

## Non-issues (won't fix)

- **Trait member ordering after insertion** — Pint's `ordered_traits` handles it; rector intentionally inserts after the last existing trait.
- **Long fluent chains on one line** — Pint / php-cs-fixer territory; rector won't break chains itself.
- **Rector set re-ordering in consumer `rector.php`** — not caused by this package.
