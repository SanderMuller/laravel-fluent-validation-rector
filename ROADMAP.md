# Roadmap

Tracks release status, peer feedback, and planned work.

## Released

### 0.1.0 — 2026-04-12

Initial release. Core rectors for string-form and array-based rules, trait insertion (FormRequest + Livewire), wildcard/dotted grouping, and set lists (`CONVERT`, `GROUP`, `TRAITS`, `SIMPLIFY`, `ALL`).

### 0.1.1 — 2026-04-12

Three fixes surfaced by early real-world runs: synthesized parent no longer `->nullable()`, `children()`/`each()` print multi-line, trait insertion emits proper top-of-file `use` import + blank-line separator.

### 0.2.0 — broken tag

Tag landed on a pre-feature commit (before gap 1 + finding A + gap 2 shipped). Immutable, so the features were re-tagged as 0.3.0. Users on `^0.2.0` should upgrade to `^0.3.0`.

### 0.3.0 — 2026-04-12

Broader coverage of existing rule input forms, polish on trait-insertion output, ancestor-aware trait detection.

- **Array-tuple rules** — `['max', 65535]` → `->max(65535)` via a new `buildModifierCallFromTupleExprArgs()` dispatcher in the shared trait.
- **Flat wildcard folding** — `'items.*' => [...]` folds into parent `->each(<scalar>)`. Const-concat wildcard keys (`self::VIDEO_IDS . '.*'`) handled.
- **Synthesized `FluentRule::` uses short name** — `GroupWildcardRulesToEachRector` no longer emits the FQN pre-Pint.
- **Trait `use` imports insert alphabetically** — replaces the 0.1.1 prepend via `ManagesNamespaceImports`.
- **Ancestor trait detection** — both trait rectors walk the class's ancestor chain via `ReflectionClass` and skip insertion when a parent already declares the trait.
- **`Rule::unique()->withoutTrashed()` regression fixture** — pinned (pre-existing behavior).
- **PHPStan `#[FluentRules]` attribute string-literal lookup** — forward-compatibility.

### 0.3.1 — 2026-04-12

Finished the pre-Pint output polish: converter-pathway `FluentRule::` short name + sorted use-import insertion via `ManagesNamespaceImports`. Regression at release time (see 0.3.2).

### 0.3.2 — 2026-04-12

Urgent fix for a 0.3.1 pipeline regression caught by hihaho re-verification: the converter rectors registering `Namespace_` competed with group/trait rectors on the same traversal pass, silently no-op'ing the downstream rules. Reverted converter node-type dispatch, fell back to `UseNodesToAddCollector` for import management (Pint's `ordered_imports` sorts the prepend in consumer projects), taught downstream rectors to recognize both short and FQN `FluentRule::` references, added a full-pipeline regression test.

### 0.4.0 — 2026-04-13

`#[Rule(...)]` Livewire attribute conversion. New `ConvertLivewireRuleAttributeRector` strips `#[Rule]`/`#[Validate]` attributes from properties and generates a `rules(): array` method. Maps `as:` to `->label()`. Hybrid bail (class with attributes + explicit `$this->validate([...])` call). 7 fixtures.

### 0.4.1 — ghost tag

Tag landed on the pre-fix commit (`ab4a7a5`) before the three 0.4.1 fixes were merged. Immutable, so re-tagged as 0.4.2. Both collectiq and mijntp caught the zero-delta on re-verification (ghost-tag SHA mismatch). Users on `^0.4.1` should upgrade to `^0.4.2`.

### 0.4.4 — 2026-04-13

Fast point-release. One data-loss fix to 0.4.3's skip-log sentinel, one cosmetic nit.

- **Skip-log `fflush` before sentinel unlock** — caught by mijntp during 0.4.3 verification. `flock(LOCK_UN)` is POSIX advisory-only and does not flush PHP's userland stream buffer; the implicit `fclose` flush runs in `finally` (after the unlock). So the next worker to acquire the sentinel lock could read through an empty/stale sentinel, decide the session was fresh, and re-truncate the log — wiping entries an earlier worker had already appended. 100% repro on macOS/APFS under `withParallel()` for small file counts (2-file bail-only runs produced zero log output across 3 consecutive runs). Fix: `fflush($handle)` immediately before `flock($handle, LOCK_UN)` in `ensureLogSessionFreshness()`.
- **`@return` docblock short-name emit** — caught by collectiq during 0.4.3 verification. `setDocComment` wrote `\SanderMuller\FluentValidation\FluentRule` in the `@return` annotation even though `queueFluentRuleImport()` already guarantees the short alias is in scope. Pint's `fully_qualified_strict_types` cleaned it up, but the pre-Pint output was chattier than necessary — same class of nit as the 0.3.0 "synthesized `FluentRule::` uses short name" fix. Emitting `FluentRule` short name directly.

### 0.4.3 — 2026-04-13

Four polish items:

