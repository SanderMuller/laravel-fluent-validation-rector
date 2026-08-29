# Rule reference

Any rule can be registered on its own, without pulling in its set:

```php
use SanderMuller\FluentValidationRector\Rector\ValidationArrayToFluentRuleRector;
use SanderMuller\FluentValidationRector\Rector\ValidationStringToFluentRuleRector;

return RectorConfig::configure()
    ->withRules([
        ValidationStringToFluentRuleRector::class,
        ValidationArrayToFluentRuleRector::class,
    ]);
```

| Rule | Set | Does |
|---|---|---|
| [`InlineResolvableParentRulesRector`](05-converters.md) | `CONVERT`, in `ALL` | inlines a `...parent::rules()` spread when the parent is a plain return |
| [`ValidationStringToFluentRuleRector`](05-converters.md) | `CONVERT`, in `ALL` | pipe-delimited strings → FluentRule chains |
| [`ValidationArrayToFluentRuleRector`](05-converters.md) | `CONVERT`, in `ALL` | rule arrays, `Rule::` and `Password::` objects → FluentRule chains |
| [`ConvertLivewireRuleAttributeRector`](06-livewire.md) | `CONVERT`, in `ALL` | Livewire `#[Rule]` / `#[Validate]` → a generated `rules()` |
| [`GroupWildcardRulesToEachRector`](07-grouping.md) | `GROUP`, in `ALL` | flat wildcard and dotted keys → nested `each()` / `children()` |
| [`AddHasFluentRulesTraitRector`](08-traits.md) | `TRAITS`, in `ALL` | adds `use HasFluentRules;` to FormRequests using FluentRule or declaring `schema()` |
| [`AddHasFluentValidationTraitRector`](08-traits.md) | `TRAITS`, in `ALL` | adds the Livewire trait, plain or Filament variant |
| [`PromoteFieldFactoryRector`](09-simplify.md) | `SIMPLIFY`, **not** in `ALL` | `field()->rule('max:61')` → `string()->max(61)` |
| [`SimplifyFluentRuleRector`](09-simplify.md) | `SIMPLIFY`, **not** in `ALL` | factory shortcuts, `between()`, redundant-type cleanup |
| [`SimplifyRuleWrappersRector`](09-simplify.md) | `SIMPLIFY`, **not** in `ALL` | `->rule('in:a,b')` and friends → native typed methods |
| [`InlineMessageParamRector`](09-simplify.md) | `SIMPLIFY`, **not** in `ALL` | `->message()` / `->messageFor()` → inline `message:` param |
| [`UpdateRulesReturnTypeDocblockRector`](10-polish.md) | `POLISH`, **not** in `ALL` | narrows `@return` on pure-fluent `rules()` |
| [`ConvertToFluentSchemaRector`](11-schema.md) | `SCHEMA`, **not** in `ALL` | `rules()` of `FluentRule::` chains → a `schema(FluentSchema $rules)` builder |

The frozen public surface (symbols, wire keys, behaviour) is in [`PUBLIC_API.md`](https://github.com/SanderMuller/laravel-fluent-validation-rector/blob/main/PUBLIC_API.md).
