# `laravel-fluent-validation` 1.19.0 Surface Support

## Overview

Main-package release 1.19.0 (unreleased at time of writing — tag
coordinated with this spec by peer `91mf9wnj`) adds eleven new
`FluentRule::*` static factories, a new `DeclinedRule` class, and
four `NumericRule` sign-helper methods. Each creates rewrite
opportunities across three existing rectors:

1. **`ValidationStringToFluentRuleRector`** / **`ValidationArrayToFluentRuleRector`**
   — map Laravel's string/array-form rule tokens directly to the new
   factories (`'ipv4'` → `FluentRule::ipv4()`, not `FluentRule::string()->ipv4()`).
2. **`SimplifyFluentRuleRector`** — extend `FACTORY_SHORTCUTS` to
   collapse already-fluent chains (`FluentRule::string()->ipv4()` →
   `FluentRule::ipv4()`).
3. **`SimplifyRuleWrappersRector`** — extend rewrite targets to
   include `enum`, and map the literal-zero comparison tokens
   (`'gt:0'`, `'gte:0'`, `'lt:0'`, `'lte:0'`) to the new sign
   helpers.

Single spec because all three axes share the same 1.19.0 surface
delta; splitting would force three reviews of overlapping
justification.

---

## 1. New Surface (verified against vendored 1.19.0)

### 1.1 Static factories on `FluentRule`

All `?string $label` on the last parameter — label-preservation via
factory-arg substitution already handled by
`SimplifyFluentRuleRector`'s `LABEL_FIRST_FACTORIES` list.

| Factory                     | Returns       | Chain-collapse from                     |
|-----------------------------|---------------|-----------------------------------------|
| `ipv4(?string $label)`      | `StringRule`  | `FluentRule::string()->ipv4()`          |
| `ipv6(?string $label)`      | `StringRule`  | `FluentRule::string()->ipv6()`          |
| `macAddress(?string $label)`| `StringRule`  | `FluentRule::string()->macAddress()`    |
| `json(?string $label)`      | `StringRule`  | `FluentRule::string()->json()`          |
| `timezone(?string $label)`  | `StringRule`  | `FluentRule::string()->timezone()`      |
| `hexColor(?string $label)`  | `StringRule`  | `FluentRule::string()->hexColor()`      |
| `activeUrl(?string $label)` | `StringRule`  | `FluentRule::string()->activeUrl()`     |
| `regex(string $pattern, ?string $label)` | `StringRule` | `FluentRule::string()->regex($pattern)` |
| `list(?string $label)`      | `ArrayRule`   | `FluentRule::array()->list()`           |
| `enum(string $type, ?Closure $cb, ?string $label)` | `FieldRule` | `FluentRule::field()->enum(...)` |
| `declined(?string $label)`  | `DeclinedRule` (new class) | N/A — no prior chain form |

**`ip()` stays as-is.** Pre-1.19.0 factory; not a synonym for
`ipv4`/`ipv6` (accepts both). Rector must NOT collapse `ip()` to
any of the new factories.

### 1.2 `NumericRule` sign helpers

| Method         | Maps from Laravel token |
|----------------|-------------------------|
| `positive()`   | `'gt:0'`  (strictly > 0) |
| `negative()`   | `'lt:0'`  (strictly < 0) |
| `nonNegative()`| `'gte:0'` (>= 0)        |
| `nonPositive()`| `'lte:0'` (<= 0)        |

Only literal-zero mapping. Non-zero comparison tokens (`'gt:5'`,
`'gt:other_field'`) stay out-of-scope — leave to existing logic /
escape-hatch. Peer confirmation: match strictly on literal `0`
(optionally `'0'`, `0.0`).

### 1.3 `enum()` is on `HasEmbeddedRules`

`HasEmbeddedRules::enum(string $type, ?Closure $callback): static`
already shipped pre-1.19.0; 1.19.0 adds the `FluentRule::enum(...)`
static as a shortcut. Classes exposing `->enum()`: `StringRule`,
`NumericRule`, `EmailRule`, `DateRule`, `FieldRule`. Classes NOT
exposing it: `ArrayRule`, `BooleanRule`, `AcceptedRule`,
`DeclinedRule`, `ImageRule`, `FileRule`, `PasswordRule`.

