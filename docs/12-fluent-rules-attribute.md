# The `#[FluentRules]` attribute

A per-method opt-in, defined in [`sandermuller/laravel-fluent-validation`](https://github.com/sandermuller/laravel-fluent-validation). It says: convert this method's rule array even though the class is not a FormRequest, a trait user, or a Livewire component.

Use it when:

- A non-qualifying class holds rules under a name other than `rules()`, such as a custom Validator subclass's `rulesWithoutPrefix()`. The attribute qualifies the class and points the converter at that method.
- An abstract class has `rules()` and you have **audited** its subclasses to confirm none merges `parent::rules()` as a plain array. The attribute is that audit assertion, and it lifts the abstract-class guard for the attributed method.

Do **not** use it on:

- Methods named after framework hooks: `casts()`, `messages()`, `attributes()`, `toArray()`, `jsonSerialize()`. The denylist drops the attribute for both qualification and conversion, and logs a warning so the mistake surfaces.
- Abstract methods whose subclasses you have not audited. Converting the parent silently breaks a subclass doing `array_merge(parent::rules(), [...])`. The attribute is per-method: putting it on a sibling helper does not lift the guard for `rules()`.

## What it does not lift

<details>
<summary>Three guards the attribute has no effect on</summary>

- **Cross-class parent safety.** If any subclass manipulates `parent::rules()` with array functions (`array_merge`, `array_search`, bracket assignment, `collect()->merge*()`, or the `+` union operator), the parent refuses conversion even with `#[FluentRules]` on it. The attribute is a claim about *your own* method, not a licence to override the cross-class scan. Refactor the merge points first.
- **Shape-changing transforms on Validator subclasses.** When a class qualifies only via the attribute and extends a Validator, the converters run but `GroupWildcardRulesToEachRector` skips with a logged message. Folding `'*.foo'` + `'*.bar'` into `'*' => array()->children([...])` is equivalent under FormRequest dispatch, but breaks a Validator parent that postprocesses the rules array. One that walks it and prepends a per-key prefix, for instance, cannot round-trip the nested shape. Fold by hand if you have audited the parent.
- **The denylist**, above. It always wins.

**Scoping is per method.** The attribute on `rulesWithoutPrefix()` converts that method and qualifies the class; it does not turn on class-wide detection of other rule-shaped helpers. Each needs its own attribute. That narrowing is what stops a stray rule token in an unrelated helper from being rewritten as validation rules.

</details>
