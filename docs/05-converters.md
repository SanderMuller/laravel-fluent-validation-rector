# String and array converters

Three rectors in `CONVERT` rewrite rule arrays. All of them fire in FormRequest `rules()`, `$request->validate()`, `Validator::make()`, and `RuleSet::from([...])` wrappers anywhere in PHP source. The wrapper stays; only the inner array converts.

## `ValidationStringToFluentRuleRector`

Pipe-delimited strings (`'required|string|max:255'`) become fluent chains.

## `ValidationArrayToFluentRuleRector`

Array-based rules (`['required', 'string', Rule::unique(...)]`), including `Rule::` objects, `Password::min()` chains, conditional tuples, closures and custom rule objects.

<details>
<summary>Conditional tuples and dynamic arguments</summary>

- **Conditional tuples accept**: explicit enum-value args (`['exclude_unless', 'type', Enum::CASE->value]`), and in-tuple variadic spread on variadic fluent signatures (`['required_unless', $field, ...Enum::list()]`).
- **Conditional tuples bail** on spread targeting non-variadic methods (`excludeWith`, `requiredIfAccepted`), or spread on the rule-name or field position. The array form is preserved.
- **Non-conditional tuples accept dynamic expressions**: `['max', $this->limit ?? 10]`, `['between', config('a'), config('b')]`, `['max', match($x) { ... }]`.
- **Non-conditional tuples bail** on object/callable/array producers (`new Obj()`, `fn() => 5`, `[1, 2]`) and side-effectful mutators (`$x = 5`, `$i++`).
- **COMMA_SEPARATED conditional rules** keep strict string-like args, to avoid `Closure|bool|string $field` overload ambiguity.
- **Dynamic concat rule strings** (`'required_if_accepted:' . $field`) lower to the native method when the rule name is a static leading literal and the rule takes the tail as one string. Otherwise they stay on a string-coercion-safe `->rule(<concat>)`.
- **`Rule::` presence conditionals** (`requiredIf`, `requiredUnless`, `excludeIf`, `excludeUnless`, `prohibitedIf`, `prohibitedUnless`) convert when the argument is a closure or bool literal; matching is case-insensitive. Any other shape could be read as a *field name*, so the native array stays.
- **Composite `Rule::` builders bail**: `Rule::when()` / `Rule::unless()` (`ConditionalRules`) and `Rule::forEach()` (`NestedRules`) have no faithful fluent equivalent.

</details>

## `InlineResolvableParentRulesRector`

Inlines `parent::rules()` when it is a spread at index 0 of a child `rules()`, unblocking the converters, which otherwise bail on spread items. Runs first in `CONVERT`.

<details>
<summary>Supported shapes and bail conditions</summary>

- **Handles** `...parent::rules()` when the parent is a plain `return [...];`, and `...$base` when `$base` is the method's only top-level assignment and its right-hand side is a literal array or `parent::rules()`. That covers the `$base = parent::rules(); return [...$base, 'new' => '...'];` idiom.
- **Bails on** parents that merge, concatenate or call methods over their return, and on methods with peer top-level assignments, assignments in nested scope (`if` / `foreach` / `try`), or multi-use variables.

</details>
