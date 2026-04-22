# Array-Form `#[Validate([...])]` Attribute Conversion

## Overview

Livewire v3's `#[Validate]` attribute (successor to the deprecated `#[Rule]`)
supports two array-of-rules shapes: a **list-array** (`['required', 'email']`)
and a **keyed-array** (`['todos' => 'required', 'todos.*' => ['min:3']]`).
The current rector only handles the list form; the keyed form is
structurally unsupported and produces garbage output. Independently, every
successful attribute conversion silently regresses real-time validation
because `#[Validate]` triggers validation on every property update while the
generated `rules()` method only fires on explicit `$this->validate()` calls.
This spec closes both gaps and corrects the named-arg coverage against
Livewire v3 source-of-truth.

---

## 1. Current State

### 1.1 What works

`ConvertLivewireRuleAttributeRector::convertAttributeToFluentExpr()`
(src/Rector/ConvertLivewireRuleAttributeRector.php:258-307) branches on the
attribute's first-arg shape:

- `String_` → shared `convertStringToFluentRule()` pipeline.
- `Array_` → `convertArrayAttributeArg()` → shared
  `ConvertsValidationRuleArrays::convertArrayToFluentRule()`.

The array path is wired for **list** shape only. Fixture coverage:

- `attribute_array_simple.php.inc` — `['required', 'string', 'max:255']`
- `attribute_array_nullable_email.php.inc`
- `attribute_array_with_as_label.php.inc` — `as:` named arg
- `attribute_array_with_password_chain.php.inc` — `new Password(8)`
- `attribute_array_with_rule_unique.php.inc`
- `skip_attribute_array_empty.php.inc`

### 1.2 Bug: keyed-array form produces wrong output

Livewire's array syntax for validating sub-keys is documented as:

```php
#[Validate([
    'todos' => 'required',
    'todos.*' => ['required', 'min:3'],
])]
public array $todos = [];
```

`convertArrayToFluentRule()` (src/Rector/Concerns/ConvertsValidationRuleArrays.php:115-211)
iterates `$rulesArray->items` reading only `$arrayItem->value` and ignoring
`$arrayItem->key` entirely. A keyed-map attribute therefore collapses into
a single FluentRule chain built from the concatenated values — losing the
per-key scoping. Result is not a skip-log; it's a **silent wrong conversion**.
The rector then installs the bogus chain as one `propertyName => expr` entry,
dropping the `.*` wildcard key shape that the converter-side counterpart
`ValidationArrayToFluentRuleRector` + `GroupWildcardRulesToEachRector`
pipeline would normally produce.

### 1.3 Bug: stripping `#[Validate]` regresses real-time validation

Livewire v3 semantics (verified against livewire.laravel.com/docs/validation):
`#[Validate]` properties validate on every `wire:model.live` update;
`rules()` method alone only runs on explicit `$this->validate()` /
`validateOnly()`. The rector strips the attribute on every successful
conversion (src/Rector/ConvertLivewireRuleAttributeRector.php:229-237).
Every `#[Validate]`-attributed property in a `wire:model.live` form loses
its on-update validation the moment this rector runs. No skip log, no
diagnostic — the test passes because the generated code type-checks.

Livewire's documented remedy for keeping real-time validation while using
`rules()`: retain an empty `#[Validate]` attribute on the property as a
marker. That marker is what the rector needs to emit.

### 1.4 Named args coverage is incomplete and partly wrong

`describeUnsupportedAttributeArgs()`
(src/Rector/ConvertLivewireRuleAttributeRector.php:386-408) recognizes:

- `message` (singular, string)
- `messages` (plural) — **not a Livewire-documented arg**; this is a
  fabrication in the current code
- `onUpdate` (boolean)

Livewire v3 actually documents (per the validation docs):

| Arg | Type | Notes |
|-----|------|-------|
| `message:` | `string\|array` | String for list form; array-keyed map (`'required' => '...'`) for array form |
| `as:` | `string\|array` | String in list form → `->label()`; array-keyed map in array form |
| `attribute:` | `string\|array` | Custom attribute name — array-keyed map in array form |
| `onUpdate:` | `bool` | Disable on-update validation (default `true`) |
| `translate:` | `bool` | Toggle `trans()` on messages/attributes |

