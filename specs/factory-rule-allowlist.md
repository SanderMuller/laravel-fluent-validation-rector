# Cross-Rector Factory-Rule Allowlist

## Overview

Consumer-declared allowlist that tells the rectors "these static-factory or constructor calls on custom classes represent rule objects — treat them as opaque but valid, don't skip-log them, and narrow `@return` contracts accordingly."

Sources: mijntp (Model::existsRule, DutchPostcodeRule, Iban, DutchLicensePlateRule — ~13 hits across multiple files), hihaho (Model::existsRule, Enum::externalRule, Question::existsRule with `->where(...)` chain — ~8 hits).

---

## 1. Current State

Projects with Laravel conventions like `Model::existsRule()` (static factory returning `Rule::exists()->where(...)` preconfigured) or custom rule classes (`new DutchPostcodeRule()`) surface in two rector paths:

1. **`UpdateRulesReturnTypeDocblockRector`**: a rules() method whose value includes `Model::existsRule()` hits `isFluentRuleChainValue` → innermost walk ends at a `StaticCall` on `Model`, not `FluentRule` → value rejected → whole method skipped with "value at key '...' is not a FluentRule chain (shape: StaticCall)".
2. **`SimplifyRuleWrappersRector`**: `->rule(new DutchPostcodeRule())` hits the unparseable-payload branch and skip-logs "rule payload not statically resolvable to a v1 shape: New_ App\Validation\DutchPostcodeRule".

Both skips are technically correct — the rectors have no way to know these objects produce rule-compatible values. But in mature Laravel codebases they're a major actionable-log-noise source AND a POLISH false-negative (the objects ARE valid `ValidationRule` implementations, the rule just can't introspect that statically).

---

## 2. Proposed Design

### 2a. Allowlist config, shared across rectors

Two shapes:

**Static factories** — `[Class, 'methodName']` returning a rule-compatible value.

```php
use App\Models\Question;

->withConfiguredRule(UpdateRulesReturnTypeDocblockRector::class, [
    UpdateRulesReturnTypeDocblockRector::TREAT_AS_FLUENT_COMPATIBLE => [
        [Question::class, 'existsRule'],
        ['App\\Models\\*', 'existsRule'],  // glob on class name
    ],
])
```

**Constructors** — FQNs of rule classes.

```php
->withConfiguredRule(UpdateRulesReturnTypeDocblockRector::class, [
    UpdateRulesReturnTypeDocblockRector::TREAT_AS_FLUENT_COMPATIBLE => [
        'App\\Validation\\DutchPostcodeRule',
        'App\\Validation\\Iban',
    ],
])
```

Both forms live in the same list. The rector detects which shape by checking if the entry is an array (`[Class, method]`) or string (constructor FQN).

### 2b. Shared trait

Add `AllowlistedRuleFactories` trait to `Concerns/`:

```php
trait AllowlistedRuleFactories
{
    /** @var list<array{0: string, 1: string}|string> */
    private array $allowlistedRuleFactories = [];

    public function configure(array $configuration): void
    {
        $allowlist = $configuration[self::TREAT_AS_FLUENT_COMPATIBLE] ?? [];
        $this->allowlistedRuleFactories = $allowlist;
    }

    protected function isAllowlistedRuleFactory(Expr $value): bool
    {
        if ($value instanceof New_) {
            $fqn = $this->getName($value->class);
            foreach ($this->allowlistedRuleFactories as $entry) {
                if (is_string($entry) && $this->matchesGlob($fqn, $entry)) {
                    return true;
                }
            }
            return false;
        }

        if ($value instanceof StaticCall) {
            $class = $this->getName($value->class);
            $method = $this->getName($value->name);
            foreach ($this->allowlistedRuleFactories as $entry) {
                if (is_array($entry) && count($entry) === 2
                    && $this->matchesGlob($class, $entry[0])
                    && $method === $entry[1]) {
                    return true;
                }
            }
        }

        // MethodCall chain tail: default behavior is to NOT trust the chain
        // just because its root is allowlisted — an allowlisted factory can
        // expose methods that return non-rule values, and trusting the chain
        // root would silence skip logs incorrectly and defeat docblock
        // analysis. See §3a. Chain-tail acceptance is opt-in via a separate
        // config flag (see §2c).
        return false;
    }
}
```

