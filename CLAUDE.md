## Project Conventions

```yaml
schema-version: 1
github:
  owner: sandermuller
  repo: laravel-fluent-validation-rector
testing:
  backend_framework: pest
```

## Anonymize Fixtures, Docs, and Specs

This is a **public, open-source repository**. Test fixtures, the rule
`CodeSample` heredocs in `src/`, the snippets in `README.md`, and the spec files
in `specs/` are all world-readable on GitHub — and `src/` plus the README also
ship in the Composer dist archive. `tests/` and `specs/` (and the AI/config
dirs) are `export-ignore`d in `.gitattributes`, so they stay out of the
archive, but that only trims the dist; it hides nothing on GitHub. Doc examples
and specs are the easy things to forget precisely because they are not
"fixtures" — they leak just the same.

Every example — fixture, `CodeSample`, doc snippet, or spec — must be
**synthetic**. Never copy proprietary application code — from hihaho or any
consumer/dogfooding codebase — into one. Reconstruct the smallest generic
example that demonstrates the rule, then strip every domain detail not needed
to make the point.

This keeps internal domain models, naming, business terms, and logic out of a
public artifact, and it makes for better examples: the transformation stands
out instead of being buried in incidental domain noise.

## Anonymize these

- **Class and namespace names** — use framework-conventional placeholders
  (`App\Http\Requests\StorePostRequest`, `App\Models\Article`). Don't reach for
  the product's real domain entities.
- **Variable, property, and method names** that carry domain meaning.
- **String literals** — validation field names, route paths, table and column
  names, config keys, labels, messages. Invent neutral values; never paste a
  real schema column or form field key.
- **Business terminology and comments** lifted from real code.
- **Logic and control flow** that mirrors a real implementation.

## Keep these — they are not leaks

- **Framework and vendor public symbols** (`Illuminate\…`, `FluentRule`,
  `HasFluentRules`, `FormRequest`, `Rule`, `Validator`). The rule usually has to
  match these to fire, and they are public API.
- **Generic example nouns** — `User`, `Post`, `Order`, `Article`, `Comment`.
- **The convention the rule enforces** (string-rule → fluent conversions,
  trait insertion, `each()` folding). That is the package's public contract,
  not proprietary.

## Specs leak provenance, not just code

A spec in `specs/` rarely contains a real schema column — its leak vector is
**provenance metadata** describing where the work came from. Scrub all of it:

- **Internal PR / issue / ticket numbers** ("modelled on PR #1234",
  "ABC-123"). Describe the *change* generically ("a manual validation cleanup")
  instead of citing the source. (Don't reference a real PR number here either —
  these examples are deliberately fake.)
- **Employee names, handles, and authorship** of the originating work.
- **Real domain method / class names** copied from the source change, even in
  prose. Use the same neutral placeholders the spec's code examples use.
- **Dogfooding / consumer-app references** ("from the hihaho app", file/line
  counts of a private PR).

State *what* the rule does and *why*, never *which internal change or person*
it came from.

## Rule of thumb

An example should read like a generic framework tutorial snippet, not like a
slice of one company's application. If a reader could tell which product it
came from, anonymize further — prefer a neutral noun (`Article`, `Order`) over
an actual product entity.

## When adding or editing a fixture, doc example, or spec

1. Keep only the framework symbols the rule matches against.
2. Replace real names and strings with neutral equivalents.
3. For a fixture, run the test to confirm the rule still fires
   (`vendor/bin/pest path/to/RuleTest.php`); for a `CodeSample` or README
   snippet, keep it consistent with the rule it documents; for a spec, strip
   provenance (see "Specs leak provenance" above).
4. Before committing, scan the diff for product names, real table/column
   names, domain jargon, and internal PR/ticket/person references — across
   `specs/` and `README.md` too, not only `tests/` and `src/`.

---

## Performance benchmarks

This package's rules run per-AST-node during Rector traversal, so the hot path is
the rule pipeline in `src/Rector/` — and the fast-path rule-string cache in
`src/Rector/Concerns/ConvertsValidationRuleStrings.php`.

Performance work goes through the **autoresearch** loop, not a Pest group. The
benchmark scripts live in `autoresearch/` and run this package's rules against a
synthetic consumer corpus, measuring wall-clock via `hrtime()`:

```bash
php autoresearch/rule-pipeline-bench.php   # whole-pipeline wall-clock
php autoresearch/per-rule-profile.php      # per-rule breakdown
```

