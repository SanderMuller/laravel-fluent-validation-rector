# Sets

| Set | Rules |
|---|---|
| `ALL` | `CONVERT` + `GROUP` + `TRAITS`: the full migration pipeline |
| `CONVERT` | [`InlineResolvableParentRulesRector`](05-converters.md), [`ValidationStringToFluentRuleRector`](05-converters.md), [`ValidationArrayToFluentRuleRector`](05-converters.md), [`ConvertLivewireRuleAttributeRector`](06-livewire.md) |
| `GROUP` | [`GroupWildcardRulesToEachRector`](07-grouping.md) |
| `TRAITS` | [`AddHasFluentRulesTraitRector`](08-traits.md), [`AddHasFluentValidationTraitRector`](08-traits.md) |
| `SIMPLIFY` | [Four post-migration cleanups](09-simplify.md), **not** in `ALL` |
| `POLISH` | [`UpdateRulesReturnTypeDocblockRector`](10-polish.md), **not** in `ALL` |
| `SCHEMA` | [`ConvertToFluentSchemaRector`](11-schema.md), **not** in `ALL` |

```php
// Just conversion
->withSets([FluentValidationSetList::CONVERT])

// Conversion + traits, no grouping
->withSets([
    FluentValidationSetList::CONVERT,
    FluentValidationSetList::TRAITS,
])
```

**Do not bundle `ALL` + `SIMPLIFY` + `POLISH` + `SCHEMA` into one config.** `SIMPLIFY` is meant to run after you have reviewed the initial diff, and `POLISH` needs `CONVERT`'s multi-pass output to have stabilized. Each is its own `vendor/bin/rector process` invocation against its own `withSets([...])`.
