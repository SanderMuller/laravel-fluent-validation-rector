<package-boost-guidelines>
# Package Boost Guidelines

These guidelines replace Laravel Boost's default foundation for
repositories that are **Laravel packages**, not applications. The
framing, tooling, and trade-offs differ — follow this version when
working inside a package codebase.

## Foundational Context

This codebase is a **Laravel package** distributed via Composer, not a
Laravel application. Key consequences:

- There is no `artisan`, no `app/`, no `bootstrap/`, no `routes/`, no
  `.env`, and no database by default. A Testbench-provided Laravel
  application is spun up only at test time.
- The primary artefact is the package's public API (service provider,
  facades, classes) — everything else is scaffolding.
- Downstream apps consume this package. Every public change is a
  user-facing API change governed by semver.
- `composer.json` is the source of truth for supported PHP and
  Laravel versions. Check `require.php` and `require.illuminate/*`
  before using version-specific features.

## Use `vendor/bin/testbench`, not `php artisan`

Running artisan commands directly against the package fails — there is
no host application. Use Testbench's binary:

| Instead of | Use |
|---|---|
| `php artisan test` | The package's configured test runner (`vendor/bin/pest` or `vendor/bin/phpunit`) |
| `php artisan tinker` | `vendor/bin/testbench tinker` |
| `php artisan make:*` | Create files manually under `src/` |
| `php artisan vendor:publish` | `vendor/bin/testbench vendor:publish` |

### Commands that require `laravel/boost`

These only apply when the package has `laravel/boost` as a dev
dependency. Skip if Boost isn't installed — `package-boost:sync`
prints a warning and moves on.

| Instead of | Use |
|---|---|
| `php artisan boost:install` | `vendor/bin/testbench boost:install` |
| `php artisan boost:mcp` | `vendor/bin/testbench boost:mcp` |

Register the package's service provider in `testbench.yaml` under
`providers:` so Testbench boots it. Published files land in
`workbench/` by default, not `config/` or `resources/` of a host app.

## Source Layout

- `src/` — package source, PSR-4 autoloaded per `composer.json`
- `tests/` — Pest or PHPUnit suite, base case `Orchestra\Testbench\TestCase`
- `config/` — publishable defaults (the file shipped with the package)
- `resources/` — views, translations, Boost skills / guidelines
- `database/migrations`, `database/factories` — only if the package
  ships them
- `workbench/` — developer-only Testbench scaffolding; never shipped

Check sibling files before inventing structure. Do not introduce new
top-level directories without a clear reason.

## Cross-Version Compatibility

Supporting multiple Laravel / PHP majors is routine for packages.
Activate `cross-version-laravel-support` **before** writing the
code; activate `ci-matrix-troubleshooting` **after** a matrix cell
has failed.

## Conventions

- Match existing code style, naming, and structural patterns — check
  sibling files before writing new ones.
- Use descriptive names (`resolvePublishDestination`, not `resolve()`).
- Reuse existing helpers before adding new ones.
- Do not add dependencies without approval; every new `require` is a
  constraint downstream consumers inherit.

## Tests Are the Specification

The package has no running application to click through. Tests are how
behaviour is pinned down.

- Write tests alongside any behavioural change. Feature tests through
  Testbench are preferred over ad-hoc tinker scripts.
- Do not create "verification scripts" when a test can prove the same
  thing.
- Run the project's configured test runner (`vendor/bin/pest` or
  `vendor/bin/phpunit`) before claiming a change is done.

## Public API Discipline

- Every `public`, `protected`, or exported symbol is part of the
  package's surface. Breaking changes require a major version bump.
- Prefer `final` classes and `private`/`@internal` markers for anything
  not intended for extension.
- Keep config keys, published asset paths, and service container
  bindings stable across patch and minor versions.

## Documentation Files

Only create or edit documentation (README, CHANGELOG, docs/) when
explicitly requested or when a behaviour change requires it.

## Replies

Be concise. Focus on what changed and why. Skip restating what the
diff already shows.

---

## Public API discipline

The package's public API surface is enumerated in `PUBLIC_API.md` at the repo
root. Every symbol listed there is governed by SemVer 2.0 — renames or
signature changes require a MAJOR bump. Symbols not listed are `@internal`
and may change in any release.

### When adding a public symbol

