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

### Phase 1: Fix keyed-array attribute handling (Priority: HIGH)

- [ ] Detect the keyed-array shape inside `convertArrayAttributeArg()`: if
      any `ArrayItem->key` is a `String_`, route to a new
      `convertKeyedArrayAttribute()` path instead of the list-chain converter
- [ ] Expand each keyed entry into its own `property.subkey => FluentRule…`
      entry in the collected rules map. Keys that are pure property names
      merge with the annotated property's own entry; `.` / `.*` keys produce
      additional entries. Reuse `convertArrayToFluentRule()` or
      `convertStringToFluentRule()` for each value based on its shape
- [ ] Reject keyed arrays where any value can't convert — skip-log with the
      offending key and leave the whole attribute intact (better a visible
      no-op than partial rules)
- [ ] Tests — fixtures for: single keyed entry, property + `.*` wildcard
      pair, value-is-list-array, value-is-string, value-with-rule-object,
      unconvertible value bail

### Phase 2: Preserve real-time validation semantics (Priority: HIGH)

- [ ] After conversion, detect whether the property needs real-time
      preservation. Heuristic: the property had `#[Validate]` (not the
      deprecated `#[Rule]`) and no explicit `onUpdate: false` on any of
      its Validate attributes. `onUpdate: false` attributes don't need
      the marker — stripping them is a no-op
- [ ] When preservation is needed, retain (or synthesize) an empty
      `#[Validate]` attribute on the property instead of removing all
      attribute groups. Documented Livewire idiom:
      `#[Validate] public string $name = '';`
- [ ] Add a config flag `preserve_realtime_validation` (default `true`) so
      consumers whose properties aren't bound to `wire:model.live` can opt
      out of the marker attributes if they find them noisy
- [ ] Tests — fixtures for: `#[Validate]` preserved as empty marker,
      `#[Validate(onUpdate: false)]` stripped cleanly, `#[Rule]` deprecated
      form stripped cleanly (no marker), config-disabled mode strips all

### Phase 3: Correct the named-args surface (Priority: MEDIUM)

- [ ] Remove `'messages'` from `describeUnsupportedAttributeArgs()` — it is
      not a Livewire-accepted arg. If encountered, still warn (user-authored
      typo worth flagging) but drop the "dropped" verb since there's nothing
      to migrate
- [ ] Add detection for `attribute:` and `translate:` args. `attribute:`
      in array form maps sub-keys to human-friendly labels — expand into
      per-entry `->label()` calls. `translate: false` has no FluentRule
      equivalent and is rare; skip-log and preserve the `#[Validate]` marker
      (Phase 2 composes here)
- [ ] Extend `as:` handling for array-form: accept an array-keyed map and
      apply `->label()` per expanded rule entry, not just to the root
      property
- [ ] `message:` array-form handling — deferred to Phase 4 (see Open
      Questions); for now skip-log array-form `message:` with a clear
      pointer to the deferred work
- [ ] Tests — fixtures for each recognized arg + a fixture pinning array-form
      `as:` / `attribute:` expansion across wildcard keys

### Phase 4: `message:` migration to `messages(): array` (Priority: LOW)

- [ ] Only after Phase 1-3 are shipped and stable. Design depends on the
      keyed-array expansion landing first because the `field.rule` message
      keys need the expanded entry names
- [ ] Decide: always-on, or opt-in behind `migrate_messages` config
      (mirrors `AddHasFluentValidationTraitRector::FILAMENT_CONFLICT_RESOLUTION`
      pattern)
- [ ] Implement for the two documented shapes: string `message:` in list
      form (attaches to the single attribute's rules) and array `message:`
      in array form (keyed by rule name or `key.rule`)
- [ ] Generalize `resolveGeneratedRulesVisibility()` to resolve visibility
      for any generated method name (`rules`, `messages`, `validationAttributes`)
- [ ] Tests — fixtures for each `message:` shape + a merge-into-existing-
      `messages()`-method path

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
  Phase 3. Phases 2–4 remain queued.