Receiver-type-aware rewrite: `SimplifyRuleWrappersRector` can map
`Rule::enum(X::class)` → `->enum(X::class)` on the 5 allowed
receivers; others fail the existing method-availability check and
skip-log per the existing pipeline.

---

## 2. Proposed Changes

### 2.1 String/array-to-fluent token mapping

`ValidationStringToFluentRuleRector` and
`ValidationArrayToFluentRuleRector` currently map tokens like
`'string'` → `FluentRule::string()`, `'email'` → `FluentRule::email()`
via the shared `ConvertsValidationRuleStrings::buildFluentRuleFactory()`.
Extend that factory-name resolution with the 1.19.0 additions.

Token recognition matrix:

| Laravel token (snake_case) | Fluent factory (camelCase) |
|----------------------------|-----------------------------|
| `ipv4`                     | `ipv4`                      |
| `ipv6`                     | `ipv6`                      |
| `mac_address`              | `macAddress`                |
| `json`                     | `json`                      |
| `timezone`                 | `timezone`                  |
| `hex_color`                | `hexColor`                  |
| `active_url`               | `activeUrl`                 |
| `list`                     | `list`                      |
| `declined`                 | `declined`                  |

Snake→camel mapping lives in the same `normalizeRuleName()` helper
the strings concern already uses. Add entries; no structural change.

Interaction with the existing `'string'` / `'array'` prefix tokens:

- Source `['string', 'ipv4']` (array form) or `'string|ipv4'` (string
  form) currently produces `FluentRule::string()->ipv4()`. Post-change,
  the rector should recognize the `'ipv4'` component as a factory-
  replacement candidate and emit `FluentRule::ipv4()` directly,
  skipping the redundant `->string()->ipv4()` detour.
  **Caveat:** only valid when `'string'` has no other siblings that
  would also need to be factory-promoted (e.g. `['string', 'ipv4',
  'max:255']` → `FluentRule::ipv4()->max(255)` — safe because
  `ipv4` returns `StringRule`, and `StringRule` has `->max(int)`).
- Source `'array|list'` → `FluentRule::list()`. Same logic with
  `array` + `list`.

### 2.2 Chain-collapse factory shortcuts

`SimplifyFluentRuleRector::FACTORY_SHORTCUTS` already handles the
`string()->url()` → `url()` collapse. Extend:

```php
private const array FACTORY_SHORTCUTS = [
    'string' => [
        'url' => 'url',
        'uuid' => 'uuid',
        'ulid' => 'ulid',
        'ip' => 'ip',
        // 1.19.0 additions:
        'ipv4' => 'ipv4',
        'ipv6' => 'ipv6',
        'macAddress' => 'macAddress',
        'json' => 'json',
        'timezone' => 'timezone',
        'hexColor' => 'hexColor',
        'activeUrl' => 'activeUrl',
        'regex' => 'regex',
    ],
    'numeric' => ['integer' => 'integer'],
    // 1.19.0 addition:
    'array' => ['list' => 'list'],
];
```

**Label preservation** already works via the existing
`LABEL_FIRST_FACTORIES` list — `string('Name')->ipv4()` collapses to
`ipv4('Name')` because `string` is in the label-first list and the
factory-shortcut transform transfers the factory args. Verify: add
`ipv4`, `ipv6`, `macAddress`, `json`, `timezone`, `hexColor`,
`activeUrl`, `regex`, `list` to `LABEL_FIRST_FACTORIES` so the
collapsed output can accept a label arg on the new factory.

**`regex` and `enum` carry required method arguments that the
existing shortcut mechanism doesn't handle, AND collide with the
label-preservation flow.** The current `FACTORY_SHORTCUTS` transform
at `src/Rector/SimplifyFluentRuleRector.php:180` gates on
`$method['args'] === []` — zero-arg methods only. Two issues:

**Issue 1: arg-passthrough is missing.** Adding `regex => regex`
and `enum => enum` to the map is not sufficient; the transform
needs to promote the method's args to factory args.