A class is public when it lives under `src/` outside the
`SanderMuller\FluentValidationRector\Internal\` namespace AND lacks an
`@internal` PHPDoc tag. If you add a class, public method, or public
constant matching this shape, **update `PUBLIC_API.md` in the same
commit**:

- Add the FQN under the appropriate section heading (Set list constants,
  Rector class FQNs, Rector configuration constants, etc.).
- If introducing a new section, add it to the existing structure rather
  than creating a parallel doc.
- Update `tests/InternalAuditTest::PUBLIC_CLASSES` so the namespace audit
  test recognizes the new symbol as intentionally public (otherwise it
  fires "missing @internal tag" on the next test run).

If the symbol is internal — anything intended as implementation detail
that consumers should NOT import — place it under
`src/Internal/` (namespace `SanderMuller\FluentValidationRector\Internal\`).
The namespace IS the structural commitment; the `@internal` PHPDoc tag is
optional inside that namespace.

### When deleting or renaming a public symbol

Direct removal of a public symbol is a MAJOR-bump-only event. For pre-MAJOR
releases:

- **Add a deprecation cycle.** Keep the old name as a `class_alias` (for
  classes) or method-level `@deprecated` PHPDoc tag (for methods),
  pointing at the new name.
- **Document the cycle in `PUBLIC_API.md`** — note when the deprecation
  shipped and when the removal is slated. Example: 0.20.0's `Diagnostics` /
  `RunSummary` namespace move added shims at the old location with
  `@deprecated since 0.20.0 — removal slated for 1.0`; the shims were
  removed in 0.22.0 (the 1.0 RC scope-lock release).
- **Mention it in the release notes** under a "Deprecations" section so
  consumers see the timeline.

### `Internal\` namespace as a do-not-import signal

Classes under `SanderMuller\FluentValidationRector\Internal\` are
implementation detail. They are NOT part of the public API:

- Their existence, signatures, and behavior may change in any release.
- Downstream consumers importing them are doing so against the
  documentation — breakage is on them.
- The namespace placement is the do-not-import signal. The PHPDoc
  `@internal` tag is optional but harmless inside `Internal\`.

When designing a new helper, default to `Internal\`. Only place a class
in the public namespace root or `Set\`/`Config\`/`Config\Shared\` when
it's an intentional consumer-facing surface.

### When in doubt

Default to `@internal`. Promoting a class from internal to public later is
a non-event (just add the `PUBLIC_API.md` entry); demoting a public class
back to internal requires a deprecation cycle and a MAJOR bump. The
asymmetric cost makes "internal until proven otherwise" the safe default.

## Release Notes vs CHANGELOG

`CHANGELOG.md` is **auto-populated by CI** on release. Do not hand-edit it.

When you need to document a user-facing change for a release, write it to `RELEASE_NOTES_<version>.md` at the repo root (already gitignored via the `RELEASE_NOTES*.md` pattern). The CI release job picks it up and promotes it into `CHANGELOG.md` as part of the tag flow.

If you find yourself editing `CHANGELOG.md` directly, stop — it will be overwritten.

## Verification Before Completion

Before claiming any work is complete or successful, run the verification command fresh and confirm the output. Evidence before claims, always.

### Required Before Any Completion Claim

1. **Run** the relevant command (in the current message, not from memory)
2. **Read** the full output
3. **Confirm** it supports the claim
4. **Then** state the result with evidence

### During Development (after each change)

| Claim            | Required verification                              |
|------------------|----------------------------------------------------|
| Code style clean | `vendor/bin/pint --dirty --format agent` output    |
| Tests pass       | Related tests pass via `--filter` or specific file |
| Bug fixed        | Previously failing test now passes                 |

### At Completion Only (feature/phase done, before PR)

These are slow checks — only run them once at the very end:

| Claim             | Required verification                                           |
|-------------------|-----------------------------------------------------------------|
| Rector ran clean  | `vendor/bin/rector process` showing 0 changes                   |
| PHPStan clean     | `vendor/bin/phpstan analyse --memory-limit=2G` showing 0 errors |
| Full suite passes | `vendor/bin/pest` output showing 0 failures                     |
| Feature complete  | All above checks pass                                           |

### Always Capture Command Output

Append `|| true` to all verification commands (tests, linting, type checks) so the output is always captured, even on failure. Without it, a non-zero exit code can hide the output, forcing an expensive second run just to read the errors.

```bash
# CORRECT — output always visible
vendor/bin/pest --filter=testName || true
vendor/bin/pint --dirty --format agent || true

# WRONG — output lost on failure, wastes time re-running
vendor/bin/pest --filter=testName
```

### Never Use Without Evidence

- "should work now"
- "that should fix it"
- "looks correct"
- "I'm confident this works"

These phrases indicate missing verification. Run the command first, then report what actually happened.
</package-boost-guidelines>
