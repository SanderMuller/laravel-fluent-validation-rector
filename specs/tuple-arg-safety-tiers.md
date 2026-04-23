# Tuple Arg Safety — Widen Acceptance for Dynamic Expressions

## Overview

`ValidationArrayToFluentRuleRector` currently bails on any rule array that contains a tuple with a "complex" arg — `Ternary`, `MethodCall`, `FuncCall`, `PropertyFetch` (not on a ClassConstFetch), etc. The restriction is historical — the `isSafeTupleArg` whitelist was narrow enough to only accept statically-scalar expressions. But both emit paths (fluent-method lowering and the `->rule([...])` escape hatch) can safely preserve any PHP expression that evaluates to a rule param at runtime — the user's existing array form already trusts the expression. Widen the acceptance to convert rules that currently bail purely because of a dynamic-but-valid arg.

---

## 1. Current State

### 1a. `isSafeTupleArg` whitelist (`ConvertsValidationRuleArrays.php:1132-1173`)

Accepts only:
- `String_`, `Int_`, `Float_`
- `ConstFetch` (`true`, `false`, `null`)
- `Variable`
- `Concat` (recursive)
- `ClassConstFetch`
- `PropertyFetch` on a `ClassConstFetch` with `name === 'value'` (added in 0.10.0 for `Enum::CASE->value`)

Anything else — `Ternary`, `MethodCall`, `FuncCall`, `PropertyFetch` on `$this`, `NullsafePropertyFetch`, `Match_`, etc. — falls through to `return false`, which propagates through `allTupleArgsSafe` → `classifyArrayTuple` → whole rule array bails.

### 1b. Why the restriction was too conservative

Two downstream guards already exist and cover the real risks:

1. **`tupleHasForeignClassConst` (`:552`)** — prevents the `->rule([...])` escape hatch from hitting Laravel's `implode(',', $params)` with an unstringifiable BackedEnum case. This is the only implode-at-runtime concern; other expressions resolve to scalars at runtime.
2. **Existing variadic signature gates (0.10.0)** — the signature-aware spread gate ensures spread args only reach variadic-compatible fluent methods.

The narrow whitelist, by contrast, rejects expressions that are already safely handled by the existing guards.

### 1c. Motivating case in the wild

From `hihaho/app/Http/Requests/Enrich/SaveInteractionRequest.php:102-106`:

```php
self::INTERACTIONS . '.*.attributes.chapters' => [
    ['required_if', self::INTERACTIONS . '.*.type', InteractionType::CHAPTER->value],
    'array',
    ['max', $this->video()->orientation?->isLandscape() ? 15 : 20],
],
```

The sibling `required_if` tuple + Enum-value arg converts cleanly on 0.10.0. The whole rule bails only because the `['max', <ternary>]` tuple has a `Ternary` arg. `->max($ternary)` is perfectly valid PHP — the ternary evaluates to `15` or `20` at `rules()` construction time.

Same shape appears across any FormRequest that uses `->video()->has...` or runtime-config values in rule params.

---

## 2. Proposed Changes

### 2a. Split into two predicates — strict and permissive

The existing `isSafeTupleArg` whitelist serves two distinct consumers, which warrant different safety bars:

- **COMMA_SEPARATED conditional rules** (`requiredIf`/`excludeUnless`/...): the fluent signature is overloaded — `Closure|bool|string $field` for arg 1, variadic `string|int|bool|BackedEnum ...$values` for the rest. A dynamic expression evaluating to a closure/bool/object at runtime would take a different branch than the field-comparison the source array-tuple encoded. Keep the strict whitelist here; no widening.
- **Fluent-method lowering** (`['max', $n]` → `->max($n)`) and **`->rule([...])` escape hatch**: no overload ambiguity, just runtime propagation of the expression. Safe to widen to a blacklist as long as non-scalar-capable and object-producing shapes are explicitly rejected (otherwise `->max([1,2])` or `->max(fn() => 5)` would emit and surface a different failure mode than the array form).

Add a second predicate, `isEmittableTupleArg`, used only on the non-COMMA_SEPARATED emit paths:

```php
/**
 * Permissive safety check for tuple args that will be emitted as-is
 * into a fluent method call or ->rule([...]) escape hatch. Rejects
 * shapes that can't plausibly evaluate to a scalar/BackedEnum rule
 * param, either because they produce objects, arrays, or callables
 * (which would break Laravel's implode/serializeValues on the array
 * form and typed fluent signatures), or because they don't return a
 * value at all.
 */
private function isEmittableTupleArg(Expr $expr): bool
{
    // Non-scalar-producing shapes: bail unconditionally.
    if ($expr instanceof New_
        || $expr instanceof Clone_
        || $expr instanceof Closure
        || $expr instanceof ArrowFunction
        || $expr instanceof Array_
        || $expr instanceof Yield_
        || $expr instanceof YieldFrom
        || $expr instanceof Throw_
        || $expr instanceof Include_
        || $expr instanceof Eval_
        || $expr instanceof Assign
        || $expr instanceof AssignOp
        || $expr instanceof PreInc
        || $expr instanceof PostInc
        || $expr instanceof PreDec
        || $expr instanceof PostDec) {
        return false;
    }

    // Concat needs recursive inspection — 'x' . new Obj() must bail,
    // 'foo.' . $var must pass.
    if ($expr instanceof Concat) {
        return $this->isEmittableTupleArg($expr->left) && $this->isEmittableTupleArg($expr->right);
    }

    // Everything else — Ternary, MethodCall, FuncCall, StaticCall,
    // PropertyFetch, NullsafePropertyFetch, NullsafeMethodCall,
    // ArrayDimFetch, Cast, Match_, ClassConstFetch, etc. — resolves
    // to a runtime value the user already trusts in the array form.
    return true;
}
```

Rejection list rationale:
- `New_`, `Clone_`: produce objects.
- `Closure`, `ArrowFunction`: produce callables — overload ambiguity in fluent methods plus `implode()` failure at runtime.
- `Array_`: produces array — typed fluent methods like `->max(int)` would TypeError at a different point than the array form's Laravel parser.
- `Yield_`, `YieldFrom`, `Throw_`, `Include_`, `Eval_`: don't return scalar values (or don't return at all).
- `Assign`, `AssignOp`, `PreInc/PostInc/PreDec/PostDec`: side-effectful; presence in a rule arg is user error and rewriting could change when the side effect fires.

### 2b. Routing in `classifyArrayTuple`

Preserve current behavior for COMMA_SEPARATED; add permissive check on the non-conditional paths:

```php
// (spread gate unchanged)

// COMMA_SEPARATED arm — strict check (no widening; overload ambiguity)
if (in_array($ruleName, self::COMMA_SEPARATED_ARGS_RULES, true)
    && $this->isModifierValidForType($type, $ruleName)) {
    if (! $this->allTupleArgsSafe($tuple)) {
        // Existing strict whitelist failed — bail the COMMA_SEPARATED
        // attempt. (Note: current code runs allTupleArgsSafe before
        // the COMMA_SEPARATED branch; move it inside so strict-fail
        // doesn't short-circuit the permissive-emit paths below.)
        // Fall through to fluent-lowering / escape hatch.
    } else {
        // emit ->requiredIf(...), etc. as today
        return new MethodCall(...);
    }
}

// Defense-in-depth spread bail (unchanged)
if ($hasSpread) return null;

// tupleHasForeignClassConst (unchanged) — still guards implode path
if ($this->tupleHasForeignClassConst($tuple)) return null;

// Permissive check for the fluent-lowering and escape-hatch paths
if (! $this->allTupleArgsEmittable($tuple)) return null;

// Fluent-lowering arm (unchanged)
if ($this->isModifierValidForType($type, $ruleName)) { ... }

// Escape hatch (unchanged)
return $this->wrapInRuleCall($expr, $tuple);
```

Key change: `allTupleArgsSafe` moves from the top of `classifyArrayTuple` into the COMMA_SEPARATED arm only. Non-COMMA tuples that previously bailed because of a strict-whitelist miss now get a second chance via the permissive check.

### 2c. No change to `isSafeTupleArg`, `adaptEnumArg`, or `tupleHasForeignClassConst`

