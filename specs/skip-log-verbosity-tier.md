# Skip-Log: Verbosity Tiers

## Overview

3-level verbosity tier for the `LogsSkipReasons` trait. Current binary (`FLUENT_VALIDATION_RECTOR_VERBOSE` off/on) gives default-mode silence and verbose-mode-everything. Hihaho's 0.12.0 dogfood: 336 entries, ~249 non-actionable (Livewire-detect, trait-already-present, non-FormRequest classes). New `actionable` tier surfaces only the ~87 entries a consumer can realistically act on.

**Dedup half dropped from this spec.** Original draft bundled intra-class dedup, but hihaho peer (yw6d2sll) investigated the observed "4 entries per class" claim against a known 3-call class (`CreateNewUser` has 3× `->rule(new DoesNotContainUrlRule())` calls at lines 155/161/174). Log showed exactly 1 entry per `(class, rule-class)` pair — current dedup works. The original 4-count was inter-class (4 distinct classes using `DomainPatternRule`), not a bug. Dedup work retired.

Source: hihaho 0.12.0 dogfood.

---

## 1. Current State

### 1a. Two-level env gate

`Diagnostics::VERBOSE_ENV = 'FLUENT_VALIDATION_RECTOR_VERBOSE'`. `Diagnostics::isVerbose()` returns bool. `LogsSkipReasons::logSkip(..., verboseOnly: true)` gates individual log lines.

Current distribution of entries (hihaho dogfood):

| Tier                                             | Hits  | Actionable? |
|--------------------------------------------------|------:|:-----------:|
| Default-mode always-on                           | ~tens | Usually     |
| `verboseOnly: true` only-when-verbose            | ~hundreds | Often no |

---

## 2. Proposed Design

### 2a. Verbosity tier: default / actionable / everything

Replace boolean with three-value env:

- `FLUENT_VALIDATION_RECTOR_VERBOSE` unset — default mode, only always-actionable entries.
- `FLUENT_VALIDATION_RECTOR_VERBOSE=actionable` — plus verbose-tagged-actionable entries.
- `FLUENT_VALIDATION_RECTOR_VERBOSE=1` (legacy) or `=all` — current "everything" behavior.

`LogsSkipReasons::logSkip` signature expands:

```php
private function logSkip(
    Class_ $class,
    string $reason,
    bool $verboseOnly = false,
    bool $actionable = true,  // new: surfaces in actionable-tier even if verboseOnly
): void
```

Classification guidance (consumer-side taxonomy, rector-side labels):

| Reason class                                                  | Actionable? |
|---------------------------------------------------------------|:-----------:|
| "rule payload not statically resolvable (StaticCall Password::default)" | Yes |
| "rule payload not statically resolvable (New_ App\CustomRule)"          | Yes |
| "min() not on FieldRule — type-dependent (hint: string/numeric/array/file)" | Yes |
| "detected as Livewire (uses HasFluentValidation instead)"              | **No**  |
| "already has HasFluentRules trait"                                    | **No**  |
| "abstract class with rules() (subclasses may merge)"                  | Yes |
| "class does not extend FormRequest / use trait"                       | **No**  |

Migration: every existing `verboseOnly: true` call site gets an explicit `actionable: true|false` label based on whether the user can act on it. Default `actionable: true` matches current surface.

### 2b. Env parsing

`Diagnostics::verbosityTier()` returns enum or string:

- `'off'` — no value or empty
- `'actionable'` — exactly `'actionable'`
- `'all'` — `'1'`, `'true'`, `'all'`, or any other truthy

Backward-compat: existing `VERBOSE=1` consumers keep seeing everything (no silent regression).

**Migration**: retrofit existing `verboseOnly: true` call sites with an explicit `actionable: true|false` label incrementally — each rector gets the audit pass when it's next touched for other work. Default `actionable: true` preserves the current surface for unaudited sites. Shipping Phase 1 without the full audit is safe; tier `actionable` will just include more entries than strictly necessary until retrofits land.

---

## 3. Safety Analysis

### 3a. Backward compat for `VERBOSE=1`

Existing CI logs and scripts grepping for specific messages must keep working. `VERBOSE=1` stays synonymous with `all` — no change.

### 3b. Actionable classification debate

Rector authors pick the actionable label; different consumers may disagree. Livewire-detection is non-actionable for a FormRequest-heavy project but arguably actionable for a mixed project where someone wants to know "which of these Livewire classes should I add the trait to manually?" Compromise: `actionable: true` default; verboseOnly entries get `actionable: false` explicitly when the rector author is confident it's non-actionable (trait-already-present, Livewire-not-FormRequest). Otherwise left `actionable: true`.

---

## 4. Fixtures

Not a fixture-driven change primarily — instrumentation more than rule logic.

- `DiagnosticsTest`: parse `VERBOSE=off/actionable/1/all/true/empty`, returns tier.
- `LogsSkipReasonsTest` (new): `logSkip(..., actionable: true)` surfaces in all three tiers; `actionable: false` surfaces only in `all`; default-mode shows only non-`verboseOnly` entries regardless of `actionable` flag.

