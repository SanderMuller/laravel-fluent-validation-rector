# Roadmap

What's coming up. Release history lives in CHANGELOG.md; detailed designs live in `SPEC_*.md` files at the repo root.

## 0.4.9 — deferred from 0.4.8

### Run-summary stdout line

Originally planned as 0.4.8 item #2. Both collectiq and mijntp surfaced this as the highest-ROI DX change in their 0.4.7 retrospectives. Deferred because Rector's parallel architecture makes parent-process emit non-trivial: our rectors instantiate in the parent process (DI container construction) but don't *execute* there — they run in forked workers. `register_shutdown_function` from worker code doesn't reach the parent. A proper implementation needs a spike on Rector's event system or output-formatter extension points.

Target shape:

```
[fluent-validation] X converted, Y skipped — see .rector-fluent-validation-skips.log
```

Emitted once per Rector invocation from the parent process. Parent-process emit sidesteps the `withParallel()` STDERR swallow that motivated the file sink in 0.4.2.

Plan: spike Rector 2.x for `ConsoleOutput` interceptor or a `TerminateEvent` hook; if neither exists cleanly, consider emitting the summary as a trailing line in the skip log itself that users `tail -1` for.

## Pre-1.0

Not scoped for 0.4.x; queued for when a 1.0 commitment is on the table.

- **Auto-tag CI workflow.** Two ghost-tags in this cycle (0.4.1, 0.4.4) cost real peer time. A GitHub Actions workflow that tags on CHANGELOG-merge eliminates the class of error. Manual tagging has been OK for peer-iteration velocity; for a 1.0 public contract it's the right discipline. (collectiq)
- **Methodology writeup.** The peer-iterated release loop (three-vantage-peer model: output polish + scale + architecture, pre-cut spec + post-cut verification, firing-counts-as-invariant regression guard) is portable across rector packages. hihaho and collectiq both nudged preservation. Could live as a gist, a section in the README, or a short `METHODOLOGY.md`.

## Parked

Pre-scoped but contingent on external signal. Not doing unless something changes.

- **Trait-split renaming** to tokenizer/builder/installer architecture. Current `ConvertsValidationRuleStrings` + `ConvertsValidationRuleArrays` works fine for two input shapes. If a third shape arrives (YAML config, external rule source, etc.), the refactor becomes "add a tokenizer + reuse builder" — renaming preemptively is speculative generality.
- **`new Password()` / `new Rule\Unique()` attribute-context detection.** Originally killed on scope-leak-risk grounds. mijntp's follow-up proposed a parent-node-is-`Attribute` gate that eliminates the leak. ~30-minute implementation. Not doing unless a concrete user request materializes — attribute-form codebases are already rare, and the `->rule(new X())` escape-hatch output is runtime-correct.
- **Concurrent-rector-invocation PID liveness check.** The sentinel design trusts "one rector run per cwd." Rare-but-real failure mode when IDE + pre-push hooks race. `posix_kill(pid, 0)` liveness check against a PID stored alongside PPID would address it. Do if someone files.
- **`--dry-run-skip-log` flag.** Materialize the skip log without applying transformations, for pre-conversion audit. Currently unclear whether `--dry-run` suppresses the skip-log writes; if not, the behavior exists in spirit already. Investigate if someone files. (hihaho)
- **`FluentValidationSetList::ONLY_CONVERTERS` set.** For mid-adoption "convert first, insert traits separately" rollouts. Niche; easy to add if demand surfaces. (hihaho)
- **Hybrid-bail dormant-pattern linter.** Separate project — the rector's hybrid-bail correctly preserves attributes in files where `validate([...])` overrides them, but the source state is already dead code. A sibling `RemoveDeadLivewireAttributesRector` or inspection mode could consume the hybrid-bail log. Not the rector's scope; noting in case it becomes a separate tool later. (collectiq)

## Non-issues (won't fix)

- **Long fluent chains on one line.** Pint / php-cs-fixer territory; rector won't break chains itself.
- **Namespace-less files.** Classes at the file root without a `namespace` are skipped. Not a concern for Laravel apps.
- **Classes in external packages / vendor.** Rector's standard path filtering applies.
- **Inline tuple comments.** Comments on tuple AST nodes are lost when the tuple lowers. Not tractable without deeper AST plumbing.
- **Configurable trait insertion ordering.** Pint's `ordered_traits` handles the alphabetical case. 0.4.8's `ManagesTraitInsertion` emits at the sorted position so Pint is typically a no-op.
- **Post-rector import sorting.** Framework-gated. Rector's `PostFileProcessor` hardcodes its post-rector list with no DI extension; regular rectors registered last can't see post-processed imports because Rector 2.x doesn't re-run rectors after `PostFileProcessor`. Pint's `ordered_imports` remains the canonical answer. Trait-insertion pathways emit sorted imports via `ManagesNamespaceImports` + sorted class-body trait insertion (0.4.8).
- **Ternary rules → `->when()`** (hihaho's original gap 3). Three-for-three zero-demand demand check across peer codebases; three mature Laravel codebases have gravitated to `Rule::when(...)` or branched method bodies. Users reaching for ternaries optimize for terseness; `->when(fn, fn)` closure shape loses that axis (mijntp observation).
