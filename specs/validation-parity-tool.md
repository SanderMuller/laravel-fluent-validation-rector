# Validation Parity Tool

## Overview

Extend the existing `ValidationEquivalenceTest` runtime-parity harness
to cover the three axes it doesn't assert today: attribute-label
rendering via `Validator::setAttributeNames()`, custom-message
rendering via `Validator::setCustomMessages()`, and lang-file
overrides keyed on validation message paths. Current test already
asserts full error-message-string equality via
`assertSame($s->errors()->messages(), $f->errors()->messages())` for
16 data sets — leaf messages and message-bag keys already covered.
The gap is user-configuration paths the rector's `->label()` emission
interacts with.

---

## 1. Current State

### 1.1 What `ValidationEquivalenceTest` already asserts

`tests/ValidationEquivalenceTest.php:45-49`:

```php
$this->assertSame(
    $stringValidator->errors()->messages(),
    $fluentValidator->errors()->messages(),
    'String and fluent forms produced divergent validation errors',
);
```

`errors()->messages()` returns `array<string, list<string>>` — an
**attribute-only-keyed** map of rendered message strings. Keys are
attribute paths like `email` or `items.0.name`, **not**
`email.required` or `items.0.name.required` — the `.{rule}` suffix
lives in a separate resolution path (`validation.custom.{attribute}.{rule}`
in `FormatsMessages::getCustomMessageFromTranslator`), not in the
bag. `assertSame` against two `messages()` arrays covers:

- **Outcome** (pass/fail) — different error counts per attribute
  fail the assertion.
- **Attribute-path key set** (`email` vs `user.email`, or flat
  `items.*.name` vs index-resolved `items.0.name`) — different
  keys fail the assertion.
- **Leaf message strings** (`The email field is required.` vs
  `The Email field is required.`) — different rendered strings fail.
- **Per-key message ordering** — `assertSame` is order-sensitive on
  both axes; a rule-execution reordering across shapes fails.

It does NOT cover rule-lookup-path parity (`email.required` key
into custom messages or `validation.custom.email.required` into
lang). Those are separate invariants covered by Phases 2 and 3
below.

16 data sets cover presence rules, conditionals, bail, nullable,
sometimes, min/max, in, nested wildcard, boolean. Spread across
rule families the rector emits.

### 1.2 What the existing test does NOT assert

Three gaps where the rector's output interacts with user
configuration paths not exercised by the suite:

- **`Validator::setAttributeNames(['email' => 'E-mail'])` path.**
  Runtime replaces `:attribute` substitution. Pre-rector
  `['email' => 'required']` and post-rector
  `FluentRule::email()->required()->label('E-mail')` must produce
  identical messages when `setAttributeNames` is applied, and must
  also produce identical messages when it is NOT. A rector that
  emits `->label(...)` where the source had none would render
  `The E-mail field is required.` vs `The email field is required.`
  — same outcome, different user-visible copy.
- **`Validator::setCustomMessages(['email.required' => '...'])`
  path.** Rendered-message source changes. Pre/post shapes must
  agree on which message-key the custom message is looked up under.
  Nested-vs-flat output from the grouping rector could, in theory,
  re-key custom messages (`items.*.name.required` vs
  `items.0.name.required`). Not currently tested.
- **Lang-file override path** (`lang/en/validation.php` with
  `validation.custom.email.required`). Similar to setCustomMessages
  but via translator. Additionally exercises `validation.attributes`
  array (`['email' => 'E-mail address']`) which interacts with
  `->label()` emission.

### 1.3 Why this is rector-scope, not main-package scope

The gaps exist because the rector's output surface (especially
`->label()` and the GROUP set's flat→nested shape flip) interacts
with runtime configuration that isn't visible at rector time. The
main package has no "pre-rector" notion, so a parity harness living
there can't compare the two shapes. Lives rector-side.

---

## 2. Proposed Changes

Extend `ValidationEquivalenceTest` with three additional data-provider
columns (or add parallel test methods), each exercising one of the
uncovered configuration paths. Keep the core `assertSame(messages)`
assertion — don't introduce a new comparison framework.

Pattern per new data row:

```php
yield 'required with attribute-rename — labeled' => [
    'data' => ['email' => ''],
    'stringRules' => ['email' => 'required'],
    'fluentRules' => ['email' => FluentRule::email()->required()],
    'attributeNames' => ['email' => 'E-mail address'],
    'customMessages' => [],
    'expectedMessagesContain' => 'E-mail address',
];
```