**Issue 2: the `label() → factory arg` pattern runs AFTER shortcuts
(line 202 vs line 176), so any arg-carrying shortcut would drop
chained labels.** Concrete example:

```php
FluentRule::string()->label('Code')->regex('/\d+/')
// Current simplifyChain order:
//   1. Shortcut pass: string()->regex() sees `regex` with 1 arg →
//      consumes the method, emits FluentRule::regex('/\d+/')
//      with NO label arg. The ->label('Code') call is still in
//      $methods.
//   2. label() → factory arg pass: would promote ->label('Code')
//      to factory arg, BUT the factory now has non-empty args
//      (['/\d+/']) — gate at line 202 `$factory['args'] === []`
//      prevents promotion. Label silently LOST.
```

**Fix: reorder `simplifyChain` to run label-promotion BEFORE
factory-shortcut collapse.** Rationale: label-promotion only fires
when the factory is arg-less (same as the arg-carrying shortcut
pre-condition), so swapping order is safe; after reorder:

1. Label pass runs first: `string()->label('Code')->regex(...)` →
   `string('Code')->regex(...)` (label attaches to factory).
2. Shortcut pass: factory now has args `['Code']`, so the
   arg-carrying regex/enum shortcut gate should be
   `$factory['args'] === []` → doesn't fire.
3. No label loss.

But that still doesn't produce `regex('/\d+/', 'Code')` — the label
stays on `string` and the regex shortcut can't fire. For the
`string()->label('Code')->regex(...)` case to collapse all the way
to `FluentRule::regex('/\d+/', 'Code')`, need a dedicated
label-preserving regex/enum transform that knows how to thread the
label into the right positional slot (`regex($pattern, $label)`,
`enum($type, $cb, $label)`). Complex; scope out of v1.

**V1 policy:** arg-carrying shortcut fires ONLY when `$factory['args']
=== []` AND no `label()` method exists anywhere in the chain. If a
label is present, leave the chain alone and let the user write
`FluentRule::regex(...)->label(...)` manually if they want the
factory-arg form. Simpler invariant; no silent label loss.

```php
private const array FACTORY_SHORTCUTS_WITH_ARGS = [
    'string' => ['regex' => 'regex'],
    'field' => ['enum' => 'enum'],
];

// In simplifyChain, AFTER the existing label-promotion pass:
if (isset(self::FACTORY_SHORTCUTS_WITH_ARGS[$factory['name']])
    && $factory['args'] === []
    && ! $this->chainHasLabelCall($methods)) {
    // ... promote method args to factory args ...
}
```

Fixture plan must include: no-change pin for
`string()->label('Code')->regex(...)` (confirm not collapsed),
no-change pin for `string()->regex(...)->label('Code')`
(label-after-regex — existing label-promotion can't fire because
factory will get args from shortcut... actually same concern;
re-verify after reorder), and transform pin for plain
`string()->regex(...)` → `regex(...)`.

### 2.3 `SimplifyRuleWrappersRector` extensions

Two changes:

**Add `enum` to v1 rewrite targets.** Allowlist receivers per §1.3:
String / Numeric / Email / Date / Field. Input shapes:

- `Rule::enum(X::class)` — `Illuminate\Validation\Rules\Enum`
  facade call. Single required arg (enum class-string).
- `['enum', X::class]` — array form. Arity 1.
- `'enum:...'` string form? Laravel's string form for enum is
  typically `Rule::enum(...)` wrapped via `Validator::make`'s
  array handling, not a pipe-delimited string. Skip the string-
  form shape for `enum` in v1; add only Rule:: facade + array
  form.

**Add literal-zero comparisons.** Extend `RULE_NAME_TO_METHOD` and
per-family parsing:

```php
private const array RULE_NAME_TO_METHOD = [
    // ... existing ...
    'gt' => 'positive',        // only when arg === 0 (see gating below)
    'gte' => 'nonNegative',
    'lt' => 'negative',
    'lte' => 'nonPositive',
];
```

The mapping is conditional: only fires when the arg is the literal
`0` / `'0'` / `0.0`. Anything else (variable, non-zero literal,
another field name, `'00'`, `'-0'`) skips — default escape-hatch.

