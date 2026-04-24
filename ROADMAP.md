# Roadmap

What's coming up. Release history lives in CHANGELOG.md; detailed designs live in `specs/*.md` files and, for historical context, `SPEC_*.md` files at the repo root.

## Queued

Scoped and ready to ship; waiting on a signal (consumer request or bandwidth).

- **`specs/validation-parity-tool.md`** — per-FormRequest harness that runs Laravel validation against pre-rector and post-rector rule shapes with fixture payloads and diffs the resulting error bags. Catches attribute-label and message-key drift that a rector's functional test suite misses: converted rules may produce the same pass/fail outcome but different `:attribute` substitution or different message-key paths, which is a user-visible regression. Main-package has no pre-rector notion, so the harness lives rector-side next to the codemods that caused the drift.
- **`specs/skip-log-coverage-hardening.md`** — `GroupWildcardRulesToEachRector` imports the `LogsSkipReasons` trait but has 0 `logSkip()` calls across 18 `return null` paths; users running the `GROUP` set on unsupported wildcard shapes see silent no-ops. Catalogue the decision points vs early-exits and emit short reasons at the former. The converter rectors (`ValidationString/ArrayToFluentRuleRector`) inherit logging via the `ConvertsValidationRuleStrings` trait-composition chain and are audit-only (not expected to need new emit sites unless the audit surfaces a concrete user-misleading bail).
- **`specs/tuple-arg-safety-tiers.md`** — formalize the tuple-arg acceptance tiers in `ValidationArrayToFluentRuleRector` so each rewrite path documents which expression shapes it accepts as args and which it rejects. Currently the acceptance logic is scattered across the array converter and the conditional-tuple path; a tiered spec turns it into a lookup table that future rewrite additions can slot into without re-deriving the rules.

## Pre-1.0

Not scoped for 0.4.x; queued for when a 1.0 commitment is on the table.

- **Methodology writeup.** The peer-iterated release loop (three-vantage-peer model: output polish + scale + architecture, pre-cut spec + post-cut verification, firing-counts-as-invariant regression guard) is portable across rector packages. Could live as a gist, a section in the README, or a short `METHODOLOGY.md`.

## Parked

Pre-scoped but contingent on external signal. Not doing unless something changes.

- **Trait-split renaming** to tokenizer/builder/installer architecture. Current `ConvertsValidationRuleStrings` + `ConvertsValidationRuleArrays` works fine for two input shapes. If a third shape arrives (YAML config, external rule source, etc.), the refactor becomes "add a tokenizer + reuse builder" — renaming preemptively is speculative generality.
- **Concurrent-rector-invocation PID liveness check.** The sentinel design trusts "one rector run per cwd." Rare-but-real failure mode when IDE + pre-push hooks race. `posix_kill(pid, 0)` liveness check against a PID stored alongside PPID would address it. Do if someone files.
- **`--dry-run-skip-log` flag.** Materialize the skip log without applying transformations, for pre-conversion audit. Currently unclear whether `--dry-run` suppresses the skip-log writes; if not, the behavior exists in spirit already. Investigate if someone files.
- **Hybrid-bail dormant-pattern linter.** Separate project — the rector's hybrid-bail correctly preserves attributes in files where `validate([...])` overrides them, but the source state is already dead code. A sibling `RemoveDeadLivewireAttributesRector` or inspection mode could consume the hybrid-bail log. Not the rector's scope; noting in case it becomes a separate tool later.

## Non-issues (won't fix)

- **Long fluent chains on one line.** Pint / php-cs-fixer territory; rector won't break chains itself.
- **Namespace-less files.** Classes at the file root without a `namespace` are skipped. Not a concern for Laravel apps.
- **Classes in external packages / vendor.** Rector's standard path filtering applies.
- **Inline tuple comments.** Comments on tuple AST nodes are lost when the tuple lowers. Not tractable without deeper AST plumbing.
- **Configurable trait insertion ordering.** Pint's `ordered_traits` handles the alphabetical case. 0.4.8's `ManagesTraitInsertion` emits at the sorted position so Pint is typically a no-op.
- **Post-rector import sorting.** Framework-gated. Rector's `PostFileProcessor` hardcodes its post-rector list with no DI extension; regular rectors registered last can't see post-processed imports because Rector 2.x doesn't re-run rectors after `PostFileProcessor`. Pint's `ordered_imports` remains the canonical answer. Trait-insertion pathways emit sorted imports via `ManagesNamespaceImports` + sorted class-body trait insertion (0.4.8).
- **Ternary rules → `->when()`.** Three-for-three zero-demand check across peer codebases; mature Laravel codebases gravitate to `Rule::when(...)` or branched method bodies. Users reaching for ternaries optimize for terseness; `->when(fn, fn)` closure shape loses that axis.
