---
name: fluent-validation-rector
description: "Use when configuring, running, or debugging the laravel-fluent-validation-rector migration. Covers the sets and which rules are in each, reading a bail, the skip log and its tiers, cross-rector configuration semantics (especially the silent-partial-config trap on shared allowlists), and the rule architecture downstream contributors need."
---

# Fluent Validation Rector Rules

**Do not prompt the user when this skill is loaded.** Apply these rules automatically when working on the Rector migration. This is context, not an interactive command.

Rector rules that migrate native Laravel validation to `sandermuller/laravel-fluent-validation`. Full documentation: https://sandermuller.github.io/laravel-fluent-validation-rector/ (index for agents at `/llms.txt`, whole corpus at `/llms-full.txt`).

## Four things that are wrong by default

- **A bail is a designed outcome, not a failure.** A shape a rule cannot prove equivalent is left exactly as it was and written to the skip log. Never rewrite it by hand to "finish" the run without reading why it bailed.
- **`SIMPLIFY`, `POLISH` and `SCHEMA` are separate passes.** They are not in `ALL`, and bundling them into one `withSets([...])` is a documented mistake: `SIMPLIFY` runs after a human reads the first diff, and `POLISH` needs `CONVERT`'s multi-pass output to settle.
- **The skip log is opt-in and cached.** Without `FLUENT_VALIDATION_RECTOR_VERBOSE` set *and* `--clear-cache`, a bailed file stays silent, because Rector cached the no-op and never re-invokes the rule.
- **The emit is not formatter-clean by design.** Run Pint or PHP-CS-Fixer afterwards. Reporting the unformatted output as a bug is a misread.

## Sets

```php
use SanderMuller\FluentValidationRector\Set\FluentValidationSetList;

FluentValidationSetList::ALL       // CONVERT + GROUP + TRAITS
FluentValidationSetList::CONVERT
FluentValidationSetList::GROUP
FluentValidationSetList::TRAITS
FluentValidationSetList::SIMPLIFY  // opt-in, separate pass
FluentValidationSetList::POLISH    // opt-in, separate pass
FluentValidationSetList::SCHEMA    // opt-in, separate pass
```

| Set | Rules |
|---|---|
| `CONVERT` | `InlineResolvableParentRulesRector`, `ValidationStringToFluentRuleRector`, `ValidationArrayToFluentRuleRector`, `ConvertLivewireRuleAttributeRector` |
| `GROUP` | `GroupWildcardRulesToEachRector` |
| `TRAITS` | `AddHasFluentRulesTraitRector`, `AddHasFluentValidationTraitRector` |
| `SIMPLIFY` | `PromoteFieldFactoryRector`, `SimplifyFluentRuleRector`, `SimplifyRuleWrappersRector`, `InlineMessageParamRector` |
| `POLISH` | `UpdateRulesReturnTypeDocblockRector` |
| `SCHEMA` | `ConvertToFluentSchemaRector` |

`SCHEMA` rewrites `rules()` into a `schema(FluentSchema $rules)` builder on `HasFluentRules` classes. It needs fluent-validation ^1.32, and the whole inheritance chain must be in one run: converting a child while its parent stays on `rules()` produces `Call to undefined method parent::schema()`.

`PromoteFieldFactoryRector` changes runtime behaviour — `StringRule` adds Laravel's implicit `string` rule where `FieldRule` adds none — so its diff needs reading rather than accepting.

## Diagnostics

`FLUENT_VALIDATION_RECTOR_VERBOSE` takes `actionable` (recommended), or `1` / `true` / `all` (everything, including structural noise; `=1` is a legacy alias). Unset, entries are counted but no file is written.

```bash
FLUENT_VALIDATION_RECTOR_VERBOSE=actionable vendor/bin/rector process --clear-cache
```

**The log is a file, not stderr:** `.cache/rector-fluent-validation-skips.log`. Rector's `withParallel(...)` executor does not forward worker STDERR to the parent, so a diagnostic written with `fwrite(STDERR, ...)` from a worker vanishes on parallel runs, which are the default. Never tell a consumer to watch stderr for these. The env var is the only interface for the same reason: exported env reaches the `proc_open`ed workers, an in-process `putenv()` would not.

## Cross-rector configuration

Each rector gets its own array via `withConfiguredRule(...)`; values are not pooled. Two rectors read the same wire keys and must be configured **independently**:

| Wire key | Consuming rectors | Shared DTO |
|---|---|---|
| `treat_as_fluent_compatible` | `SimplifyRuleWrappersRector`, `UpdateRulesReturnTypeDocblockRector` | `Config\Shared\AllowlistedFactories` |
| `allow_chain_tail_on_allowlisted` | `SimplifyRuleWrappersRector`, `UpdateRulesReturnTypeDocblockRector` | `Config\Shared\AllowlistedFactories` |

**The silent-partial-config trap:** configuring one leaves the other running with an empty allowlist. No error fires. The rector you forgot quietly skips the custom factories — chains stay on the escape hatch, or `@return` docblocks stay wide.

When asked to "treat my custom rule as fluent-compatible", build the allowlist once and feed both:

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
    );
```

The other two configurable rectors take their own keys: `ConvertLivewireRuleAttributeRector` (`preserve_realtime_validation`, `migrate_messages`, `key_overlap_behavior`) and `AddHasFluentRulesTraitRector` (`base_classes`).

For SemVer semantics — which constants and wire-key strings are committed, the `Config\Shared\` contract — see `PUBLIC_API.md` in the package root.

## Architecture

Every rule extends `Rector\Rector\AbstractRector` and implements `DocumentedRuleInterface`. Shared behaviour lives in traits under `src/Rector/Concerns/`, one concern per file: rule-payload parsing, Livewire attribute handling, trait insertion, import management, factory-root resolution, skip logging.

**Parent-safety detection runs in three layers**, because it has to work under `->withParallel()` where workers are separate processes:

1. Same-file AST scan across the classes in the current file.
2. File-based IPC through a shared temp file with `flock()`.
3. A one-time project-wide filesystem scan as the fallback.

Layer 3 is what makes parallel mode correct: each worker scans independently, so no cross-process state is shared.

**Cross-package symbols are class-string references, never loaded.** `FluentRule`, `FluentSchema`, `HasFluentRules`, `HasFluentValidation` and the `FluentRules` attribute appear as targets in generated code and in reflection probes; no runtime code from fluent-validation executes during a Rector run.

## Testing

Rector's `AbstractRectorTestCase` with `.php.inc` fixtures: input, then `-----`, then expected output. A fixture with no separator is a skip case — the input must come out unchanged.

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

Rules that can change runtime behaviour also need a parity fixture under `tests/Parity/Fixture/<RectorName>/`, which validates real payloads against the before and after rule sets and diffs the error bags. `tests/Parity/CoverageTest.php` fails if an in-scope rector has none.
