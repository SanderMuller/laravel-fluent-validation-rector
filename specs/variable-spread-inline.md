# Variable-Spread Inline for `InlineResolvableParentRulesRector`

## Overview

Extend `InlineResolvableParentRulesRector` to resolve `...$base` spread when `$base` is statically traceable to a variable assignment whose value is a literal `Array_`. Current rector handles only `...parent::rules()`.

**Status: speculative, hold until multi-repo demand signal surfaces.**

mijntp peer review (ynu0t0kb) 2026-04-24 grepped mijntp: zero files match the top-level `$base = [...]` + `return [...$base, ...]` pattern. Mijntp's 2 observed hits are `...collect(...)->mapWithKeys(...)->all()` — dynamic, NOT what this spec handles. Ship only if collectiq or hihaho grep turns up real cases. Until then, this is a design-complete doc so the implementation is trivially scaffoldable when demand arrives.

---

## 1. Current State

`InlineResolvableParentRulesRector` (shipped 0.12.0) walks child `rules()` method for an `ArrayItem` with `unpack = true` at index 0. If the value is a `StaticCall` on `parent` with method `rules`, it:

1. Resolves parent class via reflection.
2. Parses parent file AST, caching by `path + mtime`.
3. Finds parent's `rules()` ClassMethod, checks it has a single `Return_` with a literal `Array_`.
4. Merges parent array items into child array, respecting child's later keys (child wins on conflict per PHP spread semantics).

Other spread shapes (variable, method call, function call at index 0 or elsewhere) fall through to `UpdateRulesReturnTypeDocblockRector` / converter rectors as a spread-at-value item, which they skip with "encountered spread at index N".

---

## 2. Proposed Design

### 2a. New resolver path: variable-spread

Extend `InlineResolvableParentRulesRector` with a second resolver:

```php
if ($unpackedValue instanceof Variable) {
    $varName = $this->getName($unpackedValue);
    $resolvedArray = $this->resolveVariableToArrayLiteral($method, $varName);
    if ($resolvedArray instanceof Array_) {
        // inline as before
    }
}
```

`resolveVariableToArrayLiteral(ClassMethod $method, string $varName)`:

1. Walk `$method->stmts` for an `Expression(Assign($varName, $rhs))`.
2. If exactly one assignment found and `$rhs` is an `Array_` literal, return it.
3. If zero, multiple, or `$rhs` is anything else (function call, method call, concatenation), return null.
4. If the assignment is AFTER the return statement, return null.

### 2b. Scope gates

**Codex-review constraint (2026-04-24).** "Single assignment, precedes return, source order" is insufficient — a single assignment nested in `if`, `match`, `foreach`, `try/catch`, `while`, or any control-flow scope is still "unique + earlier" but may not execute at runtime. Inlining `...$base` on a path where `$base` was never assigned would fabricate parent rules on that path. Gate must require the assignment to **dominate** the return, not merely exist and precede it.

Practical approximation of dominance (no full CFG needed):

1. Assignment is a **top-level statement** in `$method->stmts` — directly a child of the method body, not nested in any `If_`, `Foreach_`, `For_`, `While_`, `Do_`, `Switch_`, `Match_`, `TryCatch`, or `Else_` stmt.
2. Variable assigned **exactly once** via a top-level statement.
3. Assignment RHS is a literal `Array_` (or recursively resolvable to one per §2c).
4. Top-level-assignment precedes the return statement in source order.
5. Variable is used exactly once in the method (in the spread). If used elsewhere too, inline would duplicate behavior.
6. No `unset($base)` or by-reference binding anywhere in the method.

Nested assignments (even if "the only one") are rejected with verbose-only log:

- `"variable-spread source $foo reassigned N times at top level, cannot inline"`
- `"variable-spread source $foo assigned inside <stmt kind> (if/foreach/try/etc.) — assignment does not dominate return, cannot inline"`
- `"variable-spread source $foo assigned from non-literal expression, cannot inline"`
- `"variable-spread source $foo used in multiple positions, cannot inline"`

