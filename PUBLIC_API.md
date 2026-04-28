# Public API

This file enumerates the package's frozen public API surface. Every symbol and
behavioral commitment listed here is governed by [SemVer 2.0](https://semver.org)
per the [README's Versioning policy](README.md#versioning-policy). Symbols not
listed are `@internal` and may change in any release without a MAJOR bump.

> **What's API:** symbol names (class FQNs, constant names), wire-key string
> values, observable behavior (skip-log paths, line format, env var name +
> accepted values).
>
> **What's NOT API:** symbol-internal logic, return types of `@internal`
> methods, skip-log reason text content, classmap layout on disk, anything
> under the `SanderMuller\FluentValidationRector\Internal\` namespace.

## Namespace structure

The package's namespace tree is split into three tiers:

- **Public**: `SanderMuller\FluentValidationRector\` (root) — rector class
  FQNs, set list, top-level entry points listed below.
- **Public, scoped**: `SanderMuller\FluentValidationRector\Set\`,
  `…\Config\`, `…\Config\Shared\` — narrowly-scoped public surfaces (set
  list constants, typed-config DTO builders, shared value objects). Every
  symbol must appear under one of the sections below.
- **Internal**: `SanderMuller\FluentValidationRector\Internal\` (added in
  0.20.0) — implementation-detail classes whose namespace placement IS
  the do-not-import signal. May change in any release without a MAJOR
  bump. Do not import. Pre-0.20.0 these classes lived at the root with
  `@internal` PHPDoc tags only; root-namespace deprecation shims were
  removed in 0.22.0.

`Rector\Concerns\` traits are also implementation detail (intended to be
mixed into rector classes only); they have always been `@internal` by
PHPDoc and are not under the `Internal\` namespace for path-stability
reasons (Rector's autoloader expects `Rector\` prefix for rector
discovery).

## Set list constants

`SanderMuller\FluentValidationRector\Set\FluentValidationSetList` — the
constant *names* are committed; the values (config file paths) are
implementation detail.

- `FluentValidationSetList::ALL`
- `FluentValidationSetList::CONVERT`
- `FluentValidationSetList::GROUP`
- `FluentValidationSetList::TRAITS`
- `FluentValidationSetList::POLISH`
- `FluentValidationSetList::SIMPLIFY`

## Rector class FQNs

All rector class names are committed. Renames break consumer
`withRule(...)` / `withConfiguredRule(...)` registrations.

- `SanderMuller\FluentValidationRector\Rector\AddHasFluentRulesTraitRector`
- `SanderMuller\FluentValidationRector\Rector\AddHasFluentValidationTraitRector`
- `SanderMuller\FluentValidationRector\Rector\ConvertLivewireRuleAttributeRector`
- `SanderMuller\FluentValidationRector\Rector\GroupWildcardRulesToEachRector`
- `SanderMuller\FluentValidationRector\Rector\InlineMessageParamRector`
- `SanderMuller\FluentValidationRector\Rector\InlineResolvableParentRulesRector`
- `SanderMuller\FluentValidationRector\Rector\PromoteFieldFactoryRector`
- `SanderMuller\FluentValidationRector\Rector\SimplifyFluentRuleRector`
- `SanderMuller\FluentValidationRector\Rector\SimplifyRuleWrappersRector`
- `SanderMuller\FluentValidationRector\Rector\UpdateRulesReturnTypeDocblockRector`
- `SanderMuller\FluentValidationRector\Rector\ValidationArrayToFluentRuleRector`
- `SanderMuller\FluentValidationRector\Rector\ValidationStringToFluentRuleRector`

## Rector configuration constants

Constant *names* AND *string values* are both committed. Consumers passing
the constant into `withConfiguredRule(..., [Const::KEY => ...])` and
consumers passing the literal string key are both protected.

### `SimplifyRuleWrappersRector`

- `TREAT_AS_FLUENT_COMPATIBLE` (value: `'treat_as_fluent_compatible'`)
- `ALLOW_CHAIN_TAIL_ON_ALLOWLISTED` (value: `'allow_chain_tail_on_allowlisted'`)

### `ConvertLivewireRuleAttributeRector`

- `PRESERVE_REALTIME_VALIDATION` (value: `'preserve_realtime_validation'`)
- `MIGRATE_MESSAGES` (value: `'migrate_messages'`)
- `KEY_OVERLAP_BEHAVIOR` (value: `'key_overlap_behavior'`)
- `OVERLAP_BEHAVIOR_BAIL` (value: `'bail'`)
- `OVERLAP_BEHAVIOR_PARTIAL` (value: `'partial'`)

#### Canonical configuration shape

```php
use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\ConvertLivewireRuleAttributeRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->ruleWithConfiguration(
        ConvertLivewireRuleAttributeRector::class,
        [
            ConvertLivewireRuleAttributeRector::KEY_OVERLAP_BEHAVIOR
                => ConvertLivewireRuleAttributeRector::OVERLAP_BEHAVIOR_PARTIAL,
        ],
    );
};
```

The keys are the constant *names* (committed); the values are the constant
*values* (committed wire keys). Mixing constant references with the raw
string values is supported but the constant references are preferred so
IDE refactoring follows correctly.

### `AddHasFluentRulesTraitRector`

- `BASE_CLASSES` (value: `'base_classes'`)

### `UpdateRulesReturnTypeDocblockRector`

These constants share their wire key (string value) with the equally-named
constants on `SimplifyRuleWrappersRector`, but they configure this rector
independently — pass the key on each rector that consumes it.

- `TREAT_AS_FLUENT_COMPATIBLE` (value: `'treat_as_fluent_compatible'`)
- `ALLOW_CHAIN_TAIL_ON_ALLOWLISTED` (value: `'allow_chain_tail_on_allowlisted'`)

## Wire keys (committed indefinitely)

The literal-string keys consumers may pass directly to
`withConfiguredRule($rector, [<key> => <value>])`. These are the canonical
wire format; the constants above are convenience symbols pointing at them.
Listing the wire keys as first-class API ensures literal-string consumers
get the same semver guarantees as constant-using consumers — a future
constant rename does NOT change the wire key.

**Per-rector configuration.** Each rector receives its own configuration
array via `withConfiguredRule(...)`. When the same wire key appears on
multiple rectors (e.g. `'treat_as_fluent_compatible'` on both
`SimplifyRuleWrappersRector` and `UpdateRulesReturnTypeDocblockRector`),
pass the key on each rector that consumes it — the values are not pooled
across rectors.

### `SimplifyRuleWrappersRector` wire keys

- `'treat_as_fluent_compatible'` (`array<class-string>`) — additional class
  FQNs the rector should treat as wrappers around `FluentRule` chains.
- `'allow_chain_tail_on_allowlisted'` (`bool`) — permit non-fluent chain
  tails on allowlisted classes.

### `ConvertLivewireRuleAttributeRector` wire keys

- `'preserve_realtime_validation'` (`bool`) — preserve Livewire real-time
  validation marker on converted properties.
- `'migrate_messages'` (`bool`) — migrate `message:` attribute args into a
  `messages()` method override.
- `'key_overlap_behavior'` (`'bail' | 'partial'`) — overlap mode between
  attribute keys and explicit `validate([...])` keys.

### `AddHasFluentRulesTraitRector` wire keys

- `'base_classes'` (`array<class-string>`) — additional FormRequest base
  classes that should receive the trait.

### `UpdateRulesReturnTypeDocblockRector` wire keys

Same wire keys as `SimplifyRuleWrappersRector`, with identical semantics
per key. Configured independently per the per-rector configuration rule
above — pass the key on each rector that consumes it.

- `'treat_as_fluent_compatible'` (`array<class-string>`) — additional
  class FQNs the rector should treat as wrappers around `FluentRule`
  chains (for the docblock-narrow predicate).
- `'allow_chain_tail_on_allowlisted'` (`bool`) — permit non-fluent chain
  tails on allowlisted classes (for the docblock-narrow predicate).

## Verbose-mode env var

- `FLUENT_VALIDATION_RECTOR_VERBOSE` — env var name. Canonical accepted
  values: `off`, `actionable`, `all`. Legacy synonyms `1` / `true`
  (case-insensitive) resolve to `all`; preserved indefinitely for
  pre-0.13 compatibility. Renames of the env var name or removal of
  the canonical vocabulary are MAJOR-bump events. Empty / unset
  resolves to `off`. Resolution is case-insensitive for the named
  values.

## Skip-log file paths

Two paths, mode-dependent. Both are committed observable contracts.

- **Verbose tier on** (`=1` / `=actionable` / `=all`):
  `<cwd>/.cache/rector-fluent-validation-skips.log`
  (`.cache/` subdir auto-created; falls back to cwd-root if `.cache/`
  cannot be created — read-only mount, restrictive perms, etc.).
- **Verbose tier off** (default, env unset):
  `sys_get_temp_dir() . '/rector-fluent-validation-skips-<cwd-hash>.log'`
  (hashed temp path scoped per-cwd; unlinked by the package's internal
  end-of-run summary handler, so no artifact persists).

Collapsing both to a single path is a MAJOR-bump event.

## Skip-log line format

```
[fluent-validation:skip] <RectorShortName> <ClassFQCN> (<file-path>[:<line>]): <reason>
```

Slot semantics:

- `[fluent-validation:skip]` — fixed prefix; consumers grep on it.
- `<RectorShortName>` — `(new ReflectionClass($this))->getShortName()`.
- `<ClassFQCN>` — fully-qualified name of the class the skip applies to.
- `(<file-path>[:<line>])` — path to the source file, with an optional
  `:<line>` suffix when the skip-emit site has a known AST node
  position (added 0.21.0). Parenthesized, single-spaced from the FQCN.
  IDE click-to-open supports both forms (PhpStorm / VS Code terminal /
  vim quickfix all parse `path:42` inside parens correctly). Per-key /
  per-item skips emit the offending node's line; class-wide skips emit
  the `class Foo` declaration line. Skips emitted from name-only call
  sites (no AST node available) omit the `:<line>` suffix.
- `:` — colon separator before the reason text.
- `<reason>` — free-text reason. Text content is implementation; do not
  parse beyond the colon.

Removing or rearranging slots, removing parenthesization, changing the
prefix, or inserting new fields between existing slots is a MAJOR-bump
event. Reason text changes are not breaking. The `:<line>` suffix is
optional — its presence/absence on a given entry is implementation
detail (depends on whether the emit site had AST positioning info).

## Skip-log header shape

```
# laravel-fluent-validation-rector <version> — generated <ISO-8601 UTC>
# verbose tier: <off|actionable|all>

```

Each header line starts with `# `, has a colon-separated key/value, and
is followed by a single blank line before entries. Field *values*
(version string, timestamp format, tier vocabulary) are implementation.

## Heuristic boundaries (implementation detail)

The rectors decide which AST shapes are convertible vs. skippable
using internal heuristics. Heuristic tightening (catching more true
positives, eliminating more false positives) ships in MINOR releases
without MAJOR bumps. The categories below are explicitly NOT
SemVer-committed:

- **Detection-heuristic boundaries** — which AST shapes a rector
  classifies as unsafe-to-convert, closure-scoped, wrapper-around-
  FluentRule, FormRequest descendant, etc.
- **Classification confidence / trace depth** — e.g. data-flow trace
  may deepen across MINOR releases when consumer signal warrants.
- **Skip-text reason content** — the free-text portion after the
  colon in the skip-log line. The line *format* (slot semantics,
  prefix, parenthesization) IS committed; the text is not.
- **Fixture coverage** — existing tests pin shapes the rector handles
  correctly today. The absence of a fixture for a shape is not a
  promise the shape stays unhandled.
- **Diagnostic granularity** — per-key vs. per-class skip emission
  cardinality. The line-format slot count IS committed; emission
  count per source class is not. Consumers gating CI on skip-log
  entry counts should expect entry-count variance across MINOR
  releases as diagnostic granularity tightens; gate on rector-class
  FQN presence + reason-text grep instead.

What IS committed for behavior is enumerated above (skip-log file
paths, line-format slot semantics, env var name + accepted vocabulary,
wire-key strings, rector class FQNs, set list constant names,
configuration constant names + their wire-key values).

## Trait FQNs (referenced by trait-add rectors)

Live in the main `laravel-fluent-validation` package, not this rector
package. Listed here because the rector's behavior is observable via the
inserted traits — renames upstream cascade.

- `SanderMuller\FluentValidation\HasFluentRules`
- `SanderMuller\FluentValidation\HasFluentValidation`
- `SanderMuller\FluentValidation\HasFluentValidationForFilament`

## Inspecting test fixtures (semantics-pinning)

The Composer archive ships only the runtime artifacts (`src/`, `config/`,
`composer.json`); the `tests/` directory is excluded to keep the package
size lean. Cold consumers wanting to spot-check what shapes the rector
exercises — particularly the parity-harness fixtures that pin runtime
semantics across the converter rectors — can browse them directly on
GitHub:

- [`tests/Parity/Fixture/`](https://github.com/SanderMuller/laravel-fluent-validation-rector/tree/main/tests/Parity/Fixture)
  — parity-harness fixtures, organized by rector class
  (`SimplifyRuleWrappersRector/`, `GroupWildcardRulesToEachRector/`,
  `PromoteFieldFactoryRector/`, `Attributed/`).
- [`tests/<Rector>/Fixture/`](https://github.com/SanderMuller/laravel-fluent-validation-rector/tree/main/tests)
  — per-rector before/after pairs.
- [`tests/FullPipelinePolish/Fixture/`](https://github.com/SanderMuller/laravel-fluent-validation-rector/tree/main/tests/FullPipelinePolish/Fixture)
  — end-to-end multi-rector scenarios under the `ALL + POLISH` set
  list combination.

The parity-harness runner is `SanderMuller\FluentValidationRector\Tests\Parity\ParityTest`.
Each fixture is a `.php` file declaring `rules_before` / `rules_after` /
`payloads` arrays; the harness validates both shapes against the same
payloads and asserts identical error bags. If the rector's output ever
diverges semantically from its input, the parity test surfaces the
regression at CI time.
