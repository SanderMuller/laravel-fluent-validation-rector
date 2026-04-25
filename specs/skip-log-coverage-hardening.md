# Skip-Log Coverage Hardening

## Overview

`LogsSkipReasons` infrastructure is sound (PPID-coordinated truncation
under `withParallel()`, per-process dedupe, verbose-mode STDERR
mirror). One rector imports the trait but never calls `logSkip()`
across its 18 `return null` paths — users running `GroupWildcardRulesToEachRector`
on a codebase with shapes the rector can't fold see "no change" with
no explanation. Close that gap, then audit rector-level bail points
(pre-concern) in the two converter rectors to decide which deserve a
skip entry.

---

## 1. Current State

### 1.1 Coverage audit (as of 0.7.0)

Audited 2026-04-20 against the laravel-fluent-validation peer's
request. 4 rectors + 3 concerns have solid coverage (35 `logSkip()`
call sites); one rector has a real gap, two have apparent gaps that
are actually handled via trait composition.

| Rector / concern                        | `use LogsSkipReasons` | `logSkip()` calls | Notes |
|-----------------------------------------|-----------------------|-------------------|-------|
| `ConvertLivewireRuleAttributeRector`    | ✅ direct             | 6                 | —     |
| `UpdateRulesReturnTypeDocblockRector`   | ✅ direct             | 9                 | —     |
| `AddHasFluentRulesTraitRector`          | ✅ direct             | 5                 | —     |
| `AddHasFluentValidationTraitRector`     | ✅ direct             | 6                 | —     |
| `SimplifyRuleWrappersRector`            | ✅ direct             | 5                 | —     |
| `ExpandsKeyedAttributeArrays` concern   | ✅ direct             | 3                 | —     |
| `ReportsLivewireAttributeArgs` concern  | ✅ direct             | 3                 | —     |
| `ConvertsValidationRuleStrings` concern | ✅ direct             | 2                 | See below |
| `ValidationStringToFluentRuleRector`    | via strings concern   | transitive        | Concern covers inner paths; rector-level bails are few |
| `ValidationArrayToFluentRuleRector`     | via strings concern   | transitive        | Arrays concern composes strings concern via `use ConvertsValidationRuleStrings;` → same `refactorFormRequest()` code path → inherits the 2 log calls |
| `ConvertLivewireRuleAttributeRector`    | also uses arrays concern | transitive     | Same composition chain |
| **`GroupWildcardRulesToEachRector`**    | ⚠️ **imported, 0 calls** | **0**          | **18 `return null` sites, none logged** |

### 1.2 Why the converter-rector "gap" isn't a gap

`ConvertsValidationRuleArrays` uses `use ConvertsValidationRuleStrings;`
(arrays concern has `use` of the strings trait at the top). The strings
concern defines `refactorFormRequest(ClassLike)` + the
`abstract processValidationRules(Array_)` method; each converter rector
implements `processValidationRules()` for its own input shape. All three
rectors that consume the chain (`ValidationStringToFluentRuleRector`,
`ValidationArrayToFluentRuleRector`, `ConvertLivewireRuleAttributeRector`)
inherit the 2 skip-log calls the strings concern emits. An earlier audit
that reported "no trait import, no logging" missed the trait-composition
chain.

### 1.3 Why the GroupWildcard gap is a real gap

`GroupWildcardRulesToEachRector` imports `LogsSkipReasons` and
never calls it. 18 `return null` paths, all silent. A user running
`GROUP` on a codebase that has unsupported wildcard-grouping shapes
(mixed wildcard/keyed siblings, non-FluentRule parent chains, keys
that resolve to `$this->`-method results, etc.) sees nothing in the
skip log.

---

## 2. Proposed Changes

Three fixes, ordered by signal-to-effort:

### 2.1 Primary: `GroupWildcardRulesToEachRector`

Walk the 18 `return null` sites. Per the `LogsSkipReasons` trait's
docblock guidance (line 26-29): emit at **decision points**, not at
every early-exit. Categorize each site:

- **Decision point** — bails because the input shape can't be folded
  (mixed wildcard + keyed siblings at same depth, parent rule is a
  string vs a FluentRule chain, `$this->` method reference as key,
  key has non-literal expression, dot-key points into a value the
  rector can't resolve). Emit `logSkip($class, '<specific reason>')`.
- **Early exit** — bails because the AST node isn't the shape the
  rector cares about (not an `Array_`, no keyed items, node is a
  match arm, etc.). No log.

Expected outcome: ~5-8 new emit sites, ~10 sites stay silent as
non-decision early-exits.

### 2.2 Secondary: rector-level bail audits

`ValidationStringToFluentRuleRector` and `ValidationArrayToFluentRuleRector`
inherit the strings concern's 2 log calls, but their own `refactor()`
methods have pre-concern bail points worth auditing:

- File path outside configured scope → handled by Rector framework,
  don't log (already filtered).
- Node is neither `ClassLike`, `MethodCall`, nor `StaticCall` →
  framework-level filter, don't log.
- Specific `MethodCall`/`StaticCall` identifier mismatches
  (`$this->validate(...)` vs `$this->validateSometimes(...)`) →
  early-exit, don't log.

Provisional audit conclusion: rector-level bails in the converter
rectors are all genuine early-exits, not decision points. Skip
unless the audit surfaces a case where silence misleads users.
Document findings here in `## Findings` so a later reviewer doesn't
redo the audit.

### 2.3 Regression guard

Add fixture-based regression coverage for the `GroupWildcard` log
entries. Use the existing `FLUENT_VALIDATION_RECTOR_VERBOSE=1`
capture pattern (`RunSummaryTest` has a working example) to assert
the specific reason strings. Pin each new emit site.

---

## Implementation

- [x] Re-read each of the 18 `return null` paths in
      `GroupWildcardRulesToEachRector`. Document (in `## Findings`)
      which are decision points vs early-exits. Must be a concrete
      list with line numbers so the diff is auditable
- [x] Add `$this->logSkip($class, '<reason>')` at each decision-point
      site. Reasons must be specific (not "skipped" — "mixed
      wildcard/keyed siblings at depth %d cannot fold to each()")
- [x] Audit `ValidationStringToFluentRuleRector::refactor()` and
      `ValidationArrayToFluentRuleRector::refactor()` rector-level
      bail paths. Record the audit in `## Findings`. Add emit sites
      only if a case surfaces where silence misleads users
- [x] Regression fixtures under `tests/GroupWildcardRulesToEach/Fixture/`
      named `skip_<reason>.php.inc` — one per new emit site.
      Assertion pattern: verify the fixture's output equals input
      (skip is a no-op transform), and a companion test reads the
      verbose-mode skip-log path and asserts the expected reason
      string is present. Reuse the `RunSummaryTest` pattern for the
      log-read + cleanup
- [x] Tests — each regression fixture has matching test coverage.
      Verify via `vendor/bin/pest tests/GroupWildcardRulesToEach/`

---

## Open Questions

1. **Should "mixed wildcard/keyed siblings" log or silently pass
   through?** The rector's contract is "best-effort folding"; silent
   pass-through is consistent. But the ambiguity is a code-smell
   worth surfacing to the user. Recommend log with
   `mixed wildcard/keyed siblings at depth %d — cannot fold to each()`.

2. **Per-item vs per-class logging granularity.** A `rules()` method
   with 10 unsupported wildcard keys could produce 10 log lines or
   1. The existing dedup key (`rule|file|className|reason`) already
   collapses repeated same-reason entries to one per class. So
   per-item logging is effectively per-class after dedup, which is
   the right default. No special handling needed.

3. **Do any `GroupWildcard` bails warrant a diagnostic severity hint
   (warn vs info)?** The current `[fluent-validation:skip]` channel
   is flat — no severity. Not in scope for this spec; noting in case
   a consumer asks.

---

## Findings

<!-- Notes added during implementation. Do not remove this section. -->

### Bail-site audit — `GroupWildcardRulesToEachRector` (2026-04-25)

File grew since the spec's 0.7.0 audit; actual bail count is now ~28
across `return null` and `return false`. Categorization:

**Early-exits** (no log):
- L128 `refactor()` — node not `Namespace_` (framework filter)
- L145 `refactor()` — `! $hasChanged` (common no-op path)
- L172 `refactorClass()` — node not `Return_(Array_)` (AST traversal)
- L181 `refactorClass()` — `groupRulesArray` returned false (delegation)
- L241 `groupRulesArray()` — no string-keyed entries (common)
- L247 `groupRulesArray()` — no groupable patterns found (common)
- L322/329/335 `resolveClassConstToString()` — AST-shape filters
- L349/355 `resolveConcatToString()` — AST-shape filters
- L367 `classConstToSyntheticKey()` — AST-shape filter
- L428/433 `parseConcatKey()` — incomplete parse / no `.` suffix (early-exit; key isn't a wildcard pattern)
- L696 `applyGroups()` — nothing to remove/update/insert
- L767 `processGroup()` — `collectGroupChainItems` returned null (delegation)
- L851 `collectGroupChainItems()` — no eachItems/childrenItems/eachScalar (common no-group)
- L946 `hasInvalidWildcard()` — predicate return
- L1002/1007/1013/1020 `allGroupEntriesAreFluentRule()` — predicate returns (decision is at the L758 caller)
- L1042 `isRedundantWildcardParent()` — predicate return

**Decision points** (emit `logSkip`):
- L758 `processGroup()` — group entries mix FluentRule and raw rules (parent: `<key>`). User wrote `'items' => [...]` raw alongside `'items.*' => FluentRule::...` — folding would lose the raw form. Reason: `"wildcard group has non-FluentRule entries — cannot fold to each() (parent: <key>)"`.
- L778 `processGroup()` — parent rule is not a FluentRule chain. User wrote `'items' => 'array|min:1'` raw string + `items.*` children. Reason: `"parent rule for '<key>' is not a FluentRule chain — cannot append ->each()"`.
- L782 `processGroup()` — parent factory doesn't allow `each()`/`children()`. User wrote `FluentRule::string()` parent + wildcard children. Reason: `"parent factory <factory>() doesn't support each()/children() — only array() and field() do"`.
- L858 `collectGroupChainItems()` — wildcard parent has non-redundant rules that would be lost. Reason: `"wildcard parent '<key>.*' has type-specific rules that would be lost in grouping"`.
- L499/516 `findTopLevelGroups()` — double-wildcard `**` (or non-first `*`) detected. Reason: `"double wildcard or non-first '*' in key suffix — cannot fold to nested each()"`.
- `indexConcatKey()` — when `parseConcatKey` returns null (paths L404/409/414/423 mean "concat too complex to parse"). Reason: `"concat key too complex to parse for grouping (expected '<ClassConst> . \".suffix\"' shape)"`.

**6 new emit sites** total. The remaining ~22 bails are framework
filters, AST shape mismatches, or internal predicate returns where
emitting would be noise.

### Plumbing decision

`processGroup`/`collectGroupChainItems`/`indexConcatKey`/`findTopLevelGroups`
don't currently take a `Class_` arg. Threading it through 4 helpers is
invasive — instead, store `private ?Class_ $currentClass` as instance
state set in `refactorClass()`, mirroring the existing `$this->localConstants`
pattern. Reset to `null` on entry to keep the rector reentrant across
classes within a namespace.

### Bail audit — `ValidationStringToFluentRuleRector` / `ValidationArrayToFluentRuleRector`

Both rectors dispatch on three node types via `getNodeTypes()`:
`[ClassLike::class, MethodCall::class, StaticCall::class]`. Their
`refactor()` methods have the same shape — a 3-arm `if` matching each
type then a single trailing `return null;` for the no-match case.

The single `return null` (L121 / L123) fires when the visited node is
a MethodCall/StaticCall whose `name` isn't `validate` or `make`. That
matches every other method call in the codebase: `$user->save()`,
`Carbon::now()`, `$builder->where()`, etc. Logging here would emit
thousands of noise lines per file with zero actionable signal.

Concern-level logging (`ConvertsValidationRuleStrings`) already fires
at the actual decision points: rule string can't be tokenized, rule
shape isn't recognized, etc. Those reach the user as one log line per
unhandled rule.

**Conclusion**: no new emit sites in either converter rector. Spec's
provisional audit confirmed.

### Implementation notes

- Added `private ?Class_ $currentClass` instance prop + `logGroupSkip()`
  helper rather than threading `Class_` through 4 helper signatures.
  Mirrors the existing `$this->localConstants` / `$this->needsFluentRuleImport`
  state pattern on the rector.
- `processGroup()` L778 ("parent rule isn't a FluentRule chain") is
  defensive — `allGroupEntriesAreFluentRule()` at L758 already catches
  the case when the parent entry exists in the entries map but isn't
  a FluentRule chain. Kept the bail itself (defensive) but dropped the
  `logGroupSkip()` call — an unreachable emit is dead noise in the
  dedup cache (Codex review catch).
- 4 of 6 emit sites had pre-existing `skip_*.php.inc` fixtures
  (`skip_double_wildcard`, `skip_non_redundant_wildcard`,
  `skip_raw_array_parent`, `skip_wrong_parent_type`). 1 new fixture
  added (`skip_complex_concat_key`).
- Log-content regression test (`GroupWildcardSkipLogTest`) had to copy
  fixtures to unique temp paths before running rector — Rector's
  per-process file cache (path+mtime keyed) caused cache hits when
  the canonical fixture path had already been processed by
  `GroupWildcardRulesToEachRectorTest` earlier in the same suite run.
  Without the temp-copy, the rector silently no-ops on the second
  visit, no logSkip fires, and the assertion fails. Single test class
  invocation passes; full-suite invocation needed the cache bypass.
- PHPStan baseline complexity bumped 174 → 177 (one helper method +
  one instance prop addition). Not padding — reflects real (small)
  growth from the emit-site additions.