### 2c. Interaction with parent::rules() path

Variable may itself be assigned from `parent::rules()`:

```php
public function rules(): array
{
    $base = parent::rules();
    return [
        ...$base,
        'foo' => 'required',
    ];
}
```

Resolver recurses: `$base` assignment RHS is a `StaticCall` on parent → fall through to the parent::rules() resolver on that RHS → inline the parent's array literal.

### 2d. Caching

Existing cache (`path + mtime`) still applies for parent::rules() resolution. Variable-spread resolution is per-method, no file-level cache needed.

---

## 3. Safety Analysis

### 3a. Single-assignment requirement

Multiple assignments (`$base = [...]; if ($c) $base = [...];`) would inline the first literal, losing the conditional branch. Single-assignment gate ensures behavior parity.

### 3b. Method-call RHS stays unresolvable

`$base = $this->baseRules();` or `$base = collect(...)->all();` cannot be statically resolved to an array literal without walking the method body. Skip. Covers mijntp's actual 2 hits which are dynamic collection pipelines — those remain skips.

### 3c. Scope analysis

Variable-use count requires a light walk of the method body. Use `NodeFinder::findInstanceOf` for `Variable` nodes and filter by name. Cheap.

### 3d. Multi-statement method bodies

The rector's current parent::rules() path requires single-Return_ method. This spec relaxes that — variable assignments + a single return stmt becomes the allowed shape. Still reject multi-return methods.

---

## 4. Fixtures

Under `tests/InlineResolvableParentRules/Fixture/`:

- `inline_variable_spread_literal_array.php.inc` — `$base = ['a' => 'required']; return [...$base, 'b' => 'required'];`.
- `inline_variable_spread_from_parent_rules.php.inc` — `$base = parent::rules(); return [...$base, 'foo' => '...'];` — two-step resolution.
- `skip_variable_spread_multiple_assignments.php.inc` — `$base` reassigned in an if branch.
- `skip_variable_spread_method_call_rhs.php.inc` — `$base = $this->baseRules();`.
- `skip_variable_spread_used_elsewhere.php.inc` — `$base` also passed to another function before spread.
- `skip_variable_spread_assigned_after_return.php.inc` — assignment post-return (unreachable but AST-valid).
- `skip_variable_spread_assigned_in_if_block.php.inc` — `if ($c) { $base = [...]; } return [...$base];` — non-dominating assignment (codex must-have).
- `skip_variable_spread_assigned_in_foreach.php.inc` — assignment inside `foreach` loop body.
- `skip_variable_spread_assigned_in_match_arm.php.inc` — assignment inside `match` expression arm.
- `skip_variable_spread_assigned_in_try.php.inc` — assignment inside `try`/`catch` block.

---

## 5. Open Questions

1. **Should the resolver handle variable-of-variable spread** (`$a = [...]; $b = $a; return [...$b];`)? Two-step trace. Probably diminishing returns — one-hop covers the real cases.
2. **Is multi-statement method support coupled to this?** Current parent::rules() path requires single-return. Variable-spread requires pre-return assignments. Generalize the single-stmt rule to "single Return_, optional pre-return Expression(Assign)s" — would also need to hold for the variable-use analysis.
3. **Should the skip-reason include the variable name** (`variable-spread source $base ...`)? Leans yes — helps users identify the offending assignment in verbose logs.

---

## 6. Out of Scope

- Collection-pipeline spread resolution (`...collect(...)->all()`). Would require modeling the collection API's runtime behavior. Not tractable statically.
- Property-spread (`...$this->baseRules`). Requires cross-class state flow analysis.
- Variable-variable spread (see OQ 1).
- Runtime-conditional arrays (`$base = $flag ? [a] : [b]; return [...$base];`). Conservative skip.

---

## Implementation status (2026-04-24)

**Shipped.** User opted in despite mijntp's zero-match grep — harness is cheap and the rector is a pure upgrade (preserves current behavior when it can't resolve).