`extractLabelArg()` handles only the string `as:`. Array-form `as:` and
the entire `attribute:` / `translate:` args are unrecognized — they pass
through undetected and unreported. `messages:` is recognized by a name the
framework doesn't accept.

---

## 2. Proposed Changes

The conversion must produce **semantically equivalent** Livewire code, not
just syntactically plausible code. Three correctness pillars:

1. Keyed-array attributes expand into one `rules()` entry per key (wildcards
   preserved).
2. Real-time validation survives conversion when the property relies on it.
3. Named args the rector recognizes match what Livewire actually accepts;
   unrecognized args bail loudly rather than silently.

---

## Implementation

### Phase 1: Fix keyed-array attribute handling (Priority: HIGH) — ✅ shipped

- [x] Detect the keyed-array shape inside `convertArrayAttributeArg()`: if
      any `ArrayItem->key` is a `String_`, route to a new
      `convertKeyedArrayAttribute()` path instead of the list-chain converter
- [x] Expand each keyed entry into its own `property.subkey => FluentRule…`
      entry in the collected rules map. Keys that are pure property names
      merge with the annotated property's own entry; `.` / `.*` keys produce
      additional entries. Reuse `convertArrayToFluentRule()` or
      `convertStringToFluentRule()` for each value based on its shape
- [x] Reject keyed arrays where any value can't convert — skip-log with the
      offending key and leave the whole attribute intact (better a visible
      no-op than partial rules)
- [x] Tests — fixtures for: single keyed entry, property + `.*` wildcard
      pair, value-is-list-array, value-is-string, value-with-rule-object,
      unconvertible value bail
- [x] Post-ship hardening: reject numeric-string keys (`['0' => 'required']`)
      which would synthesise bogus top-level rule entries. Codex adversarial
      review catch, fixture pins the bail.

### Phase 2: Preserve real-time validation semantics (Priority: HIGH) — ✅ shipped

- [x] After conversion, detect whether the property needs real-time
      preservation. Heuristic: the property had `#[Validate]` (not the
      deprecated `#[Rule]`) and no explicit `onUpdate: false` on any of
      its Validate attributes. `onUpdate: false` attributes don't need
      the marker — stripping them is a no-op
- [x] When preservation is needed, retain (or synthesize) an empty
      `#[Validate]` attribute on the property instead of removing all
      attribute groups. Documented Livewire idiom:
      `#[Validate] public string $name = '';`
- [x] Add a config flag `preserve_realtime_validation` (default `true`) so
      consumers whose properties aren't bound to `wire:model.live` can opt
      out of the marker attributes if they find them noisy
- [x] Tests — fixtures for: `#[Validate]` preserved as empty marker,
      `#[Validate(onUpdate: false)]` stripped cleanly, `#[Rule]` deprecated
      form stripped cleanly (no marker), config-disabled mode strips all
- [x] Post-ship hardening: aggregate `onUpdate: false` check across all
      `#[Validate]` attributes on the property (first-wins for rule
      extraction; aggregated veto for marker preservation). Codex adversarial
      review catch, regression fixture pins the multi-attribute case.

### Phase 3: Correct the named-args surface (Priority: MEDIUM) — ✅ shipped

- [x] Remove `'messages'` from `describeUnsupportedAttributeArgs()` — it is
      not a Livewire-accepted arg. If encountered, still warn (user-authored
      typo worth flagging) but drop the "dropped" verb since there's nothing
      to migrate
- [x] Add detection for `attribute:` and `translate:` args. `attribute:`
      in array form maps sub-keys to human-friendly labels — expand into
      per-entry `->label()` calls. `translate: false` has no FluentRule
      equivalent and is rare; skip-log and preserve the `#[Validate]` marker
      (Phase 2 composes here)
- [x] Extend `as:` handling for array-form: accept an array-keyed map and
      apply `->label()` per expanded rule entry, not just to the root
      property
- [x] `message:` array-form handling — deferred to Phase 4 (see Open
      Questions); for now skip-log array-form `message:` with a clear
      pointer to the deferred work
