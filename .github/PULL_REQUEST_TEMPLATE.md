<!--
Thanks for contributing. Please complete the checklist below.
The release-notes line is critical for any user-visible change.
-->

## Summary

<!-- 1-3 bullets on what changed and why. -->

## Type of change

- [ ] Bug fix (PATCH)
- [ ] New feature (MINOR — additive only)
- [ ] Breaking change (MAJOR — see [Versioning policy](../README.md#versioning-policy))
- [ ] Documentation only
- [ ] Internal refactor / chore (no observable effect)

## Checklist

- [ ] Tests added or updated for the changed behavior
- [ ] Fixture-per-bug if this fixes a regression
- [ ] `vendor/bin/pint --dirty --format agent` clean
- [ ] `vendor/bin/phpstan analyse --memory-limit=2G` clean
- [ ] `vendor/bin/pest` passes
- [ ] `vendor/bin/rector process` produces no changes
- [ ] README or `.ai/` updated if behavior is user-visible
- [ ] [PUBLIC_API.md](../PUBLIC_API.md) updated if a public symbol or wire key was added/renamed
- [ ] `RELEASE_NOTES_<version>.md` updated if shipping the next tag

## Semver impact

<!--
If this is MINOR or MAJOR, name the symbol(s) added or changed and the
rationale. Reference PUBLIC_API.md anchors when relevant.
-->

## Linked issue / spec

<!-- Closes #N — or — implements specs/<filename>.md phase X -->