**Gating must happen on the raw token text BEFORE
`literalForToken()` normalizes.** `literalForToken` converts every
digit-only token to `Int_((int)$token)`, so `'00'` → `Int_(0)` and
`'-0'` → `Int_(0)` post-normalization. A post-parse AST-node check
(`$arg->value instanceof Int_ && $arg->value->value === 0`) would
fire on all three, broader than the intended exact-zero match.

For `parseStringRule` (token form like `'gt:0'`): inspect the
post-`:` substring directly. Accept exactly `'0'` and `'0.0'`
(whitespace-trimmed), reject everything else.

For `parseArrayRule` (array form like `['gt', 0]`): inspect
`$item->value` before any coercion. Accept `Int_` with
`value === 0`, `Float_` with `value === 0.0`, `String_` with
`value === '0'` or `'0.0'`. Reject `String_('00')`,
`String_('-0')`, `Int_(0)` produced by `literalForToken('-0')`
(so don't route array-form through `literalForToken` for the
gt/gte/lt/lte path).

Receiver allowlist for sign helpers: `NumericRule` only.
`SimplifyRuleWrappersRector`'s existing `methodAcceptsFloat()` and
reflection allowlist handle the receiver gate naturally — `positive`
/ `negative` / `nonNegative` / `nonPositive` exist only on
`NumericRule`.

---

## Implementation

### Phase 1: Chain-collapse shortcuts in `SimplifyFluentRuleRector` (Priority: HIGH) — ✅ shipped

- [x] Extended `FACTORY_SHORTCUTS` with 8 new string→string-rule
      zero-arg entries + 1 new `array → list` entry
- [x] Added `FACTORY_SHORTCUTS_WITH_ARGS` constant + transform branch
      for `regex` / `enum`. Two-part gate: `$factory['args'] === []`
      AND `chainHasLabelCall($methods) === false`. Label-preservation
      via positional-slot threading is out of v1 scope; conservative
      gate prevents silent label loss
- [x] `chainHasLabelCall(list<array>): bool` helper added
- [x] Extended `LABEL_FIRST_FACTORIES` with the 10 new factories
      (`ipv4`, `ipv6`, `macAddress`, `json`, `timezone`, `hexColor`,
      `activeUrl`, `regex`, `list`, `declined`). Excluded `enum`
      (label is third positional)
- [x] Extended `REDUNDANT_ON_FACTORY` so `FluentRule::ipv4()->ipv4()`
      drops the redundant zero-arg call (same for the 7 other new
      string-returning factories + `list`)
- [x] Tests — 5 fixtures: zero-arg shortcuts across all 9 factories,
      label-promotion through factory shortcut, regex pattern-arg
      passthrough, enum passthrough, and the no-change skip pin for
      arg-carrying shortcut + label-in-chain (gate fires; chain
      partially collapses via existing label-promotion only)

### Phase 2: Token→factory recognition in converters (Priority: HIGH) — ✅ shipped

- [x] Extended `TYPE_MAP` in `ConvertsValidationRuleStrings` with 9
      new tokens (`ipv4`, `ipv6`, `macAddress`, `json`, `timezone`,
      `hexColor`, `activeUrl`, `list`, `declined`). `normalizeRuleName`
      already maps the snake-case sources (`mac_address` → `macAddress`,
      `hex_color` → `hexColor`, `active_url` → `activeUrl`)
- [x] Added `TYPE_PROMOTING_MODIFIERS` constant + post-loop promotion
      in `convertStringToFluentRule` for `string|ipv4`-style sibling-
      token merging — the converters now emit `FluentRule::ipv4()`
      directly, no verbose detour through `FluentRule::string()->ipv4()`
- [x] Mirrored the promotion in
      `ConvertsValidationRuleArrays::convertArrayToFluentRule` so
      array-form (`['string', 'ipv4', 'max:255']`) gets the same
      treatment. Tracked the superseded type-token index so Pass 2
      drops the now-redundant `'string'` slot instead of wrapping it
      in `->rule('string')` escape hatch
- [x] `declined` lands as `FluentRule::declined()` via the standalone
      `TYPE_MAP` entry; existing `SIMPLE_MODIFIERS` membership stays
      as a no-op fallback (TYPE_MAP wins ordering)
