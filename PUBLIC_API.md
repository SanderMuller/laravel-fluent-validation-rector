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
> methods, skip-log reason text content, classmap layout on disk.

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

### `AddHasFluentRulesTraitRector`

- `BASE_CLASSES` (value: `'base_classes'`)

### `UpdateRulesReturnTypeDocblockRector`

- `TREAT_AS_FLUENT_COMPATIBLE` (value: `'treat_as_fluent_compatible'`)
- `ALLOW_CHAIN_TAIL_ON_ALLOWLISTED` (value: `'allow_chain_tail_on_allowlisted'`)

## Wire keys (committed indefinitely)

The literal-string keys consumers may pass directly to
`withConfiguredRule($rector, [<key> => <value>])`. These are the canonical
wire format; the constants above are convenience symbols pointing at them.
Listing the wire keys as first-class API ensures literal-string consumers
get the same semver guarantees as constant-using consumers — a future
constant rename does NOT change the wire key.

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

- `'treat_as_fluent_compatible'` (`array<class-string>`) — same semantics
  as the equally-named key on `SimplifyRuleWrappersRector`.
- `'allow_chain_tail_on_allowlisted'` (`bool`) — same semantics as the
  equally-named key on `SimplifyRuleWrappersRector`.

## Verbose-mode env var

- `FLUENT_VALIDATION_RECTOR_VERBOSE` — env var name. Accepted values:
  `off`, `actionable`, `1`, `true`, `all`. Renames or value-vocabulary
  removal are MAJOR-bump events.

## Skip-log file paths

Two paths, mode-dependent. Both are committed observable contracts.

- **Verbose tier on** (`=1` / `=actionable` / `=all`):
  `<cwd>/.cache/rector-fluent-validation-skips.log`
  (PSR-4 cwd; `.cache/` subdir auto-created; falls back to cwd-root if
  `.cache/` cannot be created).
- **Verbose tier off** (default, env unset):
  `sys_get_temp_dir() . '/rector-fluent-validation-skips-<cwd-hash>.log'`
  (hashed temp path scoped per-cwd; unlinked by `RunSummary` after the
  end-of-run summary line emits, so no artifact persists).

Collapsing both to a single path is a MAJOR-bump event.

## Skip-log line format

```
[fluent-validation:skip] <RectorShortName> <ClassFQCN> (<file-path>): <reason>
```

Slot semantics:

- `[fluent-validation:skip]` — fixed prefix; consumers grep on it.
- `<RectorShortName>` — `(new ReflectionClass($this))->getShortName()`.
- `<ClassFQCN>` — fully-qualified name of the class the skip applies to.
- `(<file-path>)` — path to the source file. Parenthesized,
  single-spaced from the FQCN.
- `:` — colon separator before the reason text.
- `<reason>` — free-text reason. Text content is implementation; do not
  parse beyond the colon.

Removing or rearranging slots, removing parenthesization, changing the
prefix, or inserting new fields between existing slots is a MAJOR-bump
event. Reason text changes are not breaking.

## Skip-log header shape

```
# laravel-fluent-validation-rector <version> — generated <ISO-8601 UTC>
# verbose tier: <off|actionable|all>

```

Each header line starts with `# `, has a colon-separated key/value, and
is followed by a single blank line before entries. Field *values*
(version string, timestamp format, tier vocabulary) are implementation.

## Trait FQNs (referenced by trait-add rectors)

Live in the main `laravel-fluent-validation` package, not this rector
package. Listed here because the rector's behavior is observable via the
inserted traits — renames upstream cascade.

- `SanderMuller\FluentValidation\HasFluentRules`
- `SanderMuller\FluentValidation\HasFluentValidation`
- `SanderMuller\FluentValidation\HasFluentValidationForFilament`