- **`validateOnly()` in hybrid bail** — `ConvertLivewireRuleAttributeRector::hasExplicitValidateCall()` now also matches `$this->validateOnly($field, $rules)` with an explicit rules array at arg 1. `validateOnly($field)` without a rules override keeps converting (it uses `rules()`, so no dead code risk). Two new fixtures pin both shapes.
- **Tighter `@return` on generated `rules()`** — emits `@return array<string, FluentRule|string|array<string, mixed>>` via `setDocComment` so rector-preset's `DocblockReturnArrayFromDirectArrayInstanceRector` doesn't overwrite with the loose `@return array<string, mixed>`. Updated 6 existing fixtures.
- **Skip-log race under `withParallel()`** — caught by mijntp's 0.4.2 verification. Per-worker `static $logFileTruncated` meant every worker independently decided it was "first" and truncated the log, wiping earlier workers' entries. Replaced with a PPID-keyed session sentinel (`.rector-fluent-validation-skips.log.session`) under `flock(LOCK_EX)`: first worker to see a PPID-mismatch truncates, all others append. POSIX feature-detect with an mtime-staleness fallback (300s) for non-POSIX / Windows so the same mechanism degrades gracefully without per-worker data loss. `.gitignore` updated to include the sentinel.
- **README "Tips" — manual spot-check note** — flagged by mijntp. Files without component-level tests should be spot-checked post-`#[Rule]`-conversion since the rector verifies syntactic correctness only, not behavioral equivalence. Points at `.rector-fluent-validation-skips.log` for dropped-arg inspection.

### 0.4.2 — 2026-04-13

Three polish items surfaced by collectiq's 7-file `#[Rule]` verification (originally planned as 0.4.1 but that tag ghosted):

- **File-sink skip log** — `LogsSkipReasons` now writes to `.rector-fluent-validation-skips.log` in cwd (with `FILE_APPEND | LOCK_EX`) so workers spawned by `withParallel()` don't lose their skip output. Critical UX fix: pre-0.4.2, ~100% of production runs (which use `withParallel()` by default) saw zero skip-log output regardless of `FLUENT_VALIDATION_RECTOR_VERBOSE=1` because Rector's parallel executor doesn't forward worker STDERR. Added to `.gitignore`.
- **Blank line before generated `rules()`** — `ConvertLivewireRuleAttributeRector` emits a `Nop` between the previous class member and the appended method, so Pint's `class_attributes_separation` fixer no longer fires on every converted file.
- **Property-type-aware type inference** — when the rule string has no type token (e.g. `#[Validate('max:2000')]`) but the PHP property is typed (`public string $description`), the rector uses the property type as the factory base. Result: `FluentRule::string()->max(2000)` instead of `FluentRule::field()->rule('max:2000')` escape hatch. Maps `string`/`int`/`integer`/`bool`/`boolean`/`float`/`array` to the corresponding factory; nullable types unwrap to the inner scalar; union/intersection/object types fall through to the no-hint behavior. New `property_type_inference` fixture covers the matrix.

## Peer feedback log

### collectiq — `8qylh9ys` (Pieter)

