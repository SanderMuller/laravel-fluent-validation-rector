# Diagnostics

The skip log is **opt-in**. A default run still counts skips and reports the total, but writes no file:

```
[fluent-validation] 42 skip entries. Re-run with FLUENT_VALIDATION_RECTOR_VERBOSE=actionable and --clear-cache for details.
```

`FLUENT_VALIDATION_RECTOR_VERBOSE` takes three values, case-insensitive:

| Value | Surfaces |
|---|---|
| unset | **off.** Always-actionable entries are counted, nothing is written |
| `actionable` | **recommended.** Payloads needing manual migration, stale `@return` docblocks and the like, without structural noise |
| `1` / `true` / `all` | **everything**, including the noise. `=1` stays an alias so existing CI scripts keep working |

```bash
FLUENT_VALIDATION_RECTOR_VERBOSE=actionable vendor/bin/rector process --clear-cache
```

**`--clear-cache` matters.** Rector caches per-file results, and a file that bailed produced no transformation, so its skip entry is written once and the rule is not re-invoked on a cached run. Clear the cache (or delete `.cache/rector*`) to have every bail re-logged.

The difference between the tiers is large in practice: a five-component Laravel 12 / Filament v5 app measured 110 entries at `=all` against 5 at `=actionable` on the same surface.

<details>
<summary>Log location, format, and why the flag is env-only</summary>

Any opt-in tier writes `.cache/rector-fluent-validation-skips.log`, plus a `.session` sentinel coordinating truncation across parallel workers, and the end-of-run line points at it. `.cache/` matches Rector's own convention, so most projects already ignore it.

The first line is a per-run header (package version, ISO-8601 UTC timestamp, verbose tier), which keeps diffs stable across releases in CI:

```
# laravel-fluent-validation-rector 1.9.0 — generated 2026-05-06T11:47:12Z
# verbose tier: actionable

[fluent-validation:skip] ...
```

The header is emitted even on a zero-entry run, so the file's existence is stable.

**Env-only is deliberate.** The flag has to reach parallel workers, which are fresh PHP processes spawned via `proc_open`. Exported env inherits automatically; an in-process `putenv()` would not.

**The sink is a file for the same reason.** Rector's `withParallel(...)` executor does not forward worker STDERR to the parent, so a line written with `fwrite(STDERR, ...)` from a worker vanishes on parallel runs, which is the default. A file survives worker death and can be read after the run. Worth knowing if you write your own Rector rules: `withParallel()` plus STDERR means silent data loss.

</details>
