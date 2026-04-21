# SimplifyRuleWrappersRector

## Overview

Rewrites escape-hatch `->rule(...)` calls into native fluent methods on typed
FluentRule subclasses so chains read as first-class fluent API rather than
strings/`Rule::` facade detours. v1 covers the highest-volume validation
families — `in`, `notIn`, `min`, `max`, `between`, `size`, `regex` — bounded
by what exists natively in `laravel-fluent-validation` v1.17.1. Receiver-type
inference is the load-bearing piece: each rewrite must prove the resolved
typed-rule subclass actually exposes the target method before swapping.

**Naming-rename note:** Laravel's `size:N` rule corresponds to fluent-validation's
`exactly(N)` method (intentional rename for clarity, see
`vendor/sandermuller/laravel-fluent-validation/src/Exceptions/TypedBuilderHint.php:24-25`).
The rewrite preserves the rename: `->rule('size:N')` → `->exactly(N)`. It is
*not* a 1:1 method-name mapping like the others.

---

## 1. Existing Surface in `laravel-fluent-validation` v1.17.1

Native methods live on typed subclasses, not on `FluentRuleContract` or the
base `FluentRule`. Verified signatures:

| Method     | Subclass(es)                                                              | Trait source           |
|------------|---------------------------------------------------------------------------|------------------------|
| `in`       | StringRule, NumericRule, EmailRule, FieldRule, DateRule                   | `HasEmbeddedRules`     |
| `notIn`    | StringRule, NumericRule, EmailRule, FieldRule, DateRule                   | `HasEmbeddedRules`     |
| `min`      | StringRule (`int`), NumericRule (`int\|float`), ArrayRule (`int`), FileRule (`int\|string`), PasswordRule (`int`) | direct on subclass |
| `max`      | StringRule (`int`), NumericRule (`int\|float`), ArrayRule (`int`), FileRule (`int\|string`), PasswordRule (`int`), EmailRule (`int`) | direct on subclass |
| `between`  | StringRule (`int,int`), NumericRule (`int\|float,int\|float`), ArrayRule (`int,int`), FileRule (`int\|string,int\|string`), DateRule (`DateTimeInterface\|string,DateTimeInterface\|string`) | direct on subclass |
| `regex`    | StringRule (`string`)                                                     | direct on subclass     |
| `exactly`  | StringRule (`int`), NumericRule (`int`), ArrayRule (`int`), FileRule (`int\|string`) | direct on subclass — Laravel's `size:` renamed |

Notes:
- Laravel's `size:N` → fluent's `exactly(N)`. Confirmed by
  `TypedBuilderHint::for('size')` in laravel-fluent-validation, which exists
  precisely to surface this rename when users hit a missing-method runtime.
  The rector treats `'size:N'` strings as inputs that rewrite to
  `->exactly(N)` on the four classes above; on PasswordRule/EmailRule/
  DateRule/BooleanRule it skip-logs.
- Argument types differ by subclass (`FileRule::min` accepts `int|string`
  for `'2mb'` shorthand; `StringRule::min` is `int`-only). The rector emits
  the literal AST node from the source `'min:2mb'` token verbatim — runtime
  validates the type, the rector does not.
- `EmailRule` is missing `min` and `between` and `exactly`. Asymmetric; not
  a bug.

## 2. Inputs the Rector Must Recognise

Three escape-hatch shapes feed `->rule(...)`:

### 2.1 String form
```php
->rule('in:a,b,c')
->rule('min:3')
->rule('between:1,5')
->rule('size:64')
->rule('regex:/^\d+$/')
```
**Parsing is local to this rector**, not delegated. The
`ConvertsValidationRuleStrings` trait's parsing helpers (`parseRulePart`,
`buildModifierCall`, `wrapInRuleEscapeHatch`) are private and tightly
coupled to the FormRequest `rules()`-method conversion flow — pulling them
into a `MethodCall`-driven rector would require a refactor disproportionate
to the parsing surface needed here. Instead, ship a small focused splitter:
`explode(':', $s, 2)` for the name, `explode(',', $tail)` for `in`-family
args, `substr($s, 6)` for `regex:` (the pattern itself can contain `:` and
`,` — must not split). This keeps the new rector self-contained and avoids
destabilising the FormRequest pipeline.