- [x] `extractReturnArray` relaxed: accepts single-`return` (strict) and N-top-level-`Expression(Assign)`-then-return (relaxed). Strict mode kept for the recursive parent-side parse.
- [x] `resolveSpreadSource` dispatcher — `parent::rules()` → parent resolver, `Variable` → `resolveVariableSpread`, anything else → null (leave spread intact).
- [x] `ResolvesVariableSpread` trait (`src/Rector/Concerns/ResolvesVariableSpread.php`) — holds the 5 helpers to keep the rector under PHPStan's cognitive-complexity threshold.
- [x] Dead-assign strip after variable-spread inline — `stripDeadVariableSpreadAssign` removes the single `$var = ...;` stmt so impure RHS (method calls, `new`, etc.) don't evaluate twice.
- [x] Parent resolver rewrite — switched from PHPStan scope (`$scope->getClassReflection()`) to native `ReflectionClass` walking from `$class->extends`, and FQCN-based class matching in `loadParentRulesMethod` instead of short-name.
- [x] 13 fixtures: 2 inline (literal + `parent::rules()` two-step), 1 inline (impure RHS with `$this->computeLimit()` — proves dead-assign strip), 10 skip (multi-assign / method-call RHS / used-elsewhere / post-return / if / foreach / match-arm / try / closure-capture).

### Findings

- **PHPStan scope returns null for non-autoloaded classes (blocker).** The original `resolveParentRulesItems` used `$scope->getClassReflection()` which worked for `InlineParentRulesPlainLiteral` but silently returned null for every other fixture. Existing fixtures hid this by being skip-tests: a null child reflection bailed to a no-op, which matched the single-section fixture's expected "no change". Fix: use native `ReflectionClass` walking from `$class->extends`. Side benefit — now works in consumer codebases where the child lives outside the composer autoloader's scanned roots.
- **Codex HIGH: impure-RHS double-evaluation.** First implementation left the `$base = ['k' => $this->compute()]` assignment in place and inlined a CLONE of the array items, causing `compute()` to evaluate twice at runtime. Fixed by `stripDeadVariableSpreadAssign` — safe because the `countVariableReferences(...) === 2` gate already guaranteed the assignment was single-use.
- **Codex MEDIUM: multi-namespace same-short-name collision.** `loadParentRulesMethod` previously matched by short name only; a legal multi-namespace file could return the wrong class. Fixed by tracking the active namespace through a `Namespace_` walk and matching on FQCN (`$ns . '\\' . $short`) in `findClassByFqcn`. Pre-existing short-name behavior would also have been wrong under scope-mode; the switch-plus-fix eliminates both paths.
- **Codex HIGH (second pass): peer-assignment reordering.** The dead-assign strip from the first pass fixed double-evaluation but could still reorder side effects when OTHER top-level assigns sit around the spread source. Example: `$a = sideA(); $base = sideBase(); $b = sideB(); return [...$base];` — after stripping `$base`, the inlined items' RHS (`sideBase`) runs AFTER `sideB()` instead of between `sideA` and `sideB`. Fixed by tightening the gate: `countTopLevelAssignments === 1` required, so the spread source is the only assignment in the body. Added `skip_variable_spread_peer_assignment.php.inc` fixture.
- **Codex LOW: closure-capture reference counting.** Added `skip_variable_spread_closure_capture.php.inc` to prove the gate's `!= 2` arm handles `use ($base)` captures correctly. Destructuring writes (`[$base] = ...`) and `unset($base['k'])` paths are implicitly handled — the former appears as a Variable node (counted), the latter doesn't appear at all (unset detects only the scalar-variable case, which is the intended behavior).
- **`NodeFinder` import removed** — replaced with a manual `matchClassInStmts` recursion that tracks namespace context. Was not reachable via `NodeFinder::findFirst` alone.

### Tests

- 505 tests / 863 assertions / 0 failures (+14 new fixtures in `tests/InlineResolvableParentRules/Fixture/`).
- Pint clean. PHPStan 0 errors (after extracting `ResolvesVariableSpread` trait to keep class complexity under 80). `vendor/bin/rector process` 0 self-changes.
