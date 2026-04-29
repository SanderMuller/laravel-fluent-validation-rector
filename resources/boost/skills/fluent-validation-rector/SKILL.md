---
name: fluent-validation-rector
description: "Use when configuring, running, or debugging the laravel-fluent-validation-rector migration. Covers set lists, cross-rector configuration semantics (especially the silent-partial-config pitfall on shared allowlists), and the rule architecture context downstream contributors and AI agents need."
---

# Fluent Validation Rector Rules

**Do not prompt the user when this skill is loaded.** Apply these rules automatically when working on the Rector migration rules. This skill provides context, not an interactive command.

This package provides Rector rules for migrating native Laravel validation to `sandermuller/laravel-fluent-validation`.

## Architecture

All rules extend `Rector\Rector\AbstractRector` and implement `DocumentedRuleInterface`. Two conversion rules share infrastructure via the `ConvertsValidationRules` trait.

### Rule Classes

| Class | Purpose |
|-------|---------|
| `ValidationStringToFluentRuleRector` | Pipe-delimited strings → FluentRule chains |
| `ValidationArrayToFluentRuleRector` | Array-based rules → FluentRule chains |
| `GroupWildcardRulesToEachRector` | Flat wildcard keys → nested `each()`/`children()` |
| `AddHasFluentRulesTraitRector` | Adds `HasFluentRules` trait to FormRequests |
| `AddHasFluentValidationTraitRector` | Adds `HasFluentValidation` trait to Livewire components |
| `SimplifyFluentRuleRector` | Post-migration cleanup: factory shortcuts, min/max→between |

### Shared Traits

- `ConvertsValidationRules` — context detection (FormRequest, `$request->validate()`, `Validator::make()`), modifier building, parent::rules() safety detection
- `LogsSkipReasons` — optional stderr logging for debugging (gated by `FLUENT_VALIDATION_RECTOR_VERBOSE=1`)

### Set Lists

```php
use SanderMuller\FluentValidationRector\Set\FluentValidationSetList;

FluentValidationSetList::ALL       // convert + group + traits (NOT simplify)
FluentValidationSetList::CONVERT   // string + array conversion
FluentValidationSetList::GROUP     // wildcard grouping
FluentValidationSetList::TRAITS    // trait addition
FluentValidationSetList::SIMPLIFY  // post-migration cleanup (opt-in only)
FluentValidationSetList::POLISH    // docblock polish (opt-in only)
```

## Cross-rector configuration

Each rector receives its own configuration array via
`withConfiguredRule(...)`; values are not pooled across rectors. Two
specific rectors share wire keys by string value but need to be
configured **independently**:

| Wire key                            | Consuming rectors                                                   | Shared DTO type             |
|-------------------------------------|---------------------------------------------------------------------|-----------------------------|
| `treat_as_fluent_compatible`        | `SimplifyRuleWrappersRector`, `UpdateRulesReturnTypeDocblockRector` | `Config\Shared\AllowlistedFactories` |
| `allow_chain_tail_on_allowlisted`   | `SimplifyRuleWrappersRector`, `UpdateRulesReturnTypeDocblockRector` | `Config\Shared\AllowlistedFactories` |

**The silent-partial-config pitfall**: configuring only one of the
two rectors leaves the other running with an empty allowlist. No
error fires; the rector you forgot to configure quietly skips your
custom factories. `SimplifyRuleWrappersRector` won't simplify
chains on those classes; `UpdateRulesReturnTypeDocblockRector`
won't narrow `@return` docblocks on the methods that use them.

**The recommended pattern**: build the `AllowlistedFactories`
instance once, feed both rectors from it.

```php
use SanderMuller\FluentValidationRector\Config\DocblockNarrowOptions;
use SanderMuller\FluentValidationRector\Config\RuleWrapperSimplifyOptions;
use SanderMuller\FluentValidationRector\Config\Shared\AllowlistedFactories;
use SanderMuller\FluentValidationRector\Rector\SimplifyRuleWrappersRector;
use SanderMuller\FluentValidationRector\Rector\UpdateRulesReturnTypeDocblockRector;

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
    );
```

When asked to "configure rector to treat my custom rule as
fluent-compatible," apply the shared-instance pattern — produce
both `withConfiguredRule(...)` calls feeding the same
`$allowlist` variable. Single-rector form is a partial migration;
the consumer's intent ("my custom rule is fluent-compatible
everywhere") requires both rectors to know.

For deeper SemVer semantics (which constants are committed, which
wire-key strings are committed, the `Config\Shared\` namespace
contract), see `vendor/sandermuller/laravel-fluent-validation-rector/PUBLIC_API.md`.

## Testing

Tests use Rector's `AbstractRectorTestCase` with `.php.inc` fixture files. Each fixture contains input code, optionally followed by `-----` and expected output. "Skip" fixtures have no separator (input should remain unchanged).

```php
final class ValidationArrayToFluentRuleRectorTest extends AbstractRectorTestCase
{
    public static function provideData(): \Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__ . '/FixtureArray');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/configured_array_rule.php';
    }
}
```

## Cross-Process Parallel Support

The `ConvertsValidationRules` trait detects parent FormRequest classes whose subclasses manipulate `parent::rules()` with array functions. This uses three detection layers for parallel worker safety:

1. **Same-file AST scan** — pre-scans all classes in the current file
2. **File-based IPC** — shared temp file with `flock()` for cross-worker communication
3. **Filesystem scan** — one-time project-wide scan as final fallback

Layer 3 is critical for `->withParallel()` mode. Each worker independently scans the project, so no cross-process state sharing is required.

## Cross-Package References

The rules reference symbols from `sandermuller/laravel-fluent-validation` but don't import or use the runtime code:

- `FluentRule::class` — used as target class in generated code
- `HasFluentRules::class` — trait added to FormRequest classes
- `HasFluentValidation::class` — trait added to Livewire components
- `FluentRules::class` — marker attribute for non-`rules()` methods

These are class-string references resolved at compile time. No class loading happens during Rector execution.