Benchmark when touching the rule pipeline or its caches: capture a baseline,
make one change, re-run, and keep it only if the metric improves. The
`autoresearch` skill drives the full measure → change → keep-or-revert loop —
activate it for any sustained optimization work.

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

---

## Authoring Rector Rules

Repo-specific conventions and gotchas for adding a rule under `src/Rector/`.
For the general mechanics of building a rule, follow the vendor
`rector-developer` skill — this guideline only covers what bites in *this*
package.

## Gate cheaply, resolve names once

The per-node bottleneck is the name-resolver machinery, not your refactor
logic. `refactor()` runs on every matching node in the consumer's codebase, so:

- Bail at the **file** level first with the `ShortCircuitsIrrelevantFiles`
  trait — a `str_contains()` needle check (`FluentRule`, `'required`,
  `extends FormRequest`) that skips the vast majority of consumer files before
  any per-node work, cached per file path.
- Gate each node with a direct `instanceof` check on its `name` / `class`
  (`$node->name instanceof Identifier`, `$node->class instanceof Name`). This
  bails most surviving nodes before any name resolution.
- Then resolve the name **once** (`$this->getName($node)`) and compare —
  never loop `isName()` / `isNames()` across accepted spellings.
- PHP function, class, **and method** names are all case-insensitive, so match
  with `strtolower()` / `strcasecmp()` rather than an exact `toString()`
  comparison — a `MethodCall` / `StaticCall` rule that compares the method name
  exactly silently skips valid mixed-case calls (e.g. `FluentRule::String()`).

`ShortCircuitsIrrelevantFiles` is the canonical file-gate shape; the hot path
itself is the rule-string cache in
`src/Rector/Concerns/ConvertsValidationRuleStrings.php`.

## `provideMinPhpVersion()`: use `PhpVersion::PHP_*`, not a new `PhpVersionFeature::*`

`PhpVersionFeature` feature aliases can be **newer than the `rector/rector`
floor** in `composer.json` (`^2.4.1`). `PhpVersionFeature::NAMED_ARGUMENTS`, for
instance, does not exist in the floor and throws `Undefined constant` on the CI
`prefer-lowest` leg — green locally (latest Rector), red in CI.

Return a stable `Rector\ValueObject\PhpVersion::PHP_8X` constant instead (e.g.
`PhpVersion::PHP_80` for a PHP-8.0 feature), unless you have confirmed the
feature alias exists in the floor. The `PhpVersion::PHP_*` values are
long-stable.

## Test-double `Source/` classes must be Rector-clean

`rector.php` processes `tests/`, and the CI **Auto-fix** workflow
(`.github/workflows/auto-fix.yml`) runs `vendor/bin/rector process` on **every
push to any branch** (the `push:` trigger has no branch filter) and commits the
result as `chore: auto-fix` directly on the branch — so a feature branch gets
mangled on push just like `main`.

So a reflection test double — a `tests/.../Source/*.php` class (e.g. under
`tests/InlineResolvableParentRules/Source/`) whose method signatures exist only
so a rule can resolve parameter names — gets mangled on push: the deadcode set
strips "unused" parameters (`RemoveUnusedPublicMethodParameterRector`) and
deletes empty-body methods (`RemoveEmptyClassMethodRector`), breaking the
fixtures that depend on those signatures.

Give every `Source` method a body that genuinely uses all its parameters, and
avoid empty bodies. Confirm `vendor/bin/rector process --dry-run` reports
**0 changes before pushing** — otherwise the auto-fix bot rewrites the double
on push and reds the test legs.

---

## AskUserQuestion Phrasing

When writing an `AskUserQuestion` question, option labels, or option descriptions, **avoid first- and second-person pronouns** — `I`, `me`, `my`, `we`, `our`, `you`, `your`. In that tool the user is reading a question *from* the assistant and answering it, so the roles are inverted and these pronouns are ambiguous: the reader cannot tell whether `I`/`my` means the assistant or themselves, nor whether `you`/`your` means them or the assistant.

Name the actor explicitly instead — "the assistant" (these guidelines are shared across agents, so avoid hard-coding a product name like Claude or Copilot) and "the user" (or a concrete role) for the person answering — or rephrase to drop the pronoun entirely.

```text
❌ "Which approach do you want me to take?"
❌ "Should I keep the existing tests you wrote?"

✅ "Which approach should the assistant take?"
✅ "Keep the existing tests, or replace them?"   (pronoun dropped)
✅ "Should the assistant keep the tests already in the repo?"
```

