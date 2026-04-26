# Contributing

Thanks for considering a contribution. This package is a Laravel-package
distributed via Composer (no host application). Read [CLAUDE.md](CLAUDE.md)
for the full package-development conventions; the points below cover the
day-to-day flow.

## Local setup

```bash
git clone https://github.com/sandermuller/laravel-fluent-validation-rector
cd laravel-fluent-validation-rector
composer install
```

The package has no `php artisan`. Use Composer-installed binaries:

| Task                  | Command                                            |
|-----------------------|----------------------------------------------------|
| Run tests             | `vendor/bin/pest`                                  |
| Filter one test       | `vendor/bin/pest --filter=TestName`                |
| Static analysis       | `vendor/bin/phpstan analyse --memory-limit=2G`     |
| Rector self-process   | `vendor/bin/rector process`                        |
| Code style            | `vendor/bin/pint --dirty --format agent`           |
| Testbench tinker      | `vendor/bin/testbench tinker`                      |

## Workflow

1. **Spec-first for non-trivial work.** Behavioral changes, new rectors,
   or anything affecting the documented public API surface should land
   with a spec in `specs/` first (use the `write-spec` skill). Bug fixes
   and small refactors do not need a spec.
2. **Branch off `main`.** Keep branches scoped: one feature or one fix.
3. **Tests are mandatory.** Every behavioral change must ship with
   coverage. Existing tests are the package's specification — adding a
   "verification script" instead of a test is rejected.
4. **Run pint dirty + phpstan + pest before pushing.** The
   `pre-release` skill runs the full pre-tag checklist (Rector, Pint,
   Pest, PHPStan, README/`.ai/` staleness audit) — invoke it before
   tagging a release.

## Commit message style

Follow the shape of `git log --oneline -20`. Semantic prefixes are
expected (`feat:`, `fix:`, `docs:`, `chore:`, `ci:`, `test:`,
`refactor:`); scopes optional (`feat(diagnostics):`). Keep the subject
under 70 chars; use the body to capture the *why* when it isn't obvious
from the diff.

## Public API discipline

Every `public` symbol on a class/trait/interface listed in
[PUBLIC_API.md](PUBLIC_API.md) is a semver-governed surface. Renames
require a MAJOR bump. If you're unsure whether a change is breaking,
flag it in the PR description and the [Versioning policy](README.md#versioning-policy)
covers the bump-class table.

Symbols not on the public list carry an `@internal` class-level PHPDoc
tag. If you add a new helper class under `src/`, either add it to the
public list (rare — discuss first) or mark it `@internal`. The
`InternalAuditTest` enforces this automatically.

## CHANGELOG

`CHANGELOG.md` is auto-populated by CI on tag. Do not hand-edit it. To
ship release notes for a version, write `RELEASE_NOTES_<version>.md` at
the repo root — the release job promotes its content into the changelog.

## Pull requests

- Reference the spec or issue you're addressing in the PR body.
- Note any user-visible changes or semver impact.
- Update README or `.ai/` documentation when the user-visible behavior
  shifts (the `pre-release` skill audits this).
