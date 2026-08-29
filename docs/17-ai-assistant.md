# AI assistant integration

The package ships an agent [skill](https://docs.claude.com/en/docs/claude-code/skills) at `resources/boost/skills/fluent-validation-rector/`. It carries what an agent needs to drive the migration rather than guess at it: the set lists and what each contains, the rule architecture, and the cross-rector configuration semantics. That includes the silent-partial-config trap, where configuring an allowlist on one of the two rectors that read it leaves the other running empty, with no error.

With [`laravel/boost`](https://github.com/laravel/boost) installed the skill is discovered from the installed package:

```bash
php artisan boost:install
```

Any Boost-compatible agent picks it up: Claude Code, Cursor, Copilot.

## What it changes

Without the skill, an agent asked to "make rector treat my custom rule as fluent-compatible" typically writes one `withConfiguredRule(...)` call. That is a partial migration: `SimplifyRuleWrappersRector` then simplifies chains on the class while `UpdateRulesReturnTypeDocblockRector` quietly declines to narrow the docblocks that use it, or the reverse. The skill states the shared-instance pattern, so both calls come out of one `AllowlistedFactories`.

It also stops the two mistakes this documentation keeps repeating, because an agent reads the skill before it reads a page: that `SIMPLIFY`, `POLISH` and `SCHEMA` are separate passes rather than something to bundle into `ALL`, and that a bail is a designed outcome to read in the [skip log](15-diagnostics.md), not a failure to work around.

## Reading the docs directly

The published site also serves plain text for readers that are not browsers:

| URL | Holds |
|---|---|
| [`/llms.txt`](https://sandermuller.github.io/laravel-fluent-validation-rector/llms.txt) | the index: the package's rules up front, then one line per page |
| [`/llms-full.txt`](https://sandermuller.github.io/laravel-fluent-validation-rector/llms-full.txt) | every page in reading order, in one fetch |
| any page URL plus `.md` | that page alone, without the HTML |
