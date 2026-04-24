# `SimplifyRuleWrappersRector` Extensions

## Overview

Three narrow additions to an existing rector, grouped because they share the "extract a native method from a `->rule(...)` wrapper" shape and will touch the same resolution code path.

1. **Nested FluentRule unwrap**: `->rule(FluentRule::anyOf([...]))` → `->anyOf([...])`.
2. **Single-string rule token**: `->rule('accepted')` → `->accepted()`.
3. **Conditionable-proxy type propagation**: preserve receiver type across `->when($cond, fn (StringRule $r) => ...)` so downstream rewrites fire.

Sources: hihaho 0.12.0 dogfood (items 1, 2, 6). 2 + 2 + 1 hits respectively but the shape recurs across projects.

---

## 1. Current State

### 1a. Nested FluentRule unwrap

`SimplifyRuleWrappersRector` parses `->rule($payload)` by classifying `$payload` as either a string token, a tuple, a Laravel `Rule::*()` call, or an opaque object. A `StaticCall` on `FluentRule` falls into the "opaque object" bucket and hits the default skip — "rule payload not statically resolvable to a v1 shape: StaticCall SanderMuller\FluentValidation\FluentRule::anyOf".

Real case (hihaho): `FluentRule::string()->required()->rule(FluentRule::anyOf([FluentRule::numeric()->integer(), FluentRule::string()->in(['all', 'none'])]))`. The outer `->rule(...)` is the escape hatch; peeling it gives the cleaner `FluentRule::string()->required()->anyOf([...])`.

### 1b. String-token `->rule('accepted')`

`SimplifyRuleWrappersRector::RULE_NAME_TO_METHOD` maps Laravel rule names (`'in'`, `'not_in'`, `'enum'`, etc.) to fluent method names for the tuple-form path (`->rule(['in', ...])`). The string-form path (`->rule('in:a,b')`) already parses pipe-delimited rules and picks up the same table. What's missing: single-token rules with no colon, like `'accepted'`, `'declined'`, `'present'`, `'prohibited'`, `'nullable'`, `'sometimes'`. The current parser treats them as unparseable payloads and logs the new 0.12.0 verbose diagnostic.

### 1c. Conditionable proxy breaking type resolution

`SimplifyRuleWrappersRector::resolveReceiverClass` walks `MethodCall->var` backward until it hits a `StaticCall` on `FluentRule`. When the walk encounters `->when($cond, $callback)`, `->unless(...)`, or `->whenInput(...)`, it currently bails — the return type of `when()` is `HigherOrderWhenProxy`, not the original typed rule, so reflection can't narrow.

Real case (hihaho, `UpdateUserProfileInformation`): `FluentRule::string()->when($bool, fn (StringRule $r) => $r->url())->rule('max:255')`. The outer receiver is still `StringRule` (the proxy round-trips), but the walk can't see through. Result: `max()` rewrite skipped with `"receiver type unknown — Conditionable proxy in chain"`.

---

## 2. Proposed Design

### 2a. Unwrap nested `FluentRule::*()` payloads

Add a new branch to `refactorRuleWrapperCall`:

```php
if ($payload instanceof StaticCall
    && $this->getName($payload->class) === FluentRule::class) {
    $factoryMethod = $this->getName($payload->name);
    if ($factoryMethod === null) return null;

    // Verify the factory method is exposed on the receiver class (e.g. anyOf
    // exists on FluentRuleContract via its HasAnyOf trait, but `string()`
    // wouldn't make sense to unwrap onto another typed rule).
    if (! $this->isMethodAvailable($receiverClass, $factoryMethod)) {
        // verboseOnly skip
        return null;
    }

    return new MethodCall($node->var, new Identifier($factoryMethod), $payload->args);
}
```

Restriction: only unwrap when the payload's factory method is one of the shared-surface methods (`anyOf`, `when`, `unless`). Type-specific factories (`string`, `numeric`) shouldn't unwrap onto other typed receivers — semantics would change.

### 2b. String-token single-rule

Extend `SimplifyRuleWrappersRector::RULE_NAME_TO_METHOD` with single-token rules that map to zero-arg methods:

```php
'accepted' => 'accepted',
'declined' => 'declined',
'present' => 'present',
'prohibited' => 'prohibited',
'nullable' => 'nullable',
'sometimes' => 'sometimes',
'required' => 'required',
'required_array_keys' => 'requiredArrayKeys',  // already queued in ROADMAP
'filled' => 'filled',
'bail' => 'bail',
```

Extend the string-rule parser to treat `'<token>'` (no colon) as `['<token>']` tuple, then run through the existing tuple dispatch. Zero-arg tuples collapse to `->method()` naturally.

### 2c. Conditionable proxy type propagation

**Codex-review blocker (2026-04-24).** Original spec proposed "Path A" stepping through `when`/`unless`/`whenInput` as neutral hops on the assumption that the closure returns the receiver unchanged. That contradicts Laravel's `Conditionable` contract: `when(mixed $value, ?callable $callback = null, ?callable $default = null)` returns `$this|TWhenReturnType`, and when a callback is provided its **return value** propagates. A chain like `FluentRule::string()->when($c, fn () => FluentRule::array())->rule('max:255')` would analyze as `StringRule` under Path A but at runtime the receiver becomes `ArrayRule`. Silent miscompile.