This applies to every part of the question payload: the `question` text, each option `label`, and each option `description`.

---

## Fixing PHPStan Errors

When fixing a PHPStan error, first decide whether it represents a runtime bug a test could catch — and if so, write that test before the fix.

### Process

1. **Assess testability** — does the error represent a runtime bug a test could reproduce (a wrong argument type, a missing method, an incorrect return type used downstream)?
2. **Write the test first** — if a test can catch it, write a failing test that reproduces the error before applying the fix.
3. **Fix the code** — apply the fix so both the PHPStan error and the new test pass.
4. **Verify both** — confirm PHPStan reports no error and the test passes.

### When to Write a Test

Write a test when the PHPStan error indicates a fault that would surface at runtime:

- A method call on a value of the wrong type
- Missing or incorrect arguments to a function or method
- A return-type mismatch that would break callers
- Accessing a property or method that does not exist
- Any type error that would manifest as a runtime exception

### When to Skip the Test

Skip the test when the error is purely static and cannot cause a runtime failure:

- Missing return-type declarations
- PHPDoc mismatches with no runtime impact
- Unused variables or imports
- Generic-type parameter issues

---

## Signed Commits

Applies **only when the repository has commit signing enabled** (e.g. `git config commit.gpgsign` is `true`, or a `user.signingkey` / `gpg.format` is set). If signing is not enabled, this guideline does not apply — commit normally.

### Never fall back to an unsigned commit

When signing is enabled, every commit must be signed. If the signing backend or agent (1Password, `gpg-agent`, `ssh-agent`, a hardware key, etc.) is unavailable, locked, or not responding:

- **Stop and surface the failure** to the user with the exact error.
- **Do not** retry with `--no-gpg-sign`, unset `commit.gpgsign`, or otherwise produce an unsigned commit to "get past" the problem.

A missing signature is a blocker to resolve (unlock the agent, re-authenticate 1Password, plug in the key), not a step to skip. Let the user fix the signing setup, then commit signed.

---

## Verification Before Completion

Before claiming any work is complete or successful, run the verification command fresh and confirm the output. Evidence before claims, always.

### Required Before Any Completion Claim

1. **Run** the relevant command (in the current message, not from memory)
2. **Read** the full output
3. **Confirm** it supports the claim
4. **Then** state the result with evidence

| Claim            | Required verification                                            |
|------------------|------------------------------------------------------------------|
| Tests pass       | The project's test command, output showing 0 failures            |
| Code style clean | The project's formatter/style checker, output showing no changes |
| Linting clean    | The project's linter, output showing 0 errors                    |
| Types check      | The project's type checker, output showing 0 errors              |
| Bug fixed        | The previously failing test now passes                           |
| Feature complete | All related tests pass                                           |

Use the project's own commands — check its `composer.json` / `package.json` scripts, CI config, or sibling docs to find them. Do not assume a specific tool.

### Delegating the checks

Where the project has dedicated quality-check skills synced, delegate to them — `backend-quality` for backend files, `frontend-quality` for frontend files, both when a change spans both. Otherwise, run the project's own equivalent commands directly.

### Never Use Without Evidence

- "should work now"
- "that should fix it"
- "looks correct"
- "I'm confident this works"

These phrases indicate missing verification. Run the command first, then report what actually happened.

---

## FluentRule Validation

- This project uses `sandermuller/laravel-fluent-validation` for type-safe validation rules. Use `FluentRule::` instead of string rules or `Rule::` where possible.
- FormRequests MUST use `HasFluentRules` trait. Livewire components MUST use `HasFluentValidation` trait.
- Do NOT use `->rule('string_rule')` when a native FluentRule method exists. Check the skill references before using escape hatches.
- Available types: `FluentRule::string()`, `integer()`, `numeric()`, `email()`, `date()`, `dateTime()`, `boolean()`, `array()`, `file()`, `image()`, `password()`, `field()`.
- Convenience shortcuts: `FluentRule::url()`, `uuid()`, `ulid()`, `ip()` — shorthand for `FluentRule::string()->url()`, etc.
- `email()` and `password()` use app defaults (`Email::default()`, `Password::default()`). Pass `defaults: false` to opt out.
- All conditional modifiers (`requiredIf`, `excludeIf`, `prohibitedIf`, etc.) accept both `(string $field, ...$values)` AND `(Closure|bool)` — do NOT wrap in `Rule::requiredIf()`.
- For converting validation rules, activate the `fluent-validation-optimize` skill which has a complete method reference.
- For Livewire-specific guidance, activate the `fluent-validation-livewire` skill.

