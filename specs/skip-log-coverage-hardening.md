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

- [ ] Re-read each of the 18 `return null` paths in
      `GroupWildcardRulesToEachRector`. Document (in `## Findings`)
      which are decision points vs early-exits. Must be a concrete
      list with line numbers so the diff is auditable
- [ ] Add `$this->logSkip($class, '<reason>')` at each decision-point
      site. Reasons must be specific (not "skipped" — "mixed
      wildcard/keyed siblings at depth %d cannot fold to each()")
- [ ] Audit `ValidationStringToFluentRuleRector::refactor()` and
      `ValidationArrayToFluentRuleRector::refactor()` rector-level
      bail paths. Record the audit in `## Findings`. Add emit sites
      only if a case surfaces where silence misleads users
- [ ] Regression fixtures under `tests/GroupWildcardRulesToEach/Fixture/`
      named `skip_<reason>.php.inc` — one per new emit site.
      Assertion pattern: verify the fixture's output equals input
      (skip is a no-op transform), and a companion test reads the
      verbose-mode skip-log path and asserts the expected reason
      string is present. Reuse the `RunSummaryTest` pattern for the
      log-read + cleanup
- [ ] Tests — each regression fixture has matching test coverage.
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

- Populate during Phase 1 implementation: per-line categorization of
  the 18 `return null` sites in `GroupWildcardRulesToEachRector`.
  Format: `- line NNN — decision-point | early-exit | unclear — reason`.
- Populate during Phase 2: audit conclusion for
  `ValidationString/ArrayToFluentRuleRector` rector-level bails.