- **0.1.0 run** — 8 FormRequests, reported two semantic bugs (synthesized parent `->nullable()`, one-line `children()`). Both fixed in 0.1.1.
- **Nit A (0.1.1)** — synthesized parent emitted FQN. **Fixed in 0.3.0.**
- **Nit B (0.1.1)** — trait `use` imports prepended. **Fixed in 0.3.0** via `ManagesNamespaceImports`.
- **0.3.0 + 0.3.1 re-verification** — Pint is a no-op on collectiq's output. Gold-standard `validation_rejects_missing_fields` HTTP test held through every release.
- **0.3.2 sanity check** — 8 files converted, same set, 10/10 HTTP tests pass. No regressions from the pipeline fix.
- **0.4.0 verification** — 7 `#[Rule]`-attribute files. Caught `message:` singular silently dropped (pre-tag fix), 5-of-7 hybrid classes (pre-tag fix via `hasExplicitValidateCall`). 26/26 feature tests green.
- **0.4.1 ghost-tag catch** — on re-verification detected byte-identical vendor source vs 0.4.0, then a clean SHA check (`git rev-parse 0.4.1` → `ab4a7a5` vs expected `db64e56`) diagnosed the ghost-tag within minutes. Drove the 0.4.2 re-tag.
- **0.4.2 re-verification** — full acceptance scorecard across all 3 items: file-sink log created (72 lines under `withParallel(300, 15, 15)`), `FluentRule::string()->max(2000)` inference working on typed properties, blank line before `rules()` present and `class_attributes_separation` Pint fixer no longer firing. 26/26 tests, 52 assertions.
- **0.4.3 verification** — confirmed tighter `@return` docblock lands (post-Pint: short `FluentRule` alias; pre-Pint: FQN — flagged the nit for 0.4.4). 1-of-1 `message:` drop entry on 15-worker run (PPID sentinel de-dup holding on collectiq's scale). 26/26 tests green.

### hihaho — `y0vob4dg`

- **0.1.0 run** — 108 files, 2,098 tests pass, 2.8× human coverage. Ranked 6 gaps by frequency.
- **Gap 1 — tuple unwrapping** → **shipped in 0.3.0.**
- **Gap 2 — flat wildcard folding** → **shipped in 0.3.0.** Const-concat edge case (`self::VIDEO_IDS . '.*'`) handled.
- **Gap 3 — ternary rules → `->when()`** → deferred (narrow pattern, experimental).
- **Gap 4 — `Rule::unique()->withoutTrashed()` unwrap** → pre-existing behavior; regression fixture pinned in 0.3.0.
- **Gap 5 — long chains on one line** → README-documented; not rector's job.
- **Gap 6 — FQN-inline trait** → **shipped in 0.1.1.**
- **Fixture contributions** — `UpdateVideoRequest`, `CopyInteractionsRequest`, `DuplicateVideosRequest` baked in verbatim as 0.3.0 fixtures. Four more (`RequestSubscriptionModal`, `GeneralSettings`, `CreateApiToken`, full `CopyInteractionsRequest`) pasted for future use.
- **0.3.1 regression catch** — caught the `Namespace_` pipeline regression that the per-rector test configs couldn't see. Drove the 0.3.2 emergency fix.
- **0.3.2 re-verification** — all 15 grouping opportunities + 64 trait insertions fire on the 108 files. 566 feature tests green.

### mijntp — `kb7vilgo`

- **0.1.0 run** — 23 files, 900 tests pass.
- **Issue 1 — missing blank line after Livewire trait** → **shipped in 0.1.1.**
- **Issue 2 — `#[Rule(...)]` attribute conversion** → **shipping in 0.4.0.** Peer contributed a reference before/after from `InventorizationAdminSettings`, confirmed target-A design (migrate to `rules(): array` rather than a parallel attribute class).
- **Issue 3 — trait ordering alphabetical vs after-last-trait** → Pint's `ordered_traits` absorbs it. Documented.
- **Finding A (0.3.0 re-verify)** — import ordering. Vacuously passed on mijntp (no trait insertion fires) but verified elsewhere.
- **Finding B (0.3.0 re-verify)** — redundant trait on inheriting subclasses. **Shipped in 0.3.0** via `DetectsInheritedTraits`. Caught not just the 6 explicitly-flagged Livewire subclasses but also all 17 FormRequests extending a local abstract base — zero-files-touched on 0.3.0+ for mijntp.
- **0.3.2 verification** — batched with 0.4.0 per peer preference.
- **0.4.1 ghost-tag co-catch** — independently confirmed byte-for-byte no-op vs 0.4.0 on the 3 Settings files, pinpointed `installRulesMethod()` L361 as still-flat at that SHA. Second signal that turned the ghost-tag into an unambiguous tag issue rather than a subtle regression.
- **0.4.2 verification + skip-log race finding** — confirmed all 3 fixes landed on `00b6589`; deterministically reproduced a per-worker truncation race in `LogsSkipReasons.php` under `withParallel()` (Run A: 0 array-form entries; Run B `--debug`: all entries; Run C parallel-only: file missing). Drove the 0.4.3 PPID-sentinel fix. Pinned dirty-log-preseed + run-twice as the regression scenarios.
- **0.4.3 verification + fflush finding** — scenarios 2 (dirty-log preseed) and 3 (run-twice) passed; scenario 1 (baseline) and 4 (debug vs parallel) failed with bail-only entries disappearing entirely under parallel. Diagnosed the missing `fflush` before `flock(LOCK_UN)` — the sentinel write was buffered past unlock, letting the next worker truncate through stale content. Drove the 0.4.4 fix. Reproduced 100% of runs on macOS/APFS.

## Deferred

- **Ternary rules → `->when(cond, thenFn, elseFn)`** (hihaho gap 3) — narrow pattern, experimental. Needs a concrete sub-set of "both branches are single rule-string literals, no side effects".
- **Array-form `#[Rule([...])]` attributes** — requires sharing more of `ValidationArrayToFluentRuleRector`'s private helpers via the `ConvertsValidationRules` trait. Tractable; deferred to a follow-up.
- **Inline tuple comments** — comments attached to tuple AST nodes are lost when the tuple lowers to a fluent method call. Pre-existing from 0.1.x; not tractable without deeper AST plumbing.
- **Configurable trait insertion ordering** (mijntp issue 3) — Pint's `ordered_traits` handles it. Not planned.

## Non-issues (won't fix)

- **Long fluent chains on one line** — Pint / php-cs-fixer territory; rector won't break chains itself.
- **Namespace-less files** — classes declared at the file root without a `namespace` are skipped. Not a concern for Laravel apps.
- **Classes in external packages / vendor** — Rector's standard path filtering applies; nothing package-specific to handle.
