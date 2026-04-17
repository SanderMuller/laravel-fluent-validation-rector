# Roadmap

What's coming up. Release history lives in CHANGELOG.md; detailed designs live in `specs/*.md` files and, for historical context, `SPEC_*.md` files at the repo root.

## Queued

Scoped and ready to ship; waiting on a signal (consumer request or bandwidth).

- **Phase 4 of `specs/array-form-rule-attribute-conversion.md`: `message:` → generated `messages(): array` method.** Migrate Livewire's `#[Validate('…', message: '…')]` arg into a companion `messages()` method on the converted class. Design blocked on Phase 1 (shipped in 0.4.19) so the `field.rule` message keys align with the expanded entry names; now unblocked. Spec recommends opt-in via a `migrate_messages` config flag by default, mirroring `preserve_realtime_validation`'s pattern. No consumer has asked yet — queued until someone does.

## Pre-1.0

Not scoped for 0.4.x; queued for when a 1.0 commitment is on the table.

- **Methodology writeup.** The peer-iterated release loop (three-vantage-peer model: output polish + scale + architecture, pre-cut spec + post-cut verification, firing-counts-as-invariant regression guard) is portable across rector packages. hihaho and collectiq both nudged preservation. Could live as a gist, a section in the README, or a short `METHODOLOGY.md`.

## Parked

Pre-scoped but contingent on external signal. Not doing unless something changes.

- **Trait-split renaming** to tokenizer/builder/installer architecture. Current `ConvertsValidationRuleStrings` + `ConvertsValidationRuleArrays` works fine for two input shapes. If a third shape arrives (YAML config, external rule source, etc.), the refactor becomes "add a tokenizer + reuse builder" — renaming preemptively is speculative generality.
- **Concurrent-rector-invocation PID liveness check.** The sentinel design trusts "one rector run per cwd." Rare-but-real failure mode when IDE + pre-push hooks race. `posix_kill(pid, 0)` liveness check against a PID stored alongside PPID would address it. Do if someone files.
- **`--dry-run-skip-log` flag.** Materialize the skip log without applying transformations, for pre-conversion audit. Currently unclear whether `--dry-run` suppresses the skip-log writes; if not, the behavior exists in spirit already. Investigate if someone files. (hihaho)
- **Hybrid-bail dormant-pattern linter.** Separate project — the rector's hybrid-bail correctly preserves attributes in files where `validate([...])` overrides them, but the source state is already dead code. A sibling `RemoveDeadLivewireAttributesRector` or inspection mode could consume the hybrid-bail log. Not the rector's scope; noting in case it becomes a separate tool later. (collectiq)
- **`@return array<string, mixed>` on emitted `rules()` methods.** collectiq's ask. Would regress mijntp's strict-mode `rector/type-perfect` + `tomasvotruba/type-coverage` setup, which flags `mixed` as too-broad. Current `array<string, ValidationRule|string|array<mixed>>` union is narrower and valid on both configs. Could become a `return_type_annotation` config flag if demand surfaces; current policy "narrower-is-safer" holds by default. (collectiq)
- **`@phpstan-ignore method.unused` on emitted `rules()` methods.** collectiq's ask. PHPStan flags `rules()` as unused when no direct caller is visible; framework-called methods are the Livewire PHPStan extension's domain, not the rector's output. Baking PHPStan-specific annotations into emitted Laravel code is unusual and hides real unused-method bugs. A proper fix is consumer-side via a PHPStan Livewire/Laravel extension. (collectiq)

## Non-issues (won't fix)

- **Long fluent chains on one line.** Pint / php-cs-fixer territory; rector won't break chains itself.
- **Namespace-less files.** Classes at the file root without a `namespace` are skipped. Not a concern for Laravel apps.
- **Classes in external packages / vendor.** Rector's standard path filtering applies.
- **Inline tuple comments.** Comments on tuple AST nodes are lost when the tuple lowers. Not tractable without deeper AST plumbing.
- **Configurable trait insertion ordering.** Pint's `ordered_traits` handles the alphabetical case. 0.4.8's `ManagesTraitInsertion` emits at the sorted position so Pint is typically a no-op.
- **Post-rector import sorting.** Framework-gated. Rector's `PostFileProcessor` hardcodes its post-rector list with no DI extension; regular rectors registered last can't see post-processed imports because Rector 2.x doesn't re-run rectors after `PostFileProcessor`. Pint's `ordered_imports` remains the canonical answer. Trait-insertion pathways emit sorted imports via `ManagesNamespaceImports` + sorted class-body trait insertion (0.4.8).
- **Ternary rules → `->when()`** (hihaho's original gap 3). Three-for-three zero-demand demand check across peer codebases; three mature Laravel codebases have gravitated to `Rule::when(...)` or branched method bodies. Users reaching for ternaries optimize for terseness; `->when(fn, fn)` closure shape loses that axis (mijntp observation).
