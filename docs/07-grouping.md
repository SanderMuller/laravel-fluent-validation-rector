# Wildcard grouping

## `GroupWildcardRulesToEachRector`

Folds flat wildcard and dotted keys into nested `each()` / `children()` calls, in FormRequests and Livewire components alike. When the folded children are [`FluentSchema`](11-schema.md) chains, the synthesized parent is emitted in instance form (`$rules->array()->children([...])`) to match the receiver.

```php
// Before
'tags'   => FluentRule::array()->nullable(),
'tags.*' => FluentRule::string()->max(50),

// After
'tags' => FluentRule::array()->nullable()->each(
    FluentRule::string()->max(50),
),
```

On Livewire this is safe: the `HasFluentValidation` trait's `getRules()` flattens the nested form back to wildcard keys at runtime.

<details>
<summary>Bail conditions</summary>

Each emits its own [skip-log](15-diagnostics.md) entry under `=actionable`:

- A wildcard group with non-FluentRule entries, such as `'items' => ['required', ...]` beside `'items.*' => FluentRule::...`.
- A parent factory without `each()` / `children()`. Only `FluentRule::array()` and `FluentRule::field()` have them.
- A wildcard parent (`items.*`) carrying type-specific rules that folding would silently drop.
- A double wildcard (`**`), or a non-first `*` in a key suffix.
- A concat-keyed wildcard (`$prefix . '.*.foo'`) whose prefix is not a static class constant.
- Branched-return bodies (several top-level returns) bail uniformly, rather than rewrite across branches.

</details>

<details>
<summary>Edge cases it handles</summary>

- A dot-notation key with no explicit parent gets a synthesized bare `FluentRule::array()` parent, so nested `required` children still fire.
- A `FluentRule::field()->…->rule(Rule::array())` parent is promoted to `array()` before folding, because the array factory seeds the same implicit rule, but only `array()` exposes `each()`. Gated to provably equivalent chains: no label arg on the `field()` root, the only rule hop is the bare array rule, and every other hop exists on both `FieldRule` and `ArrayRule`. Labeled parents, `FieldRule`-only methods, conditionable closures, object-valued `->rule(...)` hops, a `message()` bound to the array rule, keyed `Rule::array([...])` and macro-based size constraints keep the escape hatch.
- Wildcard-prefix concat keys (`'*.' . CONST_NAME => …`) fold when every sibling resolves its suffix from a self/static class constant. A mixed group keeps its literal-keyed entries and bails-with-log on the const branch. Partial conversion, no rule loss.
- `rules()` returning `RuleSet::from([...])` folds by descending into the array argument; the wrapper stays intact.

</details>
