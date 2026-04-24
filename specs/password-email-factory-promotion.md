# `Password::default()` / `Email::default()` Factory Promotion

## Overview

Promote `FluentRule::string()->...->rule(Password::default())` chains to `FluentRule::password()->...` (and the analogous `Email::default()` → `FluentRule::email()`) when the chain's non-password modifiers are compatible with the typed password receiver.

Source: collectiq 0.12.0 dogfood, 1 hit. Confirmed runtime-safe via peer verification against `vendor/sandermuller/laravel-fluent-validation/src/Rules/PasswordRule.php:37` — `FluentRule::password()` defaults wrap `Password::default()`.

---

## 1. Current State

### 1a. Observed shape (collectiq, `app/Livewire/Pages/SettingsPage.php:296`)

```php
FluentRule::string()
    ->required()
    ->same('newPasswordConfirmation')
    ->rule(Password::default())
```

Three skips fire on this line:
- `SimplifyRuleWrappersRector`: `rule payload not statically resolvable to a v1 shape: StaticCall Illuminate\Validation\Rules\Password::default`.
- `PromoteFieldFactoryRector`: no-op (factory is already `string()`, not `field()`).
- `SimplifyFluentRuleRector`: no-op (no collapsible shortcut).

User intent is clearly "password confirmation with default Laravel password rules". The `FluentRule::password()` factory expresses that directly and runtime-resolves to the same `Password::default()` callback.

### 1b. Runtime semantics

`FluentRule::password()` constructor (defaults=true by default) calls `Password::default()` at runtime, which itself calls `Password::$defaultCallback`. So `Password::setDefault(fn() => Password::min(8)->letters())` elsewhere in the consumer's code is respected identically whether the rule is written as `FluentRule::password()` or `FluentRule::string()->rule(Password::default())`.

Same for `Email::default()` / `FluentRule::email()`.

---

## 2. Proposed Design

### 2a. Extend `PromoteFieldFactoryRector` (not a new rector)

The existing rector already handles factory promotion based on chain analysis. This extension adds a second trigger:

- **Trigger A (existing)**: `FluentRule::field()->rule(...)` chain where wrapper payloads resolve to methods on a single typed rule class. Promote `field()` → typed factory.
- **Trigger B (new)**: `FluentRule::string()->...->rule(Password::default())` chain where `Password::default()` is the *only* Password/Email reference. Promote `string()` → `password()` and drop the `->rule(Password::default())` call.

Promotion table:

| Source factory | `->rule()` call                         | Target factory                 |
|----------------|-----------------------------------------|--------------------------------|
| `string()`     | `Password::default()`                   | `password()`                   |
| `string()`     | `Password::min($n)`                     | `password($n)` (if $n literal) |
| `string()`     | `Email::default()`                      | `email()`                      |

Intermediate chain modifiers (`required`, `nullable`, `same`, `different`, `confirmed`, and any other shared-surface method) survive the promotion unchanged — they're on `FieldRule` base and inherited by `PasswordRule` / `EmailRule`.

### 2b. Safety gates

Reject promotion when:

1. **Source factory has ANY positional args.** `FluentRule::password(?int $min = null, ?string $label = null, bool $defaults = true)` and `FluentRule::email(?string $label = null, bool $defaults = true, ?string $message = null)` do **not** share a signature with `FluentRule::string(?string $label = null)`. A source like `FluentRule::string('Password')->rule(Password::default())` naïvely promoted would become `password('Password')`, silently rebinding `'Password'` from `$label` to `$min`. **Only promote zero-arg source factories in v1.** Structural arg-rebinding deferred. Caught by codex review 2026-04-24.

