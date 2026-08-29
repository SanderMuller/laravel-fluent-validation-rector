# Configuration

Four rectors take configuration. Each receives its own array via `withConfiguredRule()`, and **values are not pooled between rectors.** When the same wire key appears on two of them, pass it to both; configuring one leaves the other on its default, which is silent partial config.

```php
use SanderMuller\FluentValidationRector\Rector\ConvertLivewireRuleAttributeRector;

return RectorConfig::configure()
    ->withConfiguredRule(ConvertLivewireRuleAttributeRector::class, [
        ConvertLivewireRuleAttributeRector::PRESERVE_REALTIME_VALIDATION => false,
    ]);
```

## `ConvertLivewireRuleAttributeRector`

| Key | Type | Default | Effect |
|---|---|---|---|
| `PRESERVE_REALTIME_VALIDATION` | `bool` | `true` | Keeps an empty `#[Validate]` marker on converted properties so `wire:model.live` validation survives. Turn off on codebases without `wire:model.live` that find the marker noisy |
| `MIGRATE_MESSAGES` | `bool` | `false` | Migrates `message:` args into a generated `messages(): array`. String → `'<prop>' => 'X'`; array → `'<prop>.<rule>' => 'X'`. Off by default because it expands the class surface and some projects centralize messages in lang files. Bails on an unmergeable existing `messages()` |
| `KEY_OVERLAP_BEHAVIOR` | `'bail'` \| `'partial'` | `'bail'` | What to do when a class has both `#[Validate]` attrs and an explicit `$this->validate([...])`. `'bail'` skips the class; `'partial'` converts only attrs whose keys do not appear in the explicit array. Only a direct `Array_` or `RuleSet::compileToArrays(<literal>)` is read; anything else bails classwide |

## `SimplifyRuleWrappersRector`

| Key | Type | Default | Effect |
|---|---|---|---|
| `TREAT_AS_FLUENT_COMPATIBLE` | `list<string>` | `[]` | FQCNs whose factory output is FluentRule-compatible. `*` matches one namespace segment, `**` recurses. Silences the "payload not statically resolvable" skip on shapes the rector cannot introspect |
| `ALLOW_CHAIN_TAIL_ON_ALLOWLISTED` | `bool` | `false` | By default a `->someMethod()` tail after an allowlisted factory is preserved. Turn on when your allowlisted factories always return another compatible node |

## `UpdateRulesReturnTypeDocblockRector`

The same two keys. Allowlisted items count as FluentRule for the narrowing decision. A mixed array with an existing narrow tag emits a stale-narrow warning.

## `AddHasFluentRulesTraitRector`

| Key | Type | Default | Effect |
|---|---|---|---|
| `BASE_CLASSES` | `list<string>` | `[]` | FormRequest **base** classes that should also get the trait. Auto-detection on concrete FormRequests runs regardless; this adds named shared bases on top |

## Typed builders

Each configurable rector has an opt-in DTO under `SanderMuller\FluentValidationRector\Config\` producing the same wire-key array through `->toArray()`. Same output, with compile-time types and autocomplete. The constant-array form keeps working; pick per call site.

| Rector | DTO | Shared type |
|---|---|---|
| `ConvertLivewireRuleAttributeRector` | `LivewireConvertOptions` | `Shared\OverlapBehavior` (enum) |
| `SimplifyRuleWrappersRector` | `RuleWrapperSimplifyOptions` | `Shared\AllowlistedFactories` |
| `UpdateRulesReturnTypeDocblockRector` | `DocblockNarrowOptions` | `Shared\AllowlistedFactories` |
| `AddHasFluentRulesTraitRector` | `HasFluentRulesTraitOptions` | `Shared\BaseClassRegistry` |

**Building a shared value once is the point.** `AllowlistedFactories` feeds both rectors that read it, so adding a class updates both surfaces at once:

```php
$allowlist = AllowlistedFactories::none()
    ->withFactories(['App\\Rules\\CustomRule'])
    ->allowingChainTail();

return RectorConfig::configure()
    ->withConfiguredRule(
        SimplifyRuleWrappersRector::class,
        RuleWrapperSimplifyOptions::with($allowlist)->toArray(),
    )
    ->withConfiguredRule(
        UpdateRulesReturnTypeDocblockRector::class,
        DocblockNarrowOptions::with($allowlist)->toArray(),
    )
    ->withConfiguredRule(
        ConvertLivewireRuleAttributeRector::class,
        LivewireConvertOptions::default()
            ->withMessageMigration()
            ->withOverlapBehavior(OverlapBehavior::Partial)
            ->toArray(),
    )
    ->withConfiguredRule(
        AddHasFluentRulesTraitRector::class,
        HasFluentRulesTraitOptions::with(
            BaseClassRegistry::of(['App\\Http\\Requests\\BaseRequest']),
        )->toArray(),
    );
```

`::with(...)` is shorthand for `::default()->with…(...)`; both produce identical output.