**Revised design — Path B with restricted closure invariant.** Step through the hop only when the closure body provably returns `$this`-equivalent:

1. Closure's body is a single `Return_` statement (or arrow-fn expression).
2. The return expression is either:
   - The closure parameter variable (`fn ($r) => $r`, `fn ($r) => $r->url()`), where the param walks back through chained `MethodCall`s to the original `$r` — common case.
   - A `MethodCall` chain rooted in the closure parameter variable (same receiver, method tail doesn't matter since `->url()` etc. return `$this`).
3. Reject if the return is a `StaticCall`, `New_`, a different `Variable`, a `Ternary`, `Match_`, function call, or a reference to anything other than the closure's own parameter.
4. If no callback is passed (`->when($c)` returning proxy), bail — the proxy's dispatch is dynamic.

Anything failing these invariants falls back to the current bail (`"receiver type unknown — Conditionable proxy in chain"`).

Optional config escape hatch `WALK_THROUGH_CONDITIONABLE_PROXIES: bool` (default `true`) lets paranoid consumers disable the step-through entirely and stay on the pre-0.13 behavior. Useful when their codebase uses custom Conditionable extensions that violate the invariant.

---

## 3. Safety Analysis

### 3a. Nested unwrap correctness

Only unwrap factory methods that exist on both the inner and outer receiver. The `isMethodAvailable` check catches mismatches. `anyOf` / `when` / `unless` are the realistic targets; others would produce semantic regressions if unwrapped.

### 3b. String-token expansion

Zero-arg tokens are semantically identical whether invoked via `->rule('accepted')` or `->accepted()`. Conditionable-like tokens (`sometimes`, `nullable`) already exist as FieldRule methods; no new surface.

### 3c. Conditionable step-through

Path A trusts that `Conditionable::when()` returns the original receiver. Verified against `Illuminate\Support\Traits\Conditionable::when()` — returns `$this` when the callback returns non-proxy. Since our `->when(...)->rule(...)` usage targets the post-`when` receiver, the identity holds.

---

## 4. Fixtures

Add to `tests/SimplifyRuleWrappers/Fixture/`:

- `rule_nested_fluent_anyof.php.inc` — `->rule(FluentRule::anyOf([...]))` → `->anyOf([...])`.
- `rule_nested_fluent_when.php.inc` — `->rule(FluentRule::when(...))` → `->when(...)`.
- `rule_string_token_accepted.php.inc`, `rule_string_token_declined.php.inc`, `rule_string_token_present.php.inc` — zero-arg tokens.
- `conditionable_when_closure_preserves_type.php.inc` — `FluentRule::string()->when($c, fn (StringRule $r) => $r->url())->rule('max:255')` → `...->max(255)` (closure returns receiver via method chain rooted in `$r`).
- `conditionable_unless_nested_when.php.inc` — nested `->unless(...)->when(...)` chain still resolves when both invariants hold.
- `skip_conditionable_closure_returns_different_rule.php.inc` — `->when($c, fn () => FluentRule::array())->rule(...)` skips (closure returns a distinct rule; step-through would miscompile). Codex-review must-have.
- `skip_conditionable_proxy_without_callback.php.inc` — `->when($c)->rule(...)` skips (proxy dispatch is dynamic).
- `skip_conditionable_closure_returns_ternary.php.inc` — `->when($c, fn ($r) => $cond ? $r->url() : FluentRule::array())->rule(...)` skips.
- `skip_nested_fluent_string_factory.php.inc` — `->rule(FluentRule::string())` inside a chain stays wrapped (string factory shouldn't unwrap onto a typed receiver).

---

## 5. Open Questions

1. **Does the 0.12.0 polymorphic-verb diagnostic (shipped in 0.12.1) interact with §2a's unwrap path?** If a user writes `->rule(FluentRule::min(5))` — implausible but possible — the unwrap would produce `->min(5)` on a FieldRule receiver, which triggers the polymorphic-verb hint. Harmless stacking, just document.
2. **Is `->rule('bail')` idiomatic?** Laravel's `bail` is usually a rule-list prefix (`'bail|required|...'`), not a standalone wrapper. Might be over-inclusive. Drop from the token list if peer review flags zero real usage.
3. **Closure-aware path B** — worth spec'ing now, or wait for a real miscompile? Lean wait.

---

## 6. Out of Scope

- `->rule(fn ($attr, $val, $fail) => ...)` inline-closure validators. Correctly stays as escape hatch.
- `->rule(new SomeCustomRule(...))` object wrappers. Covered by a separate spec (factory-rule allowlist).
- Arbitrary Laravel `Rule::*()` tail calls (`Rule::in([...])->where(fn() => ...)`). Complex receiver resolution; out of scope for this extension bundle.