2. **Method-subset check (Safety Gate #2).** `PasswordRule` does **not** extend `FieldRule`. It uses `HasFieldModifiers` trait, but `same()`, `different()`, `confirmed()` (beyond the built-in password `confirmed` modifier) are declared per-rule on `FieldRule`, `StringRule`, `DateRule`, `EmailRule`, `NumericRule` — **not on PasswordRule**. Naïve promotion of `FluentRule::string()->required()->same('passwordConfirmation')->rule(Password::default())` → `FluentRule::password()->required()->same(...)` triggers `BadMethodCallException` at runtime (collectiq peer review 2026-04-24 verified against `vendor/sandermuller/laravel-fluent-validation/src/Rules/PasswordRule.php`).

   Before promoting, verify that every non-terminal modifier in the source chain resolves to a method available on the target rule class. Implementation:

   - **Option α (preferred, ship v1)**: hardcoded allowlist per target class. Enumerate `PasswordRule` / `EmailRule` public methods at spec-write time; chain method not in the list → bail. Add a snapshot test comparing `get_class_methods()` against the hardcoded allowlist so fluent-validation version bumps that add methods fail loudly instead of silently expanding the promotion surface.
   - **Option β (robust, defer)**: runtime reflection of the target class. Auto-tracks fluent-validation releases. Adds boot-time cost.

3. The chain has any `->rule(...)` call whose payload is *not* the single `Password::default()` / `Email::default()` we're rewriting. Extra wrappers would bind to the typed factory unpredictably (the password factory already bakes in its own ruleset).

4. Conditionable hops (`->when`, `->unless`, `->whenInput`) are in the chain and the closure body references the original receiver type. Defer like `PromoteFieldFactoryRector`.

### 2c. Config

Inherits `PromoteFieldFactoryRector`'s surface. No new config.

---

## Implementation status (2026-04-24)

Shipped as Trigger B inside `PromoteFieldFactoryRector`. Implementation split across the rector's `refactor()` dispatch + a dedicated `Concerns\PromotesPasswordEmailFactory` trait. Tests pass 440 / 768 assertions; PHPStan clean; Rector self-check clean.

- [x] Extend `PromoteFieldFactoryRector::refactor()` with a factory-name dispatch: `field` → Trigger A (existing), `string` → Trigger B (new).
- [x] Add `Concerns\PromotesPasswordEmailFactory` trait housing the Trigger B logic (keeps the rector's cognitive complexity under the 80 threshold).
- [x] Implement Safety Gate #1 (zero-arg source).
- [x] Implement Safety Gate #2 (method-subset check via reflection on target class with per-process cache).
- [x] Implement Safety Gate #3 (exactly one Password/Email rule + no other `->rule()` payloads).
- [x] Implement Safety Gate #4 (Conditionable bail).
- [x] Splice out the matched rule hop + rewire any middle-chain neighbour.
- [x] Rewrite root factory + add `$min` arg when `Password::min(<literal>)`.
- [x] WeakMap-based visited-root guard to prevent inner-fire re-processing after an outer bail.
- [x] Fixture matrix per §4 (7 emit + 11 skip, 18 total — exceeds baseline, includes Codex-flagged edge cases).

### Findings

- **Rector traversal ordering**: Rector visits `MethodCall` nodes in pre-order (outermost fires first), consistent with `AbstractRector::enterNode`. The outer-first fire is the only vantage point from which `collectHopsFromRoot` can see the complete chain, so the WeakMap visited-root marker is load-bearing: inner fires arriving after the outer's decision would otherwise re-process with a truncated view and could splice one of two stacked `->rule(Password::*)` payloads, defeating the double-rule bail.
- **Chain splice**: when the matched hop is the outermost `MethodCall` (most common), return the matched hop's `->var` as the replacement — Rector substitutes the new outermost in place. When the matched hop is in the middle of a longer chain, rewire the hop above it to bypass the spliced node, then return the unchanged outermost.
- **Int_ vs ClassConstFetch `$min`**: peer-flagged widening materialized as a simple two-case accept in `resolvePasswordEmailPayload` — no signature-dependent complication since both node kinds print as valid PHP arg expressions.
- **Trait extraction for complexity**: the in-class implementation pushed `PromoteFieldFactoryRector`'s cognitive complexity from 79 → 87. Extracting Trigger B to `Concerns\PromotesPasswordEmailFactory` cleanly brought it back under the 80 threshold without restructuring logic. The `@phpstan-require-extends AbstractRector` contract on the trait lets it use `$this->getName()` and the Rector-provided methods without a runtime check.
- **Safety Gate #2 needs a divergent-modifier blocklist (Codex evaluate pass, 2026-04-24).** The spec's "method-subset check" via `methodExistsOnClass` is insufficient for `min` / `max`: they exist on both `StringRule` (`int, ?string` — adds a length-check validation rule) and `PasswordRule` (`int` only — mutates the embedded Password builder's min/max chars). Name-only availability passes; promotion silently shifts semantics AND the 2-arg form `->min(20, 'msg')` would TypeError at runtime on the narrower `PasswordRule::min` signature. Fixed with a hardcoded `DIVERGENT_MODIFIER_BLOCKLIST` keyed by target class — `PasswordRule => ['min', 'max']` — checked before the availability probe. `EmailRule::max` has matching signature + semantic to `StringRule::max` and is not blocklisted. Four new fixtures lock this in: `skip_password_promotion_with_string_min_modifier`, `skip_password_promotion_with_string_min_two_arg_form`, `skip_password_promotion_with_string_max_modifier`, `promote_email_with_max_modifier`.

---

## 3. Safety Analysis

### 3a. `Password::min($n)` promotion to `FluentRule::password($n)`

`PasswordRule` constructor takes either `bool $defaults` (true → `Password::default()`) or `int $min` (→ `Password::min($n)`). Accept as first-arg source:

- `Int_` literal (`Password::min(8)` → `password(8)`).
- `ClassConstFetch` (`Password::min(self::MIN_LENGTH)` → `password(self::MIN_LENGTH)`) — static const, emit-safe.

Skip for anything else (`$this->minLength`, `config(...)`, method calls) — the value is valid at runtime but static analyzers lose the integer bound and type-narrowing.

### 3b. `Password::setDefault()` flexibility

Consumers register a `Password::setDefault(Password|(Closure(): Password|Password[]))` callback. Post-promotion, `FluentRule::password()` still calls `Password::default()` internally, which still reads `Password::$defaultCallback`. No runtime flexibility lost. This is the critical safety property collectiq verified.

### 3c. Chains with multiple password-ish rules

`->rule(Password::default())->rule(Password::min(12))` is nonsensical (two password rulesets stacked) but syntactically valid. Skip rather than trying to merge — ambiguous intent.

### 3d. `Email::default()` is rarer

`Illuminate\Validation\Rules\Email` has `::default()` but fewer codebases use it vs `Password`. Promotion still safe; scope the rule to include it for completeness.

---

## 4. Fixtures

Under `tests/PromoteFieldFactory/Fixture/` (extending existing):

- `promote_string_to_password_via_password_default.php.inc` — the exact collectiq shape.
- `promote_string_to_password_via_password_min_literal.php.inc` — `Password::min(8)` literal arg.
- `promote_string_to_email_via_email_default.php.inc` — email analog.
- `promote_preserves_chain_modifiers.php.inc` — `same`, `different`, `confirmed`, `requiredWith` all survive.
- `skip_password_min_dynamic_arg.php.inc` — `Password::min($this->limit)` skips (dynamic arg).
- `skip_double_password_rule.php.inc` — `->rule(Password::default())->rule(Password::min(12))` skips.
- `skip_password_with_conditionable_closure.php.inc` — `->when($c, fn($r) => $r->url())->rule(Password::default())` skips (closure receiver ambiguity).
- `skip_string_factory_with_label_arg.php.inc` — `FluentRule::string('Password')->rule(Password::default())` skips (source factory has positional args — would rebind label to `?int $min` on promotion).
- `skip_chained_password_config.php.inc` — `FluentRule::string()->rule(Password::min(8)->letters()->numbers())` skips (chained password config, not a plain `Password::default()` / `Password::min($n)`).
- `skip_password_min_with_class_const_literal.php.inc` — covers `Password::min(self::MIN)` emit (positive case).
- `skip_string_to_password_with_same_modifier.php.inc` — `FluentRule::string()->required()->same('x')->rule(Password::default())` → **skip** (Safety Gate #2: `same()` absent from `PasswordRule`'s method set). Collectiq must-have.
- `allowlist_snapshot_test.php` — snapshot comparing `get_class_methods(PasswordRule::class)` against the hardcoded allowlist. Fails loudly when a fluent-validation release adds methods.

---

## 5. Open Questions

1. **Should `Password::defaults()` (plural, the static registrar) also trigger promotion?** It's a different API — returns the *default* callback, not a rule. Leave as skip.
2. **Auto-promote `FluentRule::string()->required()->rule(Email::default())` → `FluentRule::email()->required()`** loses Laravel's implicit `string` rule, but `EmailRule` adds `email` which implies `string` at validation time. Net behavior: equivalent. Accept.
3. **Does `FluentRule::password()` inherit `same()` method from `FieldRule`?** Needs verification — if `PasswordRule` doesn't extend `FieldRule`, the `->same('newPasswordConfirmation')` call would break post-promotion. **Blocker to confirm before building.** Most likely inherited via `HasFieldModifiers` trait but must grep.

---

## 6. Out of Scope

- `Rule::password()` (non-existent helper). Laravel exposes the rule via `new Password(...)` / `Password::min(...)` / `Password::default()` — no additional surface.
- PHPStan-time validation that `Password::setDefault` was configured before the rule evaluates. Consumer concern.
- Password-strength auto-tightening (e.g., suggesting `->letters()->numbers()->mixedCase()` when no `->rule(...)` exists). Cosmetic; not a rector's place.