- [x] Tests — 3 new fixtures: standalone-tokens-string-form (9 cases),
      sibling-token-promotion-string-form (5 cases incl. `array|list`),
      array-form-equivalent (3 cases). Existing 97 converter tests
      still pass

### Phase 3: `enum` + sign helpers in `SimplifyRuleWrappersRector` (Priority: HIGH) — ✅ shipped

- [x] Added `'enum'` and `'positive'`/`'negative'`/`'nonNegative'`/
      `'nonPositive'` to `V1_REWRITE_TARGETS`
- [x] Extended `RULE_NAME_TO_METHOD`: `enum`/`gt`/`gte`/`lt`/`lte`
      → corresponding native methods. Gating happens at the parse
      branch (literal-zero check) before name lookup
- [x] `parseRuleFacadeCall` arity-1 enforcement extended to `enum`
      (Laravel's `Rule::enum(string $type, ?Closure $cb = null)` —
      callback can't be threaded; bail multi-arg)
- [x] `parseArrayRule` arity match arm includes `enum` (single arg);
      sign helpers handled in dedicated branch with raw-AST literal-
      zero check (`isLiteralZeroArrayValue`)
- [x] `parseStringRule` sign-helper branch gates on
      `isLiteralZeroToken` over the RAW token text (`'0'` or `'0.0'`
      only — refuses `'00'`, `'-0'`, `'+0'`). Returns `[]` (zero-arg
      call) on success
- [x] Sign-helper rewrite emits `->positive()` etc. as zero-arg
      calls (no positional args)
- [x] Tests — 5 fixtures: enum-Rule-facade-on-string (transform),
      enum-array-form-on-field (transform), skip-enum-on-disallowed-
      receivers (Array/Boolean/File don't have `enum()` via
      HasEmbeddedRules), sign-helpers-on-numeric (currently no-change
      pin — see Findings), skip-sign-helpers-non-zero-or-field
      (`gt:5`, `gte:other_field`, `gt:00`, `gt:-0`, array-form
      non-zero, `string()->rule('gt:0')`)

### Phase 4: Release notes + README (Priority: MEDIUM) — ✅ shipped

- [x] **composer.json bump to `^1.19`** — landed once the upstream
      1.19.0 tag was cut. Sign-helper transform fixture
      (`tests/SimplifyRuleWrappers/Fixture/sign_helpers_on_numeric.php.inc`)
      flipped from no-change pin to transform shape; reflection-based
      method-availability check in `SimplifyRuleWrappersRector::bootResolutionTables`
      now finds the new methods on the resolved typed-rule subclass
- [ ] **DEFERRED — `RELEASE_NOTES_<next>.md`.** Per pre-release skill:
      release notes are written after the implementation commit is
      pushed AND CI is green AND any composer-coordination commit
      lands. The implementation can ship on `^1.17` (Phase 1 + 2 +
      most of 3 work today against vendored 1.17.x; sign-helper
      transform fixture is no-change-pinned per the spec's Phase 3
      finding). Final release notes draft happens in the
      coordination commit when 1.19.0 lands
- [x] README updated — one-line callout under SIMPLIFY description
      about the 1.19.0 surface additions across CONVERT + SIMPLIFY
- [x] `SetListTest` unchanged — no new rule registration (all three
      rector extensions live in already-registered rectors). All
      pre-existing fixtures + the new 1.19.0 fixtures pass against
      vendored 1.17.x except the sign-helper transform pin (which
      is intentionally a no-change pin until the floor bump)

### Phase 5: CI matrix validation (Priority: LOW) — verified post-bump

- [x] CI run on the coordination commit (composer floor bump) confirms
      the `--prefer-lowest` matrix cell resolves to fluent-validation
      1.19.0 cleanly. No `.github/workflows/run-tests.yml` changes
      were needed — `--prefer-lowest` reads from `composer.json`'s
      floor automatically
- [x] Tests — N/A (CI configuration only)

---

## Open Questions

1. **Composer floor: bump to `^1.19` or lazy-gate?** Bumping is
   simpler and matches the rector's semver story (new-surface
   support = new major-or-minor). Lazy-gating keeps the rector
   usable on mixed-version consumer codebases but complicates
   the allowlist bootstrap. Recommend bump — `laravel-fluent-validation`
   1.19.0 is already the way forward; mixed-version consumers can
   pin the rector to an older major if they can't upgrade the
   main package.