The test body applies `setAttributeNames($attributeNames)` +
`setCustomMessages($customMessages)` before running
`errors()->messages()` on both validators, then asserts the
`messages()` maps are identical AND that `expectedMessagesContain`
appears somewhere in the rendered output (so a rename that
silently doesn't apply still fails loudly).

## 3. Scope Boundaries

- **In scope:** per-rule-family coverage for presence / conditional
  / comparison / wildcard families. Default English locale. Attribute
  + custom-message overrides via the validator API. Lang-file
  overrides via a `workbench/`-published lang stub.
- **Out of scope (v1):** locale switching beyond `en`. Custom
  rule-object messages implementing `RuleMessageContract`.
  Async/awaitable rules. `DatabaseRule` realtime uniqueness against
  a populated fixture DB (uses sqlite in-memory and asserts the
  query-parameter shape, not live data).
- **Deliberately not re-asserted:** what the existing 16 data sets
  already cover. New data rows layer on top, not replace.

---

## Implementation

### Phase 1: Attribute-name rendering parity (Priority: HIGH)

- [ ] Extend `provideEquivalenceCases()` data provider signature to
      optionally accept `attributeNames: array<string, string>`
      and `customMessages: array<string, string>` (both default to
      `[]` so the existing 16 cases still compile)
- [ ] Test body applies `setAttributeNames` + `setCustomMessages`
      to both the string-form validator and the fluent-form
      validator before calling `errors()->messages()`. Use
      `Illuminate\Support\Facades\Validator::make(...)` (already
      imported) and call the setters on the returned instance
- [ ] Add 6-8 new data rows covering `attributeNames`-interacting
      rule families: email, url, uuid, string+max, integer+min,
      required_with, nullable+email, boolean. Pair each with a
      label-agnostic case (empty `attributeNames`) and a
      label-applied case (`['email' => 'E-mail address']`). The
      label case must NOT be accompanied by a `->label(...)` call
      in the fluent form — the whole point is that
      `setAttributeNames` takes precedence either way
- [ ] Tests — each new data row produces a Pest test case;
      verify they fail when intentionally emitting
      `->label('Overridden')` in the fluent form (a drift regression
      should be caught)

### Phase 2: Custom-message rendering parity (Priority: HIGH)

- [ ] Add data rows that populate `customMessages` with keys in
      both shapes: `'email.required' => 'Provide your email.'`
      (flat) and `'items.*.name.required' => '...'` (wildcard).
      The wildcard case exercises the GROUP set output — the
      grouping rector's `each()` output must still honor the
      wildcard-keyed custom message
- [ ] Tests — pair flat + wildcard with the GROUP-set shape flip;
      intentionally break the wildcard-key parity in a throwaway
      branch to confirm the test catches the regression

### Phase 3: Lang-file override parity (Priority: MEDIUM)

- [ ] Publish a minimal `lang/en/validation.php` stub via
      Testbench's `$this->loadLaravelMigrations` / `$this->app['translator']->addLines(...)`.
      Don't require a real published file under `workbench/lang/`
      — do it in-memory per test to avoid cross-test bleed
- [ ] Add data rows exercising `validation.custom.email.required`
      override and `validation.attributes.email` override. Both
      must produce identical rendered messages pre- and post-rector
- [ ] Tests — the lang-file cases need a `setUp` hook that registers
      the translator lines before each assertion; teardown resets
      them. Reuse the `Orchestra\Testbench\TestCase::setUp`
      lifecycle

### Phase 4: Wildcard bag-path regression fixtures (Priority: MEDIUM)

- [ ] Add data rows asserting **bag attribute-key** parity for the
      GROUP set's nested-shape output. Bag keys are attribute paths
      (no `.{rule}` suffix): pre-rector flat `items.*.name` rules
      produce a failing-attribute bag key of `items.0.name`;
      post-rector `each()` output must produce the same
      `items.0.name` bag key. The existing `array with typed
      children — items.* nested` case proves this for one shape;
      add 3-5 more for deeper nesting (`foo.*.bar.*.baz`),
      multi-sibling wildcards, and mixed keyed + wildcard at the
      same depth. The assertion is `assertSame` on
      `errors()->messages()` — if the post-rector output bags under
      a different attribute path (e.g. `items.0` with the leaf
      message folded upward, or `items` as a single-entry with the
      whole child error inlined), `assertSame` fails
- [ ] For rule-lookup-path parity (`validation.custom.items.*.name.required`
      and similar), route through Phase 2's custom-message fixtures
      — a wildcard-keyed custom message registered via
      `setCustomMessages(['items.*.name.required' => '...'])` must
      render identically regardless of whether the source was flat
      or grouped. That assertion observes the rule-lookup path
      indirectly via the rendered string
- [ ] Tests — each nested-depth variant; failures surface if
      Laravel's collapse-wildcard-to-index logic changes in a
      framework upgrade

### Phase 5: CI + contributor docs (Priority: LOW)

- [ ] Parity assertions run as part of the default `run-tests`
      workflow — no new workflow needed, the existing test file
      is already in the default suite
- [ ] README contribution section points new rule authors at the
      parity data provider: "If your rule emits `->label(...)` or
      changes error-bag key shape, add a data row to
      `provideEquivalenceCases()` covering the
      `setAttributeNames` + `setCustomMessages` interaction"
- [ ] Tests — N/A (docs only)

---

## Open Questions

1. **Should `assertSame` stay order-sensitive on per-key message
   lists?** Laravel's message order is execution-order, stable
   within a shape but not guaranteed across shapes. `assertSame`
   currently catches reordering; an opt-in `assertParityOrderAgnostic()`
   variant might reduce false positives on legitimate
   reorderings. Recommend keep `assertSame` — reorderings are a
   real signal (users relying on `errors()->first()` care).
   Revisit if a real reordering surfaces that is NOT a regression.

2. **`DatabaseRule` parity.** Testbench's sqlite in-memory can
   host a scratch table for uniqueness/exists parity, but the
   fixture cost is non-trivial (migration + seed). Scope out of
   v1; add if a consumer files.

3. **Lang-file stub vs `workbench/` publish.** In-memory translator
   mutation (`$app['translator']->addLines(...)`) is simpler but
   doesn't exercise Laravel's full lang-file resolution (which
   falls back across published/vendor paths). Recommend in-memory
   for v1; a `workbench/lang/` stub is Phase 6 if needed.

4. **Parallel-runner state bleed.** Pest's parallel runner shares
   the Testbench application state across workers within the same
   process. Translator mutations (`setAttributeNames`,
   `addLines`) must reset in `tearDown`, or Pest's
   `beforeEach`/`afterEach` equivalents. Flag in the
   contributor-docs README section added in Phase 5.

---

## Findings

<!-- Notes added during implementation. Do not remove this section. -->
