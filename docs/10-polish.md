# Docblock polish

`POLISH` is **opt-in** and not part of `ALL`. Run it after `CONVERT` has stabilized, since the rector needs the final shape.

## `UpdateRulesReturnTypeDocblockRector`

Narrows the `@return` on `rules()` from the wide `array<string, ValidationRule|string|array<mixed>>` union to `array<string, FluentRuleContract>` when every value in the returned array is a FluentRule chain. Runtime behaviour is untouched; PHPStan and editors get a narrower type.

<details>
<summary>What qualifies, what is left alone</summary>

- **Qualifying classes**: `FormRequest` subclasses anywhere in the ancestor chain, aliased imports included, and classes using `HasFluentRules` / `HasFluentValidation` / `HasFluentValidationForFilament` directly or through an ancestor.
- **Narrowed**: methods with no `@return`, with `@return array`, or with the wide union this package's converters emit.
- **Left untouched**: user-customized annotations, `@inheritDoc`, widened unions and intersections, and any non-prose suffix.
- **Skipped** when the returned array is not a single literal `Array_` (multi-return, builder variants, `RuleSet::from(...)`, collection pipelines), when any value is not a FluentRule chain (`Rule::in(...)`, `new Custom()`, closures, string rules, ternary, match), or when the method is `): ?array` or has unkeyed items.

</details>

Rector's multi-pass convergence means it eventually fires on the final shape, but a single run mixing `CONVERT` and `POLISH` may need a second invocation if any file had string-rule items mid-convert. It takes the same [allowlist keys](13-configuration.md#updaterulesreturntypedocblockrector) as `SimplifyRuleWrappersRector`.