- [x] Tests — fixtures for each recognized arg + a fixture pinning array-form
      `as:` / `attribute:` expansion across wildcard keys

### Phase 4: `message:` migration to `messages(): array` (Priority: LOW) — ✅ shipped

- [x] Opt-in via `MIGRATE_MESSAGES = 'migrate_messages'` config flag
      (default `false`). Mirrors `PRESERVE_REALTIME_VALIDATION` pattern;
      keeps legacy skip-log behaviour intact for consumers who centralize
      messages in lang files
- [x] String `message: 'X'` migrates to a whole-attribute key (`'<prop>'
      => 'X'`) per Livewire's documented behaviour — Laravel matches the
      attribute-only key against any rule failing on that attribute
- [x] Array `message: ['rule' => 'X']` migrates per-rule
      (`'<prop>.<rule>' => 'X'`); keys already containing `.` (full-path
      forms used with keyed-array first-arg attributes) pass through
      verbatim
- [x] `resolveGeneratedRulesVisibility()` reused via composing
      `ResolvesInheritedRulesVisibility` into the new
      `MigratesAttributeMessages` concern — same parent-final-method
      guard fires for both `rules()` and `messages()` generation
- [x] Merge into existing `messages(): array` via Array_ replacement
      (mutating items in place would preserve original printer-token
      positions and collapse merged entries onto a single line; rebuilding
      the Array_ with `NEWLINED_ARRAY_PRINT` keeps multi-line emission)
- [x] `ReportsLivewireAttributeArgs` suppresses the legacy "dropped /
      deferred message:" skip-log lines when the config flag is on, so
      users don't see misleading "manual migration needed" hints for
      entries the rector handled
