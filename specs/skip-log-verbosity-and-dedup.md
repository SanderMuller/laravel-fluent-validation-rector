# Skip-Log: Verbosity Tiers + Intra-Class Dedup

## Overview

Two diagnostic improvements that share the `LogsSkipReasons` trait and target the same UX problem: verbose-mode logs on large codebases are too noisy to triage.

**Ship in two independent phases.** Codex review (2026-04-24) flagged the dedup half as under-specified — spec still lists mutually-exclusive root causes for the observed duplicates. Verbosity tier work can and should proceed independently; dedup waits on a diagnostic-before-design phase.

- **Phase 1 (this spec, ready to build)**: 3-level verbosity tier. Default binary (`FLUENT_VALIDATION_RECTOR_VERBOSE` off/on) expands to default / `actionable` / `all`. Hihaho's 0.12.0 dogfood: 336 entries, ~249 non-actionable; `actionable` tier surfaces only the ~87 that matter.
- **Phase 2 (separate spec after Phase 1 ships)**: Intra-class dedup. Requires a preliminary investigation phase captured below — do not design without first pinning the root cause of the observed duplicates.

Source: hihaho 0.12.0 dogfood (both items).

---

## 1. Current State

### 1a. Two-level env gate

`Diagnostics::VERBOSE_ENV = 'FLUENT_VALIDATION_RECTOR_VERBOSE'`. `Diagnostics::isVerbose()` returns bool. `LogsSkipReasons::logSkip(..., verboseOnly: true)` gates individual log lines.

Current distribution of entries (hihaho dogfood):

| Tier                                             | Hits  | Actionable? |
|--------------------------------------------------|------:|:-----------:|
| Default-mode always-on                           | ~tens | Usually     |
| `verboseOnly: true` only-when-verbose            | ~hundreds | Often no |

### 1b. Per-process dedup key

`LogsSkipReasons` dedupes on `(rule, file, class, reason)` tuple. Hihaho case: a class with 4 `->rule(new DomainPatternRule())` calls emits 4 entries because each `->rule()` AST node has a distinct position; the `reason` string incorporates the payload description which happens to be identical, but the per-process dedup key tracks the *call site*, not just the reason text. Wait — actually re-reading: the tuple is `(rule, file, class, reason)` — if reason strings are identical, the tuple collides and dedup fires.

**Check before spec'ing.** The hihaho report claims 4 entries for one class. Either:
- Dedup IS working and peer's count is across multiple processes (parallel workers).
- Dedup key doesn't catch the reason-only collision because reason strings include something per-site (line number? AST position?).

Needs investigation before design. Likely the reason string includes enough per-call-site detail to defeat dedup. Fix: strip per-site details from the dedup-key reason while keeping them in the output.

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

### 2c. Dedup investigation (Phase 2 prerequisite, not part of Phase 1 implementation)

**Do not design dedup changes without first pinning the root cause.** Spec hihaho's observation: 4 entries for one class's repeated `->rule(new DomainPatternRule())`. Current dedup key is `(rule, file, class, reason)` — if `reason` is shape-stable, tuple should collide and coalesce. Required investigation before any dedup design work:

1. **Reproduce** with a minimal fixture — one class, three identical `->rule(new X())` calls, run under `VERBOSE=1`, count log entries.
2. **If 1 entry**: current dedup works. Peer's 4 was either cross-worker (parallel mode) or reason contained per-site detail. Move to (3a) or (3b).
3. **If 3 entries**: dedup is broken at the single-process level. Diagnose:
   - (3a) Is `writeSkipEntry` checking `loggedSkips` correctly? Trace the key hashing.
   - (3b) Does `describeUnparseablePayload`'s truncated pretty-print include line/position markers per call site? If yes, reason differs per site → tuple doesn't collide.
4. **If the answer is "parallel workers across processes"**: needs cross-worker dedup via sentinel-file-locked key set. Much heavier.

Design the fix against the concrete cause. Do not ship a normalization layer speculatively.

---

## 3. Safety Analysis

### 3a. Backward compat for `VERBOSE=1`

Existing CI logs and scripts grepping for specific messages must keep working. `VERBOSE=1` stays synonymous with `all` — no change.

### 3b. Actionable classification debate

Rector authors pick the actionable label; different consumers may disagree. Livewire-detection is non-actionable for a FormRequest-heavy project but arguably actionable for a mixed project where someone wants to know "which of these Livewire classes should I add the trait to manually?" Compromise: `actionable: true` default; verboseOnly entries get `actionable: false` explicitly when the rector author is confident it's non-actionable (trait-already-present, Livewire-not-FormRequest). Otherwise left `actionable: true`.

### 3c. Dedup may hide useful cross-site signal

If three files each have 1 entry for the same reason, dedup would coalesce to "1 entry in 1 file" (per-class-dedup is intra-class). Inter-file dedup out of scope — keep per-class as the unit.

---

## 4. Fixtures

Not a fixture-driven change primarily — instrumentation more than rule logic. Unit tests for `Diagnostics::verbosityTier()` and `LogsSkipReasons::logSkip` dedup under different inputs:

- `DiagnosticsTest`: parse `VERBOSE=off/actionable/1/all/true/empty`, returns tier.
- `LogsSkipReasonsTest` (new): four logSkip calls with identical `(rule, class, reason)` produce 1 log entry. Three with identical tuple but different `verboseOnly` flags — first wins, others suppressed.
- End-to-end: hihaho-shape fixture (`repeated_rule_payload_dedups.php.inc`) with a class calling `->rule(new X())` four times, run under VERBOSE=all, assert exactly one log entry.

---

## 5. Open Questions

1. **Tier name bikeshed**: `actionable` vs `signal` vs `triage`? `actionable` is clearest.
2. **Dedup investigation**: is the 4-entries-per-class observation hitting case (1), (2), or (3) above? Required before writing the fix — current behavior may already be correct and peer's count misleading.
3. **Should the 3-level tier be documented as the preferred default going forward**, with a README migration note telling consumers to switch from `VERBOSE=1` to `VERBOSE=actionable`? Lean yes; existing consumers can stay on `=1` if they want everything.
4. **Per-rector actionable-label table**: maintain in each rector class or centralize in a skill doc? Centralizing de-risks drift but requires a cross-file update on every new rector. Lean per-rector comment + an audit rule in the `code-review` skill.

---

## 6. Out of Scope

- Log rotation / size cap. Not the rector's job.
- JSON-structured log output for CI consumption. Can be added later.
- Per-file dedup (as opposed to per-class). Rectors run per-file already; a single file → one class → one entry per distinct reason is the right granularity.
- Retroactive relabeling of existing `verboseOnly: true` sites. Will happen incrementally as each rector is touched.
