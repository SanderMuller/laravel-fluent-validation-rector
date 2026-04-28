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