- `isSafeTupleArg` (`:1132`) stays as the strict whitelist for COMMA_SEPARATED.
- `adaptEnumArg` (`:1184`) only wraps bare `ClassConstFetch`. Permissive path doesn't change its behavior.
- `tupleHasForeignClassConst` (`:552`) still guards Laravel's `implode()` from seeing a BackedEnum case. Runs before the permissive check.

### 2d. Expected transforms

```php
// Before (bails on 0.10.0)
'chapters' => [
    ['required_if', 'type', InteractionType::CHAPTER->value],
    'array',
    ['max', $this->video()->orientation?->isLandscape() ? 15 : 20],
],

// After
'chapters' => FluentRule::array()
    ->requiredIf('type', InteractionType::CHAPTER->value)
    ->max($this->video()->orientation?->isLandscape() ? 15 : 20),
```

```php
// Before (bails on 0.10.0)
'role' => [
    'nullable',
    'string',
    ['max', $this->config('max_role_length')],  // ← bailed: FuncCall arg
],

// After (covered by this spec via permissive path)
'role' => FluentRule::string()
    ->nullable()
    ->max($this->config('max_role_length')),
```

### 2e. Still bails (intentional)

```php
// Object/array/callable-producing args — preserve original failure mode
'x' => [['max', new SomeObj()]]
'x' => [['max', clone $obj]]
'x' => [['max', [1, 2]]]
'x' => [['max', fn() => 5]]
'x' => [['max', function () { return 5; }]]

// COMMA_SEPARATED with dynamic arg — strict whitelist still applies
// (overload ambiguity: Closure|bool|string $field)
'x' => [['required_if', 'type', $this->complexMethod()]]

// ClassConstFetch on foreign BackedEnum in escape-hatch path —
// tupleHasForeignClassConst still bails here
'x' => [['unknown_rule', SomeEnum::CASE]]
```

---

## Implementation

- [x] Add `isEmittableTupleArg(Expr $expr): bool` in `src/Rector/Concerns/ConvertsValidationRuleArrays.php` using the blacklist form from §2a.
- [x] Add `allTupleArgsEmittable(Array_ $tuple): bool` mirroring `allTupleArgsSafe` but dispatching to `isEmittableTupleArg`. Skip unpack items.
- [x] Restructure `classifyArrayTuple` per §2b: strict check moved INTO the COMMA_SEPARATED arm; `allTupleArgsEmittable` added between `tupleHasForeignClassConst` and the fluent-lowering arm.
- [x] Verify `isSafeTupleArg` stays unchanged — COMMA_SEPARATED keeps its strict whitelist.
- [x] Verify `adaptEnumArg` still correctly passes non-`ClassConstFetch` through unchanged.
- [x] Added PHP-Parser node imports: `Clone_`, `Yield_`, `YieldFrom`, `Throw_`, `Include_`, `Eval_`, `Assign`, `AssignOp`, `PreInc`, `PostInc`, `PreDec`, `PostDec` (others already present).
- [x] Tests — new positive fixture `tests/ValidationArrayToFluentRule/Fixture/tuple_dynamic_args.php.inc` covering Ternary, MethodCall, FuncCall, Nullsafe, PropertyFetch, Match, StaticCall, ArrayDimFetch, Cast, and the motivating chapters shape.
- [x] Tests — new negative fixture `tests/ValidationArrayToFluentRule/Fixture/skip_tuple_object_or_callable_arg.php.inc` covering New_, Clone_, ArrowFunction, Closure, Array_, Assign, PostInc, Concat-with-hidden-New.
- [x] Tests — new fixture `tests/ValidationArrayToFluentRule/Fixture/skip_comma_separated_strict_still.php.inc` confirming COMMA_SEPARATED with dynamic args falls through to `->rule([...])` escape hatch (not `->requiredIf(...)`). Closure-in-field-position still bails entirely.
- [x] Update `RELEASE_NOTES_0.10.1.md` — motivating case + blacklist breakdown + COMMA_SEPARATED behavior note.

---

## Open Questions

None.

---

## Resolved Questions

