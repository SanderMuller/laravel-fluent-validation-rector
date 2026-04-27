# Roadmap

What's coming up. Release history lives in `CHANGELOG.md`.

## Queued

Scoped and ready to ship; waiting on a signal (consumer request or bandwidth).

_Nothing queued. Awaiting 0.19 dogfood feedback before committing to the next increment. Open candidates: 1.0 cut, `Internal\Diagnostics` namespace move (deferred from 0.15.0 / 0.18.0), magic-constant config deprecation cycle for 2.0._

## Parked

Pre-scoped but contingent on external signal. Not doing unless something changes.

- **Trait-split renaming** to tokenizer/builder/installer architecture. Current `ConvertsValidationRuleStrings` + `ConvertsValidationRuleArrays` works fine for two input shapes. If a third shape arrives (YAML config, external rule source, etc.), the refactor becomes "add a tokenizer + reuse builder" — renaming preemptively is speculative generality.
- **Concurrent-rector-invocation PID liveness check.** The sentinel design trusts "one rector run per cwd." Rare-but-real failure mode when IDE + pre-push hooks race. `posix_kill(pid, 0)` liveness check against a PID stored alongside PPID would address it. Do if someone files.
- **`--dry-run-skip-log` flag.** Materialize the skip log without applying transformations, for pre-conversion audit. Currently unclear whether `--dry-run` suppresses the skip-log writes; if not, the behavior exists in spirit already. Investigate if someone files.
- **Hybrid-bail dormant-pattern linter.** Separate project — the rector's hybrid-bail correctly preserves attributes in files where `validate([...])` overrides them, but the source state is already dead code. A sibling `RemoveDeadLivewireAttributesRector` or inspection mode could consume the hybrid-bail log. Not the rector's scope; noting in case it becomes a separate tool later.