### 2.2 Array form
```php
->rule(['in', 'a', 'b', 'c'])
->rule(['min', 3])
->rule(['between', 1, 5])
->rule(['size', 64])
```
Same parsing-locality policy. Iterate `Array_->items` directly: index 0 is
the rule name (must be `String_`), remainder are the args.

### 2.3 `Rule::` facade form
```php
->rule(Rule::in(['a','b','c']))
->rule(Rule::notIn([1,2]))
```
Direct `StaticCall` against `Illuminate\Validation\Rule`. Argument list maps
1:1 to the native method's signature for `in`/`notIn`. `Rule::` has no
`min`/`max`/`between`/`regex` static helpers, so this form only matters for
the embedded-rules family.

Out of scope for v1: object instances (`->rule(new In([...]))`), variable
strings (`->rule($dynamic)`), concatenated strings (`->rule('min:'.$n)`),
ternaries — anything that requires evaluating non-literal content.

## 3. Receiver-Type Inference

Rewrite must know the concrete typed-rule subclass at the `->rule(...)`
call site. Algorithm:

1. Walk the method-chain backwards from the `->rule(...)` `MethodCall` until
   the root `var` is reached.
2. If root is a `StaticCall` against `FluentRule::{factory}(...)`, resolve
   `{factory}` → typed-rule class via factory map:

   | Factory                    | Resolved class                  |
   |----------------------------|---------------------------------|
   | `string`, `url`, `uuid`, `ulid`, `ip` | `StringRule`         |
   | `numeric`, `integer`        | `NumericRule`                  |
   | `array`                     | `ArrayRule`                    |
   | `file`                      | `FileRule`                     |
   | `image`                     | `ImageRule` (extends `FileRule`) |
   | `date`, `dateTime`          | `DateRule`                     |
   | `boolean`                   | `BooleanRule`                  |
   | `password`                  | `PasswordRule`                 |
   | `email`                     | `EmailRule`                    |
   | `field`                     | `FieldRule`                    |
   | `accepted`                  | `AcceptedRule`                 |
   | `anyOf`                     | `AnyOf` — **not a typed-rule subclass; treated as unknown receiver, skip-log** |

   The map is built by introspecting `FluentRule`'s static methods at
   bootstrap, not hard-coded — keeps it auto-current with downstream
   additions. Hard-coded fallback ships for the v1.17.1 surface above so
   the rector still works when introspection target is unavailable
   (e.g. fluent-validation not in vendor).

3. If root is anything else (variable, parameter, property fetch,
   conditional, `new FluentRule(...)`), bail with a skip-log entry. PHPStan
   type-resolution is **not** used — too lossy through method chains in
   practice; explicit factory walk is the contract.

4. Once class is resolved, check the target method's existence on that
   class. Source of truth: PHP reflection against the resolved FQCN at
   bootstrap. Pre-built per-class allowlist used at refactor time.