1. **Should `isSafeTupleArg` be widened via blacklist (reject only `New_`) for all paths including COMMA_SEPARATED?** **Decision:** No. Split into two predicates: keep `isSafeTupleArg` strict (unchanged, used only by COMMA_SEPARATED), add a new permissive `isEmittableTupleArg` for fluent-lowering and escape-hatch paths. **Rationale:** COMMA_SEPARATED fluent methods are overloaded — `requiredIf(Closure|bool|string $field, ...)` takes different branches depending on arg type, so a dynamic expression evaluating to a closure/bool at runtime would silently switch from field-comparison to conditional-rule semantics. Codex review surfaced this. The permissive path has no such overload ambiguity.

2. **Should the permissive predicate's blacklist be `New_`/`Clone_` only, or broader?** **Decision:** Broader — also reject `Closure`, `ArrowFunction`, `Array_`, `Yield_`, `YieldFrom`, `Throw_`, `Include_`, `Eval_`, `Assign`, `AssignOp`, `PreInc`, `PostInc`, `PreDec`, `PostDec`. **Rationale:** These expressions produce objects, callables, arrays, or don't return scalar values — reaching typed fluent methods (`->max(int)`) or Laravel's `serializeValues()` with any of them would surface a different failure mode than the array form. Codex review flagged this gap in the original blacklist.

3. **Should `Match_` expressions be accepted?** **Decision:** Accept. **Rationale:** Same theoretical risk as `Ternary` (multi-branch return can yield non-scalars), but the existing array form already trusts the match result at runtime. Rector preserves intent; branch-type-correctness is the user's responsibility.

4. **Should `Concat` containing `new Obj()` (or another blacklisted shape) bail or allow?** **Decision:** Bail via recursion. **Rationale:** Object `__toString()` behavior is lossy and varies; `'prefix-' . new Obj()` is almost always user error. Recursing through `Concat` in `isEmittableTupleArg` makes the blacklist transitive without duplicating entries.

## Findings

- **Defense-in-depth: COMMA_SEPARATED blocked from generic fluent-lowering path.** When the strict whitelist fails for a COMMA_SEPARATED rule, the tuple falls through to the fluent-lowering arm. That arm calls `buildModifierCallFromTupleExprArgs(type, 'requiredIf', ...)`, which would happily emit `->requiredIf($dynamicArg)` — bypassing the overload-ambiguity guard the strict check was supposed to enforce. Added an explicit `! in_array($ruleName, self::COMMA_SEPARATED_ARGS_RULES, true)` guard on the fluent-lowering entry. COMMA_SEPARATED with unsafe args now only takes the `->rule([...])` escape hatch, preserving the array-form's runtime semantics. Spec §2b didn't call this out; caught during test run.

- **Existing `array_conditional_tuples.php.inc` needed update.** The `dynamic_max` row (`['max', $this->getMaxValue()]`) previously stayed as-is; now it converts to `->rule(['max', $this->getMaxValue()])` via the escape hatch on `field` type (where `max` isn't a valid typed method). Expected result updated — widening coverage is the point of this spec.

- **Removed obsolete negative cases from `skip_tuple_spread_unsafe.php.inc`.** The `InteractionType::PAUSE->name` and dynamic `->$x` cases were negative tests for PropertyFetch narrowness in the strict whitelist. With the permissive path now accepting them into the escape hatch, they no longer bail — which is the intended behavior (strict `->value`-only narrowness still applies to COMMA_SEPARATED lowering, just not to the escape hatch). Removed those two rows to keep the fixture focused on its actual subject (spread handling).

- **Rector's import-shortening touched fixture class references.** Initial fixture used `\stdClass` (global), which Rector's namespace-import pass shortens to `stdClass`. Switched to a non-global fixture class (`App\Widgets\Gadget`) to avoid the shortening side effect during testing.

- **Closure in field position (`['required_if', fn() => true]`) bails entirely.** Strict whitelist fails (Closure not whitelisted), permissive blacklist rejects Closure, escape-hatch path bails. The whole rule array for that key stays as-is. This is the strongest guarantee — Closure in any tuple arg position can't reach either emit path. Locked in by case 'c' of the strict-still fixture.