---

# Package Boost Guidelines

These guidelines replace Laravel Boost's default foundation for
repositories that ship as Composer packages — Laravel-targeted or
framework-agnostic. The framing, tooling, and trade-offs differ from
application development; follow this version when working inside a
package codebase.

## Foundational Context

This codebase is a **Composer package**, not an application. The rules
below hold regardless of which framework (if any) the package targets.

- There is no `app/`, `bootstrap/`, `routes/`, `.env`, or database by
  default. Tooling that assumes an application context (e.g. running
  `php artisan` against the package itself) does not apply.
- The primary artefact is the package's public API — entry-point
  classes, service providers, exposed contracts. Everything else is
  scaffolding.
- Downstream consumers depend on this package via Composer. Every
  public change is a user-facing API change governed by semver.
- `composer.json` is the source of truth for supported PHP versions
  and any framework constraints. Check `require.php` (and any
  `require.<framework>/*` entries) before using version-specific
  features.

## Source Layout

- `src/` — package source, PSR-4 autoloaded per `composer.json`
- `tests/` — Pest or PHPUnit suite
- `config/` — publishable defaults shipped with the package, when
  applicable
- `resources/` — views, translations, Boost skills / guidelines, when
  applicable
- `database/migrations`, `database/factories` — only if the package
  ships them
- `workbench/` — developer-only Testbench scaffolding when Testbench
  is in use; never shipped

Check sibling files before inventing structure. Do not introduce new
top-level directories without a clear reason.

## Tests Are the Specification

The package has no running application to click through. Tests are how
behaviour is pinned down.

- Write tests alongside any behavioural change.
- Do not create "verification scripts" when a test can prove the same
  thing.
- Run the project's configured test runner (`vendor/bin/pest` or
  `vendor/bin/phpunit`) before claiming a change is done.

## Public API Discipline

- Every `public`, `protected`, or exported symbol is part of the
  package's surface. Breaking changes require a major version bump.
- Prefer `final` classes and `private`/`@internal` markers for
  anything not intended for extension.
- Keep config keys, published asset paths, and service container
  bindings stable across patch and minor versions.

## Conventions

- Match existing code style, naming, and structural patterns — check
  sibling files before writing new ones.
- Use descriptive names (`resolvePublishDestination`, not `resolve()`).
- Reuse existing helpers before adding new ones.
- Do not add dependencies without approval; every new `require` is a
  constraint downstream consumers inherit.

## Extending boost-core

If your package authors a custom `FileEmitter` (to write a file like
`.mcp.json` into the host during `boost sync`), declare the
`boost-extension` tag in your `boost.php` `withTags([...])`. That pulls
the `writing-file-emitter` skill — gated off by default so consumers
who do not extend the engine don't carry it, which is why an
emitter-authoring package has to opt in explicitly. The same tag pulls
`skill-authoring` for writing boost-family skills.

## Documentation Files

Only create or edit documentation (README, CHANGELOG, docs/) when
explicitly requested or when a behaviour change requires it.

## Replies

Be concise. Focus on what changed and why. Skip restating what the
diff already shows.

---

# Release Automation

Conventions the package-boost family shares for release flow. The
procedural detail lives in the `pre-release` and `release-notes`
skills — loaded on-demand, not pinned here.

## CHANGELOG is CI-managed

`.github/workflows/update-changelog.yml` prepends the release body to
`CHANGELOG.md` on `release: released` and commits to the release's
target branch (typically `main`). Don't hand-edit `CHANGELOG.md` as
part of a release. Post-release typo fixes are committed directly.

## Release notes live in `internal/release-notes-<version>.md`

`internal/` is gitignored — drafts stay local. The notes file becomes
the release body. The first line pins the green commit so the pre-tag
gate can fail closed on drift:

```
<!-- verified-sha: <full sha> -->
```

## Tag and title

- Tag: bare version (`0.7.0`) — Composer and Packagist read the tag.
- Release title: `v`-prefixed (`v0.7.0`) — cosmetic.
- Notes file: bare (`internal/release-notes-0.7.0.md`).

## Agent handoff

Agents stop at the ready-to-tag handoff. The user runs the pre-tag
gate and publishes the release (GitHub UI, `gh`, or otherwise). See
the `pre-release` skill for the full procedure and the no-release-create
rule.