5. If the method exists on the resolved class, rewrite. If not, skip-log
   ("`->rule('regex:…')` on `FluentRule::numeric()` — `regex()` only
   available on `StringRule`").

Intermediate chain calls between factory and `->rule(...)` are mostly
pass-through — typed-rule subclasses return `static` from every fluent
method — **with one exception**: every typed-rule class uses Laravel's
`Conditionable` trait (verified across `StringRule`, `NumericRule`,
`ArrayRule`, `FileRule`, `DateRule`, `BooleanRule`, `PasswordRule`,
`EmailRule`, `AcceptedRule`, `FieldRule`). `Conditionable::when()` and
`unless()` declare `@return static|HigherOrderWhenProxy` — the
single-argument form returns a proxy, not the original receiver. A chain
like `FluentRule::string()->when($cond)->rule('regex:/x/')` has receiver
type `HigherOrderWhenProxy` at the `->rule()` call, not `StringRule`. The
proxy doesn't expose `regex()`, so a naive pass-through resolution would
emit broken code.

**Receiver-walk halt rule:** if any intermediate call's name is in
`{when, unless, whenInput}`, bail with skip-log
`receiver type unknown — Conditionable proxy in chain`. Two-arg
`when($cond, $callback)` is technically safe (callback returns
`static|TWhenReturnType`, defaults to `static`) but the receiver-walk
algorithm has no cheap way to prove the callback didn't return a different
typed rule, so the policy is uniform: bail on any `when`/`unless`/`whenInput`
in the intermediate chain. Conservative but sound. Future refinement could
PHPStan-resolve the proxy/callback return type, but that is out of v1
scope.

Variable / helper-method roots (`$rule->rule(...)`, `$this->makeRule()->rule(...)`,
property fetches) are explicitly **out of scope** for v1 — not a "solved"
case, an acknowledged coverage gap. Real codebases will hit this; the
skip-log entry exists so users see why their chain wasn't rewritten.

## 4. Skip Cases

Each skip emits a `LogsSkipReasons` entry with the offending file/line and a
short reason string. Skipping is silent only when the input was already in
the desired native form (no-op).

`logSkip()` signature is `(Class_ $class, string $reason)` — confirmed
against `src/Rector/Concerns/LogsSkipReasons.php:83`. The trait pulls the
file path internally via `$this->getFile()->getFilePath()`. Reasons should
be short and actionable.

| Case | Reason |
|---|---|
| Receiver type unknown (variable root, `new FluentRule(...)`, `FluentRule::anyOf(...)`, helper-method root) | `receiver type unknown` |
| Conditionable hop in chain (`->when(...)`, `->unless(...)`, `->whenInput(...)`) | `receiver type unknown — Conditionable proxy in chain` |
| Method not on resolved class (`numeric()->rule('regex:…')`) | `regex() not on NumericRule` |
| Argument is non-literal (`->rule('min:'.$n)`) | `argument not statically resolvable` |
| Rule shape unparseable (`->rule(Rule::dimensions()->maxWidth(10))`) | `rule expression not in v1 scope` |
| Already native (`FluentRule::string()->in([...])`) | no log — no-op |

## 5. Composition with Sibling Rectors

`ValidationStringToFluentRuleRector` and `ValidationArrayToFluentRuleRector`
already produce `->rule('X:args')` chains during the initial conversion.
SimplifyRuleWrappersRector runs **after** them in the set list — it folds
the leftover `->rule(...)` escape-hatches that those rectors couldn't
natively express. This places it adjacent to `SimplifyFluentRuleRector` in
ordering: cleanup pass over already-fluent code.

`SimplifyFluentRuleRector::FACTORY_SHORTCUTS` already collapses
`string()->url()` → `url()`. The factory map (§3) must treat `url()` /
`uuid()` / `ulid()` / `ip()` as `StringRule` so that a chain
`FluentRule::url()->rule('regex:…')` still resolves to `StringRule` and
permits the `regex` rewrite.

## 6. Set List Wiring

`FluentValidationSetList` exposes set constants (`ALL`, `CONVERT`, `GROUP`,
`TRAITS`, `SIMPLIFY`, `POLISH`) that point at `config/sets/*.php` files.
Each set file is a Rector config closure that registers its rector classes.
SimplifyRuleWrappersRector belongs in `config/sets/simplify.php` alongside
`SimplifyFluentRuleRector`, registered after it so the simplifier's factory
shortcuts (e.g. `string()->url()` → `url()`) run first and SimplifyRuleWrappers
sees the already-shortened receiver.

**`ALL` deliberately does not import SIMPLIFY** (or POLISH) — pinned by the
existing assertions in `tests/SetListTest.php:19,29`. SIMPLIFY is opt-in.
The new rector ships under the existing SIMPLIFY set without modifying ALL
or its tests. v1 ships under SIMPLIFY only; consumers explicitly opt in.

---

## Implementation

### Phase 1: Scaffold + receiver-type inference (Priority: HIGH) — ✅ shipped

- [x] Create `src/Rector/SimplifyRuleWrappersRector.php` extending
      `AbstractRector implements DocumentedRuleInterface`. Mirror
      `SimplifyFluentRuleRector` structure: `WeakMap` to dedupe
      chain-walk visits, `getNodeTypes(): [MethodCall::class]`,
      `RunSummary::registerShutdownHandler()` in constructor
- [x] Implement `resolveReceiverType(MethodCall $node)` —
      walks `$node->var` inward to the root `StaticCall`, returns
      `['class' => …, 'factoryName' => …]` or `'conditionable_proxy'`
      sentinel or `null`. Bail on non-`StaticCall` root
- [x] Build factory→class map. Hard-coded for v1.17.1 surface, with a
      reflection overlay that introspects `FluentRule::class` static
      methods filtered to those returning a `FluentRuleContract` impl
      (excludes `anyOf` → `AnyOf`)
- [x] Build per-class method allowlist via reflection at bootstrap. Cache
      in static prop. Restricted to v1 method set: `in`, `notIn`, `min`,
      `max`, `between`, `regex`, `exactly` (the rewrite-target for `size`)
- [x] Implement intermediate-call halt: if the chain contains any of
      `when`, `unless`, `whenInput`, bail with skip-log per §3
- [x] Wire `LogsSkipReasons` for each skip case in §4
- [x] Tests — `tests/SimplifyRuleWrappers/SimplifyRuleWrappersRectorTest.php`
      with fixtures: variable receiver bail, conditionable proxy bail,
      already-native no-op, intermediate-call chain still resolves factory

### Phase 2: `in` / `notIn` rewrite family (Priority: HIGH) — ✅ shipped

- [x] Recognise three input shapes for `in`/`notIn`:
      (a) `Rule::in([...])` static call → pass args verbatim,
      (b) `'in:a,b,c'` string → split on `,` after `:`, build `Array_`
      of `String_` items,
      (c) `['in', 'a', 'b']` array → take items past index 0, build
      `Array_`
- [x] Emit single-arg call: `->in([...])`. The native signature accepts
      `Arrayable|array|string` so wrapping a single value as array is
      always safe
- [x] Skip if resolved class is not in `{String, Numeric, Email, Field,
      Date}Rule` (i.e. ArrayRule, FileRule, BooleanRule, PasswordRule
      lack `HasEmbeddedRules`) — enforced via the §3 reflection allowlist
- [x] Tests — fixtures per (factory × input-shape) cell that should
      rewrite (5 transform fixtures: in/Rule::/string, in/string/numeric,
      notIn/Rule::/email, in/array/field, in/array/string with chain),
      plus a per-class skip fixture (`array()`/`file()`/`boolean()` stay
      as `->rule(...)`)

### Phase 3: `min` / `max` / `between` / `size`→`exactly` rewrite family (Priority: HIGH) — ✅ shipped

- [x] Recognise input shapes (string + array forms; no `Rule::` facade
      since Laravel doesn't expose `min`/`max`/`between`/`size` statics)
- [x] Argument type policy via `literalForToken()`: pure base-10 ints
      (incl. negative) become `Int_`; everything else stays `String_`.
      Preserves `'2mb'` for `FileRule::min` shorthand without coercing
- [x] `size` rewrites to `exactly` via `RULE_NAME_TO_METHOD`. Per-class
      reflection allowlist naturally restricts to String/Numeric/Array/File
- [x] EmailRule asymmetry handled by reflection allowlist:
      `email()->rule('max:N')` rewrites; `min`/`between`/`size` skip-log
- [x] Tests — 6 new fixtures: min+max numeric (string form), between
      array form, file `'2mb'` shorthand preservation (string token),
      `size`→`exactly` regression pin across String/Numeric/Array/File,
      EmailRule `max` supported, EmailRule `min`/`between`/`size` +
      Boolean/Password/Date unsupported skips

### Phase 4: `regex` rewrite (Priority: HIGH) — ✅ shipped

- [x] String form: `parseStringRule` splits on the first `:` only, then
      `buildArgsFromStringTail` short-circuits the regex branch to emit
      the entire post-`:` substring as a single `String_` verbatim — no
      further splitting on `,` or any other delimiter
- [x] Array form: `parseArrayRule` arity-1 path passes `$item->value`
      through verbatim
- [x] No `Rule::regex(...)` shape implemented — Laravel doesn't expose it
- [x] StringRule-only restriction enforced via §3 reflection allowlist —
      `regex()` is only present on StringRule, so other receivers fail
      `isMethodAvailable()` and skip-log
- [x] Tests — 4 fixtures: string-form on string, array-form on string,
      skip on `numeric()`/`array()`/`email()`, plus a regression pin for
      patterns containing `:` and `,` (must not split)

### Phase 5: Set-list integration + docs (Priority: MEDIUM) — ✅ shipped

- [x] Registered `SimplifyRuleWrappersRector::class` in
      `config/sets/simplify.php` after `SimplifyFluentRuleRector`
- [x] `tests/SetListTest.php` updated with ordering assertion (rule
      present + after prerequisite). ALL ⊄ SIMPLIFY contract preserved
- [x] README updated in two places: SIMPLIFY-set description (line ~106)
      and individual-rules matrix table (line ~170)
- [x] `RELEASE_NOTES_0.7.0.md` written — full scope matrix, receiver-
      type inference behavior, skip cases, set-list ordering rationale,
      `size`→`exactly` rename callout, `LogsSkipReasons` companion change
- [x] `SetListTest` assertion shipped. FullPipeline fixture deferred —
      existing FullPipeline config uses ALL set; SIMPLIFY composition
      would need a separate test config harness, lower-value than the
      end-to-end coverage already provided by the per-input-shape
      transform fixtures in `tests/SimplifyRuleWrappers/Fixture/`

### Phase 6: Coverage hardening (Priority: LOW) — ✅ shipped

- [x] `ImageRule extends FileRule` — reflection's `hasMethod()` walks
      the inheritance chain by default, so `image()->rule(...)` rewrites
      via the FileRule allowlist entries. New fixture
      `image_inherits_file_methods.php.inc` pins min/max/between/exactly
      across the inheritance boundary
- [x] Fast-path bail in `refactor()` — already present from Phase 1:
      `if (! $node->name instanceof Identifier || $node->name->toString() !== 'rule') return null;`
      runs before the chain walk so non-`->rule(...)` MethodCalls cost
      one identifier comparison
- [x] Benchmark harness deferred — repo has no `benchmark.php` /
      `--group=benchmark` infrastructure (the pre-release skill
      description references the main fluent-validation package's
      harness, not this rector's). Adding a benchmark suite is a
      separate cross-rule cleanup, not in scope for this spec

---

## Open Questions

1. **Should the factory→class map be hard-coded, reflection-driven, or
   both?** Hard-coded is simpler and survives autoload misses; reflection
   keeps the map honest as fluent-validation grows. Recommend both: ship
   a hard-coded baseline for v1.17.1 and overlay reflection results when
   the class is autoload-reachable. Phase 1 implements both.

2. **Argument coercion: should `'min:2mb'` against `StringRule` be a
   skip-log even though the rector can syntactically rewrite to
   `->min('2mb')`?** Subclass typing rejects it at runtime, so the rewrite
   would compile but throw on validate. Two options: (a) trust the user's
   original source — they wrote `'min:2mb'` so they meant it, the rewrite
   is a faithful translation; (b) refuse to translate type-mismatches.
   Recommend (a) — rector is a syntactic tool, not a type checker; the
   pre-existing string was equally broken at runtime. Phase 3 ships with
   no coercion check.

3. **Should `Rule::in([...])` rewrites preserve the import or strip it?**
   If the file uses `Rule::` only for `Rule::in`, stripping the use becomes
   safe after rewrite. Cross-file analysis is heavy and Phase 6 territory.
   Recommend leave the use alone — `ManagesNamespaceImports` already
   handles the inverse direction; let a follow-up pass clean unused
   imports if it becomes noise.

4. **Should the rector handle `->rule(Rule::in([...])->where(fn ...))` —
   the conditional-Rule builder pattern?** Tail method calls on the
   `Rule::` static call mean the value isn't a plain `Rule::in` call.
   Skipping is correct (no native equivalent for `where()` exists).
   Confirm with a fixture in Phase 2 that it skip-logs cleanly rather
   than emitting partial output.

---

## Findings

<!-- Notes added during implementation. Do not remove this section. -->

- 2026-04-21 — **Phase 6 codex-review pass: 3 high/medium findings
  fixed.** (a) `literalForToken` was emitting `Float_('1.5')` as a
  `min`/`max`/`between`/`size` arg without checking the target method's
  parameter type — `StringRule::min(int)`, `ArrayRule::min(int)`,
  `EmailRule::max(int)`, `PasswordRule::min(int)`, `NumericRule::exactly(int)`
  are all int-only signatures, so the rewrite would runtime-`TypeError`
  on Laravel-valid input like `'min:1.5'`. New `methodAcceptsFloat()`
  reflects the first parameter's type and skip-logs when a Float_ arg
  meets an int-only or string-only signature. (b) `DateRule::between(from, to)`
  expands to `after()->before()` (chronological), but Laravel's `between:`
  on a date field computes via `getSize()` → `mb_strlen` (size). Same
  method name, different semantics. New `METHOD_RECEIVER_DENYLIST`
  excludes DateRule from `between` rewrites with a "semantics differ"
  skip-log. (c) Parse failures on FluentRule-rooted chains were silent
  — codex flagged the observability gap. Refactor reordered: receiver
  resolves first, only known-FluentRule chains emit the parse-failure
  skip-log. Unrelated `->rule()` calls in non-FluentRule libraries stay
  silent. Three new skip fixtures pin each fix; one new transform
  fixture pins NumericRule float compatibility. Class cognitive
  complexity exceeded the PHPStan cap (96 > 80) so parsing extracted
  into a new `ParsesRulePayloads` concern (matches existing pattern of
  `ConvertsValidationRuleStrings`/`Arrays`). 288 tests / 346 assertions
  / 0 failures / PHPStan clean / Rector clean.
- 2026-04-21 — **Phases 3-5 shipped, final verification clean.** 281
  tests / 339 assertions / 0 failures, PHPStan 0 errors, Rector 0
  changes, Pint clean. Phase 3 added the `min`/`max`/`between`/`size`
  family with a `literalForToken()` helper that emits `Int_` for
  pure base-10 integers (incl. negative) and `String_` everything
  else — preserves `'2mb'` for `FileRule::min` shorthand without
  pre-validating the type. Phase 4's regex implementation was already
  in place from Phase 3's parser refactor; only fixtures were added
  (incl. a `:`-and-`,`-in-pattern regression pin). Phase 5 wired
  `config/sets/simplify.php`, added the `SetListTest` ordering assertion,
  updated README in two places (SIMPLIFY description + individual-rules
  matrix), and wrote `RELEASE_NOTES_0.7.0.md`. Phase 5 also extended
  `LogsSkipReasons` with a `logSkipByName(string, string)` variant —
  required because `MethodCall`-driven rectors can't reliably parent-walk
  to a `Class_` in Rector v2 (`getAttribute('parent')` is not populated);
  resolution now goes via `AttributeKey::SCOPE` → PHPStan
  `Scope::getClassReflection()->getName()`. Phase 6 (LOW priority —
  `ImageRule` inheritance fixture, benchmark fast-path) deferred per
  default skill policy.
- 2026-04-21 — **Phase 2 shipped.** `in`/`notIn` rewrites for the five
  HasEmbeddedRules consumers (String/Numeric/Email/Field/Date). Three
  parsing paths (`Rule::` facade `StaticCall`, `'name:args'` string,
  `['name', args...]` array) live in dedicated `parseRule*` helpers off
  `parseRulePayload()`. `RULE_NAME_TO_METHOD` constant maps Laravel rule
  tokens → fluent methods (identity for in/min/max/regex, plus
  `not_in`→`notIn` and `size`→`exactly`). Three notes from the test pass:
  (a) Rector's `withImportNames()` does NOT auto-strip the
  `use Illuminate\Validation\Rule;` import after the rewrite — fixtures
  retain it (matches OQ #3 recommendation: leave imports alone, separate
  pass cleans orphans). (b) PhpParser v5 deprecated
  `PhpParser\Node\Expr\ArrayItem` in favour of `PhpParser\Node\ArrayItem`
  — using the new location. (c) `skip_intermediate_chain_resolves_factory.php.inc`
  Phase 1 fixture removed; superseded by Phase 2's
  `in_array_form_with_intermediate_chain.php.inc` which now actually
  transforms.
- 2026-04-20 — **Phase 1 shipped.** Scaffolding + receiver-type inference
  in `src/Rector/SimplifyRuleWrappersRector.php`. `refactor()` returns
  `null` for now (no rewrites yet); future phases hook into the resolved
  `['class', 'factoryName']` plus `isMethodAvailable()`. Two design notes:
  (a) the factory-map reflection overlay filters return types via
  `is_a($returnClass, FluentRuleContract::class, true)` rather than a
  shared base class — typed rules don't extend a common parent, only
  share the contract interface; this naturally excludes `FluentRule::anyOf()`
  whose `AnyOf` return doesn't implement the contract; (b) `findEnclosingClass()`
  walks `parent` attributes to scope the skip-log entry, since
  `LogsSkipReasons::logSkip()` requires a `Class_` node — top-level
  scripts (rare) bail without logging. Phase 1 fixtures are no-change
  (`-----`-less) since no transformations exist; once Phase 2 lands, the
  intermediate-chain fixture will flip to a `-----`-separated transform
  fixture documenting the rewrite.
- 2026-04-20 — Initial spec missed three structural correctness items
  caught in self-evaluate + codex review: (a) `size`→`exactly` rename in
  fluent-validation (`Exceptions/TypedBuilderHint.php`) — first draft
  excluded `size` entirely as "no native"; reality is the equivalent
  exists under a renamed method; (b) Composition with
  `ConvertsValidationRuleStrings`/`Arrays` was over-claimed — those
  traits' parsing helpers are private and FormRequest-flow-coupled, so
  the rector ships its own minimal splitter; (c) Receiver-walk treated
  `Conditionable::when()`/`unless()` as transparent, but
  single-arg form returns `HigherOrderWhenProxy` — every typed-rule
  class uses `Conditionable`, so this would have produced broken
  rewrites for any chain with a `when()` hop. Halt rule added.
  Codex also flagged: `Rule::regex(...)` doesn't exist on Laravel's
  facade (Phase 4 input-shape contradiction with §2.3) and `ALL`
  deliberately excludes SIMPLIFY (`tests/SetListTest.php:19,29`) — both
  fixed before implementation start.