- [x] Tests — 7 fixtures (codex review surfaced 3 hardening cases on
      top of the original 4): string-message → attribute key, array-
      message → per-rule keys, array-message with full-path keys
      preserved, merge-into-existing path, multi-attribute-first-wins
      (only first attribute's message contributes — matches rule-
      extraction's first-wins contract), non-literal-message keeps
      legacy skip-log (per-attribute granularity via
      `attributeMessageWasMigrated` set), preflight-bail when existing
      `messages()` body isn't a simple `return [...]` (whole-conversion
      aborts so `message:` data isn't lost). New test class
      `ConvertLivewireRuleAttributeMessageMigrationTest.php` + dedicated
      config so the opt-in path runs alongside the default off-path
      coverage in `ConvertLivewireRuleAttributeRectorTest`

---

## Open Questions

1. **Should keyed-array attributes emit wildcard-grouped output?** The
   existing `GroupWildcardRulesToEachRector` folds `'todos.*' => [...]` into
   `FluentRule::array()->each(...)`. Phase 1 could either (a) emit the
   flat `'todos.*'` entries and let the downstream grouping rector pick them
   up on the next pass, or (b) group inline. Option (a) reuses existing
   machinery and keeps this rector single-purpose; option (b) produces
   prettier one-pass output. Recommend (a) unless grouping is reliably
   reachable in the configured set list ordering.

2. **Phase 2 marker attribute: empty `#[Validate]` or `#[Validate(onUpdate: true)]`?**
   Empty is the documented Livewire idiom and clearer diff-wise. Explicit
   is more self-documenting. Both behave identically. Recommend empty.

3. **Phase 4 `message:` migration — opt-in?** Generating a second method
   is a larger transformation than `rules()` alone. Some consumers
   centralize messages in lang files; for them the `messages()` method is
   noise. An opt-in `migrate_messages` config flag is the lowest-surprise
   option. Recommend opt-in, default off.

4. **Multi-attribute aggregation when a property has multiple `#[Validate]`
   attributes?** Current silent "first wins" is the worst option (data loss
   without diagnostic). Concat chains is faithful but can produce
   semantically odd results when the user intended overrides. Recommend bail
   with a skip-log entry; defer concat support until a real consumer asks.
   Not a scheduled phase — address opportunistically when Phase 1 refactors
   the extraction loop.

---

## Findings

<!-- Notes added during implementation. Do not remove this section. -->

- 2026-04-15 — Initial draft had three structural errors caught in codex
  review: (a) missed Livewire's keyed-array syntax entirely, (b) assumed
  `messages:` plural was a real arg and that singular `message:` broadcast
  across all rule tokens, (c) treated real-time-validation regression as a
  LOW-priority edge case on `onUpdate:` rather than a HIGH-priority regression
  on every conversion. Rewrite brings all three into alignment with
  livewire.laravel.com/docs/validation.
- 2026-04-15 — **Phase 1 shipped.** Keyed-array expansion lives in a new
  `ExpandsKeyedAttributeArrays` concern. `extractAndStripRuleAttribute()`
  now returns `?list<{name: ?string, expr: Expr}>` — null name means "use the
  annotated property name" (default single-chain case), explicit names come
  from keyed entries. 5 new fixtures cover: single keyed entry, property +
  `.*` wildcard pair, list-array values, rule-object values, and the
  fails-closed skip-log path for unconvertible values. `as:` / `message:` /
  named-args on keyed-first-arg attributes are skip-logged via the existing
  unsupported-args path — array-form expansion of those stays scoped to
  Phase 3.
- 2026-04-15 — **Phase 2 shipped.** Real-time validation preservation via
  empty `#[Validate]` marker, default on. `ConfigurableRectorInterface`
  wired with `PRESERVE_REALTIME_VALIDATION = 'preserve_realtime_validation'`
  config key (default `true`) so consumers without `wire:model.live` can
  opt out. Decision + insertion live in a new `ResolvesRealtimeValidationMarker`
  concern. `#[Rule]` (deprecated) and `#[Validate(onUpdate: false)]` correctly
  skip the marker. Also extracted `ResolvesInheritedRulesVisibility` concern
  to keep `ConvertLivewireRuleAttributeRector` under the PHPStan cognitive-
  complexity cap. ~11 existing `#[Validate]` fixtures updated to show the
  preserved marker; 3 new regression fixtures pin `onUpdate: false` strips
  cleanly, `#[Rule]` strips without marker, and config-disabled opt-out via
  a separate test class + fixture directory (pattern from prior
  `insteadof`-opt-in test setup).
- 2026-04-15 — **Phase 3 shipped.** `as:` / `attribute:` recognised as
  synonyms for the field display name. String-form on either arg emits
  `->label('…')` on the root chain. Array-form keyed maps applied per-entry
  via an `applyKeyedLabels()` step that runs after keyed-first-arg
  expansion. Label extraction + `logUnsupportedAttributeArgs` moved into
  dedicated `ExtractsLivewireAttributeLabels` and `ReportsLivewireAttributeArgs`
  concerns to keep the host class under the PHPStan complexity cap.

  **`attribute:` wins over `as:` on conflict.** Initial draft of the
  extractor used PHP array-merge semantics, which made the precedence
  source-order dependent: `as: [x => A], attribute: [x => B]` produced B,
  but swapping the arg order produced A. Codex adversarial review caught
  it; fix collects each arg's map separately and merges with `$attributeMap
  + $asMap` (the PHP array-union operator keeps the left operand on
  duplicate keys). Same precedence applies to the string form via
  `extractRootLabel`. Rationale for picking `attribute:`: it matches
  Laravel's own `attribute` naming for custom attribute display names
  (fourth arg to `Validator::make`); `as:` is Livewire's shorter alias.
  Either is correct in isolation; the policy is deterministic and
  independent of source ordering.

  `translate: false` added to the dropped-unsupported-args log (no
  FluentRule equivalent). `messages:` (plural) no longer classified as a
  dropped known arg — it was never a Livewire-documented shape. Emitted
  instead via a dedicated "unrecognized arg; likely typo for `message:`?"
  log line. Array-form `message:` gets its own deferred-to-Phase-4 log
  pointer. Rule conversion continues to run on the first-arg rule payload
  regardless of unsupported named args; only the log-line category changed.

  9 new fixtures: string `attribute:` synonym, array-form `as:` with
  wildcard, array-form `attribute:` with wildcard, `translate: false`
  dropped, `messages:` typo, array-form `message:` deferred, plus three
  regression pins from the codex review (string `as:`/`attribute:` conflict
  in both source orders, array-form conflict). Phase 4 remains queued.