2. **Should `declined` string-form recognition fire on
   `'declined_if:...'` / `'declined_when:...'` too?** Those are
   conditional-declined rules with different semantics. Current
   main package's `DeclinedRule` is unconditional; conditional
   variants stay on their existing conditional-presence route via
   `->declinedIf(...)`. Recommend only recognize bare `'declined'`
   → `FluentRule::declined()`; leave conditional variants alone.

3. **`enum` string form.** Laravel supports `'enum:'` string shape
   but the class-string would be a raw string in the rule token
   (`'enum:App\\Enums\\Status'`). Parsing a class-string out of a
   rule string has escape-handling complexity (backslashes). Skip
   in v1; add if demand surfaces.

4. **`gt`/`gte`/`lt`/`lte` on Rule:: facade?** Laravel's `Rule::`
   doesn't expose these as static factories (they're string-only
   rule tokens in Laravel). So the Rule:: input shape in
   `SimplifyRuleWrappersRector` doesn't need to grow for the sign
   helpers — string and array forms only.

5. **Label preservation through the token-level converters.**
   Source `['string', 'ipv4']` has no label. Source
   `['string' => 'My Field', 'ipv4']` is malformed (keyed entry in
   a list-shape array). If a user writes
   `FluentRule::string('My Field')->ipv4()` manually, the
   simplifier collapses to `FluentRule::ipv4('My Field')`. Decide
   whether the converter phase should attempt to infer labels from
   sibling attribute-names arrays. Recommend no — label inference
   is a separate feature, not in 1.19.0 surface scope.

---

## Findings

<!-- Notes added during implementation. Do not remove this section. -->

- 2026-04-22 — **Codex review surfaced 2 cross-spec hardening fixes
  applied alongside this spec's Phase 1-3:** (a) array-form sibling-
  token promotion was not source-aware — when type came from a
  `Rule::string()->alpha()` chain rather than a plain `'string'`
  token, promotion would supersede the type-index slot and silently
  drop the chain ops (`alpha()`). Added `typeKind` tracking in
  `detectArrayRuleType`; promotion now requires `typeKind === 'string_token'`.
  (b) Phase 4 message-migration anchored on first PRESENT Livewire
  attribute, not first CONVERTIBLE — orphaning later-attribute
  message: args when an earlier attribute had a non-literal first
  arg. Added `attributeIsConvertibleShape` predicate matching
  `extractAndStripRuleAttribute`'s skip-on-non-convertible
  semantics. Two regression fixtures pin both behaviors. The third
  codex finding (messages() preflight too lax — accepted any return-
  array anywhere) led to extracting `trivialReturnArrayMethodBody`
  shared between `canInstallMessagesMethod` (preflight) and
  `mergeIntoExistingMessagesMethod` (mutation), enforcing exactly
  one top-level Return_(Array_) with no other executable stmts.
  Multi-return fixture pins the preflight bail.
- 2026-04-22 — **Phase 3 sign-helper transform fixture is gated on
  vendored fluent-validation version.** The reflection-based
  method-availability check in `SimplifyRuleWrappersRector::bootResolutionTables`
  inspects the vendored fluent-validation copy. With the floor at
  `^1.17` and 1.19.0 not yet tagged, `NumericRule` doesn't yet
  expose `positive`/`negative`/`nonNegative`/`nonPositive`. The
  `sign_helpers_on_numeric.php.inc` fixture is therefore a no-change
  pin (rector skip-logs because the methods aren't on the resolved
  class), with an inline comment documenting the post-1.19.0 flip
  to a transform fixture. `enum` is a separate story — it landed
  on `HasEmbeddedRules` pre-1.19.0, so the enum fixtures are
  full transform fixtures and verify against the current vendor.
  The sign-helper IMPLEMENTATION is complete (parsing, gating,
  emit); only the test-runtime verification awaits the
  composer-floor bump in Phase 4.