### 2c. Per-rector integration

**`UpdateRulesReturnTypeDocblockRector`**: extend `isFluentRuleChainValue` to accept allowlisted values. `FluentRule::string()` chains and allowlisted objects both narrow safely to `FluentRuleContract` because the contract is the package's internal typing only — the consumer's custom rule object doesn't implement it, so actually the target annotation becomes wrong.

**Wait — safety concern.** `FluentRuleContract` is `SanderMuller\FluentValidation\Contracts\FluentRuleContract`. A user's `DutchPostcodeRule` doesn't implement it. Narrowing `@return array<string, DutchPostcodeRule|FluentRuleContract>` to `array<string, FluentRuleContract>` would be a type-lie.

Revised approach: allowlisted values make the rule *respect* the existing docblock (don't narrow) instead of skip-logging. Skip becomes silent, docblock unchanged. This removes the log noise without introducing type-lies.

Alternative: narrow to a wider contract `array<string, \Illuminate\Contracts\Validation\ValidationRule>` when allowlisted values are present in the array, and narrow to `FluentRuleContract` only when all values are proven-fluent. Two-tier narrowing. More design work.

**`SimplifyRuleWrappersRector`**: `->rule(<allowlisted>)` stays as escape hatch (nothing to simplify) but the skip is downgraded to *silent* (not even verbose). Log goes from 13 noisy entries to 0.

### 2c. Opt-in chain-tail acceptance

```php
->withConfiguredRule(UpdateRulesReturnTypeDocblockRector::class, [
    UpdateRulesReturnTypeDocblockRector::TREAT_AS_FLUENT_COMPATIBLE => [
        [Question::class, 'existsRule'],
    ],
    UpdateRulesReturnTypeDocblockRector::ALLOW_CHAIN_TAIL_ON_ALLOWLISTED => true,  // default false
])
```

When `false` (default), only exact `New_` / `StaticCall` shapes match. `Question::existsRule()->where(fn () => ...)` does NOT match because `where()` could return something non-rule (Codex review, 2026-04-24).

When `true`, the innermost-receiver walk recurses as before. Consumer explicitly accepts the drift risk. Document that this should only be flipped for allowlist entries whose declared methods all return the same rule-compatible type — ideally enforced by the consumer's own PHPStan config.

### 2d. Glob matching

Glob support on class names via a restricted syntax (**not** fnmatch — backslashes in class names conflict with fnmatch's escape semantics). Accepted patterns:

- Exact match: `App\Models\Question`.
- Single-segment wildcard `*`: matches one namespace segment (no backslashes). `App\Models\*` matches `App\Models\Foo` but not `App\Models\Sub\Bar`.
- Recursive wildcard `**`: matches any depth including zero segments. `App\Models\**` matches `App\Models\Foo` AND `App\Models\Sub\Bar` AND `App\Models\A\B\C`.
- Prefix wildcard: `*\Requests\BaseRequest` — matches any class at that suffix (single segment).

Recursive `**` addition driven by peer review — mijntp's `existsRule()` callees span `App\Models\SuperAdmin\*` and `App\Models\Credit\*` subnamespaces; hihaho's span `App\Models\Playlist\*` and `App\Models\Billing\*`. A recursive `App\Models\**::existsRule` single entry covers both patterns.

Implementation: compile pattern to a PHP regex. Match `**` before `*` greedily (double-star emits `.+`; single-star emits `[^\\\\]+`). `preg_quote` literals; anchor `^`/`$`. No `?` / `[a-z]` / brace-expansion support.

Document syntax explicitly so consumers don't assume full fnmatch.

---

## 3. Safety Analysis

### 3a. Docblock narrowing (revised per §2c)

Do NOT narrow to `FluentRuleContract` when allowlisted values are present — it would lie about the item type. Instead:

- All-fluent array: narrow to `FluentRuleContract` (existing behavior).
- All-allowlisted or mixed-fluent+allowlisted array: leave docblock unchanged, silent skip.
- Anything else: existing skip-with-log behavior.

### 3b. Configuration drift risk

A consumer adds `[Question::class, 'existsRule']` to the allowlist, then later refactors `Question::existsRule()` to return a non-rule value. The rector silently suppresses skips for that call. Consumer-accepted risk — allowlist is explicit opt-in.

### 3c. Glob specificity

`App\\*` matches `App\Models\Foo`, `App\Http\Requests\Bar`, etc. — over-broad. Document "prefer fully-qualified class list or subnamespace globs (`App\\Models\\*`), avoid top-level globs".

---

## 4. Fixtures

Under `tests/FactoryRuleAllowlist/Fixture/`:

- `allowlist_static_factory_narrows_silently.php.inc` — `[Question::class, 'existsRule']` allowlisted, rules() has mixed FluentRule + `Question::existsRule()` → docblock left alone, no skip log.
- `allowlist_constructor_class.php.inc` — `DutchPostcodeRule::class` allowlisted, `->rule(new DutchPostcodeRule())` silent.
- `skip_allowlist_method_chain_tail_default.php.inc` — allowlisted static factory followed by `->where(...)` → skip by default (chain tail not trusted without opt-in).
- `allowlist_method_chain_tail_with_opt_in.php.inc` — same as above but with `ALLOW_CHAIN_TAIL_ON_ALLOWLISTED => true` → accepted.
- `skip_allowlist_chain_tail_non_rule_method_negative.php.inc` — codex-review negative case: allowlisted root + `->toSomethingElse()` that returns a non-rule value, chain-tail opt-in enabled → must still skip-log if the rule detects the method doesn't return a `ValidationRule`-compatible type (future hardening). Document current v1 behavior: opt-in trusts the chain unconditionally; consumer owns the risk.
- `allowlist_glob_match.php.inc` — `App\\Models\\*::existsRule` glob matches multiple model classes.
- `skip_non_allowlisted_factory.php.inc` — unlisted factory still skips with current diagnostic.
- `allowlist_does_not_override_string_rule_skip.php.inc` — `'password' => 'required|string'` bare string still skipped correctly regardless of allowlist.

Integration tests confirm `UpdateRulesReturnTypeDocblockRector` and `SimplifyRuleWrappersRector` share the allowlist via trait composition — one config entry affects both rectors.

---

## 5. Open Questions

1. **Single global config key or per-rector duplicates?** Per-rector config is Rector-idiomatic (each rule is independently configurable). Global would be ergonomic but requires `RectorConfig::parameters()->set(...)` pattern which isn't this package's precedent. Lean per-rector with trait-sharing logic.
2. **Should the allowlist support return-type annotations instead of class globs?** A factory method with `: ValidationRule` return type is self-documenting. Could auto-accept any method returning `ValidationRule` / `FluentRuleContract`. Adds reflection cost. Defer.
3. **Closure-returning factories** like `fn () => Rule::exists('users', 'email')->where(...)` — Not in scope of the allowlist. Closures at value positions stay as skip.
4. **Should allowlist also affect `ValidationArrayToFluentRuleRector`?** That rector's job is converting bare rule-string/array forms; allowlisted custom-rule objects wouldn't appear in its input. Non-issue.

---

## 6. Out of Scope

- PHPStan integration — consumers can declare the allowlist separately in phpstan config. Not a rector surface.
- Reflection-based auto-discovery (scan classpath for methods returning `ValidationRule`). Too much startup cost.
- Generating the allowlist from usage stats (skip-log frequency). Separate tooling, not a rector feature.
