# Simplify

`SIMPLIFY` is **opt-in** and not part of `ALL`. Run it as its own pass once you have reviewed the initial conversion. Its four rectors run in the order below.

## `PromoteFieldFactoryRector`

Promotes `FluentRule::field()` to a typed factory when every `->rule(...)` wrapper in the chain resolves to a rule whose target method lives on exactly one typed subclass. `FluentRule::field()->rule('max:61')` becomes `FluentRule::string()->max(61)`, which unblocks the escape-hatch cleanup below.

**Promoting changes validation behaviour.** `StringRule` adds Laravel's implicit `string` rule, `NumericRule` adds `numeric`; `FieldRule` adds neither. Intent matches in nearly every `max(N)` case, but the diff is worth reading.

<details>
<summary>Other promotions and bail conditions</summary>

- `string()->rule(Password::default())` / `->rule(Email::default())` → `FluentRule::password()` / `::email()`.
- `field()->required()->rule('accepted')` → `FluentRule::accepted()->required()`, and the `declined` analog: those factories seed the constraint in their constructors, so the `->rule()` hop is spliced out. Promotion to `boolean()` stays blocked, because boolean's implicit constraint rejects `"yes"` / `"on"` / `"true"` and `"no"` / `"off"` / `"false"`.
- **Bails on** conditionable hops, chains whose compatible-class intersection is not a singleton, and `accepted` / `declined` chains carrying further `->rule(...)` payloads.

</details>

## `SimplifyFluentRuleRector`

Factory shortcuts (`string()->url()` → `url()`), `->label()` folded into factory args, `min()` + `max()` → `between()`, redundant type removal.

<details>
<summary>Bail conditions</summary>

- The `min()`+`max()` fold bails when either carries `messageFor('min'/'max')` or a positional `message()`, because folding would drop the binding.
- Factory-shortcut promotion bails when the chain has a `label()` call, or the shortcut method is not adjacent to the factory.

</details>

## `SimplifyRuleWrappersRector`

Rewrites escape-hatch `->rule(...)` calls into native typed methods. Runs after `SimplifyFluentRuleRector` so shortcuts apply first. Takes an [allowlist](13-configuration.md#simplifyrulewrappersrector) for factories it cannot introspect.

<details>
<summary>Rewrite table</summary>

| Rule family | Receivers | Notes |
|---|---|---|
| `in` / `notIn` | `String`/`Numeric`/`Email`/`Field`/`Date` | `HasEmbeddedRules` consumers |
| `min` / `max` / `between` | per-class allowlist | `EmailRule` has only `max` |
| `regex` | `StringRule` only | |
| `size` → `exactly` | `String`/`Numeric`/`Array`/`File` | renamed per `TypedBuilderHint` |
| `enum` | `HasEmbeddedRules` consumers | typed-rule allowlist |
| Literal-zero comparisons | `NumericRule` | `gt:0` → `->positive()`, `gte:0` → `->nonNegative()`, and so on. Non-zero literals and field refs stay escape |
| Zero-arg string tokens | receivers with a matching method | `accepted`, `declined`, `present`, `prohibited`, `nullable`, `sometimes`, `required`, `filled` |

</details>

<details>
<summary>Conditional rules and receiver inference</summary>

- **COMMA_SEPARATED conditional rules** in array, string and concat form all lower to native methods: `->rule(['required_if', 'field', 'value'])`, `->rule('required_if:field,value')` and `->rule('required_with:' . self::FIELD)`. String form splits the tail on commas; the fluent variadic re-joins with `,`, so escaped or quoted commas round-trip. Concat form is scoped to the pure-field family and gated to statically simple string-oriented operands, so a method call, arithmetic or ternary operand stays an escape hatch. BackedEnum cases in tail positions auto-wrap with `->value`. `required_if_accepted` and `exclude_with` stay escape hatches.
- **`Rule::` facade conditionals** (`Rule::requiredIf($cond)` and siblings) pass the single `Closure|bool` through verbatim. Bails on multi-arg calls, on a literal `null` condition (valid Laravel, but `->requiredIf(null)` would `TypeError`), and on named args, since param names differ between facade and builder.
- **Receiver inference** walks back to the `FluentRule::*()` factory, stepping through `Conditionable` proxy hops when the closure is a bare return, no return, or `fn ($r) => $r`. Other closure shapes bail, as do variable receivers and methods absent from the resolved class.

</details>

## `InlineMessageParamRector`

Collapses `->message('…')` and `->messageFor('key', '…')` into the inline `message:` named parameter. Needs fluent-validation ^1.20; earlier floors get zero rewrites via a reflection-time probe.

<details>
<summary>Rewrite predicates and skip categories</summary>

Three predicates: **factory-direct** (`FluentRule::email()->message('Bad')` → `FluentRule::email(message: 'Bad')`, only with no intervening hop), **rule-method matched-key** (`->min(3)->messageFor('min', 'Too short.')` → `->min(3, message: 'Too short.')`), and **rule-object** (`->rule(new In([...]))->messageFor('in', '…')`).

Skipped, each with a log entry: variadic-trailing methods (`requiredWith`, `contains`) where inline would bind to the wrong slot; composite methods (`digitsBetween`, `DateRule::between`, `ImageRule::dimensions`) where it would bind to the last sub-rule; mode modifiers (`EmailRule::strict`, `PasswordRule::letters`) that never call `addRule`; deferred-key factories (`date`, `dateTime`); L11/L12-divergent `Password`; and factories with no implicit constraint (`field`, `anyOf`).

Pre-existing user misbindings (`->min(3)->messageFor('max', …)`) stay chained silently. Not the rector's to fix.

</details>