---

## 5. Open Questions

1. **Tier name bikeshed**: `actionable` vs `signal` vs `triage`? `actionable` is clearest. Confirmed by hihaho review.
2. **Should the 3-level tier be documented as the preferred default going forward**, with a README migration note telling consumers to switch from `VERBOSE=1` to `VERBOSE=actionable`? Lean yes, confirmed by hihaho review. Existing `VERBOSE=1` grandfathered to `=all` alias.
3. **Per-rector actionable-label table**: maintain in each rector class or centralize in a skill doc? Centralizing de-risks drift but requires a cross-file update on every new rector. Lean per-rector comment + an audit rule in the `code-review` skill. Confirmed by hihaho review.

---

## 6. Out of Scope

- Log rotation / size cap. Not the rector's job.
- JSON-structured log output for CI consumption. Can be added later.
- Intra-class dedup changes. Current `(rule, file, class, reason)` tuple already coalesces correctly (hihaho peer review 2026-04-24 confirmed against `CreateNewUser` with 3× identical `->rule()` calls — 1 log entry produced).
- Retroactive relabeling of existing `verboseOnly: true` sites. Will happen incrementally as each rector is touched.

---

## Implementation status (2026-04-24)

**Shipped.** 3-tier (`off` / `actionable` / `all`) with legacy `=1` back-compat. All 9 `verboseOnly: true` sites audited + labeled in this pass (spec's "incremental retrofit" optimization not needed — 9 sites is small enough to do at once).

- [x] `Diagnostics::TIER_OFF|TIER_ACTIONABLE|TIER_ALL` + `verbosityTier()` parser (case-insensitive, legacy `=1`/`=true`/`=all` → `all`).
- [x] `Diagnostics::isVerbose()` kept as alias for `TIER_ALL` (legacy-consumer behavior preserved).
- [x] `Diagnostics::skipLogPath()` — any opt-in tier (actionable / all) writes to cwd; only `off` hides in tmp.
- [x] `LogsSkipReasons::logSkip` / `logSkipByName` / `writeSkipEntry` + internal helper `tierAllowsVerboseOnly(bool $actionable)` — `off` never, `actionable` iff label=true, `all` always.
- [x] `RunSummary` — shutdown cleanup + hint-line gating both pivot on `verbosityTier() === TIER_OFF` (not `isVerbose()`). Default-tier hint now recommends `=actionable` instead of `=1`.
- [x] 9 call sites audited — AddHasFluent*Trait (6 sites) → `actionable: false`; UpdateRulesReturnTypeDocblock user-customized `@return` (1) → `actionable: false`; SimplifyRuleWrappers unparseable payload (1) → `actionable: false`; SimplifyRuleWrappers method-not-on-receiver with FieldRule hint (1) → `actionable: true` (default — the hint IS the actionable guidance).
- [x] `DiagnosticsTest`: 8 new cases covering tier parsing (off/actionable/all/legacy/empty/case-insensitive + actionable cwd path).
- [x] `LogsSkipReasonsTest`: 5 new cases covering actionable-surfaces, actionable-hides-non-actionable, actionable-still-default, all-surfaces-non-actionable, legacy-one-surfaces-all.
- [x] `RunSummaryTest`: new actionable-tier case + updated default-mode-hint assertion.

### Findings

- **RunSummary cleanup regression (Codex HIGH).** First pass only updated `Diagnostics::skipLogPath()` to route `actionable` to cwd; `RunSummary::registerShutdownHandler` still unlinked the log whenever `! isVerbose()` — so an `actionable` run wrote the log, then the parent deleted it at shutdown, and the hint line still said `Re-run with ...=1`. Fixed by replacing both `isVerbose()` checks with `verbosityTier() === TIER_OFF`.
- **Non-actionable label on SimplifyRuleWrappers unparseable payload (Codex MEDIUM).** Existing code comment explicitly said "not actionable for consumers" — I initially left it at default `actionable: true`. Flipped to `false`.
- **Non-actionable label on UpdateRulesReturnTypeDocblock user-customized `@return` (Codex MEDIUM).** Reason is "respecting consumer customization" — not something the package can act on. Flipped to `false`.
- **Split labeling on SimplifyRuleWrappers method-not-on-receiver (judgment call).** The FieldRule-enriched hint (`consider FluentRule::string() / numeric() / array() / file()`) is the whole point of logging — consumers act on it to migrate. Kept `actionable: true`.
- **`RunSummary` hint evolution.** Default-mode hint now says `Re-run with FLUENT_VALIDATION_RECTOR_VERBOSE=actionable` (was `=1`). Legacy `=1` consumers see zero behavior change; new consumers are nudged toward the quieter tier.

### Tests

- 492 tests / 850 assertions / 0 failures. +14 tests (8 Diagnostics, 5 LogsSkipReasons, 1 RunSummary).
- Pint clean. PHPStan 0 errors. `vendor/bin/rector process` 0 self-changes.
