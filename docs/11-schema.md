# Adopting FluentSchema

`SCHEMA` is **opt-in** and not part of `ALL`. Adopting the instance-based builder is a style choice. Run it as its own pass once `CONVERT` and `TRAITS` have produced FluentRule chains on a `HasFluentRules` class. It needs fluent-validation ^1.32, whose `schema()`/`rules()` merge is what lets an `#[FluentRules]`-marked abstract base convert safely.

## `ConvertToFluentSchemaRector`

Rewrites a `rules()` built from `FluentRule::` static chains into the `schema(FluentSchema $rules)` builder. The injected receiver drops the repeated prefix.

```php
// Before
public function rules(): array
{
    return [
        'name'  => FluentRule::string()->required()->max(255),
        'email' => FluentRule::email()->required(),
    ];
}

// After
public function schema(FluentSchema $rules): array
{
    return [
        'name'  => $rules->string()->required()->max(255),
        'email' => $rules->email()->required(),
    ];
}
```

**It only fires on `HasFluentRules` users.** The trait's `createDefaultValidator()` is the only runtime that dispatches a `schema(FluentSchema)` method, detected by the typed first parameter the container resolves. A plain FormRequest without the trait, a Livewire component (`HasFluentValidation` has no `schema()` hook), and a Filament page would all silently lose validation if `rules()` were renamed, so they are left alone. The gate resolves the trait directly, through `FluentFormRequest`, or through any ancestor.

**It no-ops on an older install.** The builder and its dispatch shipped in fluent-validation 1.31. A reflection-time probe for the `FluentSchema` class makes the rule emit zero rewrites without it, because there `createDefaultValidator` still calls `rules()` and a converted `schema()` would never run. The composer floor is `^1.32`; the probe guards a path or dev install that bypasses it.

<details>
<summary>What it rewrites</summary>

- **Every `FluentRule::x()` becomes `$rules->x()`.** `FluentSchema` mirrors each factory one-to-one and forwards macros through `__call`, so the swap preserves the produced rule. Nested chains inside `each([...])` and `children([...])` convert too.
- **Self-referential `rules()` calls.** `parent::rules()` becomes `parent::schema($rules)` when the parent provably converts (a concrete or `#[FluentRules]`-opted `HasFluentRules` class with a public `rules()`, resolved by reflection) or already declares the builder. Re-running over a partly converted chain therefore finishes the child instead of stranding it. A base that stays on `rules()`, abstract and not opted in or from vendor, leaves the child unconverted so the call keeps resolving. `$this->rules()`, `self::rules()` and `static::rules()` are rewritten the same way; sibling calls like `parent::messages()` are never touched.
- **Chains inside closures.** A chain or `parent::rules()` call inside a plain `function () { … }` converts: the receiver is swapped and the closure gains a `use ($builder)` capture, renamed if it would clash with the closure's own parameters. Arrow functions auto-capture.
- **Imports.** Adds `use SanderMuller\FluentValidation\FluentSchema;` and drops the orphaned `FluentRule` import when nothing in the file still references the static factory. A type hint, a `FluentRule::class`, or an unconverted chain keeps it.
- **Parameter naming.** The builder is `$rules` by convention. When the body already uses that local, as in the `$rules = […]; … return $rules;` assembly pattern, a free fallback name is chosen so the method converts instead of skipping.

</details>

<details>
<summary>Bail conditions</summary>

- **An abstract class without `#[FluentRules]`.** The rename could break a subclass calling `parent::rules()` or dropping a base key. Add the attribute to the `rules()` method to assert subclass-safety; the ^1.32 merge then makes a subclass's `rules()` override merge with the renamed base rather than shadow it. Skip-logged as actionable.
- A class that already declares `schema()`, since renaming would fatal on the duplicate.
- A `rules()` with a non-standard signature: parameters, non-public, or static.
- A `rules()` calling `parent::rules()` whose parent will not provably convert: abstract without the attribute, no trait, or an unresolvable vendor base.
- A self-referential `rules()` call in a method other than `rules()`, which the rename would strand with no builder in scope.
- A chain built in a scope the builder cannot be threaded into: an anonymous class or a nested named function. When such a chain sits beside a `parent::rules()` call the bail is skip-logged rather than silent, since leaving it would strand once the base converts.

</details>

## Ordering does not matter

Every other rector resolves a chain's factory from both spellings: `FluentRule::string()` and `$rules->string()` on a `FluentSchema`-typed receiver, whether that is the `schema()` parameter or a `RuleSet::define(fn (FluentSchema $rules) => …)` closure. `AddHasFluentRulesTraitRector` also adds the trait to a hand-written `schema()` FormRequest.

So `SCHEMA` can run before or after `SIMPLIFY`, `POLISH` and `GROUP`, and hand-written builder code is treated like static code. Rewrites keep the receiver: a `$rules->field()` promotion stays `$rules->string()`, and a wildcard fold synthesizes `$rules->array()->children([...])`.

## Process the inheritance chain together

`ConvertToFluentSchemaRector` rewrites a child's `parent::rules()` when the parent *would* convert if processed, and Rector gives a rule no way to confirm the parent's file is in this run.

Running `SCHEMA` over a lone child while leaving out its convertible parent rewrites the child and leaves the parent on `rules()`, producing `Call to undefined method parent::schema()`. PHPStan or the first request catches it immediately, so it is never silent, but avoid it: run the directory or the whole codebase, which the separate-pass workflow already prescribes.
