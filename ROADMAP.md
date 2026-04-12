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

## In progress — 0.4.0

**Theme:** `#[Rule(...)]` Livewire attribute conversion.

| Item | Source | Status |
|---|---|---|
| `ConvertLivewireRuleAttributeRector` — strip `#[Rule]`/`#[Validate]` attributes, generate `rules(): array` method, map `as:` to `->label()` | Livewire-focused mijntp codebases | **Done** — string-form attribute args, 5 fixtures (single+label, two+labels, merge into existing `rules()`, bail on non-trivial `rules()`, Form component) |
| Bail + TODO for unsupported attribute args (`messages:`, `onUpdate:`) | mijntp peer suggestion | **Done** — TODO comment lists dropped args verbatim |
| Array-form `#[Rule([...])]` attributes | mijntp peer | Deferred (manual-migration TODO for now) |
| README updates + ROADMAP refresh | this release | **Done** |

## Peer feedback log

### collectiq — `8qylh9ys` (Pieter)

- **0.1.0 run** — 8 FormRequests, reported two semantic bugs (synthesized parent `->nullable()`, one-line `children()`). Both fixed in 0.1.1.
- **Nit A (0.1.1)** — synthesized parent emitted FQN. **Fixed in 0.3.0.**
- **Nit B (0.1.1)** — trait `use` imports prepended. **Fixed in 0.3.0** via `ManagesNamespaceImports`.
- **0.3.0 + 0.3.1 re-verification** — Pint is a no-op on collectiq's output. Gold-standard `validation_rejects_missing_fields` HTTP test held through every release.
- **0.3.2 sanity check** — 8 files converted, same set, 10/10 HTTP tests pass. No regressions from the pipeline fix.

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

## Deferred

- **Ternary rules → `->when(cond, thenFn, elseFn)`** (hihaho gap 3) — narrow pattern, experimental. Needs a concrete sub-set of "both branches are single rule-string literals, no side effects".
- **Array-form `#[Rule([...])]` attributes** — requires sharing more of `ValidationArrayToFluentRuleRector`'s private helpers via the `ConvertsValidationRules` trait. Tractable; deferred to a follow-up.
- **Inline tuple comments** — comments attached to tuple AST nodes are lost when the tuple lowers to a fluent method call. Pre-existing from 0.1.x; not tractable without deeper AST plumbing.
- **Configurable trait insertion ordering** (mijntp issue 3) — Pint's `ordered_traits` handles it. Not planned.

## Non-issues (won't fix)

- **Long fluent chains on one line** — Pint / php-cs-fixer territory; rector won't break chains itself.
- **Namespace-less files** — classes declared at the file root without a `namespace` are skipped. Not a concern for Laravel apps.
- **Classes in external packages / vendor** — Rector's standard path filtering applies; nothing package-specific to handle.
