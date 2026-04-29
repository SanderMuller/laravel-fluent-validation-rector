# Changelog

All notable changes to `sandermuller/laravel-fluent-validation-rector` will be documented in this file.

## 0.22.3 - 2026-04-29

### cold-consumer doc-sync sweep + 1.0 adversarial-pass candidate-pen entries

ACCEPTABLE-case patch on the 0.22.2 saturation cycle. The 1.0
adversarial dogfood pass — the consumer audit panel asked to push
back rather than confirm — surfaced three Q1-side silent-partial-config gaps
in cold-consumer canonical surfaces that the prior PUBLIC_API.md-
only audits hadn't reached. This patch sweeps the full cold-
consumer-first-touch corpus to fix the gap atomically. Plus two
PROCESS.md candidate-pen entries surfaced via the same adversarial
pass and one drift-resilient configuration-pattern recommendation.

If you bumped to 0.22.2 and your `rector.php` configures both
`SimplifyRuleWrappersRector` and
`UpdateRulesReturnTypeDocblockRector` (or you only configured one
of them and observed your custom factories still emitting
"narrow-shape mismatch" warnings on docblock narrow), 0.22.3 is
the patch that explains the pattern across the canonical docs.

If your config covers both rectors already with explicit
`->withConfiguredRule()` calls, 0.22.3 is a no-op for your
runtime behavior — the changes are documentary.

#### The silent-partial-config bug class

Two configurable rectors share wire keys by string value:
`SimplifyRuleWrappersRector` and `UpdateRulesReturnTypeDocblockRector`
both accept `treat_as_fluent_compatible` and
`allow_chain_tail_on_allowlisted`. Per the per-rector configuration
rule, each rector receives its own configuration array via
`withConfiguredRule(...)`; values are not pooled across rectors.

Cold consumers reading the README at 0.22.2 saw the
`UpdateRulesReturnTypeDocblockRector` config section say "Same two
keys as `SimplifyRuleWrappersRector`" without the per-rector
clarifier that landed in PUBLIC_API.md at 0.22.1. Reasonable
mental model: "configure once, applies to both." Actual behavior:
configuring only `SimplifyRuleWrappersRector` leaves
`UpdateRulesReturnTypeDocblockRector` running with empty
allowlist; their custom factories silently skip the narrow-`@return`-tag
predicate, no error fires. The same pattern repeated in the
README DTO worked example (showing `AllowlistedFactories` on one
rector only) and in the package-shipped Boost skill (zero
cross-rector configuration content at all).

This release closes the gap across all three surfaces.

#### README — three doc edits

##### `UpdateRulesReturnTypeDocblockRector` config: per-rector clarifier

Adds a callout under §`UpdateRulesReturnTypeDocblockRector`
config mirroring the PUBLIC_API.md F1 wording from 0.22.1:

> **Per-rector configuration.** Each rector receives its own
configuration array via `withConfiguredRule(...)`; the values
are not pooled across rectors. When the same wire key appears
on both `SimplifyRuleWrappersRector` and
`UpdateRulesReturnTypeDocblockRector`, pass the key on each
rector that consumes it — configuring only one will leave the
other running with a default-empty allowlist (silent partial
config; docblocks won't narrow on your custom factories). The
DTO builder section below shows the recommended shared-instance
pattern.

Names the failure mode explicitly so cold consumers reading the
section understand the bug class before they hit it.

##### DTO worked example: leads with shared-instance pattern

The 0.22.2 README's first DTO example showed
`RuleWrapperSimplifyOptions::default()->withAllowlistedFactories(...)`
on `SimplifyRuleWrappersRector` only. Cold consumers
pattern-matched on this and missed the cross-rector idiom shown
later in the doc.

The 0.22.3 example leads with the shared-instance form: a single
`$allowlist` variable extracted to a local, fed to both rectors
via `RuleWrapperSimplifyOptions::with($allowlist)` and
`DocblockNarrowOptions::with($allowlist)`. Cold consumer pattern-
match on this canonical example produces correct cross-rector
configuration by default.

##### Cross-rector callout reinforces the framing

The "Cross-rector shared DTOs are the canonical multi-rector
form" paragraph that already lived in the README is updated to
reference the silent-partial-config framing alongside the
lockstep-update narrative.

#### `resources/boost/skills/fluent-validation-rector/SKILL.md` — new section

The package-shipped Boost skill propagates to consumer AI
tooling via `vendor/bin/testbench package-boost:sync`. At 0.22.2
the skill was 82 lines of contributor-facing rector-internals
context, with zero mention of `treat_as_fluent_compatible`,
`AllowlistedFactories`, "cross-rector," or any per-rector
configuration semantics. Cold consumers using AI-assisted dev
asked their agent to "configure rector to treat my custom rule
as fluent-compatible" and got single-call form (since that's
what the skill taught), which produces silent partial config at
AI-amplified scale.

0.22.3 ships:

- **Frontmatter description shifted** from "developing, testing,
  or debugging Rector rules" to "configuring, running, or
  debugging the migration." The skill ships to consumers' AI
  tooling, so it should describe consumer use, not contributor
  internals.
- **New "Cross-rector configuration" section** naming the
  per-rector rule, the silent-partial-config pitfall, and the
  shared `$allowlist` recommended pattern. Includes a worked
  example AI agents can copy verbatim.

> **Re-run `vendor/bin/testbench package-boost:sync` after
bumping to 0.22.3** to pick up the updated SKILL.md in your
consumer codebase's `.claude/skills/`, `.github/skills/`, etc.
The package-boost sync isn't automatic on package upgrade.

#### PUBLIC_API.md — Form A constant-reference clarifier

Adds a paragraph after the §`ConvertLivewireRuleAttributeRector`
canonical configuration example clarifying the recommended form
when configuring rectors that share a wire-key string value:

```php
// Self-documenting — symbol matches the rector receiving the config
->ruleWithConfiguration(UpdateRulesReturnTypeDocblockRector::class, [
    UpdateRulesReturnTypeDocblockRector::TREAT_AS_FLUENT_COMPATIBLE => [...],
]);

```
Both forms (self-referencing and cross-referencing the sibling
rector's constant) work today since wire keys are committed
indefinitely. The self-referencing form is drift-resilient: if
either rector adds a new constant later, the form keeps the
consumer's intent explicit at the call site.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.22.2...0.22.3

## 0.22.2 - 2026-04-28

### class_exists symmetry sweep + generalized fixture

ACCEPTABLE-case patch on the 0.22.1 RC scope-lock follow-up. The
0.22.1 saturation re-converge dogfood pass — using a runtime-
simulation methodology — surfaced incomplete fix coverage on
0.22.1's correctness-symmetry fix: the same pattern existed at two
more sites that the 0.22.1 site-specific test fixture didn't catch
by construction. This patch generalizes the fix across all three
known sites and replaces the site-specific fixture with a
registry-driven sweep.

If you bumped to 0.22.1 and observed nothing unusual under
composer-honored install paths, 0.22.2 is a no-op — the fix
addresses the same bypass-constraint failure mode (vendored copies,
monorepo path repositories, hand-edited installed.json, plugin-
development workflows). The interesting changes are:

- The fix completes itself across all three known iteration sites,
  not just the one 0.22.1 caught.
- The test fixture generalizes from BASELINE-specific to a
  registry-driven sweep that catches future fourth sites
  automatically.

#### Source — `class_exists` pre-filter at sites #2 and #3

0.22.1 applied `array_filter(...,'class_exists')` at the
`SimplifyRuleWrappersRector::FACTORY_BASELINE` merge point — the
"correctness-symmetry restored at one point" framing. The 0.22.1
saturation re-converge pass discovered the same pattern at two more
sites in the rector source:

##### Site #2 — `InlineMessageSurface::TYPED_RULE_CLASSES`

```php
foreach (array_filter(self::TYPED_RULE_CLASSES, class_exists(...)) as $class) {
    self::collectTypedRuleAllowlist($class, $allowlist);
}


```
The reflection iteration on line 219 (inside
`collectTypedRuleAllowlist`) previously fataled when
`TYPED_RULE_CLASSES` referenced a class not shipped in the
installed `laravel-fluent-validation` version.

##### Site #3 — `PromoteFieldFactoryRector::TYPED_BUILDER_TO_FACTORY`

```php
foreach (array_filter(array_keys(self::TYPED_BUILDER_TO_FACTORY), class_exists(...)) as $class) {
    $reflection = new ReflectionClass($class);
    ...
}


```
Same pattern — `array_filter` wrapping `array_keys` to filter the
class FQCNs before the `ReflectionClass` call.

##### Cross-rector invariant restated

The original 0.22.1 reframing scoped the correctness-symmetry to
one rector. The 0.22.2 sweep restates it more broadly:

> Any rector iterating a hardcoded class-typed const table for
reflection must filter for `class_exists` before reflecting on
entries.

Three known sites, all now compliant. The 0.22.2 fixture pair
encodes this as a regression-pin contract.

#### Tests — registry-driven sweep replaces site-specific fixture

`tests/RectorInternalContractsTest.php` retires the 0.22.1
site-specific `testFactoryBaselineClassesAllResolve` in favor of a
registry-driven shape:

- `HARDCODED_CLASS_TABLES` const lists every rector class iterating
  a hardcoded class-typed const table for reflection. Future
  rectors register here rather than via a new test method per site.
- `testEveryHardcodedClassTableResolves` sweeps the registry and
  asserts every FQCN entry resolves under the installed sister-
  package surface.
- `testRectorClassesWithHardcodedTablesBootCleanly` exercises the
  boot/load path on each registered rector under the current
  installed surface.

Future BASELINE additions that race ahead of the sister-package
constraint bump fail the sweep test before shipping. Future fourth-
site iterations get coverage by registering with the const, not by
writing a new test.

##### Source-grep / AST static-guard sweep — deferred

A static-analysis test asserting "every iteration over a class-typed
const has a `class_exists` guard somewhere" was prototyped during
0.22.2 development but dropped: the assignment-then-iteration pattern
in `SimplifyRuleWrappersRector` (`$factoryMap = array_filter(...)`
followed by a separate `foreach ($factoryMap as $class)`) doesn't
fit a clean grep, and tightening to AST analysis is past the cost-
value threshold for a 3-site invariant. Defer until a 4th site lands
or a simpler invariant-encoding shape emerges from a future cycle.

The two shipping fixtures cover the static-existence and
current-surface boot invariants. The runtime-simulation
("delete-class + verify graceful boot") fixture pattern is a
methodology in PROCESS.md's candidate-pen — when that methodology
promotes to numbered status, the fixture lands alongside.

#### Process — second candidate-pen entry: runtime-simulation methodology

`specs/PROCESS.md` candidate-pen gains a second entry alongside the
0.22.1 cross-package constraint pre-flight. The runtime-simulation
methodology (delete vendor class file, re-run rector, observe boot
behavior) caught the incomplete-coverage gap that real-consumer-
shape lenses couldn't have caught by construction.

Three epistemic anchors now gate candidate-pen promotion:

1. **Premature canonicalization is the failure mode.**
2. **Invariants earn their slot, they don't get one by appearing.**
3. **Methodologies earn their slot the same way invariants do** —
   via independent multi-cycle surfacing, not single-cycle utility.

Plus an implicit corollary on saturation:

> Saturation requires both (a) consumer-shape signal-stability AND
(b) methodology-surface stability. A new methodology surfacing a
new finding mid-cycle is itself a saturation reset condition, not
just a new lens reading.

The first candidate-pen entry (cross-package constraint pre-flight,
surfaced 0.22.0) gains a cross-rector observation note: the original
site-specific framing restates more broadly as the operational
invariant codified by this release's fixture pair. The dogfood-side
pre-flight catches the same class of failure from the methodology
side.

#### Recommended action

Bump from 0.22.1 → 0.22.2. No `rector.php` changes required; no
behavior changes for any path that worked under 0.22.1.

If you observed an opaque `ReflectionException` from
`InlineMessageSurface::collectTypedRuleAllowlist()` or
`PromoteFieldFactoryRector::classesWithPublicMethod()` when bumping
the rector against a non-current sister-package version, the new
`array_filter(..., class_exists(...))` guards make the boot
graceful. The accompanying generalized fixture (`testEveryHardcodedClassTableResolves`)
catches future BASELINE-vs-constraint drift across all three sites
locally before it ships.

After 0.22.2 saturates the panel, 1.0 ships as a tag-only
commitment cut from the same SHA.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.22.1...0.22.2

## 0.22.1 - 2026-04-28

### patch from the 1.0 RC three-lens dogfood pass

ACCEPTABLE-case patch on the 0.22.0 RC scope-lock release. The
three-lens consumer dogfood panel surfaced one source-side
correctness fix, three doc-clarity edits, and motivated two new
mechanical-enforcement test fixtures. Everything bundles into a
single patch so 1.0 ships from a fresh-saturation green panel
rather than a tag with deferred items.

If you bumped to 0.22.0 and observed nothing unusual, 0.22.1 is a
no-op for compliant consumers. The interesting changes are
defensive (graceful boot under unconventional installs) and
documentary (explicit cross-rector configuration semantics +
heuristic-boundary CI guidance).

#### Source — `FACTORY_BASELINE` `class_exists` symmetry

`SimplifyRuleWrappersRector::bootResolutionTables()` had a
correctness-symmetry gap. The reflection-discovered factory loop
filtered out missing classes via `class_exists` continue:

```php
foreach ($reflection->getMethods(...) as $method) {
    $returnClass = $returnType->getName();
    if (! class_exists($returnClass)) {
        continue;          // missing classes filtered safely
    }
    ...
}



```
But the `FACTORY_BASELINE`-derived iteration further down had no
such guard. `FACTORY_BASELINE` includes class FQCNs as
`::class` constants, which resolve to FQCN strings at parse time
without requiring the class to exist (PHP language semantics). The
table loaded fine on older sister-package versions, but the
reflection iteration on the BASELINE values then fataled when the
class wasn't actually shipped.

Fix: filter the BASELINE at the merge point, mirroring the existing
loop guard.

```php
$factoryMap = array_filter(self::FACTORY_BASELINE, 'class_exists');



```
Single-source filtering, completes existing pattern, degrades
gracefully if a future BASELINE entry races ahead of the
`composer.json` constraint bump on the sister `laravel-fluent-validation`
package. Not a defensive guard against bypassed constraints — a
correctness invariant restored at one point.

Surfaced via vendor-rsync dogfood methodology that bypassed Composer's
constraint resolution; would not surface for consumers using
`composer require` end-to-end. The fix benefits the long tail —
plugin-development workflows, monorepo path repos, vendored copies,
and any other install path where constraint resolution can be
bypassed.

#### PHPDoc — `@internal` on two `InlineMessageParamRector` consts

`InlineMessageParamRector::RULE_OBJECT_KEY_OVERRIDES` and
`PASSWORD_L11_L12_SKIP_TEMPLATE` are `public const` declarations on
a public-API rector class. Both were internal-by-omission: not
listed in `PUBLIC_API.md`, no `@internal` PHPDoc tag.

The doc-only "anything not listed is internal" rule worked legally
under SemVer but wasn't mechanically enforceable — static-analysis
tooling like PHPStan's `internal-class-checked` couldn't flag
external usage as unsupported. Both consts now `@internal`-tagged.
Trivial diff, no behavior change, no SemVer concern.

#### Docs — `PUBLIC_API.md` clarifications

##### Per-rector configuration semantics

The wire-keys section now opens with an explicit clarifier:

> **Per-rector configuration.** Each rector receives its own
configuration array via `withConfiguredRule(...)`. When the same
wire key appears on multiple rectors (e.g.
`'treat_as_fluent_compatible'` on both `SimplifyRuleWrappersRector`
and `UpdateRulesReturnTypeDocblockRector`), pass the key on each
rector that consumes it — the values are not pooled across
rectors.

Reinforced on the `UpdateRulesReturnTypeDocblockRector` constants
and wire-keys subsections (which share keys with
`SimplifyRuleWrappersRector` by string value but are configured
independently).

Closes a real cold-consumer ambiguity — without this clarifier,
the duplicated keys could read as either "shared pool" (configure
once) or "independent" (configure twice). The latter is correct;
the doc now says so.

##### CI-gating guidance for skip-log entry counts

`PUBLIC_API.md` Heuristic boundaries §"Diagnostic granularity"
gains one sentence:

> Consumers gating CI on skip-log entry counts should expect
entry-count variance across MINOR releases as diagnostic
granularity tightens; gate on rector-class FQN presence +
reason-text grep instead.

Names a CI failure mode + recommended pattern in one go. Pre-empts
the natural "wc -l skip-log" assertion someone might write —
which would silently break across MINOR releases as per-class
emission cardinality changes (e.g. the per-attribute Livewire
overlap-skip refactor on the 1.x backlog).

#### Fixtures — mechanical enforcement of the public-API boundary

Two new test fixtures land alongside the source / doc changes.
They exist so future drift is caught at CI time rather than at
consumer-bump time.

##### `tests/PublicConstAuditTest.php`

Sweeps every `public const` on a non-`@internal` rector class and
asserts each is either:

1. Documented in `PUBLIC_API.md` (the test mirrors the doc's
   committed-constants list in a `DOCUMENTED_PUBLIC_CONSTS` map), OR
2. Carries a `@internal` PHPDoc tag on the constant declaration.

A new public const that's neither documented nor tagged fails the
sweep. The tag-or-document rule is now mechanically enforced;
contributors adding new public-API constants must update both
`PUBLIC_API.md` and the test mirror in the same commit.

##### `tests/RectorInternalContractsTest.php` (extended)

Two new tests pin the `FACTORY_BASELINE` correctness invariant from
both directions:

- `testFactoryBaselineClassesAllResolve` — every BASELINE entry
  must point at a class that exists under the package's declared
  `composer.json` constraint. Catches BASELINE additions that race
  ahead of constraint bumps locally before the bytecode-level
  fatal lands on consumer CI.
- `testSimplifyRuleWrappersRectorBootsCleanly` — invokes
  `bootResolutionTables()` via reflection and asserts no exception
  escapes. Smoke-tests the combined invariant: BASELINE classes
  resolve + reflection-discovered factories filter cleanly +
  `array_filter(...)` guard runs. Future BASELINE entries that
  point at hypothetical classes degrade gracefully instead of
  fataling at boot.

#### Verification

A reproducible six-point check pattern for consumer-side dogfood
verification, distilled from this release's panel-driven gating:

1. Verify zero `<package>-rector` entries in `applied_rectors`
   across the dry-run JSON output (programmatic, not eyeball).
2. Verify all configured set-list constants still imported in
   `rector.php` (no silent drop).
3. Verify post-conversion target files match expected output shape
   byte-clean (idempotent re-run safe).
4. Pre-deprecation FQN-form annotation sweep across all scan paths
   (not just one directory).
5. Removed-shim import sweep across the full codebase, including
   non-rector files (tests, skills, scripts).
6. Skip-log body byte-identical against the last green baseline
   (header timestamp diff expected).

CI matrix — 0.22.1:

- 676 tests, 1255 assertions, 0 failures across the local suite
  (+4 fixture-pin tests vs 0.22.0)
- 24-cell CI matrix green on the release commit: 2 OS × 2 PHP ×
  2 stability × 3 Laravel
- Pint, Rector, PHPStan all clean

#### Process — F3 candidate-pen entry

`specs/PROCESS.md` gains a new "Candidate pen (awaiting
second-cycle confirmation)" section opening with two epistemic
anchors that gate promotion of findings to numbered invariants:

- **Premature canonicalization is the failure mode.** Codifying
  on N=1 shape risks post-hoc broadening when shape #2 surfaces.
- **Invariants earn their slot, they don't get one by appearing.**
  A finding being real isn't sufficient; the wording has to
  generalize across the failure-mode shape.

The first candidate landing in this section: "Cross-package
constraint pre-flight (vendor-rsync methodology)." The bypass-
constraint failure surfaced once (with retroactive 4-cycle
confirmation), but the failure-mode shape is package-version-
mismatch only. Composer overrides, path repos with null versions,
PHP / extension constraints, monorepo symlinks, and other bypass
paths would require post-hoc broadening. Candidate-pen preserves
the finding + provenance with zero loss; promotion drafts from a
second-cycle independent confirmation.

The candidate cross-links to this release's
`testFactoryBaselineClassesAllResolve` /
`testSimplifyRuleWrappersRectorBootsCleanly` fixtures, framing
them as the in-repo analogue to the dogfood-side pre-flight check.

#### Recommended action

Bump from 0.22.0 → 0.22.1. No `rector.php` changes required;
no behavior changes for any path that worked under 0.22.0.

If you observed an opaque `ReflectionException` from
`bootResolutionTables()` when bumping the rector against a
non-current sister-package version (vendored-copy install,
hand-edited lockfile, monorepo path repository), the new
`array_filter(...)` guard makes the boot graceful — rector skips
the unresolvable factory entry and continues. The accompanying
`testFactoryBaselineClassesAllResolve` test fixture catches future
BASELINE-vs-constraint drift locally before it ships.

After 0.22.1 saturates the panel, 1.0 ships as a tag-only
commitment cut from the same SHA.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.22.0...0.22.1

## 0.22.0 - 2026-04-28

### 1.0 RC scope-lock

Pre-1.0 release that locks the SemVer commitment surface ahead of the
1.0 tag. No new rectors and no heuristic changes — the 0.21.x cycle's
data-flow tightening + skip-log signal-to-noise pass already drained
the open consumer-shape findings. This release closes the remaining
deprecation cycles and tightens the public-API documentation so 1.0
is a tag-only commitment cut on a green-saturation 0.22.x.

If you've been tracking the 0.20.x → 0.21.x arc, this is the gate
release. If you're a cold consumer arriving at the package now, treat
0.22.0 as the surface 1.0 will pin.

#### Breaking changes

##### Root-namespace `Diagnostics` / `RunSummary` shims removed

Consumers importing `SanderMuller\FluentValidationRector\Diagnostics`
or `SanderMuller\FluentValidationRector\RunSummary` from the root
namespace will fatal-error post-bump.

Both classes have lived under `Internal\` since 0.20.0 (which moved
them to align the namespace structure with the documentation) with
`class_alias` shims at the old location PHPDoc-tagged
`@deprecated since 0.20.0 — removal slated for 1.0`. The shims were
always `@internal` and never part of the public API; consumer
imports were against the docs.

If you were importing these symbols (you shouldn't have been), update
to:

- `SanderMuller\FluentValidationRector\Internal\Diagnostics`
- `SanderMuller\FluentValidationRector\Internal\RunSummary`

…with the caveat that the `Internal\` namespace itself is the
do-not-import signal — these classes may change in any release
without a MAJOR bump.

#### Deprecations

##### `LEGACY_FQN_STANDARD_RULES_ANNOTATION_BODY` recognition path

The `NormalizesRulesDocblock` trait recognizes both the canonical
short-name docblock body AND a legacy fully-qualified-name form
(`array<string, \Illuminate\Contracts\Validation\ValidationRule|...>`)
as "already-narrowed" for passive idempotency. The FQN form was
emitted by pre-0.20.2 rector runs; the recognition keeps re-runs over
those consumer codebases inert (rather than rewriting the docblock
back to short-name on every pass).

The recognition path is now `@deprecated since 0.22.0 — removal slated for 2.0`. Behavior is preserved through 1.x; the cycle gives
consumer codebases mid-migration time to re-run the rector and
collect the canonical short-name form before the recognition goes
away. The constant itself is `protected` on a trait — not consumer-
importable — so the deprecation is informational about the
*behavior*, not a symbol-level break.

#### 1.0 RC scope-lock

`PUBLIC_API.md` is the source of truth for the 1.0 SemVer commitment.
Every section was audited end-to-end against 0.21.x behavior. Drift
fixes:

##### New section: heuristic boundaries (implementation detail)

Added an explicit carve-out clarifying that detection heuristics,
classification confidence / trace depth, skip-text reason content,
fixture coverage, and diagnostic granularity are NOT SemVer-
committed. Heuristic tightening (e.g. the 0.21.0 unsafe-parent
data-flow trace) is MINOR-bump-safe by construction; the section
makes that explicit so cold consumers don't conflate "skip-log line
format committed" with "the heuristic that decides what gets emitted
is committed."

##### Verbose-mode env var: tightened canonical vocabulary

`FLUENT_VALIDATION_RECTOR_VERBOSE` now documents three canonical
values — `off`, `actionable`, `all` — with `1` / `true` (case-
insensitive) called out as legacy synonyms preserved indefinitely
for pre-0.13 compatibility. Pre-0.22.0 the doc listed all five values
as committed accepted-vocabulary; the implementation has always been
permissive (any non-empty non-`actionable` value resolves to `all`),
but committing the permissive fall-through as API surface would have
locked in pollution behavior. The 1.0 commitment is the canonical
three-value vocabulary.

##### Cold-consumer fixture inspection: anchored GitHub links

The `tests/` directory isn't shipped in the Composer archive (size).
Cold consumers wanting to spot-check what shapes the rector exercises
now get direct anchored GitHub-tree links to `tests/Parity/Fixture/`,
`tests/<Rector>/Fixture/`, and `tests/FullPipelinePolish/Fixture/`,
plus the named test runner class
(`SanderMuller\FluentValidationRector\Tests\Parity\ParityTest`). The
prior `git clone && cd && ls` instruction is gone — production
dogfood feedback flagged the clone step as friction for a one-off
shape audit.

##### Trait-FQN section: unchanged

Trait FQNs (`HasFluentRules`, `HasFluentValidation`,
`HasFluentValidationForFilament`) live in the sister
`laravel-fluent-validation` package; renames upstream cascade on a
best-effort same-author-same-cadence basis. The implicit commitment
is appropriate as-is; explicit cross-package wording would over-
promise across a package boundary.

#### Skip-log version stamp regression pin

`Diagnostics::skipLogHeader()` sources the package version via
`Composer\InstalledVersions::getPrettyVersion()` — a single
deterministic resolution path centralized in `packageVersion()`.
0.22.0 adds `tests/SkipLogHeaderTest` pinning the resolution path so
a future refactor (composer.json read, hard-coded constant, env-var
lookup) breaks the test rather than silently shipping. The public
`skipLogHeader()` docblock that misdescribed the path as "read from
composer.json" is fixed in this release.

No behavior change — the version stamp slot has been
`InstalledVersions`-backed since the header landed; this release
just locks the contract.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.21.1...0.22.0

## 0.21.1 - 2026-04-28

### class-wide skip-log line resolves to class declaration

Patch fixing the line-number resolution introduced in 0.21.0. Pure
diagnostic fix; no semantic-rector change; no SemVer concern.

#### The bug

0.21.0 added `:<line>` suffix to skip-log file paths so consumers
can click-to-open at the offending source position. For class-wide
skips (where the offending node IS the class itself), the
implementation used `Class_::getStartLine()` — which returns the
position of the first attached token, not the `class Foo`
declaration line.

Result on real-world codebases:

| Class shape                                              | `:line` resolved to                          |
|----------------------------------------------------------|----------------------------------------------|
| `class Foo`                                              | the `class` keyword line ✓                   |
| `final class Foo`                                        | the `final` modifier line ✓ (close)          |
| `#[Layout('app')] class Foo`                             | the `#[Layout]` attribute line (~ish)        |
| Class with attached docblock above                       | the docblock start line                      |
| Class right after `use Foo;` with no blank separator     | the `use` import line ✗                      |
| Class containing `use HasTrait;` immediately after `{`   | the trait-use line inside class body ✗       |

On a small Filament-page-shape consumer surface (5 entries), 3 of
5 landed on wrong source positions. At larger production-codebase
depth (more attached docblocks, deeper attribute decoration,
denser trait-use blocks), the wrong-line rate is presumably
similar.

The whole point of the `:line` suffix was meaningful click-to-open;
the buggy resolution defeated the goal for ~40% of consumer
surface.

#### The fix

Special-case `Class_` in `LogsSkipReasons::resolveStartLine` to use
the Identifier's position rather than the node's:

```php
if ($node instanceof Class_ && $node->name instanceof Identifier) {
    $line = $node->name->getStartLine();
} else {
    $line = $node->getStartLine();
}





```
The `Class_::$name` Identifier always sits on the `class Foo`
declaration line because that's where the name token literally
appears in source. Robust against attached attributes / docblocks /
leading whitespace.

Per-key / per-item skip emit sites are unchanged — they already
pass specific AST nodes (`ArrayItem`, `MethodCall`, etc.) whose
`getStartLine()` is positioned correctly.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.21.0...0.21.1

## 0.21.0 - 2026-04-28

### unsafe-parent data-flow tightening + skip-log line numbers

Minor release driven by 0.20.x dogfood. Two interlocking heuristic
fixes plus an orthogonal skip-log convention. **1.0 RC critical-
path**: locks the unsafe-parent heuristic surface before SemVer
commitment so the Filament/Livewire false-positive class is gone
before cold consumers form a "diagnostic too vague to act on" first
impression.

#### Unsafe-parent data-flow tightening

Pre-0.21.0 heuristic: flag a parent class as unsafe-to-convert when
ANY descendant method had BOTH a `parent::*()` call AND any array op
coexisting in the same method body — regardless of whether the array
op operated on the parent's return value. Surfaced false positives
on Filament Page subclasses + Livewire wrapper components where
unrelated `parent::canAccess()` + `array_map($users)` shapes coexist.

0.21.0 tightening: data-flow trace via depth-1 alias resolution. The
op must operate on a `parent::*()` return value, either directly:

```php
return array_merge(parent::rules(), [...]);






```
…or via a depth-1 variable alias:

```php
$rules = parent::rules();
array_push($rules['title'], 'unique:videos,title');
return $rules;






```
The trace handles three argument shapes:

- Direct `StaticCall(parent, *)` as a function-call arg
- `Variable($x)` whose nearest assignment in the same method binds it
  to `parent::*()`
- `ArrayDimFetch($x['key'])` whose receiver is parent-traced

Beyond depth-1 alias chains (`$x = parent::*(); $y = $x; op($y)`),
the heuristic conservatively does NOT flag — empirically justified
across three consumer-shape audits (zero observed instances). The
reopen-on-consumer-signal pattern reserves deeper alias trace for
future need.

##### What now correctly skips false-positive flagging

- **Filament Page UI scaffolding** — descendants with
  `parent::canAccess()` / `parent::mount()` / `parent::getViewData()`
  
  + unrelated `array_map($users)` / `in_array($role, $allowed)`
    shapes. Pre-0.21.0 these flagged the parent as unsafe. Post-
    0.21.0 the data-flow trace correctly identifies the array ops as
    operating on local variables, not the parent's return.
  
- **Livewire wrapper components** — same shape class as Filament
  Pages, same false-positive elimination.
  

##### What remains correctly flagged

- `array_merge(parent::rules(), [...])` — direct
- `$rules = parent::rules(); array_push($rules['title'], …)` —
  depth-1 alias via Variable
- `$rules = parent::editorRules(); unset($rules['body'])` — depth-1
  alias via ArrayDimFetch receiver

#### Skip-log line numbers

The skip-log line format gains an optional `:<line>` suffix on the
file path:

```
[fluent-validation:skip] <RectorShortName> <ClassFQCN> (<file-path>[:<line>]): <reason>






```
- Per-key/per-item skips emit the offending node's `getStartLine()`.
- Class-wide skips emit the `class Foo` declaration line.
- Skips emitted from name-only call sites (no AST node available)
  omit the `:<line>` suffix.

IDE click-to-open works on PhpStorm, VS Code terminal, vim quickfix,
iTerm2 cmd-click. Existing grep workflows extracting paths via
`(<path>)` regex stay byte-stable; consumers wanting the line can
tighten to `:[0-9]+\)`.

`PUBLIC_API.md` skip-log line format documents the optional suffix.
The format slot semantics are unchanged otherwise — slot count,
parenthesization, and prefix all stable.

#### Recommended action

Existing `rector.php` files keep working unchanged. The fix is a
diagnostic-accuracy improvement; converters that already fold
cleanly continue to do so.

If your codebase is Filament/Livewire-heavy and you observed
unsafe-parent skips on UI-scaffolding classes (Filament Page
subclasses, Livewire wrapper components), those skips should
disappear in 0.21.0 — the data-flow trace correctly identifies
array ops as operating on local data, not parent return values.
Consumer-relevant headline.

If you grep your skip log by file path inside parens, the existing
pattern still matches (`:<line>` suffix is optional and inside the
same parens). Tighten to `:[0-9]+\)` if you want the line for
editor jump.

Skip-log emit format is unchanged otherwise — slot count,
parenthesization, prefix all stable.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.20.2...0.21.0

## 0.20.2 - 2026-04-28

### unsafe-parent noise suppression + short-name docblock emit + PUBLIC_API canonical wiring

Patch driven by 0.20.0 + 0.20.1 production-dogfood feedback. Four
consumer-driven fixes, bundled into a coherent "would I pin `^1.0`?"
pre-commitment-cycle patch.

#### Pre-check: unsafe-parent skip on classes with no rules-bearing surface

Filament Page subclasses, Livewire wrapper components, and similar
UI-scaffolding classes qualify via Livewire ancestry (they extend
`Filament\Pages\Page` → `Livewire\Component`) but rarely declare
validation rules. Pre-0.20.2 the unsafe-parent skip fired on every
such class with an array-manipulating descendant somewhere up the
chain — generating irreducible noise on classes that have nothing
the rector would convert anyway.

A new `hasRulesBearingSurface()` predicate suppresses the skip emit
when the class has:

- no `rules()` method,
- no `#[FluentRules]`-attributed method,
- no auto-detect-qualified rules-shaped method.

Surfaced via real-world dogfood: 9 Filament-Page descendants of a
single base class, none with rules-bearing methods, all generating
noise skips pre-fix.

The unsafe-parent skip remains for classes that DO have rules-
bearing methods (where the heuristic is doing real work). This
fix narrows the false-positive surface specifically to "no work to
do anyway" cases.

#### Short-name docblock emit (FQN bug)

The `STANDARD_RULES_ANNOTATION_BODY` constant in
`NormalizesRulesDocblock` previously emitted the FQN form:

```
array<string, \Illuminate\Contracts\Validation\ValidationRule|string|array<mixed>>







```
This violated downstream-project guidelines forbidding FQN in
docblocks. Consumer Pint cleaned it up post-hoc, but consumers
running rector standalone (without Pint follow-up) saw committed
FQN in their codebases.

The trait now emits the short-name form:

```
array<string, ValidationRule|string|array<mixed>>







```
…and queues a `Illuminate\Contracts\Validation\ValidationRule` use
import via a new abstract hook on the trait
(`queueValidationRuleUseImport()`). Each consuming rector
implements the hook via either `UseNodesToAddCollector` (the
five rectors that already have it injected) or
`ManagesNamespaceImports::ensureUseImportInNamespace`
(GroupWildcardRulesToEachRector).

#### `ConvertLivewireRuleAttributeRector` skip-message doc-pointer

The default-bail-mode skip message added in 0.20.1 named the
`KEY_OVERLAP_BEHAVIOR=partial` config knob in-line, but the
notation read ambiguous (CLI env var? `define()`? array key?).
Consumers reading the skip would write `'KEY_OVERLAP_BEHAVIOR' => 'partial'`
(uppercase string) when the wire form is
`KEY_OVERLAP_BEHAVIOR => OVERLAP_BEHAVIOR_PARTIAL` (constant
references).

The skip message now appends `→ see PUBLIC_API.md#convertlivewireruleattributerector`
so consumers have a deterministic next-read path. The PUBLIC_API.md
section now includes the canonical
`withConfiguredRule(... => [KEY_OVERLAP_BEHAVIOR => OVERLAP_BEHAVIOR_PARTIAL])`
shape inline alongside the constant list.

#### PUBLIC_API.md "Inspecting fixtures" section

The Composer archive ships only runtime artifacts (`src/`,
`config/`, `composer.json`); the `tests/` directory is excluded to
keep package size lean. Cold-Packagist consumers wanting to spot-
check the parity-harness fixtures had no on-package signal of
where to look.

A new "Inspecting test fixtures (semantics-pinning)" section in
PUBLIC_API.md points at `git clone … && ls tests/Parity/Fixture/`
with a brief description of what each fixture directory pins.
Trust-bridging for cold consumers; no archive size cost.

#### Recommended action

Existing `rector.php` files keep working unchanged. The fixes are
diagnostic-accuracy improvements + a docblock-emit shape change
that's passively backwards-compatible (legacy FQN form recognized
as already-narrowed; new emits use short-name).

If your codebase had `@return array<string, \Illuminate\Contracts\Validation\ValidationRule|...>`
docblocks committed by pre-0.20.2 rector runs, those will not be
rewritten by 0.20.2 — they're recognized as already-narrowed.
Pint's `fully_qualified_strict_types` will short-name them at edit
time over the natural code-churn cycle.

If you observed unsafe-parent skips on Filament Page subclasses or
Livewire wrapper components that have no validation rules of their
own, those skips should disappear in 0.20.2 — the pre-check
recognizes "no rules-bearing surface" and suppresses the noise.
Consumer-relevant headline for Filament/Livewire-heavy codebases.

If you grep your skip log for the
`ConvertLivewireRuleAttributeRector` overlap-skip message, the new
form appends `→ see PUBLIC_API.md#convertlivewireruleattributerector`
— update grep patterns accordingly. The skip-log line format
itself is unchanged.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.20.1...0.20.2

## 0.20.1 - 2026-04-28

### closure-scope leak in unsafe-parent + skip-message accuracy

Patch driven by 0.20.0 production-dogfood feedback. Three skip-log
accuracy fixes; no behavior change for converters that fold cleanly.

#### Closure-scope leak in unsafe-parent detector

`ConvertsValidationRuleStrings::collectUnsafeParentClass` walked
method bodies via `traverseNodesWithCallable` without skipping
closure / arrow-function / nested-function / class-like boundaries.
Result: `parent::rules()->modify(self::FOO, function (ArrayRule $r): … { … })`
shapes false-positive flagged the parent unsafe — the outer
`parent::*()` coexists with the closure-body's array ops, but
they're unrelated scopes.

Same root-cause pattern as the 0.19.1 codex finding on
`GroupWildcardRulesToEachRector::countNonClosureReturns`. The skip
into `Closure` / `ArrowFunction` / `Function_` / `ClassLike`
boundaries is now mirrored in the unsafe-parent detector.

Surfaced via real-world dogfood on a `parent::rules()->modify(… function (ArrayRule $r): ArrayRule { … })` shape — a `RuleSet`
fluent chain whose closure argument has nothing to do with array
manipulation on the parent's return value.

#### Unsafe-parent fallback-message rewrite

The hedged-generic fallback message (used when the in-process detail
map doesn't have the offender pinpoint) previously read 3 sentences
spanning ~80 words and included:

> "re-run with `vendor/bin/rector process` to materialize the
in-process detail"

Bad UX: `--dry-run` and apply-mode use the same visitor pass; re-
running with apply doesn't help. The detail-map population depends
on file-traversal order, not on dry-run vs apply.

Trimmed to a single honest line:

```
unsafe parent: a descendant calls `parent::*()` + uses an array op
(`array_*()` / `Arr::*()` / `unset()` / array-dim assignment) in the
same method body — heuristic detected coexistence but does not trace
data flow; descendant pinpoint unavailable in this scan tier;
possible false positive, verify before acting.








```
Honest "scan tier limitation" framing replaces the misleading "re-
run" advice. Two independent dogfood reports flagged this — both
running `--dry-run`, both observing that the apply-mode advice
doesn't change the outcome.

#### `ConvertLivewireRuleAttributeRector` overlap-skip wording

The default-bail-mode skip message read:

> "attribute conversion skipped to avoid generating dead-code rules()"

Overclaim — for `#[Validate]` attributes whose property name is NOT
in any explicit `$this->validate(...)` arg, conversion would NOT
produce dead code; the bail is conservative-safe, not actually-dead.

Rewrite hedges the certainty AND names the
`KEY_OVERLAP_BEHAVIOR=partial` config knob verbatim so the consumer
sees the escape hatch in the skip log without reading docs:

```
class calls $this->validate([...]) with explicit args — default mode
(KEY_OVERLAP_BEHAVIOR=bail) skips classwide because attributes MAY
overlap with the explicit args; conversion is conservative-safe but
the rector cannot statically prove non-overlap. To convert
attributes whose property name does not appear in any explicit
validate() arg, set KEY_OVERLAP_BEHAVIOR=partial.








```
#### Recommended action

Existing `rector.php` files keep working unchanged. The fixes are
diagnostic-accuracy improvements; converters that already fold
cleanly continue to do so.

If you observed unsafe-parent skips on classes whose descendants use
`parent::rules()->modify(closure)` or similar `RuleSet`-fluent
shapes (where the closure argument has nothing to do with array
manipulation on the parent's return value), those skips should
disappear in 0.20.1 — verify with a fresh `vendor/bin/rector process --dry-run` against your codebase. If the skip persists, file an
issue with the descendant's `rules()` method body so the heuristic
can be tightened to cover the new shape.

If you grep your skip log for the strings "attribute conversion
skipped to avoid generating dead-code rules()" or the 3-sentence
hedged fallback, update your patterns to the new shorter messages.
The skip-log line format itself is unchanged.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.20.0...0.20.1

## 0.20.0 - 2026-04-28

### `Internal\` namespace + skip-message accuracy + release-discipline

Public-API tightening on the road to 1.0. Five deliverables: a structural
namespace move that aligns the `@internal` PHPDoc tags with the namespace
hierarchy, three consumer-driven skip-log message rewrites for diagnostic
accuracy, and the `/pre-release` skill + AI guideline that codify
public-API discipline going forward.

#### `Internal\` namespace move

The two top-level `@internal` classes (`Diagnostics`, `RunSummary`) have
moved to a dedicated `SanderMuller\FluentValidationRector\Internal\`
sub-namespace:

- `SanderMuller\FluentValidationRector\Diagnostics` → `SanderMuller\FluentValidationRector\Internal\Diagnostics`
- `SanderMuller\FluentValidationRector\RunSummary` → `SanderMuller\FluentValidationRector\Internal\RunSummary`

The classes were always `@internal` per their PHPDoc tags. Pre-0.20.0 they
sat at the same namespace depth as `FluentValidationSetList` (which IS
public API) — only the docblock distinguished them. 0.20.0 makes the
distinction structural: the namespace placement is the do-not-import
signal.

##### Backwards-compat shims

Backwards-compat shims at the old locations (`src/Diagnostics.php`,
`src/RunSummary.php`) trigger `class_alias` for one minor cycle. Any
(in-violation) downstream import of the old name resolves to the
canonical class. The shims are marked `@deprecated since 0.20.0` and
**will be removed in 1.0**.

If your codebase imports `SanderMuller\FluentValidationRector\Diagnostics`
or `SanderMuller\FluentValidationRector\RunSummary` directly, update the
import to the `Internal\` location now. The classes were never public API
— removal in 1.0 is a non-event for compliant consumers.

##### `InternalAuditTest` extended

A new test (`testEveryClassUnderInternalNamespaceIsThereByNamespace`)
asserts every class under `src/Internal/` declares an `Internal\`-prefixed
namespace. Catches the failure mode where a contributor moves a file into
the directory without updating its namespace declaration.

#### Skip-log message accuracy: three consumer-driven rewrites

Two real-world dogfooders surfaced skip-log messages that read
contradictory or named the wrong side of the disqualifier. All three
rewrites land here.

##### `parentFactoryAllowsChain` split per case

Pre-0.20.0, every parent-factory bail emitted:

> "parent factory `<X>()` doesn't support each()/children() — only
array() and field() do"

This read contradictory when the rejected factory was `field()` itself
bailing on `each()` (`field()` IS one of the supported `children()`
factories — just not for `each()`). Two distinct cases now emit two
distinct messages:

```text
each() bail:    "parent factory <X>() doesn't support each() — only array() does"
children() bail: "parent factory <X>() doesn't support children() — only array() and field() do"









```
##### Concat-key bail classified per failure shape

Pre-0.20.0, every `parseConcatKey()` failure emitted the same generic
"concat key too complex to parse for grouping" string. The message
inverted which side was the disqualifier on the canonical `'literal.' . CONST` shape (the const is fine; the literal prefix is the bail
reason).

Replaced with a per-failure-kind classifier (`describeConcatKeyFailure`)
emitting a specific message per shape:

- **Non-static part** (`$var . '.foo'`, `method() . '.foo'`):
  "concat key contains a non-static part (variable / method call)"
- **Non-canonical-`'*.'`-wildcard prefix** (`'literal.' . CONST`,
  `'*. ' . CONST`, `'**.' . CONST`):
  "concat-key prefix `'<actual>'` isn't the canonical `'*.'` wildcard
  form — only the canonical wildcard prefix folds into
  `array()->children([...])`; other prefix shapes (literal,
  malformed-wildcard) stay flat by design"
- **Multiple ClassConstFetch parts**:
  "concat key has multiple ClassConstFetch parts — only one
  statically-resolvable const reference is supported per key"
- **Const-prefix without trailing `.`-suffix**:
  "concat key suffix must be a `String_` starting with `.` after the
  const prefix"

Naming the actual literal prefix in single quotes lets consumers grep
their codebase for similar shapes.

##### Unsafe-parent skip names the offender

Pre-0.20.0, the unsafe-parent message claimed:

> "unsafe parent: a subclass manipulates parent::rules() with array
functions"

This overclaimed certainty. The detector flags a parent unsafe when
ANY descendant method has BOTH a `parent::*()` call AND an array op
coexisting in the same method body — there is no data-flow link
between the two. Real-world dogfood surfaced a Filament base class
flagged because a descendant method had `parent::canAccess()` plus
unrelated `array_map($users)`.

The detector now records (descendant FQCN, method name, matched op)
and threads it into the skip-log message:

```text
unsafe parent: descendant `App\Foo\Page::doStuff()` calls `parent::*()`
and uses `array_map()` in the same method body — converting the parent
could change the merged shape if the array op operates on the parent's
return value (heuristic doesn't trace data flow; verify before treating
as actionable)









```
The hedged framing is honest about what the heuristic can prove. The
proper data-flow tightening (only flag when the array op operates on the
`parent::*()` return value) is a deferred patch.

##### `Arr::except` / `Arr::only` detection added

The unsafe-parent detector previously only matched free-function array
ops (`array_merge`, `in_array`, `collect`, etc.). It now also matches
`Illuminate\Support\Arr` static-call helpers (`Arr::except`,
`Arr::only`, `Arr::add`, `Arr::forget`, `Arr::set`, `Arr::pull`) — same
hazard class, different syntax.

#### Release-discipline machinery

Two additions codify the public-API contract going forward.

##### `/pre-release` skill: step 5c PUBLIC_API audit

A new step between docs freshness (5b) and commit/push (6) audits
`PUBLIC_API.md` against `src/`:

- Every entry in `PUBLIC_API.md` must still resolve to a class /
  trait / interface / enum / constant in `src/`.
- Every public-namespace class under `src/` (anything not in
  `Internal\`) must appear in `PUBLIC_API.md` or
  `tests/InternalAuditTest::PUBLIC_CLASSES`.

Currently a manual audit; future work bakes it into a
`PublicApiSurfaceTest` data-provider.

##### `.ai/guidelines/public-api-discipline.md`

A new persistent guideline codifying the rules:

- **`Internal\` namespace placement IS the do-not-import signal.** Default
  new helpers to `Internal\`. Only place a class in the public root or
  `Set\`/`Config\` when it's an intentional consumer-facing surface.
- **When adding a public symbol**, update `PUBLIC_API.md` AND
  `InternalAuditTest::PUBLIC_CLASSES` in the same commit.
- **Renames / removals** require a deprecation cycle: keep the old name
  as a `class_alias` (classes) or `@deprecated` PHPDoc tag (methods)
  for at least one minor cycle, document the cycle in `PUBLIC_API.md`,
  mention in release notes.

##### `PUBLIC_API.md` namespace structure section

A new "Namespace structure" section explicitly carves out the three
tiers:

- **Public** (root namespace, `Set\`, `Config\`, `Config\Shared\`)
- **Public, scoped** (the latter three — narrowly-scoped public
  surfaces)
- **Internal** (`Internal\` — added in 0.20.0; do not import)

Plus a note on `Rector\Concerns\` traits: also implementation detail,
not under `Internal\` for path-stability reasons (Rector's autoloader
expects `Rector\` prefix for rector discovery).

#### Recommended action

Existing `rector.php` files keep working unchanged. The namespace move
ships with backwards-compat shims; the skip-log message rewrites are
output-only changes (no behavior drift).

If your codebase contains direct imports of
`SanderMuller\FluentValidationRector\Diagnostics` or
`…\RunSummary` (against the `@internal` documentation) — these still
work in 0.20.0 via the shim, but **will fail at autoload time in 1.0**.
Update imports to the `Internal\` location now to avoid the break.

If you maintain CI dashboards or grep your skip log for specific reason
strings, update your patterns to the new messages — the substring text
changed for the parent-factory, concat-key, and unsafe-parent reasons.
The skip-log line format itself is unchanged (still
`[fluent-validation:skip] <RectorShortName> <ClassFQCN> (<file>): <reason>`).

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.19.1...0.20.0

## 0.19.1 - 2026-04-27

#`RuleSet::from()` descent + post-fold docblock-narrow

Patch release driven by 0.19.0 production-dogfood feedback. Two
issues closed, plus two HIGH findings from independent code review
on the cumulative diff before tagging.

### P0 — `RuleSet::from()` return-shape support

`rules()` methods following the canonical
[`sandermuller/laravel-fluent-validation`](https://github.com/sandermuller/laravel-fluent-validation)
idiom return `RuleSet::from([...])` rather than a bare array:

```php
public function rules(): RuleSet
{
    return RuleSet::from([
        '*.' . self::NAME => FluentRule::string()->required(),
        '*.' . self::COLOR => FluentRule::string()->nullable(),
    ]);
}










```
Pre-0.19.1 the rector visited, saw the `MethodCall` return
expression, walked away **without folding and without logging** —
silent-bail on the package's own recommended return shape. 0.19's
headline (`'*.' . CONST` wildcard fold) reached zero consumers
following the docs.

0.19.1 descends into the `RuleSet::from(<Array_>)` argument and
folds the inner array. The `RuleSet::from(...)` wrapper stays
intact; only the wrapped array argument mutates:

```php
public function rules(): RuleSet
{
    return RuleSet::from(['*' => FluentRule::array()->children([
        self::NAME => FluentRule::string()->required(),
        self::COLOR => FluentRule::string()->nullable(),
    ])]);
}










```
#### Skip-log entries (silent-bail closed)

Two new `=actionable` skip-log reasons surface where the descent
can't fold:

- **Non-literal `RuleSet::from()` argument** — e.g.
  `RuleSet::from($injected)`, `RuleSet::from(self::baseRules())`.
  The argument's shape isn't statically determinable, so the fold
  bails with a descriptive entry rather than silent-skipping.
- **Branched `rules()` body** — multiple top-level `Return_`
  statements (`if () return …; return …;`-shape bodies). Conservative
  bail with consumer-audit guidance; aggressive per-branch fold
  reopens on consumer signal. Across audited consumer codebases (88
  `rules()` methods between two real-world apps), zero hit this
  shape today.

### P1 — `UpdateRulesReturnTypeDocblockRector` post-fold narrow

Pre-0.19.1, when both `FluentValidationSetList::ALL` and
`FluentValidationSetList::POLISH` were loaded (the documented
post-migration cleanup config), the docblock-narrow rector skipped
the post-fold output of `GroupWildcardRulesToEachRector`:

```
[fluent-validation:skip] UpdateRulesReturnTypeDocblockRector
    (...): value at key '*' is not a FluentRule chain (shape: MethodCall)










```
Root cause: name-resolution scope. The wildcard fold emits short-name
`new StaticCall(new Name('FluentRule'), …)` nodes; the post-rector
pipeline queues the `use SanderMuller\FluentValidation\FluentRule;`
import, but the import isn't in scope when the docblock-narrow
predicate runs in the same set-list pass. `getName()` resolved to
short-name `FluentRule` instead of the FQN the predicate compared
against.

Predicate widened to accept both forms — same pattern already in
`GroupWildcardRulesToEachRector::getFluentRuleFactory`. With both
`ALL` and `POLISH` loaded, post-fold output now narrows to
`array<string, FluentRuleContract>` end-to-end:

```php
/**
 * @return array<string, FluentRuleContract>
 */
public function rules(): array
{
    return ['*' => FluentRule::array()->children([
        self::NAME => FluentRule::string()->required(),
        self::COLOR => FluentRule::string()->nullable(),
    ])];
}










```
`RuleSet::from()` wrapper returns still skip docblock-narrow (the
narrow rector requires a bare-`Array_` return expression — pinned
limitation, see README §`UpdateRulesReturnTypeDocblockRector`).

### Code-review HIGH findings (fixed pre-tag)

Two HIGH findings from independent review on the cumulative diff:

1. **Recursive `Return_` traversal allowed closure-internal
   `return RuleSet::from([...])` to fold or skip-log incorrectly.**
   The new multi-return *counter* excluded closures, but the
   *rewrite* path itself didn't. Closures in `rules()` bodies
   (validator callbacks, helper factories) could trigger fold or
   emit confusing skip logs on returns that aren't the rules()
   return value at all.
   
   Fixed by anchoring on direct-child `Return_` of `$method->stmts`
   only, with scope-local traversal that skips `FunctionLike` +
   `ClassLike` boundaries. Pinned by
   `tests/GroupWildcardRulesToEach/Fixture/skip_closure_arg_return_does_not_fold.php.inc`.
   
2. **Multi-return guard only enforced for `RuleSet::from()` path
   left mixed-branch methods half-migrated.** A `rules()` body with
   `if () return [...]; return RuleSet::from([...]);` had the bare
   branch folded immediately while the RuleSet branch logged "fold
   target ambiguous" — partial rewrite across paths.
   
   Moved the unified ambiguity guard ahead of either branch-specific
   path so both shapes bail uniformly. Pinned by
   `tests/GroupWildcardRulesToEach/Fixture/skip_mixed_branch_returns.php.inc`.
   

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.19.0...0.19.1

## 0.19.0 - 2026-04-27

### wildcard-prefix concat fold for `GroupWildcardRulesToEachRector`

Minor release widening `GroupWildcardRulesToEachRector` to recognise
a new key shape sourced from production-dogfood fixtures: the
**wildcard-prefix concat key** `'*.' . CONST_NAME => rule()`.

This was the work deferred from 0.18.0 — the new fold output had
to preserve the suffix `ClassConstFetch` as the children-array key
(not string-literalize it), which was bigger than the parser
widening 0.18 attempted.

#### What converts now

Sibling rules of the form `'*.' . SOMETHING::FIELD => …` fold into
a single `'*' => array()->children([...])` entry, with the const
preserved verbatim as the children-array key:

```php
// Before
return [
    '*.' . self::NAME       => FluentRule::string()->required(),
    '*.' . self::COLOR      => FluentRule::string()->nullable(),
    '*.' . self::SORT_ORDER => FluentRule::integer()->min(0),
];

// After
return [
    '*' => FluentRule::array()->children([
        self::NAME       => FluentRule::string()->required(),
        self::COLOR      => FluentRule::string()->nullable(),
        self::SORT_ORDER => FluentRule::integer()->min(0),
    ]),
];











```
The fold fires when **every** sibling in the group resolves the
suffix from a `self::`, `static::`, or `Class::CONST` reference
that statically resolves to a string. Mixed groups whose const
reference is dynamically computed (variable, method call, etc.)
stay flat and emit a skip-log entry under `=actionable`.

#### Mixed literal + const groups

Arrays that mix literal `'*.foo'` keys with concat
`'*.' . CONST` siblings get partial conversion:

- The literal-keyed entries fold via the existing path:
  `'*' => FluentRule::array()->children(['foo' => …])`.
- The const-keyed entries stay flat and emit a skip-log entry —
  consumers wanting the mixed shape folded uniformly hand-merge
  into a single `'*' => array()->children([...])` body.

Importantly, **no rule is dropped**. Pre-fix, a stale-snapshot bug
let both fold paths emit `'*' =>` entries into the same array,
last-write-wins silently dropping one branch. The detector now
live-scans the array for any `'*'` key (literal or const-resolving)
at decision time, so the const branch correctly bails when the
literal-keyed parent already exists. Pinned by
`tests/GroupWildcardRulesToEach/Fixture/mixed_literal_and_const_wildcard_keys.php.inc`.

#### Single-entry + custom-rule-value preservation

Two documented-behavior fixtures pinned alongside the main shape:

- **Single-entry fold** — even a single sibling triggers the fold,
  producing `'*' => array()->children([CONST => …])` with a
  one-element children body. Semantically equivalent at runtime.
- **Custom rule values** — the value `Expr` is preserved verbatim
  through the children emit. `->rule(new ColorRule())` round-trips
  cleanly; the fold does not assume FluentRule chains.

#### Real-world fixture parity

A synthetic fixture mirroring a production-dogfood validator shape
(`*.NAME`, `*.COLOR`, `*.SORT_ORDER`, `*.INTERACTIONS` with a
plain-class const host) is pinned under
`tests/GroupWildcardRulesToEach/Fixture/converts_realworld_json_interaction_group_shape.php.inc`.
If the fold output ever diverges from the consumer's hand-folded
mental model, this fixture surfaces the diff at every CI run.

The Spatie LaravelData DTO variant is structurally identical from
the rector's perspective — the const-host class's parent doesn't
affect `ClassConstFetch` resolution.

#### Parity coverage

Added a runtime parity fixture under
`tests/Parity/Fixture/GroupWildcardRulesToEachRector/wildcard_prefix_const_fold.php`,
proving the folded output validates the same dataset identically to
the pre-fold flat shape under a real `Validator::make()` round-trip.

#### CI matrix speedup

Composer cache (`actions/cache` keyed on `composer.json` + matrix
axes) added to `run-tests.yml`. Saves ~20-40s per cell on cache hit
across the 24-cell matrix; cold runs unchanged.

`pest --parallel` was attempted on Linux runners but reverted —
paratest workers re-bootstrap the autoloader and load both the
top-level `nikic/php-parser` and rector's vendored copy, triggering
a `Cannot declare interface PhpParser\Node` fatal on
`prefer-lowest`. Serial pest sidesteps this; the composer cache is
the safe win.

#### Recommended action

Existing `rector.php` files keep working unchanged — this is a new
key shape recognised by an existing rector, not a behavior change
for previously-recognised shapes.

If you maintain FormRequest or Validator-subclass `rules()` arrays
with `'*.' . CONST` sibling keys, the next `vendor/bin/rector process` run will fold them into the nested `children([...])` form
with no input from you. The fold preserves rule semantics under
both FormRequest dispatch and Livewire component validation
(via `HasFluentValidation::getRules()` flattening).

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.18.0...0.19.0

## 0.18.0 - 2026-04-27

### DTO ergonomic polish + formatter-pipeline framing

Polish-cycle minor. Three deliverables sourced from production
dogfood feedback. No behavior changes for any rector — same array
shape arrives at every `configure()` call regardless of provenance.

#### `Options::with(...)` named constructor

The three wrapper-style configuration DTOs ship a new `with()`
named constructor:

- `RuleWrapperSimplifyOptions::with(AllowlistedFactories): self`
- `DocblockNarrowOptions::with(AllowlistedFactories): self`
- `HasFluentRulesTraitOptions::with(BaseClassRegistry): self`

Reads more naturally than the equivalent
`default()->withFoo(...)` chain when you're explicitly building
with a non-default allowlist:

```php
// Before (still works):
RuleWrapperSimplifyOptions::default()
    ->withAllowlistedFactories($allowlist)
    ->toArray();

// After (recommended for non-default builds):
RuleWrapperSimplifyOptions::with($allowlist)->toArray();












```
Both produce identical wire output. Mixed-style is fine —
`default()` is still the right entry point for the zero-arg path
or when starting from defaults and conditionally mutating.

`LivewireConvertOptions` (3-field constructor) does NOT get a
`with()` form because the existing
`default()->withMessageMigration()->withOverlapBehavior(...)`
chain reads cleanly with multiple optional fields.

#### README §Typed configuration: cross-rector consolidation as canonical

When configuring both `SimplifyRuleWrappersRector` and
`UpdateRulesReturnTypeDocblockRector`, build the
`AllowlistedFactories` once and pass it to both via the new
`::with()` constructor:

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
Eliminates duplicate allowlist literals across rectors — adding a
class to the allowlist updates both surfaces atomically. The same
pattern applies to `BaseClassRegistry` for
`HasFluentRulesTraitOptions`.

This is now the **canonical multi-rector form** in the README.
The older `default()->withFoo(...)` style still works and is
documented as equivalent.

#### README §Formatter integration: pipeline contract framing

The rector emit is documented as **not formatter-clean by design**.
The recommended pipeline is explicit:

```bash
vendor/bin/rector process && vendor/bin/pint --dirty












```
Removes ambiguity for downstream consumers about whether rector
should produce PSR-12-sorted imports on its own. The three
documented seams (prepend-position imports, unused-import
preservation, fully-qualified docblock annotations) are explicitly
formatter-resolvable, not rector bugs.

#### Confirmation fixture: post-fold docblock-narrow

A new fixture under
`tests/UpdateRulesReturnTypeDocblock/Fixture/narrows_after_groupwildcard_fold.php.inc`
asserts the docblock-narrow predicate handles the
post-`GroupWildcardRulesToEachRector`-fold shape correctly:

```php
'*' => FluentRule::array()->each(FluentRule::string()->max(255))












```
Narrows to `array<string, FluentRuleContract>` as expected. Pre-
investigation dogfood feedback suggested the predicate was bailing
on this shape, but it turned out to be a side-effect of the
GroupWildcard fold producing a broken Validator-subclass shape
pre-0.17.1. With 0.17.1's gate, the predicate sees the original
flat-wildcard FluentRule values and narrows. Fixture pins this
for future regressions.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.17.1...0.18.0

## 0.17.1 - 2026-04-27

### Gate GroupWildcardRulesToEachRector on shape-change qualification

Patch release. Closes a real-world regression surfaced by 0.17.0
production dogfood: `GroupWildcardRulesToEachRector` produces a
structurally-correct fold that breaks runtime behavior on Validator
subclasses qualifying solely via `#[FluentRules]` whose parent class
postprocesses `rulesWithoutPrefix()` output.

#### The bug

The fold rewrites sibling `'*.foo' / '*.bar'` keys into
`'*' => array()->children([...])`. That shape round-trips cleanly
through FormRequest dispatch, fluent-trait `validateResolved()`, and
Livewire `validate()`. It does NOT round-trip through a parent
Validator class that walks the rule array and prepends a per-key
prefix (e.g. `JsonImportValidator::rulesWithPrefix()`):

```
RuleSet::extractObjectMetadata(): Argument #2 ($field) must be of
type string, int given













```
The nested-children's keys get walked as if they were top-level fields
with int indices, hitting the validator's metadata extraction with the
wrong type. A real consumer's adaptive-import validator failed 5/5
tests after the fold on its `rulesWithoutPrefix()` method.

#### The fix

New `qualifiesForShapeChange(Class_): bool` predicate in
`Concerns\QualifiesForRulesProcessing`. Returns true for the
class-wide-qualifying signals (FormRequest ancestry, fluent-validation
trait, Livewire component); excludes attribute-only qualifying
classes.

`GroupWildcardRulesToEachRector::refactorClass()` now bails when the
predicate fails, with a documented skip-log message:

```
shape-changing rector skipped on Validator subclass — parent class
may postprocess rules() output and the each()/children() shape is
incompatible. Wrap manually if you have audited the parent's behavior.













```
The wildcard sibling rules stay flat, the runtime contract holds, and
the Validator subclass continues to round-trip cleanly through its
parent's postprocessor.

`PromoteFieldFactoryRector` and `SimplifyRuleWrappersRector` are
chain-level transformations that don't change array-key shape, so
they don't need the same gate. The predicate is in place to extend if
a future regression surfaces on those rectors specifically.

#### Regression fixture

`tests/GroupWildcardRulesToEach/Fixture/skip_validator_subclass_attributed_method.php.inc`
models the real-world consumer case: a `JsonAdaptiveSubject`-shaped
Validator subclass with `#[FluentRules] rulesWithoutPrefix()`
declaring flat wildcard rules. Asserts the file is unchanged after
rector runs (skip-and-log path).

#### README documentation

The `### Opting in: #[FluentRules] attribute` section now ships a
**"What `#[FluentRules]` does NOT do"** sibling subsection covering
the three guards the attribute does NOT lift:

- Cross-class parent-safety (`parent::rules()` array manipulation in
  any subclass marks the parent unsafe regardless of the attribute)
- Shape-changing transformations on Validator subclasses (`GroupWildcard`
  skips with a documented log message; chain-level rectors run)
- The denylisted-method guard (`#[FluentRules] casts()` /
  `messages()` / etc. is silently dropped + warned)

Reconciles a real-world expectation gap: dogfood reports expected
`#[FluentRules]` to bypass parent-safety. Per the 1.1 spec OQ #1
resolution, it does not — the attribute is the user's claim about
*their own* method's audit-safety, not a license to override
cross-class scans. Shipped behavior was correct; docs caught up.

#### Recommended action

Consumers using `#[FluentRules]` on Validator subclasses with
flat-wildcard rules: upgrade to 0.17.1 and re-run `vendor/bin/rector process`. Previously-applied folds on Validator subclasses should be
manually reverted to flat-wildcard form; the rector won't undo them
automatically.

Consumers using `#[FluentRules]` only on FormRequest descendants /
fluent-trait users / Livewire components: no action required, no
behavior change.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.17.0...0.17.1

## 0.17.0 - 2026-04-27

### Typed config DTO builders for configurable rectors

Adds opt-in typed configuration builders for the four configurable
rectors. Wraps the canonical magic-constant array transport (committed
in [`PUBLIC_API.md`](PUBLIC_API.md)) with `final readonly` DTO classes
that terminate in `->toArray()`. Compile-time type safety + autocomplete

+ composable builder methods over the same wire format.

**Additive only** — existing magic-constant configs in `rector.php`
continue to work unchanged. Constants stay first-class API; DTOs are an
alternative, not a replacement. The constant-deprecation cycle is
deferred to a future major.

#### What ships

New under `SanderMuller\FluentValidationRector\Config\`:

- `LivewireConvertOptions` — wraps `ConvertLivewireRuleAttributeRector`
  config (`preserveRealtimeValidation`, `migrateMessages`,
  `keyOverlapBehavior`)
- `RuleWrapperSimplifyOptions` — wraps `SimplifyRuleWrappersRector`
  config
- `DocblockNarrowOptions` — wraps
  `UpdateRulesReturnTypeDocblockRector` config
- `HasFluentRulesTraitOptions` — wraps `AddHasFluentRulesTraitRector`
  config
- `Config\Shared\OverlapBehavior` — backed enum (`Bail`, `Partial`)
  for the `key_overlap_behavior` wire key
- `Config\Shared\AllowlistedFactories` — shared by
  `RuleWrapperSimplifyOptions` and `DocblockNarrowOptions` (the two
  rectors share `treat_as_fluent_compatible` /
  `allow_chain_tail_on_allowlisted` schema). Accepts class FQNs,
  wildcard patterns, or `[Class, methodName]` tuples for
  `Class::method()` matching.
- `Config\Shared\BaseClassRegistry` — shared base-class allowlist DTO
  consumed by `HasFluentRulesTraitOptions`

#### Usage example

```php
use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Config\LivewireConvertOptions;
use SanderMuller\FluentValidationRector\Config\RuleWrapperSimplifyOptions;
use SanderMuller\FluentValidationRector\Config\Shared\AllowlistedFactories;
use SanderMuller\FluentValidationRector\Config\Shared\OverlapBehavior;
use SanderMuller\FluentValidationRector\Rector\ConvertLivewireRuleAttributeRector;
use SanderMuller\FluentValidationRector\Rector\SimplifyRuleWrappersRector;

return RectorConfig::configure()
    ->withConfiguredRule(
        ConvertLivewireRuleAttributeRector::class,
        LivewireConvertOptions::default()
            ->withMessageMigration()
            ->withOverlapBehavior(OverlapBehavior::Partial)
            ->toArray(),
    )
    ->withConfiguredRule(
        SimplifyRuleWrappersRector::class,
        RuleWrapperSimplifyOptions::default()
            ->withAllowlistedFactories(
                AllowlistedFactories::none()
                    ->withFactories(['App\\Rules\\Custom'])
                    ->allowingChainTail(),
            )
            ->toArray(),
    );














```
#### Backwards compatibility

The canonical wire format is the array shape committed in
`PUBLIC_API.md`. Both magic-constant arrays AND DTO-derived
`->toArray()` outputs hit the same `configure(array)` method on each
rector. Implication:

| Surface | 0.17 status | Future |
|---|---|---|
| Array shape `['key' => value]` (canonical wire format) | Committed | Committed |
| Literal string keys (`'preserve_realtime_validation'`) | Committed | Committed |
| Magic-constant symbols (`Rector::CONST_KEY`) | First-class | Deprecated in next major |
| DTO builders (`LivewireConvertOptions::default()->...->toArray()`) | New, recommended | Recommended |

Every existing `rector.php` file works without modification.

#### Cross-rector schema sharing

Two configuration concepts repeat across rectors and are now centralized:

- **`AllowlistedFactories`** — shared by `RuleWrapperSimplifyOptions`
  and `DocblockNarrowOptions`. The simplify and docblock rectors stay
  in lockstep on what counts as "fluent-compatible" because they
  consume the same DTO instance.
- **`BaseClassRegistry`** — base-class allowlist used by
  `HasFluentRulesTraitOptions`. Future rectors that take a base-class
  list will reuse the same type.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.16.0...0.17.0

## 0.16.0 - 2026-04-27

### `#[FluentRules]` opt-in on currently-skipped abstract classes + Validator subclasses

The next pre-1.0 increment. Expands `#[FluentRules]` from a per-method
qualification marker into a method-scoped audit assertion that lifts
the abstract-with-rules safety guard, and hardens denylisted-method
handling with a 3-layer guard so misapplied attributes can't corrupt
Eloquent / Laravel framework method bodies.

#### Two newly-unlocked class shapes

Pre-0.16.0, two class shapes were universally skipped by the converter
rectors (`ValidationStringToFluentRuleRector`,
`ValidationArrayToFluentRuleRector`) on safety grounds. Both unlock via
explicit `#[FluentRules]` opt-in:

##### Abstract classes with `rules()`

The original guard bailed on every abstract class declaring `rules()`
because subclasses might do
`array_merge(parent::rules(), [...])` — converting the parent to
return FluentRule objects would silently break the merge. 0.16
narrows the bail: it fires only when `rules()` does NOT carry
`#[FluentRules]`. The attribute on `rules()` is the user's audit
assertion that subclasses don't manipulate `parent::rules()` as a
plain array. Per-method scoping is strict: a sibling-method
attribute does NOT lift the guard for the unattributed `rules()`
body.

##### Custom Validator subclasses extending `FluentValidator`

Validator subclasses (e.g.
`JsonImportValidator extends FluentValidator extends Validator`) are
not FormRequest descendants and don't use `HasFluentRules` directly,
so they fall through the auto-qualification gate. `#[FluentRules]` on
a non-`rules()` helper (e.g. `rulesWithoutPrefix()`) qualifies the
class for processing and converts the attributed method.

`extends FluentValidator` is intentionally NOT treated as a class-wide
qualifier — that would re-open the auto-detect-on-non-FormRequest
regression class 0.14.1 closed. Cross-package parent-safety is not
needed: `FluentValidator` itself has no method bodies the converter
rewrites.

#### 3-layer denylist guard

`#[FluentRules]` on a denylisted method name (`casts()`, `messages()`,
`attributes()`, `toArray()`, `jsonSerialize()`, etc.) is a mistake — the
attribute would otherwise let the converter rewrite Eloquent attribute
casts, Laravel `messages()` tables, or JSON-serialization output as
validation rules, corrupting model behavior at runtime. 0.16 closes
this with three independent layers:

1. **Qualification gate.** The class-qualification path skips
   denylisted method names before checking for the attribute. A class
   whose ONLY would-be qualifier is `#[FluentRules] casts()` does NOT
   qualify — auto-detect of unrelated rules-shaped helpers is
   prevented.
2. **Mistake warning.** Each denylisted-attributed method emits one
   skip-log entry per class (deduped by class FQCN), regardless of
   whether the class otherwise qualifies. Surfaces the misapplied
   attribute so the user notices and can move it to the right method.
3. **Per-method conversion gate.** Three rectors
   (`ConvertsValidationRuleStrings`, `GroupWildcardRulesToEachRector`,
   `UpdateRulesReturnTypeDocblockRector`) short-circuit on the
   denylist before checking the attribute. Closes the gap where a
   class qualifying via FormRequest ancestry with a misapplied
   `#[FluentRules] casts()` would still rewrite the body via the
   attribute branch.

Single source of truth: the denylist constant lives on a new shared
`Concerns\NonRulesMethodNames` trait used by all three call sites.

#### Edge-case fix on the abstract guard

The abstract-with-rules guard previously gated on `hasRulesMethod()`,
which returns true on any method named `rules` OR any
`#[FluentRules]`-attributed sibling. An abstract class with only an
attributed helper and no literal `rules()` method would fire the
guard and emit a misleading "Add `#[FluentRules]` to the rules()
method" log even though no `rules()` method existed. Tightened to
`hasLiteralRulesMethod()`, which checks only the literal `rules()`
method.

#### `#[FluentRules]` documentation

README ships a new `### Opting in: #[FluentRules] attribute` section
covering when to use the attribute (custom-named methods, audited
abstract `rules()`), when not to use it (denylisted Eloquent / Laravel
hooks, unaudited abstract methods), and the per-method scoping
rationale.

#### Parity coverage for newly-unlocked paths

The 0.15.0 parity harness scopes coverage to the 3
semantics-changing rectors (`SimplifyRuleWrappersRector`,
`GroupWildcardRulesToEachRector`, `PromoteFieldFactoryRector`). The
attributed-conversion paths newly-unlocked by 0.16 stress
parent/child merge boundaries that warrant behavioral coverage even
though the host converter rectors stay excluded from the always-run
gate. Three new fixtures under `tests/Parity/Fixture/Attributed/`:

- Abstract parent's `#[FluentRules]`-attributed `rules()` body
- Validator-subclass attributed method
- Denylisted-attributed method (no-conversion contract smoke test)

`CoverageTest` extended with two assertions: `Attributed/` ships
≥1 fixture, and only documented Fixture/ subdirectories are
permitted. New fixture subdirs for excluded rectors fail CI.

#### No code-output changes for previously-converting shapes

The release is purely additive for the existing surface. Classes
that converted in 0.15.0 produce identical output in 0.16.0; the
new behavior fires only on classes that pick up the
`#[FluentRules]` opt-in or carry the misapplied-attribute pattern
(which is silently swallowed + warned, not converted).

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.15.0...0.16.0

## 0.15.0 - 2026-04-26

### Public API enumeration

[`PUBLIC_API.md`](PUBLIC_API.md) is now the source of truth for what's
semver-governed. It enumerates:

- All 12 rector class FQNs (renames break `withRule(...)` /
  `withConfiguredRule(...)` registrations).
- All 6 `FluentValidationSetList` constants.
- All 10 rector configuration constant *names AND string values* — both
  forms are committed (constant symbol + literal wire-key string).
- The `FLUENT_VALIDATION_RECTOR_VERBOSE` env var name + accepted values.
- Both skip-log file paths (verbose-on `<cwd>/.cache/...`, verbose-off
  hashed temp path).
- The skip-log line format and per-run header shape.
- The trait FQNs the trait-add rectors insert.

The README's new [Versioning policy](README.md#versioning-policy) section
documents which changes belong to MAJOR / MINOR / PATCH bumps, including
the explicit commitment that PATCH-level rector changes must not introduce
parity violations.

### `@internal` boundary

`SanderMuller\FluentValidationRector\Diagnostics` and
`SanderMuller\FluentValidationRector\RunSummary` were never on the public
API surface — they're internal helpers used by the rectors to emit
skip-log diagnostics. 0.15.0 makes that status unambiguous via class-level
`@internal` PHPDoc tags, which static analyzers (PHPStan strict mode,
intelephense) emit warnings for on consumer references.

If your code references `Diagnostics::*` or `RunSummary::*` symbols
directly, switch to the public commitments instead:

- `FLUENT_VALIDATION_RECTOR_VERBOSE` env var to control verbosity
- The documented skip-log paths to read diagnostic output
- The skip-log line format to grep / parse entries

These are the contracts that survive across versions. The class symbols
may move or change without notice.

**Namespace move deferred.** The originally-planned move to a
`SanderMuller\FluentValidationRector\Internal\` namespace is deferred to
1.0. A reproducer confirmed Composer's `--classmap-authoritative` mode
fatals on the moved FQN with a stale optimized classmap — common in
production deploy pipelines and Docker images. Requiring consumers to
run `composer dump-autoload` on a minor bump is too disruptive; the
namespace move waits for the 1.0 major-bump where the action is
acceptable.

### Validation parity harness

A new harness under `tests/Parity/` runs Laravel's validator over the
**pre-rector** rule shape and the **post-rector** rule shape with the
same payload, then diffs the resulting error bags. Catches the failure
mode the rector's own functional tests can't see: structural correctness
≠ behavioral equivalence. Same pass/fail outcome can mask different
`:attribute` substitution, different message-key paths, or different
error ordering — all user-visible regressions.

**Scope: 3 in-scope rectors** that change which rule class handles
validation:

- `SimplifyRuleWrappersRector` — escape-hatch `->rule('accepted')` →
  typed `->accepted()` calls.
- `GroupWildcardRulesToEachRector` — wildcard sibling-key fold into
  `each(...)`.
- `PromoteFieldFactoryRector` — `field()->required()->rule(X)` →
  `X()->required()` typed factory promotion.

Pure-refactor rectors (string→fluent, array→fluent, trait-add,
docblock-only, Livewire attribute conversion) ship with structural
coverage only — their transformations don't change which rule class
handles validation, so structural equivalence is sufficient.

**14 fixtures shipped** covering documented full-match cases plus the
documented divergences (e.g. `boolean()->accepted()` rejects `'yes' / 'on'`
strings that bare `accepted` accepts — categorized as
`ImplicitTypeConstraint`, narrowed to allow only the after-rejects
direction so a future regression making a rule MORE permissive doesn't
slot in silently).

**Coverage gate** asserts every in-scope rector ships ≥1 parity fixture;
adding a new semantics-changing rector must extend the in-scope list and
ship at least one fixture.

See the new [README §Parity](README.md#parity) section for fixture
authoring + `DivergenceCategory` enum semantics.

### Cross-Laravel-version CI matrix

The CI matrix now exercises Laravel 11.x / 12.x / 13.x explicitly, each
leg pinning the matching `orchestra/testbench` major (9.x / 10.x / 11.x).
Combined with the existing PHP 8.3/8.4 × ubuntu/windows ×
prefer-lowest/prefer-stable axes, every push runs against 24 cells.

`composer.json`'s `orchestra/testbench` constraint widened to
`^9.0||^10.11||^11.0` so a clean `composer install` in any leg resolves.

`tests/CIMatrixSanityTest.php` enforces matrix-leg drift detection: each
CI cell asserts the resolved `laravel/framework` major matches the
`CI_LARAVEL_MAJOR` env var the matrix sets, catching the failure mode
where a future change accidentally pins all legs to the same major.

The README's `## Compatibility` section flips from "honest framing" to
the full-matrix table that now matches CI reality.

### Drift-detection tests

Three new reflection-driven tests guard the boundary going forward:

- **`InternalAuditTest`** asserts every class under `src/` is either on
  the documented public list or carries a class-level `@internal`
  PHPDoc tag. Catches the failure mode where someone adds a new
  internal helper class and forgets the tag.
  
- **`PublicApiSurfaceTest`** runs three audits against `PUBLIC_API.md`:
  
  1. Every `public const` on a public class is documented.
  2. Every literal `$configuration[<key>]` access in a configurable
     rector references a documented wire key (PHP-Parser AST walk).
  3. Every documented constant's runtime value matches its documented
     wire-key string (lockstep-rename guard).
  
- **`ParityHarnessTest`** + **`DivergenceCategoryTest`** + the
  fixture-driven `ParityTest` cover the parity harness itself: outcome
  classification, denylists (DB rules, closures), locale pinning,
  divergence-category invariants, and per-fixture stale-entry
  detection.
  

All run under the standard `vendor/bin/pest` invocation. New constants,
wire keys, internal classes, or rector behavior shifts without the
matching documentation / fixture / category fail CI.

### Governance scaffolding

- `CONTRIBUTING.md` — local setup, workflow, commit-message style,
  public API discipline.
- `SECURITY.md` — vulnerability reporting (GitHub Security Advisories
  preferred), supported version window.
- `.github/ISSUE_TEMPLATE/bug_report.md` — package version, PHP
  version, Laravel version, rector config snippet, expected vs actual
  output, minimal reproduction.
- `.github/ISSUE_TEMPLATE/feature_request.md` — use case, proposed API
  shape, alternatives.
- `.github/PULL_REQUEST_TEMPLATE.md` — checklist with semver impact +
  PUBLIC_API.md update prompts.

### PHPStan `@internal` enforcement

`phpstan.neon.dist` now explicitly pins
`featureToggles.internalTag: true`. PHPStan was already enforcing it via
bleeding-edge defaults; the explicit pin survives default churn between
PHPStan releases.

### Lesson from 0.14.1's `Diagnostics::VERBOSE_LOG_FILENAME` change

That constant value changed silently. The lesson is *not* that
`Diagnostics` symbols should be frozen going forward — they're internal
and should remain free to evolve. The lesson is that the *observable
behavior* (the on-disk filename consumers grep for, the env var they
set, the line format they parse) is the public commitment. Code symbols
are implementation; behavior is API. 0.15.0 freezes the behavioral
surface and explicitly internalizes the implementation, so future
symbol-value changes don't masquerade as breaking changes.

### No code-output changes

This release ships no rector behavior changes. The 12 rectors emit the
same code as 0.14.1. All boundary work is via PHPDoc tags + tests +
documentation; no class moves, no symbol renames, no behavior shifts.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.14.1...0.15.0

## 0.14.1 - 2026-04-26

### What's fixed

#### Trait-insertion regressions (the headline)

1. **`AddHasFluentRulesTrait` now requires FormRequest ancestry** before
   inserting `HasFluentRules`. The trait overrides
   `createDefaultValidator(ValidationFactory)` — a FormRequest-internal
   hook — so it's dead code on Controllers, Actions, Nova resources,
   tests, JsonImportValidator subclasses, Spatie Data objects, and any
   other non-FormRequest class. Configured `base_classes` still get the
   trait regardless (intermediate abstract bases are still supported).
   
2. **`AddHasFluentValidationTrait` now requires Livewire-side validation
   surface** before inserting `HasFluentValidation` /
   `HasFluentValidationForFilament`. The required signal is any one of:
   
   - direct Filament trait presence (`InteractsWithForms` / `InteractsWithSchemas`)
   - a `rules()` method
   - any method carrying `#[FluentRules]`
   - any property carrying `#[Validate]` / `#[Rule]`
   - any method body calling `$this->validate(...)` or `$this->validateOnly(...)`
   
   Livewire components that use FluentRule only inside standalone
   `Validator::make([...])` calls no longer get the trait — its overrides
   would never be invoked. Filament components with FluentRule-bearing
   schema/form builders DO still get the trait — the Filament-trait
   signal covers that case.
   

13 regression fixtures cover all 8 false-positive shapes the dogfood
reports surfaced (Controller / Action / abstract-with-validator-factory /
Nova / Test / JsonImportValidator subclass / Spatie Data DataObject /
non-FormRequest plain class) plus 5 positive-confirming Livewire surfaces
(explicit `validate()`, `#[Validate]` attribute, Filament schema-only).

#### Diagnostic polish (filed GH issues)

3. **[#2] `SEMANTIC_DIVERGENCE_HINTS` no longer fires on
   `FluentRule::field()->rule(...)` receivers.** When the user has
   already written the explicit escape hatch, the hint reads as "you
   did the right thing" — not actionable. The skip-log entry is now
   suppressed entirely on the `field()` + `accepted` / `field()` + `declined`
   combination. Other receiver types (string-rule, `BooleanRule`) still
   emit because the hint is genuinely useful there.
   
4. **[#3] Skip log moved from `<cwd>/.rector-fluent-validation-skips.log`
   to `<cwd>/.cache/rector-fluent-validation-skips.log`.** Most projects
   already gitignore `.cache/` (Rector itself recommends it as the cache
   directory), so this closes the gitignore footgun where a verbose
   rector run + `git add .` would stage the log into a PR. Both the
   primary log and its `.session` sentinel move together. Auto-creates
   `.cache/` if missing; falls back to cwd root on read-only mounts.
   Legacy cwd-root paths are still cleaned up by `unlinkLogArtifacts()`
   so consumers upgrading from 0.4.x / pre-0.14.1 verbose runs get the
   old artifacts removed automatically.
   
5. **[#4] Skip log gains a per-run header** with package version,
   ISO-8601 UTC timestamp, and verbose tier:
   
   ```
   # laravel-fluent-validation-rector 0.14.1 — generated 2026-04-26T11:47:12Z
   # verbose tier: actionable
   
   [fluent-validation:skip] ...
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   ```
   Lets consumers attribute skip-log diff shapes to specific releases
   when grepping the log in CI. The header is **always emitted** when
   verbose mode is on, even on zero-entry runs, so the file's existence
   is stable across runs (no "file missing → file present" CI diff
   transition the moment a single skip fires). Version is read from
   `Composer\InstalledVersions` so it reflects the actually-installed
   release, not whatever's in the package's own composer.json.
   

#### Docblock improvements

6. **`UpdateRulesReturnTypeDocblock` now emits the short
   `FluentRuleContract` name** in the `@return` annotation and queues
   the import via `UseNodesToAddCollector`. Pre-0.14.1 the rector
   emitted the FQN inline; Pint's `fully_qualified_strict_types` cleaned
   it up afterwards but every rector run forced a follow-up Pint pass on
   every touched file. Now the import lands automatically; downstream
   Pint just re-sorts the use list. Files narrowed by older releases
   (FQN form) are still recognized as already-narrowed and left alone —
   no duplicate or conflicting annotation rewrites on upgrade.
   
7. **Blank `*`-only separator lines between `@return` and adjacent
   PHPDoc tags** (`@phpstan-*` annotations, `@param`, etc.) are now
   preserved across rewrites. The continuation regex previously consumed
   them, leaving a visually-collapsed docblock after every rector run.
   
8. **Livewire 4 contract fixture for empty `#[Validate]` markers** under
   `OVERLAP_BEHAVIOR_PARTIAL` — documents the runtime expectation
   (Livewire's real-time validation pipeline checks for the attribute's
   presence, not its args) and pins the rector's emit shape so future
   Livewire releases changing the contract are caught at fixture-update
   time.
   

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.14.0...0.14.1

## 0.14.0 - 2026-04-26

### What's new

#### Three new discovery surfaces

1. **`Validator::validate(...)` static call** — same arg layout as `Validator::make(...)`, identical rules-array conversion.
   
2. **Global `validator(...)` helper** — converted when the call resolves unambiguously to the global `\validator` function. Conservative: bails on userland shadows (a `validator()` call inside `namespace Foo` without a leading `\` could resolve to `Foo\validator` if defined) and on `use function ... validator` imports. Opt in with `\validator(...)` or by writing the call in the global namespace.
   
3. **Auto-detect rules-shaped methods** — any method on a qualifying class whose body matches the rules-array shape (single-statement `return [...]` with literal-string keys + at least one rule-shaped value) gets picked up. Catches custom helpers like `editorRules()`, `rulesWithoutPrefix()`, etc., without consumer config.
   
   ```php
   // Before
   class EditPostRequest extends FormRequest
   {
       public function rules(): array
       {
           return $this->editorRules();
       }
   
       public function editorRules(): array
       {
           return [
               'title' => 'required|string',
               'body'  => 'nullable|string',
           ];
       }
   }
   
   // After
   class EditPostRequest extends FormRequest
   {
       public function rules(): array
       {
           return $this->editorRules();
       }
   
       public function editorRules(): array
       {
           return [
               'title' => FluentRule::string()->required(),
               'body'  => FluentRule::string()->nullable(),
           ];
       }
   }
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   ```

#### Class-qualification gate (shared)

Auto-detection runs only inside a qualifying class. Four signals qualify:

- Extends `FormRequest` (anywhere in the ancestor chain)
- Uses `HasFluentRules`, `HasFluentValidation`, or `HasFluentValidationForFilament` (directly or via an ancestor)
- Is a Livewire component
- Has any method carrying `#[FluentRules]`

Class-wide auto-detection is gated on the first three (the stronger, class-wide signals). A class qualifying ONLY via the per-method `#[FluentRules]` attribute keeps the attribute as a per-method opt-in — sibling helpers stay untouched. Documented in `specs/widen-rule-discovery.md`.

#### Auto-detect safety boundaries

Auto-detect requires a single-statement `return [...]` with literal-string keys and a rule-shaped value. It explicitly skips:

- **Method-name denylist** — `casts()`, `getCasts()`, `getDates()`, `attributes()`, `validationAttributes()`, `messages()`, `validationMessages()`, `middleware()`, `getRouteKeyName()`, `broadcastOn()`, `broadcastWith()`, `toArray()`, `toJson()`, `jsonSerialize()`. Eloquent `casts(): array { return ['active' => 'boolean']; }` would otherwise match the rules-shape signature because `'boolean'` is a known rule token. Lookup is case-insensitive (`Casts()` is the same method as `casts()` at runtime).
- **`ClassConstFetch` keys** — `[Status::ACTIVE => 'required|string']` is rejected; the class-const may resolve to int / enum / mixed at runtime. Methods using class-const keys still convert via the literal `rules()` name path or the `#[FluentRules]` opt-in.
- **Multi-statement bodies** — helpers like `private function buildRules() { $rules = [...]; return $rules; }` stay untouched. Inline the return or convert by hand.

#### Parent-safety guard widened

The existing parent-safety guard (which prevented converting an abstract parent's `rules()` when a subclass did `array_merge(parent::rules(), [...])`) was widened along with the discovery surface:

- Scans every method on the child for `parent::*()` calls combined with array manipulation, not just `parent::rules()` inside the child's `rules()`.
- Recognises `unset($rules['x'])` as `Stmt\Unset_` (was dead code as a `FuncCall` lookup) and `collect()` as a manipulation primitive.
- Multi-class file resolution returns every parent FQCN in the file via `preg_match_all`, with alias-aware `use ... as` matching.
- Filesystem-fallback regex updated to match `parent::\w+\(`, `unset(`, and `collect(`.

#### Trait rectors now scan every method

`AddHasFluentRulesTraitRector` and `AddHasFluentValidationTraitRector` previously walked only `rules()` looking for FluentRule usage. They now walk every method, so trait insertion fires on rules-bearing helpers too.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.13.3...0.14.0

## 0.13.3 - 2026-04-25

Diagnostic-only patch. Zero behavior change to the rector's transformations from 0.13.2 — same code rewrites, same bails. The only difference: bail paths that used to be silent now emit a specific reason to the skip log.

### What you'll see

Run the `GROUP` set on a codebase with unsupported wildcard shapes:

```bash
FLUENT_VALIDATION_RECTOR_VERBOSE=actionable vendor/bin/rector process --clear-cache



















```
You'll now see entries like these for shapes the rector deliberately leaves alone:

```
[fluent-validation:skip] GroupWildcardRulesToEachRector App\Http\Requests\OrderRequest:
  wildcard group has non-FluentRule entries — cannot fold to each() (parent: 'items')

[fluent-validation:skip] GroupWildcardRulesToEachRector App\Http\Requests\TagsRequest:
  parent factory string() doesn't support each()/children() — only array() and field() do (parent: 'items')

[fluent-validation:skip] GroupWildcardRulesToEachRector App\Http\Requests\BulkUpdateRequest:
  wildcard parent 'tags.*' has type-specific rules that would be lost in grouping

[fluent-validation:skip] GroupWildcardRulesToEachRector App\Http\Requests\NestedRequest:
  double wildcard or non-first '*' in key suffix under 'items' — cannot fold to nested each()

[fluent-validation:skip] GroupWildcardRulesToEachRector App\Http\Requests\DynamicKeyRequest:
  concat key too complex to parse for grouping — only static class-constant prefixes (e.g. self::FOO) followed by a dotted-string suffix are supported



















```
Each entry names the offending property/key so you can find and fix the source shape (or accept that the rector intentionally leaves it for manual conversion).

### Why this matters

Pre-0.13.3, `GroupWildcardRulesToEachRector` had 5 decision-point bails that returned `null` silently. Users running `GROUP` on a codebase where some wildcard shapes weren't groupable saw nothing in the skip log to explain why those keys stayed flat. Now every such bail surfaces a specific reason via the `actionable` tier introduced in 0.13.0.

The trait was already imported on the rector — this release just wires it up.

### Audit details

The 5 decision-point bails get a `logSkip` call. The remaining ~22 `return null` / `return false` paths in the rector are framework filters (node-type mismatches), AST shape gates (wrong key form), or internal predicate returns — emitting at those would be noise without signal. Audit log lives in `specs/skip-log-coverage-hardening.md` for traceability.

Concurrent audit of `ValidationStringToFluentRuleRector` and `ValidationArrayToFluentRuleRector` confirmed no new emit sites needed there: their rector-level bails fire on every non-`validate` / non-`make` MethodCall in the visited tree, which would mean thousands of noise lines per file. The concern-level logging in `ConvertsValidationRuleStrings` already handles the real decision points.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.13.2...0.13.3

## 0.13.2 - 2026-04-25

### Enrichment

`SimplifyRuleWrappersRector`'s skip log emits an enriched message when the bail target is `accepted` or `declined` on a `FieldRule` receiver:

**Before** (0.13.1):

```
SimplifyRuleWrappersRector App\Foo\CreateNewUser: accepted() not on FieldRule




















```
**After** (0.13.2):

```
SimplifyRuleWrappersRector App\Foo\CreateNewUser: accepted() not on FieldRule — auto-promotion to FluentRule::boolean() blocked because boolean's implicit constraint rejects 'yes'/'on'/'true' which the accepted Laravel rule permits. Keep the field()->rule('accepted') escape hatch, OR use FluentRule::boolean()->accepted() explicitly if your form submits only 1/true/"1".




















```
Same enrichment for `declined`, calling out the `'no'` / `'off'` / `'false'` rejection by the boolean seed.

The 0.13.1 `SEMANTICALLY_DIVERGENT_PROMOTION` blocklist (the safety win that prevented the production-regression) was correct but silent on the rationale — a consumer reading the bail line had no way to infer that auto-promotion was considered and rejected for a specific reason. This release surfaces that rationale on the same line, mirroring the `POLYMORPHIC_TYPED_VERBS` hint pattern already used for `min` / `max` / `between` on `FieldRule`.

### Implementation

- New `SEMANTIC_DIVERGENCE_HINTS` const on `SimplifyRuleWrappersRector` keyed by method name.
- Checked after the polymorphic-verb hint inside the FieldRule-receiver bail branch.
- Active only when both: receiver class is `FieldRule` AND target method is in the divergence-hint map. Other receivers and methods unaffected.

### Documentation

- README's `PromoteFieldFactoryRector` paragraph now lists the accepted/declined bail alongside the existing Conditionable-hop and non-singleton-intersection bails. Caught by the pre-release docs audit — 0.13.1 shipped the blocklist behavior without updating README.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.13.1...0.13.2

## 0.13.1 - 2026-04-24

Bundled patch release. One semantic-regression fix (caught pre-apply downstream, no consumer reached production) plus one static-analyzer DX fix from the same dogfood pass.

### Fix — `accepted` / `declined` semantic-divergence blocker

`PromoteFieldFactoryRector` was silently promoting `FluentRule::field()->rule('accepted')` to `FluentRule::boolean()->accepted()`. The `accepted()` method exists only on `BooleanRule`, so the method-name-based intersection picked it as the unique target — but `BooleanRule` seeds the implicit `'boolean'` constraint, which is **strictly more restrictive** than Laravel's `accepted` rule:

| Input | `accepted` rule | `boolean()->accepted()` (0.13.0 rewrite) |
|-------|:---:|:---:|
| `"yes"` | ✅ | ❌ rejected by `boolean` seed |
| `"on"` | ✅ | ❌ rejected by `boolean` seed |
| `"true"` | ✅ | ❌ rejected by `boolean` seed |
| `1` / `"1"` / `true` | ✅ | ✅ |

HTML checkbox defaults submit `value="yes"` (Laravel convention) or `"on"` (browser default when no value attribute is set). Every TOS-acceptance, consent, and opt-in form using `->rule('accepted')` under 0.13.0 auto-rewrite would silently start rejecting valid user input. Same divergence applies to `declined` (rejects `"no"` / `"off"` / `"false"`).

Fix extends the `SEMANTICALLY_DIVERGENT_PROMOTION` pattern already used for Password vs StringRule `min(int, ?string)` vs `min(int)` signature collisions (shipped earlier in 0.13.0). When any `->rule(<name>)` call in a chain names a rule whose Laravel semantics diverge from the would-be target factory's seed constraints, the promoter bails and leaves both the `field()` factory and the escape-hatch `->rule('…')` untouched.

**What still rewrites:** `FluentRule::boolean()->rule('accepted')` — where the user explicitly chose `boolean()` — still rewrites to `boolean()->accepted()` via `SimplifyRuleWrappersRector`'s zero-arg token rewrite. That's behavior-preserving: the user already accepted the `boolean` constraint by picking the factory.

**What got blocked:** `FluentRule::field()->rule('accepted')` (labeled or unlabeled) and `FluentRule::field()->rule('declined')` now stay as the field-plus-escape-hatch shape.

### Fix — allowlist constants re-declared on using rectors

`SimplifyRuleWrappersRector::TREAT_AS_FLUENT_COMPATIBLE` and `::ALLOW_CHAIN_TAIL_ON_ALLOWLISTED` (plus the same two on `UpdateRulesReturnTypeDocblockRector`) were inherited from the `AllowlistedRuleFactories` trait in 0.13.0. PHP 8.2+ permits reading a trait constant via the using class at runtime, but `intelephense` and PHPStan both flag the expression as "Undefined class constant" in consumer `rector.php` files — red squiggles on the recommended config shape. This release re-declares the two keys directly on each using rector, eliminating the diagnostic without changing values.

Configs written against 0.13.0 continue to work unchanged.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.13.0...0.13.1

## 0.13.0 - 2026-04-24

Bundles six spec-driven features from a multi-repo dogfood pass against 0.12.x. Every change is opt-in or strictly additive — default behavior matches 0.12.1.

### Converters (`CONVERT`)

- **`InlineResolvableParentRulesRector` — variable-spread resolver.** The rector now inlines `...$base` when `$base` is the method's only top-level assignment and its RHS is either a literal array or `parent::rules()`. Covers the `$base = parent::rules(); return [...$base, 'extra' => '...'];` idiom without the user having to refactor into the single-return form. Gates are strict on purpose — methods with any peer top-level assignments, nested-scope assignments (`if`/`foreach`/`match`/`try`), multi-use variables, or `unset($base)` are left alone. When inlining succeeds, the now-dead `$base = ...;` statement is stripped to avoid duplicate RHS evaluation on impure expressions.
  
- **`ConvertLivewireRuleAttributeRector` — `KEY_OVERLAP_BEHAVIOR` config.** New config key controlling what happens when a class has `#[Validate]` attrs AND an explicit `$this->validate([...])` call. `'bail'` (default) preserves 0.12 semantics: classwide skip to avoid fabricating a `rules()` whose shape contradicts the explicit call. `'partial'` converts attrs whose predicted emit keys don't appear in any explicit `validate([...])` array, leaves overlapping attrs + the explicit call intact. Extraction-unsafe wrappers (`array_merge(...)`, variable args, dynamic keys) force classwide bail even under `'partial'`. Keyed-array attrs (`#[Validate(['todos' => ..., 'todos.*' => ...])]`) have their internal keys — not the property name — checked against the explicit call's keys. Named `rules:` arg takes precedence over positional index so `validate(messages: [...], rules: [...])` reads the right slot.
  

### Post-migration (`SIMPLIFY`)

- **`PromoteFieldFactoryRector` — Password/Email promotion.** New trigger: `FluentRule::string()->rule(Password::default())` / `->rule(Email::default())` now promotes to `FluentRule::password()` / `::email()`. Gates: zero-arg source factory, single `Password`/`Email` match in the chain, no `Conditionable` hops, and target class has every modifier method the original chain used. Guards against the `::string($label)` → `::password($label)` rebinding hazard (Password's first arg is `?int $min`, not a label string) and against colliding methods that exist on both receivers with different signatures (`min(int, ?string)` on `StringRule` vs `min(int)` on `PasswordRule`).
  
- **`SimplifyRuleWrappersRector` — extensions.**
  
  - Zero-arg string-token rewrites: `->rule('accepted')`, `'declined'`, `'present'`, `'prohibited'`, `'nullable'`, `'sometimes'`, `'required'`, `'filled'` rewrite to their native method calls when the typed receiver has the matching method.
  - Conditionable proxy walk: `->when($cond, fn ($r) => $r->rule('...'))` no longer bails when the closure body is a bare-return, no-return, or `fn ($r) => $r` identity. The proxy hop is transparent to receiver-type inference; non-identity closure bodies (method chains, different-rule returns, field accessors like `$r->getLabel()`) still bail to preserve semantics.
  - Consumer-declared allowlist of FluentRule-compatible rule factories via `TREAT_AS_FLUENT_COMPATIBLE` + `ALLOW_CHAIN_TAIL_ON_ALLOWLISTED` (default off). Glob patterns: exact / `*` (single namespace segment) / `**` (recursive). Matches silence the "rule payload not statically resolvable" skip log on shapes like `->rule(App\Rules\Domain\DutchPostcodeRule::create())` or `->rule(new DomainPatternRule())` that rector can't introspect but the consumer knows are fluent-compatible.
  

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.12.1...0.13.0

## 0.12.1 - 2026-04-24

This release fixes two bugs, downgrades the dominant skip-log noise to verbose-only, and enriches one ambiguous skip message with actionable upgrade hints. Internal complexity refactor of `GroupWildcardRulesToEachRector` + `ConvertsValidationRuleStrings` lands alongside.

### `UpdateRulesReturnTypeDocblockRector` — two narrow-from false-positives

`@return array<string, mixed>` is Laravel's default IDE-generated docblock on scaffolded `rules()` methods. The rule previously treated it as "user-customized — respecting" and skipped. Narrowing `mixed` at the item level is strict once condition 3 has proven every value is a `FluentRule::*()` chain, so it now narrows cleanly to `array<string, \SanderMuller\FluentValidation\Contracts\FluentRuleContract>`. Removes ~14 false-positive skips per run on codebases that rely on IDE scaffolding.

`@return array<string, FluentRuleContract>` (short-name form, after consumers add `use SanderMuller\FluentValidation\Contracts\FluentRuleContract;` post-conversion) is now recognized as already-narrowed and skips silently. Previous idempotency guard only matched the FQN form the rector emits, so codebases that imported the contract via `use` kept hitting 40+ verbose-mode false-positive entries per run.

### `AddHasFluentRulesTraitRector` — Livewire-detect skip-log downgraded

`"detected as Livewire (uses HasFluentValidation instead)"` was firing in default-mode for every Livewire/Filament component in the codebase. On Livewire/Filament-heavy apps this dominated the skip log: 72/125/84 hits across three dogfood reports, all non-actionable (the user already knows these aren't FormRequests; rector correctly picks the other trait path).

Downgraded to `verboseOnly: true`. Default-mode silent, still surfaced under `FLUENT_VALIDATION_RECTOR_VERBOSE=1` for anyone debugging "why didn't the trait get added?". Kills roughly 70% of default-mode log volume on affected projects.

### `SimplifyRuleWrappersRector` — polymorphic-verb upgrade hint

When a `FluentRule::field()->rule('<verb>:…')` chain uses a verb that exists on multiple typed rule classes (`min` / `max` / `between` / `exactly` / `gt` / `gte` / `lt` / `lte`), `PromoteFieldFactoryRector` correctly declines to auto-promote — the verb's compatible-class intersection isn't a singleton and picking one would change validation behavior (string-length vs numeric-min vs array-count vs file-size). `SimplifyRuleWrappersRector` then bails with `"min() not on FieldRule"`.

The bare skip line read like a rector bug. The same skip now includes candidate factory names so verbose-mode triage directly points users at the right explicit choice:

```
[fluent-validation:skip] SimplifyRuleWrappersRector App\Requests\Foo (rules): min() not on FieldRule — min() is type-dependent; consider FluentRule::string() / FluentRule::numeric() / FluentRule::array() / FluentRule::file() depending on the field























```
### Internal: complexity reductions

`GroupWildcardRulesToEachRector::groupRulesArray()` extracted its key-classification logic (String_ / ClassConstFetch / Concat / synthetic-key fallback) into `indexRuleItem()`. Class cognitive complexity drops from 198 to 174; the three previously-baselined function offenders (`applyGroups`, `buildNestedItems`, `groupRulesArray`) come in under the 20-bar threshold. phpstan-baseline entries removed for the newly-compliant cells.

`Concerns\ConvertsValidationRuleStrings::collectUnsafeParentClassesFromFile` split into `collectExtendingClassesFromFile` + `extractExtendingClasses` helpers. Recursive `Rector\PhpParser\Node\FileNode` + `Namespace_` unwrapping replaces the previous manually-nested loop.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.12.0...0.12.1

## 0.12.0 - 2026-04-24

Three changes surfaced by the 0.11.0 dogfood pass against a heavy-inheritance Livewire/FormRequest codebase. Two new rectors + one diagnostic enrichment on an existing rector. No config or dep-floor changes.

### `InlineResolvableParentRulesRector` — new, in `CONVERT`

Inlines `parent::rules()` when it appears as a spread at index 0 of a child `rules()` method and the parent is a plain `return [...];`. Previously the converter rectors bailed silently on `[...parent::rules(), 'foo' => '...']` shapes because the produced keys can't be determined statically; now the parent's literal array is pulled in before `ValidationStringToFluentRuleRector` / `ValidationArrayToFluentRuleRector` run.

**In scope:** parents whose `rules()` is a single `return [...]` statement with a plain array literal. Child classes extending those can spread freely.

**Out of scope (unchanged, stays spread):** parents whose `rules()` merges (`array_merge(...)`), concatenates (`return [...] + [...]`), calls methods on the return value, or has multi-statement bodies. Bails silently — the original spread is preserved.

Runs first in the `CONVERT` set. Parsed parent sources are cached by `path + mtime` within one rector invocation so repeated child classes sharing the same parent don't re-parse.

### `PromoteFieldFactoryRector` — new, in `SIMPLIFY`

Promotes `FluentRule::field()` to a typed factory (`::string()`, `::numeric()`, `::array()`, etc.) when every `->rule(...)` wrapper in the chain parses to a v1-scope rule whose target method lives on exactly one typed FluentRule subclass.

Before:

```php
FluentRule::field()->rule('max:61')->rule('regex:/^[a-z]+$/');
























```
After the promotion + the next `SimplifyRuleWrappersRector` pass:

```php
FluentRule::string()->max(61)->regex('/^[a-z]+$/');
























```
Previously these chains skip-logged `"max() not on FieldRule"` and stayed on the `->rule(...)` escape hatch. Promoting the factory lets the existing `SimplifyRuleWrappersRector` pass lower the wrappers naturally on the typed receiver.

**Semantic note.** `StringRule` adds Laravel's implicit `string` rule; `NumericRule` adds `numeric`; `FieldRule` adds neither. Promoting `FluentRule::field()->rule('max:61')` to `FluentRule::string()->max(61)` therefore changes validation behavior for non-string inputs — Laravel will now fail early with "field must be a string" instead of evaluating `max` against the value. In the v1 rewrite scope this matches intent in the overwhelming majority of cases, but diff review is expected for chains where the user explicitly wanted the untyped `FieldRule` surface.

**Bails silently** on:

- Conditionable hops in the chain (`->when(...)`, `->unless(...)`, `->whenInput(...)`) — receiver becomes a `HigherOrderWhenProxy`, which would leave the escape hatch in place while still adding the implicit `string`/`numeric` rule.
- Chains whose parsed rules have a compatible-class intersection size ≠ 1 (ambiguous target type, or already-singleton-`FieldRule` which needs no promotion).
- At least one `->rule()` payload that failed to constrain (no signal about target type).

Runs first in the `SIMPLIFY` set so the typed factory reaches `SimplifyRuleWrappersRector`'s next pass. Consts `V1_REWRITE_TARGETS` and `RULE_NAME_TO_METHOD` on `SimplifyRuleWrappersRector` flipped from `private` to `public` so this rector can reason about rewrite-compatibility without duplicating the table.

### `SimplifyRuleWrappersRector` verbose skip diagnostic

The verbose-only `"rule payload not statically resolvable to a v1 shape"` skip line now includes the rejected payload's AST class and a truncated pretty-print of the expression. Dogfood passes previously had to open every flagged file to bucket causes; now a single `grep` over `.rector-fluent-validation-skips.log` partitions skips into their actual buckets:

```
[fluent-validation:skip] SimplifyRuleWrappersRector App\Requests\Foo (rules): rule payload not statically resolvable to a v1 shape: StaticCall Password::default()
[fluent-validation:skip] SimplifyRuleWrappersRector App\Requests\Bar (rules): rule payload not statically resolvable to a v1 shape: New_ App\Validation\CustomRule(…)
[fluent-validation:skip] SimplifyRuleWrappersRector App\Requests\Baz (rules): rule payload not statically resolvable to a v1 shape: MethodCall …->withoutTrashed()
























```
Skip behavior unchanged. Only verbose-mode emitters pay the pretty-print cost (via a lazily-created `Standard` printer shared across the rector's lifetime).

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.11.0...0.12.0

## 0.11.0 - 2026-04-24

Skip-log noise reduction + Livewire-class detection fix + one new rewrite target.

Driven by a `.rector-fluent-validation-skips.log` audit against a real codebase (519 entries): ~81% of skip log lines were not actionable — either misdetection bugs, routine success cases, or legitimate escape-hatch usage. This release silences the non-actionable categories by default, fixes the underlying misdetection bug, and promotes one previously-silent drop into an actionable hint.

### Livewire-class detection no longer misfires on `render()`-method carriers

The previous heuristic marked every class with a `render()` method as a Livewire component, which misfired on:

- Exceptions implementing `CustomRenderInterface::render()`
- DataObjects implementing `PaginatorContract::render()`
- Action classes with a `render()` method
- Blade view components, Filament `Page` subclasses, Nova tools

`IdentifiesLivewireClasses::isLivewireClass()` now walks the parent chain via `ReflectionClass` looking for `Livewire\Component` or `Livewire\Form`, and also treats direct `HasFluentValidation` / `HasFluentValidationForFilament` trait use on the class body as a Livewire signal. Consumers with intermediate Livewire base classes (`MyComponent extends BaseComponent extends Livewire\Component`) and Filament pages (which extend `Livewire\Component` transitively) are correctly detected. The inline copy of this heuristic in `AddHasFluentRulesTraitRector` was removed in favor of the shared trait.

### Default skip log only contains actionable entries

Four categories demoted to verbose-only output (`FLUENT_VALIDATION_RECTOR_VERBOSE=1` still surfaces them for debugging):

- **Abstract-class skip** is now gated on `hasMethod('rules')`. Previously every abstract class in the codebase emitted a skip-log entry from `AddHasFluentRulesTraitRector`, `AddHasFluentValidationTraitRector`, and both `ValidationStringToFluentRuleRector` / `ValidationArrayToFluentRuleRector`. Events, Exceptions, DataObjects, and Commands with no validation surface no longer produce entries.
- **`already has HasFluentRules trait` / `already has HasFluentValidation trait` / `already has HasFluentValidationForFilament trait` / `parent class already uses <trait> (trait inherited)` / `extends a configured base class`** — these are success cases, not skips a user can act on.
- **`existing @return tag '...' is user-customized — respecting`** from `UpdateRulesReturnTypeDocblockRector` — respecting a user docblock is correct behavior.
- **`rule payload not statically resolvable to a v1 shape`** from `SimplifyRuleWrappersRector` — legitimate escape-hatch use (`->rule(Password::default())`, `->rule(new CustomRule())`, `->rule(User::uniqueRule(...)->withoutTrashed())`).
- **`<method>() not on <ShortClass>`** from `SimplifyRuleWrappersRector` — a UX nudge for `FluentRule::field()->rule(['min', ...])` → `FluentRule::string()->min(...)`, but not actionable from the skip log directly.

The end-of-run skip summary still counts all entries from verbose mode when opted in, so projected log size on a heavy-inheritance codebase drops by ~81% (≈420 of 519 entries) without losing diagnostic coverage for consumers who need it.

### Livewire components with string-form `rules()` are now flagged

Previously, `AddHasFluentValidationTraitRector` silently dropped Livewire components whose `rules()` method contained only string rules (no `FluentRule` usage). This is actionable — the user either forgot to run `ValidationStringToFluentRuleRector` before the trait rule, or has rules the converter couldn't rewrite. The rector now emits:

```
Livewire component has rules() but no FluentRule usage — convert string rules to FluentRule (run the ValidationStringToFluentRuleRector set) before this rule fires

























```
### `->rule(['required_array_keys', ...])` lowers to `->requiredArrayKeys(...)`

Filling the last gap in the conditional/presence tuple-rewrite family. Receiver-type gating via the per-class method allowlist naturally restricts the rewrite to `ArrayRule` (the only class that declares `requiredArrayKeys(string ...$keys)`):

```php
// Before
FluentRule::array()->rule(['required_array_keys', 'id', 'slug'])

// After
FluentRule::array()->requiredArrayKeys('id', 'slug')

























```
### Opt back into verbose skip logging

```bash
FLUENT_VALIDATION_RECTOR_VERBOSE=1 vendor/bin/rector process --clear-cache

























```
Every silenced category in this release is restored. Useful when debugging why a specific file wasn't rewritten.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.10.1...0.11.0

## 0.10.1 - 2026-04-23

`ValidationArrayToFluentRuleRector` now converts rules whose tuples contain dynamic expressions that previously bailed the whole rule — `Ternary`, `MethodCall`, `FuncCall`, `PropertyFetch` on `$this`, `NullsafePropertyFetch`, `ArrayDimFetch`, `Cast`, `Match_`, `StaticCall`, etc. The motivating case:

```php
// Before (bailed, left unchanged)
'chapters' => [
    ['required_if', 'type', InteractionType::CHAPTER->value],
    'array',
    ['max', $this->video()->orientation?->isLandscape() ? 15 : 20],
],

// After
'chapters' => FluentRule::array()
    ->requiredIf('type', InteractionType::CHAPTER->value)
    ->max($this->video()->orientation?->isLandscape() ? 15 : 20),


























```
Applied only to the fluent-method lowering (`['max', $x]` → `->max($x)`) and `->rule([...])` escape-hatch paths. COMMA_SEPARATED conditional rules (`requiredIf`, `excludeUnless`, …) keep the strict whitelist — their fluent signatures are overloaded (`Closure|bool|string $field`), so a dynamic expression that evaluated to a closure/bool at runtime would silently switch between field-comparison and closure/bool branches. Such tuples fall through to `->rule([...])` instead of `->requiredIf(...)`:

```php
// Before (bailed)
'role' => [
    'string',
    ['required_if', 'type', $this->roleResolver()],
],

// After (escape hatch preserves array-form runtime semantics)
'role' => FluentRule::string()->rule(['required_if', 'type', $this->roleResolver()]),


























```
Shapes that would reach a typed fluent method (`->max(int)`) or Laravel's `serializeValues()` with a non-scalar still bail to preserve the original failure mode:

- `New_` / `Clone_` — object producers
- `Closure` / `ArrowFunction` — callable producers
- `Array_` — nested array literal
- `Yield_` / `YieldFrom` / `Throw_` / `Include_` / `Eval_` — non-returning or non-value expressions
- `Assign` / `AssignOp` / `PreInc` / `PostInc` / `PreDec` / `PostDec` — side-effectful mutators
- `Concat` containing any of the above at any depth (recursion)

### `SimplifyRuleWrappersRector` — escape-hatch `->rule([...])` for conditional rules

`->rule(['required_if', $field, $value])` and variants now lower to native fluent methods when every tuple arg is statically scalar:

```php
// Before
FluentRule::string()->rule(['required_if', 'subtitleSource', SubtitleSource::Paste->value])

// After
FluentRule::string()->requiredIf('subtitleSource', SubtitleSource::Paste->value)


























```
Covers:

- **Category A** (`string $field, ...$values`): `required_if`, `required_unless`, `exclude_if`, `exclude_unless`, `prohibited_if`, `prohibited_unless`, `present_if`, `present_unless`, `missing_if`, `missing_unless`. Minimum 2 tail args (field + value) — prevents arity-fail rewrite.
- **Category B** (`string ...$fields`): `required_with`, `required_with_all`, `required_without`, `required_without_all`, `present_with`, `present_with_all`, `missing_with`, `missing_with_all`, `prohibits`. Minimum 1 tail arg.

Bare BackedEnum cases (`Enum::CASE`) in tail positions are auto-wrapped with `->value`, mirroring the array-converter's `adaptEnumArg`..

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.10.0...0.10.1

## 0.10.0 - 2026-04-23

`ValidationArrayToFluentRuleRector` now converts two tuple shapes it previously bailed on. Both shapes are common in FormRequests that rely on enum-backed conditional validation.

### Array-form tuple: `Enum::CASE->value` as conditional arg

Tuple args written as explicit `PropertyFetch` on a `ClassConstFetch` (the BackedEnum idiom `Enum::CASE->value`) are now accepted by the safety gate. Previously the whole rule array bailed because only bare `ClassConstFetch` was recognised as safe.

```php
// Before (bailed, left unchanged)
'mode' => [
    ['exclude_unless', 'type', InteractionType::PAUSE->value],
    'sometimes',
    Rule::enum(DisplayMode::class),
],

// After
'mode' => FluentRule::field()
    ->excludeUnless('type', InteractionType::PAUSE->value)
    ->sometimes()
    ->enum(DisplayMode::class),



























```
The match is narrow — only `->value` on a `ClassConstFetch` qualifies. Dynamic property names (`->$var`) and unrelated property fetches (`->name`, `->items`) still bail.

### Array-form tuple: variadic spread on conditional rules

In-tuple variadic spread (`...Enum::list()`, `...$values`) is now preserved when the target fluent signature can absorb it without semantic drift. Spread survives for two signature categories:

- **Field + variadic values** — `requiredIf`, `requiredUnless`, `excludeIf`, `excludeUnless`, `prohibitedIf`, `prohibitedUnless`, `presentIf`, `presentUnless`, `missingIf`, `missingUnless`. Spread allowed at position ≥ 2 (the `$field` arg must be statically present).
- **Pure variadic fields** — `requiredWith`, `requiredWithAll`, `requiredWithout`, `requiredWithoutAll`, `presentWith`, `presentWithAll`, `missingWith`, `missingWithAll`, `prohibits`. Spread allowed at any position ≥ 1.

```php
// Before (bailed, left unchanged)
'duration' => [
    'bail',
    ['required_unless', 'type', ...InteractionType::getValuesWithoutDuration()],
    'numeric',
    new DoesNotExceedVideoDurationRule($this->video()),
],

// After
'duration' => FluentRule::numeric()
    ->bail()
    ->requiredUnless('type', ...InteractionType::getValuesWithoutDuration())
    ->rule(new DoesNotExceedVideoDurationRule($this->video())),



























```
**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.9.0...0.10.0

## 0.9.0 - 2026-04-22

New `InlineMessageParamRector` in the opt-in `SIMPLIFY` set. Collapses chained `->message()` / `->messageFor()` calls into the inline `message:` named parameter on `FluentRule` factories and rule methods, against the `laravel-fluent-validation` 1.20.0 surface. Composer floor bumped from `^1.19` to `^1.20`.

### `InlineMessageParamRector`

Three rewrite predicates:

**Factory-direct.** `->message()` immediately on a `FluentRule::factory()` static call, no intervening `MethodCall` (rule method or Conditionable hop) allowed. Collapses to a `message:` named arg on the factory.

```php
// Before
FluentRule::email()->message('Bad email.')->required();

// After
FluentRule::email(message: 'Bad email.')->required();




























```
Non-adjacent cases (`->email()->required()->message('x')`) stay chained — `->required()` mutates `$lastConstraint`, so the message binds to `'required'`, not `'email'`. Conditionable hops (`->when()` / `->unless()` / `->whenInput()`) reject the collapse for the same reason.

**Rule-method matched-key `messageFor`.** `->messageFor(K, msg)` on a typed-rule method whose emitted rule-token equals `K`. Rewrites to an appended `message:` named arg on the receiver method.

```php
// Before
FluentRule::string()->min(3)->messageFor('min', 'Too short.');

// After
FluentRule::string()->min(3, message: 'Too short.');




























```
Emitted-key derivation: snake_case of the method name by default, with a hardcoded override table for the ≠ snake_case cases (`exactly` → `size`, `greaterThan` → `gt`, `alphaNumeric` → `alpha_num`, the `DateRule` wrapper aliases like `beforeToday` → `before`, etc.). Source: reading `addRule()` call sites in the vendor tree + peer handoff.

**Rule-object `messageFor`.** `->rule(new $RuleClass(...))->messageFor(K, msg)` when `K` matches the rule object's class-basename-derived key (mirrors `HasFieldModifiers::addRule()`'s match statement — `RequiredIf/Unless` → `'required'`, `In` → `'in'`, `NotIn` → `'not_in'`, etc.; default fallback is `lcfirst(class_basename)`).

```php
// Before
FluentRule::string()->rule(new In(['admin', 'user']))->messageFor('in', 'Pick a valid role.');

// After
FluentRule::string()->rule(new In(['admin', 'user']), message: 'Pick a valid role.');




























```
### Skip-log taxonomy (6 categories)

The rector refuses to rewrite and emits a per-category user-facing skip-log entry in these cases:

- **Variadic-trailing methods** (`requiredWith` / `requiredWithout` / `contains` / `startsWith` / `extensions` / `mimes` / the full variadic family) — `message:` position ambiguous with trailing `...args`. Chain stays.
- **Composite methods** (`NumericRule::digits` / `digitsBetween`, `DateRule::between` / `betweenOrEqual`, `ImageRule::width` / `height` / `dimensions` / etc.) — inline `message:` binds to the last sub-rule only; a `messageFor(K, ...)` where K targets a sibling sub-rule would misbind.
- **Mode-modifier methods** (`EmailRule::strict` / `rfcCompliant`, `PasswordRule::min` / `max` / `letters` / `mixedCase` / `numbers` / `symbols` / `uncompromised`) — don't call `addRule()`, no message target exists.
- **Deferred-key factories** (`FluentRule::date()`, `FluentRule::dateTime()`) — emitted key varies at build (`date` vs `date_format:...`), so no single factory-level message target.
- **L11/L12-divergent `Password`** — `->rule(new Password(...))->messageFor('password', ...)` resolves via Laravel's `getFromLocalArray` shortRule lookup (class-basename → `'password.password'`), which is L12+ only. L11's 3-key lookup does NOT match. The verbatim skip-log template cites the lookup path, the L11 miss, and actionable sub-key alternatives (`messageFor('password.letters', ...)` / `'password.mixed'` / a `messages(): array` entry).
- **No-implicit-constraint factories** (`FluentRule::field()`, `FluentRule::anyOf()`) — factory has no implicit rule, so no inline `message:` target.

**Pre-existing user misbindings** (`->min(3)->messageFor('max', ...)`, `->rule(new DoesntContain(...))->messageFor('doesnt_contain', ...)` on pre-1.21 fluent-validation) stay chained silently with no skip-log — rector leaves consumer bugs alone per spec non-goal.

### Implementation details

- **Literal-only rewrite guard** — only rewrites when the message arg is a `String_` literal or a flat `Concat` chain of literals. Variables, `__()` / `trans()` / `sprintf()` calls, ternaries, and nested expressions stay chained. Inline and chained forms work identically; the diff churn isn't worth it for expression-heavy calls.
- **Named-arg always, never positional** — factory signatures vary (`email(?label, bool $defaults, ?message)`, `regex(string $pattern, ?label, ?message)`), so positional insertion would need per-factory arg-index logic. Named form is position-agnostic.
- **Reflection-based surface discovery** — `InlineMessageSurface::load()` reflects over `FluentRule::*` statics and the 12 typed-rule classes + `HasFieldModifiers` / `HasEmbeddedRules` trait methods at boot to build the allowlist. Cached on a static property for the PHP process lifetime; pest parallel isolates at the process level so no cross-worker contention.
- **Composer floor guard** — if `FluentRule::email`'s signature lacks the `?string $message = null` parameter (pre-1.20 consumer), the surface probe short-circuits and the rector returns no rewrites.

### Composer floor bump (^1.19 → ^1.20)

`sandermuller/laravel-fluent-validation` floor is now `^1.20`. Consumers on 1.17 / 1.18 / 1.19 should pin this rector to `^0.8` — 0.9.0+ requires 1.20 even if you only use CONVERT / GROUP / TRAITS (composer enforces the floor at install time). The peer package's 1.20.0 released alongside this rector adds the inline `message:` surface (23 factories + ~80 rule methods + `->rule(object, message:)`).

### Known interactions with fluent-validation 1.21.0

A follow-up 1.21.0 upstream release (pending tag at the time of this rector cut) explicitly maps `DoesntContain → 'doesnt_contain'` in `HasFieldModifiers::addRule()`'s match statement. On 1.20.0 `DoesntContain` falls through to the class-basename default and emits `'doesntContain'` (camelCase). The rector's reflection-based allowlist pins whichever version the consumer installs — 1.20.0 consumers calling `->rule(new DoesntContain(...))->messageFor('doesntContain', ...)` rewrite cleanly; 1.21.0+ consumers calling `messageFor('doesnt_contain', ...)` become the rewrite target instead.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.8.1...0.9.0

## 0.8.1 - 2026-04-22

Internal test infrastructure + rector-developer skill improvements. No user-visible API or behaviour change — safe patch upgrade.

### Runtime parity harness for validator configuration paths

`ValidationEquivalenceTest` pins string-form ↔ fluent-form equivalence across three configuration axes that interact with rector-emitted `->label()` and the GROUP-set shape flip but weren't previously asserted:

- **`Validator::setAttributeNames([...])`** — renamed attributes must render identically pre- and post-rector. A rector emitting `->label(...)` where the source had none would silently change user-visible copy; the harness catches it.
- **`Validator::setCustomMessages([...])`** — flat `{attr}.{rule}` and wildcard `{attr.*.nested}.{rule}` keys must resolve identically across the `items.*.name` → `FluentRule::array()->each(['name' => ...])` shape flip.
- **Lang-file overrides** — `validation.custom.{attr}.{rule}` and `validation.attributes.{attr}` registered through the translator must produce identical rendered messages across shapes.

27 new data rows (43 total, 123 assertions) cover presence, conditional (`required_if` / `required_unless`), comparison, format, multi-depth wildcard bag-path parity (`users.*.tags.*` etc), `confirmed` sibling-lookup, and mixed keyed+wildcard rule-declaration ordering.

Independent `expectedErrorKeys` + `expectedMessageCount` pins run alongside the cross-form `assertSame`, so rows survive the "both forms degrade identically" scenario where the cross-form compare would stay silent (found during Phase 3: `addLines()` without pre-`load()` silently wipes the validation group — caught by the marker-string probe, not the cross-form assert).

No public API or behaviour change. This is test-only infrastructure protecting the rector's output contract against future drift.

### `rector-developer` skill guidance updates

Expanded `.ai/skills/rector-developer/` with two patterns for new rule authors:

- **Preventing Duplicate Attributes** — `PhpAttributeAnalyzer` injection, non-repeatable vs repeatable attribute guarding, required `skip_attribute_already_present.php.inc` fixture.
- **Reducing Rule Risk** — `isFinal()` guard + `skip_non_final_class.php.inc` fixture for transformations that could break subclasses; `isPrivate()` guard or per-visibility skip fixtures for transformations of public/protected members (class API contract).

Added the missing frontmatter block so the skill registers under its canonical trigger words ("write a Rector rule to…", "create a Rector rule that…").

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.8.0...0.8.1

## 0.8.0 - 2026-04-22

Two feature bundles ship together: opt-in `messages()` migration for Livewire `#[Validate]` attributes, and full coverage of the `laravel-fluent-validation` 1.19.0 surface delta across three rectors.

### Livewire `#[Validate]` `message:` → `messages()` migration (opt-in)

New `MIGRATE_MESSAGES` config flag on `ConvertLivewireRuleAttributeRector` (default `false`). When enabled, `message:` attribute args migrate into a generated `messages(): array` method alongside `rules()`:

- String form: `#[Validate('required|email', message: 'Bad email.')]` → `'<prop>' => 'Bad email.'` (whole-attribute key per Livewire's documented behaviour — Laravel matches the attribute-only key against any rule failing on the attribute).
- Array form: `message: ['required' => 'X', 'min' => 'Y']` → `'<prop>.required' => 'X'`, `'<prop>.min' => 'Y'`. Keys already containing `.` (full-path forms used with keyed-array first-arg attributes) pass through verbatim.

Opt-in because the generated method expands the class surface and some consumers centralize messages in lang files. Default-off keeps legacy skip-log behaviour intact.

Safety: preflight check bails the whole conversion (leaving `#[Validate]` intact) when an existing non-trivial `messages()` method can't be safely merged — multi-return, conditional, builder-loop, and `return $cached` shapes all qualify. Per-property anchoring matches `extractAndStripRuleAttribute`'s first-CONVERTIBLE-wins so a non-literal first attribute can't orphan a later attribute's `message:` arg. Per-attribute migration tracking via `spl_object_id` keyset means the legacy skip-log still fires for non-migrateable shapes (non-literal values, mixed arrays).

```php
return RectorConfig::configure()
    ->withConfiguredRule(ConvertLivewireRuleAttributeRector::class, [
        ConvertLivewireRuleAttributeRector::MIGRATE_MESSAGES => true,
    ]);






























```
### `laravel-fluent-validation` 1.19.0 surface support

Three rectors grow together for the upstream 1.19.0 delta (11 new `FluentRule::*` shortcut factories + new `DeclinedRule` class + 4 `NumericRule` sign helpers). Composer floor bumped to `^1.19`.

**Converters (CONVERT set)** — `ValidationStringToFluentRuleRector` / `ValidationArrayToFluentRuleRector`:

- 9 new tokens recognized as direct factory shortcuts: `'ipv4'`, `'ipv6'`, `'mac_address'`, `'json'`, `'timezone'`, `'hex_color'`, `'active_url'`, `'list'`, `'declined'`.
- Sibling-token promotion: `'string|ipv4'` → `FluentRule::ipv4()` (not the verbose `string()->ipv4()`). Converters emit the final factory form directly because `SIMPLIFY` isn't in the default `ALL` set, so users on the default pipeline don't depend on a follow-up cleanup pass. Same pattern for `'array|list'`.
- Type-source tracking prevents over-promotion: chain-derived types (`Rule::string()->alpha()`, `Password` chains, `Email` rule objects) keep their extracted chain ops in Pass 2 — only plain string-token types promote.

**Cleanup (SIMPLIFY set)** — `SimplifyFluentRuleRector`:

- Factory-shortcut collapse for the 9 new factories (zero-arg) plus `regex` and `enum` (arg-passthrough). `string()->ipv4()` → `ipv4()`, `string()->regex($p)` → `regex($p)`, `field()->enum($t)` → `enum($t)`.
- Conservative arg-passthrough gate: only fires when the source factory is arg-less AND the chain has no `label()` call (positional-slot threading is out of v1 scope; the gate prevents silent label loss).
- Label-promotion path extended to the new factories: `string()->label('Address')->ipv4()` → `ipv4('Address')` already collapses end-to-end via the existing pipeline.
- Redundant-call removal extended: `FluentRule::ipv4()->ipv4()` collapses.

**Escape-hatch rewrite (SIMPLIFY set)** — `SimplifyRuleWrappersRector`:

- `enum` rewrite: `->rule(Rule::enum(X::class))` / `->rule(['enum', X::class])` → `->enum(X::class)` on the 5 `HasEmbeddedRules` consumers (String / Numeric / Email / Date / Field). `Rule::enum(X, $cb)` multi-arg bails (callback can't be threaded through the single-method form).
- Literal-zero comparisons: `'gt:0'` → `->positive()`, `'gte:0'` → `->nonNegative()`, `'lt:0'` → `->negative()`, `'lte:0'` → `->nonPositive()` (NumericRule only). Non-zero literals (`'gt:5'`), field references (`'gt:other_field'`), and broader spellings (`'gt:00'`, `'gt:-0'`) stay as the escape hatch — exact-zero match is the documented contract.

### Refactor

`SimplifyFluentRuleRector::simplifyChain` and `ConvertsValidationRuleArrays::convertArrayToFluentRule` cognitive complexity dropped below the PHPStan cap by extracting `tryFactoryShortcuts*` / `tryRemoveRedundantTypeCalls` / `tryPromoteLabelToFactoryArg` / `tryFoldMinMaxIntoBetween` / `chainHasLabelCall` and `detectArrayRuleType` helpers. Stale baseline ignore for the old simplifyChain complexity removed.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.7.0...0.8.0

## 0.7.0 - 2026-04-21

New `SimplifyRuleWrappersRector` (under the existing opt-in `SIMPLIFY` set) rewrites escape-hatch `->rule(...)` calls into native fluent methods on the correct typed-rule subclass.

### What it rewrites

| From | To | Receivers |
|---|---|---|
| `->rule(Rule::in([…]))` / `->rule('in:a,b')` / `->rule(['in', 'a', 'b'])` | `->in([…])` | String / Numeric / Email / Field / Date |
| same shapes for `notIn` / `not_in` | `->notIn([…])` | same |
| `->rule('min:N')` / `->rule(['min', N])` | `->min(N)` | String / Numeric / Array / File / Password |
| `->rule('max:N')` / `->rule(['max', N])` | `->max(N)` | + Email |
| `->rule('between:L,U')` / `->rule(['between', L, U])` | `->between(L, U)` | String / Numeric / Array / File |
| `->rule('size:N')` / `->rule(['size', N])` | `->exactly(N)` (intentional rename per `TypedBuilderHint`) | String / Numeric / Array / File |
| `->rule('regex:/…/')` / `->rule(['regex', '/…/'])` | `->regex('/…/')` | String only |

### Set wiring

`SIMPLIFY` registers `SimplifyRuleWrappersRector` after `SimplifyFluentRuleRector` so factory shortcuts (`string()->url()` → `url()`) collapse first. `ALL` deliberately does not include `SIMPLIFY`.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.6.1...0.7.0

## 0.6.1 - 2026-04-20

A real-world dry-run of 0.6.0 against a production Laravel codebase (~43 `rules()` methods) returned 0 emits / 43 skips — every method had a pre-existing `@return array<string, DataAwareRule>` / `DataAwareRule[]` docblock that 0.6.0 treated as "user-customized — respecting". 0.6.1 recognizes Laravel validation-contract annotations as safe-to-narrow-from, plus two AST-level fixes surfaced by the same dry-run.

### Laravel validation contracts accepted as narrow-from shapes

`UpdateRulesReturnTypeDocblockRector` now narrows `@return` annotations whose body references any Laravel validation contract, in both generic-array and `T[]`-shorthand forms:

- `array<string, ValidationRule>` / `array<string, \Illuminate\Contracts\Validation\ValidationRule>`
- `array<string, DataAwareRule>` / `array<string, \Illuminate\Contracts\Validation\DataAwareRule>`
- `array<string, ValidatorAwareRule>` / `array<string, \Illuminate\Contracts\Validation\ValidatorAwareRule>`
- `array<string, ImplicitRule>` / `array<string, \Illuminate\Contracts\Validation\ImplicitRule>`
- `array<string, Rule>` / `array<string, \Illuminate\Contracts\Validation\Rule>`
- `DataAwareRule[]`, `ValidationRule[]`, etc. (the older PHPStan `T[]` shorthand)

Safety: every `*Rule` class shipped by `sandermuller/laravel-fluent-validation` implements all four Laravel contracts (`DataAwareRule, FluentRuleContract, ValidationRule, ValidatorAwareRule`), and the polish rule's condition 3 has already proven every array item is a FluentRule chain before this matcher runs. Narrowing from any of the listed Laravel contracts to `FluentRuleContract` therefore drops no valid type at the item level.

Unconditional — no opt-in flag. POLISH is itself opt-in; expanding the narrow-from set inside it is an implementation detail of "polish narrows the docblock". Users who authored one of the listed Laravel-contract annotations and did **not** want POLISH to touch it should not run POLISH.

Behavior change from 0.6.0: annotations like `@return array<string, ValidationRule>` that were previously respected as user-customized are now narrowed. Idempotency preserved — second-pass runs are no-ops.

### Bug: `Concat`-keyed array items are now recognized

Livewire nested-field idiom `'credentials.' . Class::CONST => FluentRule::...()` produces a `BinaryOp\Concat` key node rather than a single `String_`. 0.6.0 rejected these as "not a statically-known string" and skipped the method.

0.6.1 walks the `Concat` tree recursively and accepts it when every leaf is `String_` or `ClassConstFetch`, covering arbitrarily-nested concatenations like `'prefix.' . A::X . '.suffix'`.

### Bug: misleading skip-log reason for spread items

`...$foo` inside an array is represented by php-parser 5 as `ArrayItem{value, key=null, unpack=true}`. The 0.6.0 skip path checked `key === null` before `unpack === true`, emitting `"ArrayItem key at index N is not String_ / ClassConstFetch"` — technically true (null is neither), but the real disqualifier is the spread itself.

0.6.1 checks `unpack` first and logs `"encountered spread at index N — cannot determine keys statically"`. Skip behavior unchanged; only the diagnostic improves. `skip_spread_in_return.php.inc` fixture re-covers the flipped order.

### Fixtures

Added 4 fixtures: `all_fluent_laravel_data_aware_rule`, `all_fluent_laravel_data_aware_rule_shorthand`, `all_fluent_laravel_validation_rule_fqn`, `all_fluent_concat_key`. Updated `skip_user_annotation` to use a genuinely non-Laravel-contract type (`\App\Validation\DomainRuleContract`) so the "respect user-customized" coverage survives.

Matrix now at **50 fixtures** (22 emit + 28 skip). 259 tests / 312 assertions / 0 failures / PHPStan clean.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.6.0...0.6.1

## 0.6.0 - 2026-04-20

### New opt-in polish rule: `UpdateRulesReturnTypeDocblockRector`

Narrows the `@return` PHPDoc annotation on `rules()` methods from the wide `array<string, ValidationRule|string|array<mixed>>` union down to `array<string, \SanderMuller\FluentValidation\Contracts\FluentRuleContract>` when every value in the returned array is a `FluentRule::*()` call chain. Cosmetic — runtime behavior is untouched — but gives PHPStan and editors a narrower type to reason about after migration.

Rule ships behind a new `FluentValidationSetList::POLISH` set. **Not** in `ALL`; opt-in per-invocation:

```php
return RectorConfig::configure()
    ->withPaths([__DIR__ . '/app'])
    ->withSets([FluentValidationSetList::POLISH]);

































```
#### What qualifies

The rule fires on a `rules()` method when **all five** hold:

1. Method is named `rules` with non-nullable `: array` return type (not `?array`, not union).
2. Body has exactly one `Return_` statement whose expression is a literal `Array_` (not a builder variable, not `RuleSet::from(...)`, not a collection pipeline — multi-return methods disqualify too).
3. Every `ArrayItem` has a string-typed key (`String_` or `ClassConstFetch`) and a value whose innermost `MethodCall->var` walk ends at `FluentRule::*()` (alias imports `use FluentRule as FR` resolve via Rector's `NodeNameResolver`).
4. Existing `@return` annotation (if any) is absent, `@return array` exactly, or the wide-union this package's converters emit — optionally followed by pure-prose description. User-authored unions/intersections/generics, and `@inheritDoc`, are respected.
5. Class context: extends `FormRequest` anywhere in the ancestor chain (aliased imports resolved) **or** uses `HasFluentRules` / `HasFluentValidation` / `HasFluentValidationForFilament` directly on the class or via any ancestor.

Skipped with a logged reason when any of the above fails.

#### Why opt-in

This is a polish rule, not a correctness migration. It runs against files that `CONVERT` / `GROUP` / `TRAITS` have already produced. Rector's multi-pass convergence handles it, but for clarity:

> Run `POLISH` as a separate invocation after `CONVERT` stabilizes. Single-pass runs that mix `CONVERT` + `POLISH` may need a second invocation if any file had string-rule items mid-convert.

40 fixtures lock the emit/skip behavior — see `tests/UpdateRulesReturnTypeDocblock/Fixture/` for the full matrix including intermediate-base-request ancestors, aliased imports, `when()` / `each()` / mid-chain-closure variants, widened unions that must be respected, collection-pipeline returns that must be skipped, and the `$this->passwordRules()` Fortify trait pattern.

### Shared-helper additions

Both helpers added to `SanderMuller\FluentValidationRector\Rector\Concerns\DetectsInheritedTraits`, designed to be consumed by future rules that need alias-safe / current-class-inclusive ancestry checks:

- **`anyAncestorExtends(Class_ $class, string $fqn): bool`** — mirrors the existing `anyAncestorUsesTrait` but for class-extension checks, and resolves `$class->extends` through `$this->getName(...)` before `class_exists`. Closes the alias-blindness gap the raw-`toString()` path has (`use FormRequest as BaseRequest; class Foo extends BaseRequest` now resolves).
- **`currentOrAncestorUsesTrait(Class_ $class, string $traitFqn): bool`** — inspects the current class's `TraitUse` nodes first before walking ancestors. Closes the parentless-class-with-direct-trait-use gap: `class Livewire { use HasFluentValidation; }` now qualifies regardless of inheritance.

The legacy `anyAncestorUsesTrait` stays as-is for backward compatibility with `AddHasFluentRulesTraitRector` and `AddHasFluentValidationTraitRector` callers; a follow-up alias-fix is a candidate for a separate release.

### Exposed constants on `NormalizesRulesDocblock`

`STANDARD_RULES_ANNOTATION_BODY` and `RETURN_TAG_PATTERN` flipped from `private` to `protected` so polish rules can consume them without duplicating the canonical wide-union literal or the PHPDoc continuation-aware regex.

New helper on the same trait:

- **`annotationBodyMatchesStandardUnionExactlyOrProse(string $body): bool`** — decides whether an extracted `@return` body is safe to narrow. Accepts the exact standard body optionally followed by whitespace-only or pure-prose tails; rejects any trailing type-syntax (`|`, `&`, `<`, `>`, `(`, `)`, `[`, `]`, `@`, `\`-FQN). Without this guard, widened unions like `STANDARD_BODY|\Illuminate\Support\Collection` would silently lose the additive `|Collection` member on narrow — a type-lie regression caught by Codex adversarial review during spec.

### Composer requirement bumped

- `sandermuller/laravel-fluent-validation`: `^1.8.1` → `^1.17`.

1.17 is the version that shipped the `FluentRuleContract` interface this rule narrows to. Keeping the floor at 1.17 matches the supported matrix. Non-POLISH rules have no direct 1.17 surface dependency, so if you're downstream on 1.8.1 and not planning to run POLISH, the old constraint would still work in practice — but the rector package no longer supports that configuration as a tested pair.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.5.3...0.6.0

## 0.5.3 - 2026-04-17

### 0.5.3

First release shipping the implementation behind the 0.5.0–0.5.2 CHANGELOG entries. The 0.5.0/0.5.1/0.5.2 tags pointed at CHANGELOG-only commits; the actual source for the skip-log flip and the attribute-context rule-object conversion landed in this commit.

#### What's in here

Everything described in the 0.5.0 entry (skip-log off by default, opt-in via `FLUENT_VALIDATION_RECTOR_VERBOSE=1`) and the 0.5.2 entry (attribute-context `new Password()` / `new Unique()` / `new Exists()` → FluentRule chains; legacy cwd log swept on every default-mode run) is now actually present in the shipped code.

Summary of new surface over 0.4.19:

- `SanderMuller\FluentValidationRector\Diagnostics` — env gate + path resolver for the skip log (verbose cwd, default tmp).
- `SanderMuller\FluentValidationRector\RunSummary::unlinkLogArtifacts()` — idempotent cleanup of both verbose and off-mode paths; called on parent init and in the shutdown closure after emit.
- `ConvertLivewireRuleAttributeRector` now lowers constructor-form rule objects in `#[Validate([...])]`:
  - `new Password($n)` → `FluentRule::password($n)`
  - `new Unique(...)` → `->unique(...)`
  - `new Exists(...)` → `->exists(...)`
    Non-attribute `rules()` arrays preserve the `->rule(new X(...))` escape hatch (scope-leak guard).
  

#### If you pinned 0.5.0, 0.5.1, or 0.5.2

Those tags shipped CHANGELOG text without the matching code — running a pinned 0.5.x composer install before 0.5.3 gives you 0.4.19-level behavior. Upgrade to `^0.5.3` to actually receive the documented changes.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.5.2...0.5.3

## 0.5.2 - 2026-04-17

Two changes on top of 0.5.0's skip-log flip: attribute-context constructor-form rule objects now lower to proper FluentRule chains, and the skip-log cleanup reaches legacy artifacts so upgrading consumers see a clean project root.

### `new Password(...)` / `new Unique(...)` / `new Exists(...)` in attribute context

Livewire `#[Validate([...])]` attribute args are const-expr, which forbids `Password::min(8)` / `Rule::unique(...)` — the shapes the rector already converts cleanly in `rules()` arrays. Consumers had to fall back to constructor form (`new Password(8)`, `new Unique('users', 'email')`), and the rector preserved them as-is on the `->rule(new X(...))` escape hatch.

0.5.2 lowers them like their static-factory counterparts:

```php
// Before
#[Validate(['required', new Password(8)])]
public string $password = '';

// After
protected function rules(): array
{
    return [
        'password' => FluentRule::password(8)->required(),
    ];
}



































```
Same for `new Unique(...)` → `->unique(...)` and `new Exists(...)` → `->exists(...)` against `Illuminate\Validation\Rules\Unique` / `Exists` (matching the existing `Rule::unique(...)` / `Rule::exists(...)` conversion).

**Preserved behavior.** Constructor-form rule objects inside regular `rules()` arrays still route to the escape hatch:

```php
// FormRequest::rules() — unchanged
'password' => ['required', new Password(8)],
// → FluentRule::field()->required()->rule(new Password(8))



































```
The detection is gated on a state flag set by the calling rector (`ConvertLivewireRuleAttributeRector` passes `inAttributeContext: true`; `ValidationArrayToFluentRuleRector` doesn't). This closes the original scope-leak concern that parked the feature: detecting `new Password()` globally would silently rewrite intentional constructor-form code in method arrays.

### Skip-log cleanup reaches legacy artifacts

0.5.0's parent-init cleanup only swept the current-mode path, so a `.rector-fluent-validation-skips.log` inherited from a 0.4.x install (or left behind by a verbose-mode run before flipping back to default) persisted in the project root — the exact CI-dirty-artifact problem 0.5.0 was meant to solve.

`RunSummary::unlinkLogArtifacts()` now sweeps both verbose (cwd) and off-mode (tmp) paths with their sentinels on every parent-init pass. A fresh default run on a 0.4.x-upgraded consumer now drops the legacy log automatically.

Also small polish: `RunSummary::format()` is now side-effect-free (cleanup moved to the shutdown closure after emit), the per-run cleanup helper is DRY'd into `unlinkLogArtifacts()`, and the end-of-run hint includes `--clear-cache` so the suggested re-run command is actionable as-copied (Rector caches bail results per file).

### Notes

No API changes. Configuration surface unchanged. No migration required for 0.5.x consumers; 0.4.x upgraders will see the legacy log disappear on first run.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.5.1...0.5.2

## 0.5.1 - 2026-04-17

Attribute-context `new Password(...)` / `new Rule\Unique(...)` / `new Rule\Exists(...)` now convert to proper FluentRule chains instead of the `->rule(new X(...))` escape hatch.

### Why

Livewire `#[Validate([...])]` attribute args are const-expr, which forbids the static-factory forms (`Password::min(8)`, `Rule::unique(...)`) that the rector already converts cleanly in `rules()` arrays. Consumers using attribute-form validation were stuck with the verbose `->rule()` wrapper even though the same rule in method-form arrays got the nice FluentRule lowering. The gap was parked on scope-leak concerns — detecting `new Password()` globally would rewrite intentional constructor-form code in `rules()` arrays too.

### Behavior

The converter now tracks whether it's running inside an attribute context. When yes:

```php
// Before
#[Validate(['required', new Password(8)])]
public string $password = '';

// After (0.5.1)
protected function rules(): array
{
    return [
        'password' => FluentRule::password(8)->required(),
    ];
}




































```
Same shape for `new Unique(...)` and `new Exists(...)` against `Illuminate\Validation\Rules\Unique` / `Exists`, lowered to `->unique(...)` / `->exists(...)` chain methods (matching the existing `Rule::unique(...)` conversion).

### What's preserved

Non-attribute `rules()` arrays are unchanged — `new Password(8)` stays on the `->rule(new Password(8))` escape hatch. The scope-leak fix is a state flag threaded in from the calling rector (`ConvertLivewireRuleAttributeRector` passes `inAttributeContext: true`; `ValidationArrayToFluentRuleRector` doesn't). No PARENT_NODE walking; Rector 2.x no longer populates it by default, so explicit context is cheaper and more reliable.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.5.0...0.5.1

## 0.5.0 - 2026-04-17

### 0.5.0

Skip-log default flipped: **off by default**, opt-in via env. No rule-behavior changes.

#### Why

Consumer CI pipelines running auto-fix workflows (mijntp's in particular) were picking up `.rector-fluent-validation-skips.log` and `.log.session` as dirty artifacts every rector run and trying to commit+push them — blocked on protected branches, broke the pipeline. Each consumer previously had to know to gitignore both files. Flipping the default shifts that burden: fresh consumers get zero artifacts in their project root; users who actually want the per-entry breakdown opt in explicitly.

#### Behavior

Default runs still count skips and the end-of-run summary reports the total, but writes go to a cwd-hash-scoped path under `sys_get_temp_dir()` that the summary reader unlinks after emitting its hint. No file ever surfaces in the consumer's project root.

```
[fluent-validation] 42 skip entries. Re-run with FLUENT_VALIDATION_RECTOR_VERBOSE=1 and --clear-cache for details.





































```
Opt in by exporting the env var before running Rector:

```bash
FLUENT_VALIDATION_RECTOR_VERBOSE=1 vendor/bin/rector process --clear-cache





































```
Env-only is deliberate — the flag has to reach parallel workers (fresh PHP processes spawned via `proc_open`) and shell-exported env inherits automatically, while in-process mutation would not. With verbose on, the log lands in the project root as before and the summary references it:

```
[fluent-validation] 42 skip entries written to .rector-fluent-validation-skips.log — see for details





































```
#### Migration

If you previously relied on the log appearing in your project root, export `FLUENT_VALIDATION_RECTOR_VERBOSE=1` in whichever shell runs Rector. If you had gitignore entries for `.rector-fluent-validation-skips.log*`, you can leave them in place — they only apply on verbose runs now.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.19...0.5.0

## 0.4.19 - 2026-04-15

Closes three latent correctness gaps in `ConvertLivewireRuleAttributeRector` that had been documented in the array-form spec since 0.4.17. No main-package, trait-selection, or Filament changes.

### Keyed-array `#[Validate]` now expands per-key

Livewire v3's keyed-array attribute shape used to silently produce wrong output — the converter ignored `ArrayItem` keys and collapsed all values into one chain, dropping the `.*` wildcard scoping entirely.

```php
// Before (0.4.18): silent wrong output, keys ignored
#[Validate(['todos' => 'required|array', 'todos.*' => 'string|min:3'])]
public array $todos = [];

// After (0.4.19): per-key expansion into rules()
public function rules(): array
{
    return [
        'todos' => FluentRule::array()->required(),
        'todos.*' => FluentRule::string()->min(3),
    ];
}






































```
Flat `.*` entries pass through `GroupWildcardRulesToEachRector` downstream for nested `->each(...)` folding. Fails closed on unconvertible values, numeric-string keys, and mixed keyed/positional shapes with a skip-log entry.

### `#[Validate]` marker preserved to keep real-time validation

Stripping `#[Validate]` used to silently regress `wire:model.live` on-property-update validation — Livewire fires real-time only when a `#[Validate]` attribute is present on the property. 0.4.19 keeps an empty `#[Validate]` marker after conversion:

```php
// After (0.4.19): rules() generated, empty marker preserved
#[Validate]
public string $name = '';

protected function rules(): array { /* ... */ }






































```
Deprecated `#[Rule]` (not `#[Validate]`) strips cleanly without a marker — the rector's scope is FluentRule migration, not the `#[Rule]` → `#[Validate]` upgrade. `#[Validate(onUpdate: false)]` also strips cleanly; if any `#[Validate]` on the property opts out of real-time, the marker is suppressed (aggregate veto, not first-wins).

**Opt out** on codebases that don't use `wire:model.live`:

```php
ConvertLivewireRuleAttributeRector::class => [
    ConvertLivewireRuleAttributeRector::PRESERVE_REALTIME_VALIDATION => false,
]






































```
### `as:` / `attribute:` recognised as synonyms

Both named args map to `->label()`. Array-form (`as: [key => label]`, `attribute: [key => label]`) applies per-entry across keyed-first-arg expansions. On conflict, `attribute:` wins over `as:` — precedence is deterministic and independent of source ordering.

### Named-args surface corrected

- `messages:` (plural) no longer classified as a dropped known arg. It wasn't a Livewire-documented shape in the first place; now logged as `unrecognized arg; likely typo for message:?`.
- `translate: false` added to the dropped list (no FluentRule equivalent).
- Array-form `message: [...]` gets a dedicated "deferred to a future release (messages() method generation)" log entry.
- `onUpdate: false` now consumed as the marker-veto signal from the section above; other explicit `onUpdate` values stay on the dropped list.

### New concerns

Six shared concerns extracted along the way to keep the host rector under the PHPStan cognitive-complexity cap:

- `ExpandsKeyedAttributeArrays`, `ExtractsLivewireAttributeLabels`, `ReportsLivewireAttributeArgs`, `ResolvesRealtimeValidationMarker`, `ResolvesInheritedRulesVisibility`, `DetectsLivewireRuleAttributes`.

### Config surface

Two rules now accept configuration via `withConfiguredRule()`:

- `ConvertLivewireRuleAttributeRector::PRESERVE_REALTIME_VALIDATION` (bool, default `true`)
- `AddHasFluentRulesTraitRector::BASE_CLASSES` (list of strings) — existing, documented in the README for the first time

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.18...0.4.19

## 0.4.18 - 2026-04-15

Quality-of-life release. No trait-selection or main-package changes — 0.4.16's Filament `insteadof` emission is unchanged.

### Stale `@return` docblock on mutated `rules()` method

Body-mutation rectors used to rewrite the `rules()` array but leave the surrounding `@return` annotation untouched, producing type-lies like `@return array<string, StringRule>` above a body that returned `array<string, ArrayRule>`.

Four rectors now normalize the annotation: `ConvertLivewireRuleAttributeRector` (merge path), `ValidationArrayToFluentRuleRector`, `ValidationStringToFluentRuleRector`, `GroupWildcardRulesToEachRector`. When the `@return` body references a FluentRule-family concrete type (`StringRule`, `ArrayRule`, `NumericRule`, etc.), the whole tag — including multi-line continuations — is replaced with:

```
@return array<string, ValidationRule|string|array<mixed>>







































```
This matches the annotation fresh-emitted on newly-generated `rules()` methods, so every `rules()` method this package touches now carries the same `@return` shape.

Broad or unrelated annotations are preserved (`@return array<string, mixed>`, `@return array`, `@return FooBar`). Staleness is evaluated only against the `@return` tag body — a description line elsewhere in the docblock mentioning `StringRule` does not trigger replacement.

### Hybrid-bail silent on non-candidate classes

The `ConvertLivewireRuleAttributeRector` hybrid-bail used to fire on any class with a `$this->validate([...])` call, regardless of whether the class had `#[Rule]` / `#[Validate]` attributes to migrate. On a production app that meant dozens of spurious skip-log entries on Actions, FormRequests, Controllers, and DataObjects with unrelated `validate()` methods.

Now the rector bails silently on any class without Livewire rule attributes. Genuine hybrid cases — attributes AND explicit `$this->validate([...])` — still skip-log as before.

### Removed `vendor/bin/fluent-validation-migrate`

The regex-based migrator shipped in 0.4.16 corrupted multi-trait `use X, Y, Z { … }` blocks: it renamed the top-level import but left short-name references inside the class body pointing at the old name, producing a `Trait "…HasFluentValidation" not found` fatal-at-load. Regex matching can't reliably handle the trait-use block structure.

The CLI is removed entirely. The `bin` entry in `composer.json` is dropped. A safe AST-based replacement was scoped for this release but pruned — the narrow `1.7.x → 1.8.1` upgrade window doesn't justify new migration infrastructure at this point. The edge case is documented in the README's Known Limitations section with a concise hand-fix recipe.

**If you ran 0.4.16's CLI on a codebase:** verify each converted file still loads. The single-trait-block happy path worked correctly; only multi-trait blocks with existing `insteadof` were corrupted.

### Under the hood

Three extracted concerns to keep rector class complexity under the PHPStan cognitive-complexity limit:

- `DetectsLivewireRuleAttributes` — `#[Rule]` / `#[Validate]` detection (FQN + short alias).
- `IdentifiesLivewireClasses` — parent-class or `render()`-method heuristic.
- `NormalizesRulesDocblock` — scoped `@return` rewriter with multi-line support.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.17...0.4.18

## 0.4.17 - 2026-04-15

Quality-of-life release. No trait-selection or main-package changes — 0.4.16's Filament `insteadof` emission is unchanged.

### Stale `@return` docblock on mutated `rules()` method

Body-mutation rectors used to rewrite the `rules()` array but leave the surrounding `@return` annotation untouched, producing type-lies like `@return array<string, StringRule>` above a body that returned `array<string, ArrayRule>`.

Four rectors now normalize the annotation: `ConvertLivewireRuleAttributeRector` (merge path), `ValidationArrayToFluentRuleRector`, `ValidationStringToFluentRuleRector`, `GroupWildcardRulesToEachRector`. When the `@return` body references a FluentRule-family concrete type (`StringRule`, `ArrayRule`, `NumericRule`, etc.), the whole tag — including multi-line continuations — is replaced with:

```
@return array<string, ValidationRule|string|array<mixed>>








































```
This matches the annotation fresh-emitted on newly-generated `rules()` methods, so every `rules()` method this package touches now carries the same `@return` shape.

Broad or unrelated annotations are preserved (`@return array<string, mixed>`, `@return array`, `@return FooBar`). Staleness is evaluated only against the `@return` tag body — a description line elsewhere in the docblock mentioning `StringRule` does not trigger replacement.

### Hybrid-bail silent on non-candidate classes

The `ConvertLivewireRuleAttributeRector` hybrid-bail used to fire on any class with a `$this->validate([...])` call, regardless of whether the class had `#[Rule]` / `#[Validate]` attributes to migrate. On a production app that meant dozens of spurious skip-log entries on Actions, FormRequests, Controllers, and DataObjects with unrelated `validate()` methods.

Now the rector bails silently on any class without Livewire rule attributes. Genuine hybrid cases — attributes AND explicit `$this->validate([...])` — still skip-log as before.

### Removed `vendor/bin/fluent-validation-migrate`

The regex-based migrator shipped in 0.4.16 corrupted multi-trait `use X, Y, Z { … }` blocks: it renamed the top-level import but left short-name references inside the class body pointing at the old name, producing a `Trait "…HasFluentValidation" not found` fatal-at-load. Regex matching can't reliably handle the trait-use block structure.

The CLI is removed entirely. The `bin` entry in `composer.json` is dropped. A safe AST-based replacement was scoped for this release but pruned — the narrow `1.7.x → 1.8.1` upgrade window doesn't justify new migration infrastructure at this point. The edge case is documented in the README's Known Limitations section with a concise hand-fix recipe.

**If you ran 0.4.16's CLI on a codebase:** verify each converted file still loads. The single-trait-block happy path worked correctly; only multi-trait blocks with existing `insteadof` were corrupted.

### Under the hood

Three extracted concerns to keep rector class complexity under the PHPStan cognitive-complexity limit:

- `DetectsLivewireRuleAttributes` — `#[Rule]` / `#[Validate]` detection (FQN + short alias).
- `IdentifiesLivewireClasses` — parent-class or `render()`-method heuristic.
- `NormalizesRulesDocblock` — scoped `@return` rewriter with multi-line support.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.16...0.4.17

## 0.4.16 - 2026-04-15

Main package `1.8.1` reworked `HasFluentValidationForFilament` to override the standard `validate()` / `validateOnly()` / `getRules()` / `getValidationAttributes()` — the methods Livewire and Filament both rely on. Users get transparent FluentRule behaviour on standard method names; `insteadof` disambiguation is **required** alongside Filament's `InteractsWithForms` (v3/v4) or `InteractsWithSchemas` (v5). Rector now emits the correct adaptation automatically, and the release ships a standalone migration CLI for consumers upgrading out of the 1.7.x state — where the collision fatals at class-load and locks out every AST-based tool.

**Requires:** `sandermuller/laravel-fluent-validation ^1.8.1`. `^1.8.0` is not supported — that trait shape was replaced within hours of tagging.

### What changed

#### Rector emits the 4-method `insteadof` block automatically

`AddHasFluentValidationTraitRector` on a Livewire component that **directly uses** a Filament form trait now emits:

```php
use HasFluentValidationForFilament {
    HasFluentValidationForFilament::validate insteadof InteractsWithForms;
    HasFluentValidationForFilament::validateOnly insteadof InteractsWithForms;
    HasFluentValidationForFilament::getRules insteadof InteractsWithForms;
    HasFluentValidationForFilament::getValidationAttributes insteadof InteractsWithForms;
}
use InteractsWithForms;









































```
`getMessages` is intentionally absent from the block — the trait defines it but Filament does not, so no collision to resolve.

The emission uses separate `use` blocks (one for `HasFluentValidationForFilament { … }`, one for the Filament trait) rather than a single combined block. Both forms are valid PHP; the separate-block form is simpler to emit and round-trips through Pint's `ordered_traits` fixer cleanly.

#### Ancestor-only Filament now skip-logs instead of auto-composing

When the Filament trait lives on a parent class and **not** directly on the subclass under conversion, the rector now skip-logs with a pointer to add `HasFluentValidationForFilament` on the concrete subclass manually. The 0.4.15 design tried to handle this shape, but PHP method resolution across trait chains + `parent::` forwarding is too fragile to guarantee — specifically, whether the subclass's `validate()` correctly forwards to the ancestor's Filament form-schema aggregation depends on details of the composition that the rector can't safely reason about.

Log message: *"parent class uses Filament trait — add HasFluentValidationForFilament with insteadof directly on this class if needed (rector cannot safely auto-compose through inheritance)"*.

#### Conflict guard widened for the Filament variant

If a class body declares `validate()` / `validateOnly()` / `getRules()` / `getValidationAttributes()` directly (i.e. a user-authored method on the class itself), the rector now skip-logs and refuses to insert `HasFluentValidationForFilament`. PHP's class-method-over-trait-method resolution would pre-empt the trait entirely, leaving the FluentRule chain inert — inserting the trait in that state is a visible no-op that also produces a confusingly "finished" migration diff. Better to skip-log and leave the user to reconcile.

The plain-Livewire variant's existing guard (blocks on `validate()` / `validateOnly()`) is unchanged.

#### Swap-on-detect preserves adaptations

When a class already has the **wrong** variant directly on it (e.g. plain `HasFluentValidation` on a Filament class), the rector still swaps to the correct variant and drops the orphaned top-level import. For the Filament branch, the insteadof adaptation is now included in the swap.

### New: `vendor/bin/fluent-validation-migrate`

A standalone source-text migrator ships in this release to handle the `1.7.x → 1.8.1` upgrade path. Operates entirely on file bytes — no class autoload, no Rector, no PhpParser.

**Why it exists.** Upgrading from `1.7.x` to `1.8.1` puts any Filament+Livewire class using `HasFluentValidation` into a fatal-at-load state. Rector (and every other AST-based tool) autoloads classes during analysis, so the fatal fires during the tool's own run, aborting it partway through with zero writes persisted. The migration CLI sidesteps this entirely by never touching the autoloader.

**What it does.** For every `.php` file under the given paths (or `app/` by default):

1. Detects BOTH an import of `HasFluentValidation` / `HasFluentValidationForFilament` AND a Filament form trait (`InteractsWithForms` / `InteractsWithSchemas`).
2. Swaps the import + in-class trait-use line to `HasFluentValidationForFilament` (if it was plain).
3. Adds the 4-method `insteadof` block if missing.
4. Leaves `$this->validate(...)` / `$this->validateOnly(...)` call sites alone — the trait overrides those standard names, so existing call sites stay correct.

**Usage:**

```bash
# preview
vendor/bin/fluent-validation-migrate --dry-run

# apply in-place (default path: app/)
vendor/bin/fluent-validation-migrate

# custom paths
vendor/bin/fluent-validation-migrate app/ src/Livewire/









































```
**Idempotent:** running twice yields the same result as running once. Files that don't match (plain Livewire without Filament, or classes already carrying the correct adaptation) are untouched.

**Standard migration order** for a `1.7.x → 1.8.1` upgrade:

1. `composer require sandermuller/laravel-fluent-validation:^1.8.1`
2. `vendor/bin/fluent-validation-migrate` — fix the affected classes before the fatal blocks tooling
3. `vendor/bin/rector process` — regular rector run, now against a clean codebase

### Migration path from 0.4.15

0.4.15 inserted `HasFluentValidationForFilament` without the insteadof block (against main-package 1.8.0's shape, where no adaptation was needed). On `1.8.1`, that state is broken — class will fatal at load.

0.4.16's rector does **not** retrofit those classes in-place. Use `vendor/bin/fluent-validation-migrate` — it detects the partial-migration shape (Filament trait already swapped, insteadof missing) and adds the adaptation block. Unified path, one tool, handles the fresh-upgrade and the 0.4.15-partial cases identically.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.15...0.4.16

## 0.4.15 - 2026-04-15

### Changed

#### `AddHasFluentValidationTraitRector` picks the right trait variant for Filament components

Main package `1.8.0` ships a dedicated `HasFluentValidationForFilament` trait — additive, exposes `validateFluent()` without overriding any Livewire/Filament methods, so there is no collision with Filament's `InteractsWithForms` or v5's `InteractsWithSchemas`. The rector now selects the correct variant automatically:

- Plain Livewire component → `HasFluentValidation` (transparent: overrides `validate()` / `validateOnly()`, existing call sites keep working with FluentRule objects).
- Livewire + Filament (`InteractsWithForms` v3/v4 OR `InteractsWithSchemas` v5, detected directly or via the ancestor chain) → `HasFluentValidationForFilament`. Consumer code must call `$this->validateFluent(...)` in submit handlers; `validate()` remains Filament's and handles form-schema rules as before.

Detection walks the parent chain via `ReflectionClass`, so subclasses of a shared Filament base class pick the Filament variant without needing the Filament trait re-declared on every subclass.

**Swap-on-detect:** if a class is already tagged with the wrong variant (plain `HasFluentValidation` on a Filament class, or vice versa), the rector removes the wrong one, inserts the correct one, and drops the now-orphaned top-level `use` import. Skipping on mismatch would silently ship a runtime collision; swap is the safe default.

**Conflict guard:** the trait insertion is skipped (with a skip-log entry) when the class declares a method that would collide with the chosen trait's public surface — `validate()` / `validateOnly()` for `HasFluentValidation`, `validateFluent()` for `HasFluentValidationForFilament`. These are hard user decisions the rector never overrides.

### Upgrade

- `sandermuller/laravel-fluent-validation` constraint bumped to `^1.8`. Consumers on `1.7.x` should stay on rector `0.4.14`; there is no `1.8` fallback path inside the rector.
- No rector config changes. The 0.4.15 prerelease plan had added a `filament_conflict_resolution` option with an `insteadof` adaptation emitter; that work was scrapped before release once the main-package trait-design fix landed. If you ever saw that option in a prerelease build, it has been removed.

### New rector-side helper

- `DetectsFilamentForms` concern centralises the Filament-trait substring match (`InteractsWithForms`, `InteractsWithSchemas`) + ancestor walk. Extracted so additional trait rectors can share the detection.

### Fixtures

Added 4 new fixtures under `tests/AddHasFluentValidationTrait/Fixture/`:

- `filament_interacts_with_forms_picks_filament_variant.php.inc` — v3/v4 path.
- `filament_interacts_with_schemas_picks_filament_variant.php.inc` — v5 path.
- `ancestor_filament_picks_filament_variant.php.inc` — subclass inherits `InteractsWithForms` from a base class.
- `swap_plain_trait_on_filament_class.php.inc` — existing `HasFluentValidation` on a Filament class → replaced with `HasFluentValidationForFilament`, orphaned import dropped.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.14...0.4.15

## 0.4.14 - 2026-04-15

### Changed

#### `@return` annotation uses Laravel's `ValidationRule` interface

`ConvertLivewireRuleAttributeRector::installRulesMethod()` now emits:

```php
/**
 * @return array<string, ValidationRule|string|array<mixed>>
 */
protected function rules(): array { /* … */ }










































```
The annotation imports `Illuminate\Contracts\Validation\ValidationRule` via Rector's import-names pass (the same pass that handles `FluentRule` imports), so the pre-Pint output has a proper `use` statement + short-name reference.

**Why `ValidationRule` + `string` + `array<mixed>` union:**

- `FluentRule` (the static factory class) doesn't implement any shared interface with the concrete rule classes it produces. Verified directly: `class FluentRule` has no extends/implements, while `class EmailRule implements DataAwareRule, ValidationRule, ValidatorAwareRule`. The 0.4.13 annotation `array<string, FluentRule>` was semantically wrong — PHPStan correctly flagged it as a `return.type` mismatch.
- All concrete `*Rule` classes the rector emits (`EmailRule`, `StringRule`, `FieldRule`, `IntegerRule`, etc.) already implement `Illuminate\Contracts\Validation\ValidationRule`. That's the accurate common supertype.
- `string` + `array<mixed>` cover Laravel-native rule forms a user might add via manual edit to the generated method (raw pipe-delimited strings, array-tuple rules). Future-safe annotation.

**Why not `array<string, mixed>`:**

`mixed` matches Laravel's own `rules()` convention and would also satisfy vanilla PHPStan, but strict-mode tooling (`rector/type-perfect`, `tomasvotruba/type-coverage`) flags `mixed` as "too broad — use narrower type." The `ValidationRule|string|array<mixed>` union is strictly narrower than `mixed` while still covering every shape the rector emits or a user might add.

**Why not a shared `FluentRule` interface on the main package:**

Mijntp's initial proposal was to add a shared supertype to `laravel-fluent-validation` 1.8 so the annotation could reference a package-native type. fwwl0vv3 (main-package maintainer) declined the interface on the grounds that `FluentRule` is intentionally a factory, not a value type, and Laravel's existing `ValidationRule` interface already provides the right supertype for the concrete rules. The rector's fix is standalone — no cross-package coordination or version bump required on the main package.

### Fixtures

Updated 10 fixtures under `tests/ConvertLivewireRuleAttribute/Fixture/` to match the new annotation. Each fixture now also shows the `use Illuminate\Contracts\Validation\ValidationRule;` import added to the file-level import block.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.13...0.4.14

## 0.4.13 - 2026-04-14

### Changed

#### `@return` annotation tightened to match PHPStan's inferred type

`ConvertLivewireRuleAttributeRector::installRulesMethod()` attached a three-way union docblock annotation to the generated `rules()` method since 0.4.3:

```php
/**
 * @return array<string, FluentRule|string|array<string, mixed>>
 */
protected function rules(): array { /* … */ }











































```
The union was defensive — `FluentRule` for chain entries, `string` for raw rule-string fallbacks, `array<string, mixed>` for nested Livewire dotted rules. But those last two shapes only exist on the `mergeIntoExistingRulesMethod()` path, which doesn't emit a docblock. The fresh-emit path (the only path that sets the docblock) produces entries exclusively from `convertStringToFluentRule()` and `convertArrayAttributeArg()` — both return FluentRule builder expressions.

Surfaced during 0.4.11 verification: `type-perfect` + `tomasvotruba/type-coverage` flagged `return.type` errors when the actual inferred type was a specific FluentRule subclass (e.g. `array<string, EmailRule>` from `FluentRule::email()->...`) but the declared type advertised the broader three-way union. The declared-wider-than-inferred mismatch is noise for anyone running strict-mode PHPStan on converted files.

0.4.13 narrows to `array<string, FluentRule>`:

- Accurate for the fresh-emit case (all entries are FluentRule chains).
- Covariance-safe with PHPStan's narrower inferred subclass types (`FluentRule` is a supertype of `EmailRule`, `StringRule`, etc. that the specific factory methods return).
- Still pre-empts rector-preset's `DocblockReturnArrayFromDirectArrayInstanceRector` from adding the loose `array<string, mixed>`.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.12...0.4.13

## 0.4.12 - 2026-04-14

### Fixed

#### Inheritance-aware `rules()` method generation

Since 0.4.0, `ConvertLivewireRuleAttributeRector::installRulesMethod()` always emitted `protected function rules(): array` on the concrete subclass. Two PHP inheritance rules make this unsafe without a parent-class check:

1. **A subclass cannot override a `final` parent method.** If the parent declares `final public function rules()`, PHP throws `Fatal error: Cannot override final method <Parent>::rules()` at class-load time. Any consumer with a shared Livewire base that owns validation (reasonable "parent owns rules, children extend behavior" pattern) hit this the moment their codebase loaded.
2. **Visibility cannot be narrowed across inheritance.** If the parent's `rules()` is `public`, emitting `protected rules()` on the subclass is a fatal covariance violation: `Access level to <Child>::rules() must be public (as in class <Parent>)`.

Caught by mijntp during 0.4.11 verification. Their `BaseSmsTwoFactor::rules()` is `final public`, and every concrete `#[Rule]`-attributed subclass got fatal-on-load from the rector output. Earlier verification rounds ran `php -l` (parse-check, doesn't link inheritance) and fixture tests that didn't instantiate the converted classes. PHPStan-analysing the rector output against the real project autoload — which mijntp started doing this release — caught both violations immediately.

#### What 0.4.12 does

`ConvertLivewireRuleAttributeRector::resolveGeneratedRulesVisibility()` walks the parent chain via `ReflectionClass` (detected from the AST `$class->extends` node — child class doesn't need to be autoloadable). For each ancestor:

- **Ancestor has `final rules()`** → helper returns `null`. `refactor()` logs a skip entry (`parent class declares final rules() method; cannot override — skipping to avoid fatal-on-load`) and bails before any property mutation. The child class is left unchanged; `#[Rule]` attributes stay in place.
- **Ancestor has `public rules()` (non-final)** → helper returns `MODIFIER_PUBLIC`. Generated method is emitted as `public function rules(): array { … }` to satisfy visibility covariance.
- **Ancestor has `protected` or `private rules()`** → helper returns `MODIFIER_PROTECTED`. `protected` override is legal when narrowing isn't happening.
- **No ancestor has `rules()`** (the common case — Livewire `Component` has no default `rules()`) → helper returns `MODIFIER_PROTECTED`. Matches pre-0.4.12 default.

The check runs BEFORE `extractAndStripRuleAttribute()` so a bail never strips attributes the rector couldn't replace with a generated method. The visibility resolution runs twice on the happy path (once in `refactor()` as the gate, once in `installRulesMethod()` for the emit) — one extra ReflectionClass walk per converted class is trivial cost vs. the correctness gain.

#### Fixtures pinning the behavior

Two new fixtures plus two helper classes under `tests/ConvertLivewireRuleAttribute/FixtureSupport/`:

- `skip_parent_has_final_rules.php.inc` — child extends a base with `final public rules()`, has `#[Rule]` attributes, expected output: no change + specific skip-log entry.
- `generates_public_rules_when_parent_public.php.inc` — child extends a base with `public rules()` (non-final), has `#[Rule]` attributes, expected output: `rules()` method emitted as `public function rules()`.

The helper classes (`BaseWithFinalPublicRules`, `BaseWithPublicRules`) are real autoloadable PHP files so `ReflectionClass` resolves against them at test time.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.11...0.4.12

## 0.4.11 - 2026-04-14

`GroupWildcardRulesToEachRector` now applies to Livewire components. Requires `sandermuller/laravel-fluent-validation ^1.7.1`.

### Changed

#### Livewire components are no longer skipped by the wildcard grouping rector

Before 0.4.11, `GroupWildcardRulesToEachRector` skipped Livewire components (detected via direct parent match on `Livewire\Component` / `Livewire\Form`, `HasFluentValidation` trait usage, or the presence of a `render()` method) because nested `each()` / `children()` calls broke Livewire's wildcard key reading at runtime. The rule had worked correctly on FormRequests since 0.3.0 but produced runtime-broken output on Livewire.

`sandermuller/laravel-fluent-validation` 1.7.0 shipped `HasFluentValidation::getRules()`, which flattens nested `each()` / `children()` back to wildcard keys via `RuleSet::flattenRules()`. `validate()` and `validateOnly()` on a Livewire component using the trait now see the flat form Livewire expects, regardless of whether the source `rules()` method uses nested or flat notation.

With the runtime support in place, the Rector's Livewire-skip guard is obsolete:

```php
// Before 0.4.11 — skipped on Livewire
class MyComponent extends Component {
    public function rules(): array {
        return [
            'items' => FluentRule::array()->required(),
            'items.*.name' => FluentRule::string()->required(),
        ];
    }
}

// 0.4.11 — groups into nested each(), flattened back to wildcard at runtime
class MyComponent extends Component {
    public function rules(): array {
        return [
            'items' => FluentRule::array()->required()->each([
                'name' => FluentRule::string()->required(),
            ]),
        ];
    }
}













































```
Removed from `GroupWildcardRulesToEachRector`:

- `isLivewireClass()` check at the top of `refactorClass()`
- `isLivewireClass()` method (direct parent + trait + `render()` heuristic)
- `LIVEWIRE_CLASSES` constant
- `use SanderMuller\FluentValidation\HasFluentValidation;` import (no longer needed)
- Skip-log message `'detected as Livewire (nested each() breaks Livewire wildcard handling; trait added separately)'`

The associated `skip_livewire*.php.inc` fixtures were converted to `group_livewire_*.php.inc` with expected-output halves that exercise the nested-each form.

#### Main package constraint bumped to `^1.7.1`

`composer.json` now requires `sandermuller/laravel-fluent-validation: ^1.7.1`. Consumers upgrading this Rector from 0.4.10 to 0.4.11 with an older main package pinned (1.6.x or below) will get a composer conflict rather than a silent runtime break when Livewire components start using the grouped output. The conflict is the intentionally-safer failure mode.

If you don't use Livewire, nothing breaks: FormRequest and `$request->validate()` pathways were never affected by the Livewire-skip guard and work identically across 0.4.x.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.10...0.4.11

## 0.4.10 - 2026-04-14

### Fixed

#### Run summary no longer depends on rector/extension-installer

0.4.9 registered the shutdown-emit hook from `config/config.php`, which Rector's extension-installer plugin loads automatically for packages declaring `type: rector-extension`. Both collectiq and mijntp caught that the plugin isn't installed on their projects — `rector/extension-installer` ships as a namespace-isolated internal dependency inside `rector/rector` itself but doesn't scan the consumer's vendor tree. Without the plugin, `config/config.php` is dead metadata.

Result: 0.4.9 was strictly additive on most codebases — the rules converted correctly, but the new stdout summary never fired. Worse than "visibly broken" because the discoverability gap it was meant to close was now hidden behind a different form of invisibility.

0.4.10 moves the registration into each of the 7 rule constructors. When Rector's DI container instantiates any of the fluent-validation rules during config initialization, that rule's `__construct()` calls `RunSummary::registerShutdownHandler()`. The existing idempotent guard (`self::$registered`) ensures exactly-once registration per PHP process regardless of how many rules fire. Consumers using any `FluentValidationSetList::*` set or any individual rule via `->withRules([...])` get the hook automatically — no extension-installer dependency, no `require-dev` addition, no `allow-plugins` approval.

The `config/config.php` registration is retained as belt-and-suspenders: extension-installer-enabled consumers register via the config load path; others register via rule construction. The idempotent guard prevents double-registration in either case.

#### Second gate: the rule-constructor path fires outside Rector too

Rule constructors fire whenever the class is instantiated. That includes:

- Consumer test suites that happen to spin up our rector classes (e.g. Pest / PHPUnit tests for custom Rector rule configs)
- Composer post-install autoload scripts touching the class
- IDE inspection runs
- Any arbitrary PHP process that imports the class for its own reasons

Without a second gate, each of these would register a shutdown handler that emits the summary at process exit — leaking a `[fluent-validation] N skip entries written to…` line into pest/phpunit/phpstan/composer output.

0.4.10 adds `isRectorInvocation()` — a basename check against `$_SERVER['argv'][0]`. The gate matches `rector`, `rector.phar`, `vendor/bin/rector`, and any `rector`-substring script name. Rejects `pest`, `phpunit`, `phpstan`, `composer`, `php`, and anything else. Combined with the existing `--identifier` worker check, the summary fires only during `vendor/bin/rector process`-parent invocations.

### Refactor

Reorganized `tests/` into the per-rule folder convention Rector core and extensions use (`rector-phpunit`, `rector-doctrine`, etc.): `tests/<RuleName>/{<RuleName>RectorTest.php, Fixture/, config/}`. Prompted by Rector maintainer feedback. No behavior change, no consumer impact — tests aren't distributed in the `composer require --dev` artifact. Skip fixtures under `ConvertLivewireRuleAttribute/Fixture/` also renamed from `bail_*` / `*_bails` to `skip_*` to match the same convention (`skip_*.php.inc` for no-change scenarios, single-section, no `-----` separator).

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.9...0.4.10

## 0.4.9 - 2026-04-13

### Added

#### Run-summary stdout line

Users running Rector on codebases with heavy trait-hoisting (abstract bases that propagate the performance traits via `DetectsInheritedTraits`) or hybrid Livewire `validate([...])` bail conditions see `[OK] 0 files changed` from Rector itself and assume the rules didn't fire. The actual story lives in `.rector-fluent-validation-skips.log`, but until now there was no pointer to it — users had to know to look.

0.4.9 emits a single STDOUT line at the end of each Rector invocation when the skip log contains entries:

```
[fluent-validation] 42 skip entries written to .rector-fluent-validation-skips.log — see for details















































```
Implementation is a shutdown function registered from the package's `config/config.php`, which rector-extension-installer includes in consumer projects' Rector runs. The shutdown function:

- Runs on parent PHP process exit, after Rector's own output has flushed. Doesn't interleave with Rector's `[OK]` summary.
- Gates on "am I the parent?" via absence of `--identifier` in `$_SERVER['argv']`. Workers are spawned with `--identifier <uuid>`; the parent isn't. This avoids each worker emitting its own summary line.
- Only emits when the skip log exists and is non-empty. Silent when there's nothing to report — users never see a useless summary line.
- Writes to STDOUT (not STDERR). STDOUT from the parent process reaches the user's terminal directly; STDERR under `withParallel()` has the swallow problem that motivated the file sink in 0.4.2, but we're emitting from the parent here, not a worker.

Singular/plural noun matches entry count (`1 skip entry` / `N skip entries`).

The shutdown function is idempotent — if `config/config.php` gets loaded multiple times in the same process (uncommon but possible), the handler registers exactly once via a static flag.

#### Public API

`SanderMuller\FluentValidationRector\RunSummary` has two public static methods:

- `registerShutdownHandler()` — called from `config/config.php`. Idempotent, gated on parent-ness.
- `format(): ?string` — returns the summary line as a string, or null when the log is absent/empty. Exposed for unit testing without needing to trigger a PHP shutdown cycle; consumers shouldn't need to call this directly.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.8...0.4.9

## 0.4.8 - 2026-04-13

### Added

#### Validation-equivalence integration test (correctness)

Every release so far has claimed the rector's output preserves runtime validation semantics. The fixture tests verify the output *text* matches expected shape, but until now nothing empirically verified that the converted FluentRule chains produce the same Laravel-validator error messages as the string-form rules they replaced.

This mattered for modal operators — `bail`, `sometimes`, `nullable`, `required_without`, etc. — whose semantics are order-sensitive and mode-switching, not additive. The string parser emits tokens in encounter order after pulling the type token forward. If that ordering ever subtly diverged from Laravel's expected rule-sequence semantics, downstream codebases would silently get different validation behavior than they had before running the rector.

New `tests/ValidationEquivalenceTest.php` runs 16 parametrized cases covering:

- `required + email + max` (simple, two invalid shapes)
- `bail` (empty input, non-string value — verifies only-one-error semantics)
- `sometimes` (field absent, field present + invalid)
- `nullable` (null accepted, invalid still fails)
- `required_without` (one missing, both missing)
- `integer + min/max` (both boundaries)
- `in:` list (value not in list)
- `array + each()` nested (typed children via dotted key equivalence)
- `boolean` (non-boolean input)

Each case runs Laravel's validator against invalid input using both the string-form rules (what the user wrote pre-conversion) and the FluentRule builder (what the rector would emit), and asserts the error messages are identical.

Uses Orchestra Testbench for the Laravel container + facade bootstrap (FluentRule's builder touches `Validator::` and `Rule::` facades during rule materialization). First integration-level test in the package; `AbstractRectorTestCase`-based fixture tests continue to cover conversion correctness.

Caught during mijntp's 0.4.7 open-ended feedback as the highest-priority paranoia item.

#### `NEWLINED_ARRAY_PRINT` regression guard

`ConvertLivewireRuleAttributeRector::multilineArray()` attaches Rector's `AttributeKey::NEWLINED_ARRAY_PRINT` attribute to the generated `rules()` method's return array, forcing one-item-per-line emission regardless of array size. The attribute key is Rector-internal and has churned across past major versions.

New `tests/RectorInternalContractsTest.php::testNewlinedArrayPrintConstantExists` fails fast with a targeted error message if the constant vanishes in a Rector 3+ upgrade, pointing the maintainer at the replacement attribute to wire into `multilineArray()` instead of letting the absence silently collapse generated `rules()` methods to a single line.

Flagged by mijntp.

### Changed

#### `ManagesTraitInsertion` emits at alphabetically-sorted position

0.3.0's `ManagesNamespaceImports` fix taught the rector to insert top-of-file `use` imports at the alphabetically-correct position rather than prepending. The class-body trait list (`use HasFluentRules;` inside the class) kept the old behavior: append after the last existing trait.

0.4.8 extends the symmetry to class-body trait insertion. `ManagesTraitInsertion::resolveSortedTraitInsertPosition()` walks existing `TraitUse` statements and inserts the new trait at the position where it sorts alphabetically among them:

```php
// Before (append)
class MyRequest {
    use HasAuditLog;
    use HasRateLimit;
    use Sanitizes;
    use HasFluentRules;   // appended
}

// After (sorted)
class MyRequest {
    use HasAuditLog;
    use HasFluentRules;   // sorted between HasAuditLog and HasRateLimit
    use HasRateLimit;
    use Sanitizes;
}
















































```
Pint's `ordered_traits` continues to resort if a consumer's existing trait list wasn't already alphabetical, but on well-ordered class bodies Pint is typically a no-op now.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.7...0.4.8

## 0.4.7 - 2026-04-13

### Fixed

#### Skip-log noise from trivial non-candidates

0.4.3 introduced the skip log as a diagnostic surface for "why wasn't my component converted" investigations. On codebases with lots of classes that *happen* to have a `rules()` method but aren't actually FormRequests or Livewire components — Actions, `Console\Kernel`, Collections, PHPUnit helpers — the trait rectors would evaluate each class, find it non-convertible, and log that decision. Those log entries are diagnostic noise: the user has no action to take; the class was never a realistic candidate.

Caught by hihaho's 0.4.5 regression-insurance verification: 2988 skip-log entries / 777KB on their 108-file corpus, dominated by two repeat messages:

- 1316 × `"no FluentRule usage in rules() method"` (from `AddHasFluentRulesTraitRector`, firing on every class with a `rules()` method that didn't use FluentRule — mostly non-FormRequest classes in hihaho's naming-convention style).
- 1255 × `"not detected as a Livewire component (no Livewire parent or render() method)"` (from `AddHasFluentValidationTraitRector`, firing on every class that wasn't Livewire — which is most of the codebase).

The remaining ~417 entries were the actually-interesting categories: abstract classes, inherited traits, hybrid `validate()` / `validateOnly()` conflicts, unsafe parent detection, FormRequest/Livewire trait mismatches.

0.4.7 silences the two noisy messages. Both rectors now treat "class doesn't look like our target" as a silent no-op:

- `AddHasFluentValidationTraitRector` now gates on `isLivewireClass()` *first* (before the abstract / already-has-trait / ancestor / validate-conflict / FluentRule-usage checks). Non-Livewire classes are silent no-ops; the other checks fire only on actual Livewire components.
- `AddHasFluentRulesTraitRector` keeps its existing check order but silences the "no FluentRule usage" bail. Classes with a `rules()` method that lacks FluentRule are silent no-ops instead of log entries.

Interesting categories stay logged: abstract classes, `alreadyHasTrait`, `anyAncestorUsesTrait`, `hasValidateMethodConflict`, `extends a configured base class`, `isLivewireClass (uses HasFluentValidation instead)` (on the FormRequest rector), `unsafe parent`, and all attribute-converter skips are untouched.

#### Tradeoff

The "not detected as a Livewire component" log used to help debug an edge case: a user's Livewire class that the rector's heuristic (Livewire parent OR `render()` method OR `HasFluentValidation` trait) fails to detect. In 0.4.7 that misdetection becomes silent. If a user reports "my Livewire class wasn't converted and there's no log," we'd add candidacy-gated logging in a follow-up — but hihaho's three-codebase data argued this is a rare case against 2571 actually-noisy entries, so the simpler filter wins for now.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.6...0.4.7

## 0.4.6 - 2026-04-13

### Added

#### Array-form Livewire attribute conversion

`ConvertLivewireRuleAttributeRector` now handles array-form attribute rules, matching the existing string-form behavior:

```php
// Before
#[Rule(['required', 'string', 'max:255'])]
public string $name = '';

#[Validate(['nullable', 'email'])]
public ?string $email = null;

// After
public string $name = '';

public ?string $email = null;

/**
 * @return array<string, FluentRule|string|array<string, mixed>>
 */
protected function rules(): array
{
    return [
        'name' => FluentRule::string()->required()->max(255),
        'email' => FluentRule::email()->nullable(),
    ];
}


















































```
`as:` label mapping continues to work (`#[Rule([...], as: 'x')]` → `->label('x')`). Empty arrays (`#[Rule([])]`) now emit a specific skip-log entry and leave the attribute in place, instead of silently converting to `FluentRule::field()`.

#### Known behavior: rule-object constructors get the `->rule()` escape hatch

PHP attribute args must be const-expressions. This rules out static method calls like `Password::min(8)` and `Rule::unique('users', 'email')` inside `#[Rule([...])]` — the only legal forms are the constructor calls `new Password(8)` and `new Rule\Unique('users', 'email')`.

The array converter's type-detection layer looks specifically for the `Password::min(...)` and `Rule::factoryMethod(...)` shapes. Constructor calls fall through to the `->rule(...)` escape hatch:

- `#[Rule(['required', new Password(8)])]` → `FluentRule::field()->required()->rule(new Password(8))`
- `#[Rule(['required', 'email', new Rule\Unique('users', 'email')])]` → `FluentRule::email()->required()->rule(new Rule\Unique('users', 'email'))`

Both outputs are runtime-correct. For the richer `FluentRule::password(8)` / `->unique('users', 'email')` form, prefer `rules(): array` over attribute-form when you need rule objects. Attribute-form is at its best for pure-string rule lists; the const-expr ceiling limits what's expressible beyond that.

### Changed

#### Internal: trait split

`ConvertsValidationRules` (1061 lines) split into two composing traits:

- `ConvertsValidationRuleStrings` — the rule-string surface: type tokens, modifier dispatch, factory construction, the `$needsFluentRuleImport` state. Used directly by `ValidationStringToFluentRuleRector`.
- `ConvertsValidationRuleArrays` — array-specific helpers + the `convertArrayToFluentRule()` entry point. Composes `ConvertsValidationRuleStrings` via `use`, so any rector using the array trait also gets the string surface. Used by `ValidationArrayToFluentRuleRector` and `ConvertLivewireRuleAttributeRector`.

`$needsFluentRuleImport` stays on the string trait (single owner), so import coordination is unchanged. No user-facing behavior change; `ValidationArrayToFluentRuleRector` drops from 1009 lines to ~170 after the extraction. `detectRuleFactoryType()` got a minor refactor into an `applyFactoryChainCall()` helper during the split.

If you're consuming `ConvertsValidationRules` directly (unlikely for internal-infrastructure traits but possible): rename your import to `ConvertsValidationRuleStrings`. No compat shim in this release; if a consumer reports breakage, a shim ships in 0.4.7.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.5...0.4.6

## 0.4.5 - 2026-04-13

### Fixed

#### Skip-log `fflush` before sentinel unlock (data loss under `withParallel()`)

0.4.3 introduced a PPID-keyed session sentinel (`.rector-fluent-validation-skips.log.session`) with `flock(LOCK_EX)` to coordinate first-worker truncation across Rector's parallel workers. The mechanism had a latent bug: after writing the session marker under the lock, the code called `flock($handle, LOCK_UN)` and only flushed the buffer later via `fclose` in the `finally` block.

`flock(LOCK_UN)` is POSIX advisory-only and does not imply a buffer flush. Between unlock and `fclose`, another worker could acquire the sentinel lock, `stream_get_contents` through an empty or stale sentinel (the session marker was still sitting in PHP's userland stream buffer on the first worker's side), decide the session was fresh, and re-truncate the log — wiping any entries earlier workers had already appended via `FILE_APPEND | LOCK_EX`.

Reproduced by mijntp during 0.4.3 verification with 100% consistency on macOS/APFS:

- Baseline scenario (5 files, 3 convert + 2 array-form bail, default parallel): log had 9 entries, zero from the bail files.
- `--debug` (single-process) on the same inputs: log had all 8 expected entries.
- Parallel runs of only the 2 bail files: log file did not exist at all across 3 consecutive runs (each worker raced to truncate through the unflushed window).

Scenarios 2 (dirty-log preseed) and 3 (run-twice) passed — those exercise only the single-worker hot path, where the `fclose` flush at the end of the process handled the race by accident.

Fix: explicit `fflush($handle)` immediately before `flock($handle, LOCK_UN)` in `ensureLogSessionFreshness()`. Guarantees the session marker is written through to the OS before the next lock-holder reads it. The race window is now zero for correctly-implementing platforms.

#### `@return` docblock emits short alias pre-Pint

0.4.3 added `setDocComment` to pre-empt rector-preset's loose `@return array<string, mixed>`, but wrote the type as `\SanderMuller\FluentValidation\FluentRule` — the fully-qualified name — even though `queueFluentRuleImport()` already registers the short alias in the file's imports. Pint's `fully_qualified_strict_types` fixer cleaned it up post-rector, but the pre-Pint output was chattier than necessary.

Flagged by collectiq during 0.4.3 verification. Fix: emit `FluentRule` short name directly in the Doc string. Same class of polish as the 0.3.0 "synthesized `FluentRule::` uses short name" fix. No fixture updates needed — the test config's `->withImportNames()` was silently normalizing the FQN to short name in fixture assertions, so consumer-facing output now matches what the fixtures already expected.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.4...0.4.5

## 0.4.4 - 2026-04-13

### Fixed

#### Skip-log `fflush` before sentinel unlock (data loss under `withParallel()`)

0.4.3 introduced a PPID-keyed session sentinel (`.rector-fluent-validation-skips.log.session`) with `flock(LOCK_EX)` to coordinate first-worker truncation across Rector's parallel workers. The mechanism had a latent bug: after writing the session marker under the lock, the code called `flock($handle, LOCK_UN)` and only flushed the buffer later via `fclose` in the `finally` block.

`flock(LOCK_UN)` is POSIX advisory-only and does not imply a buffer flush. Between unlock and `fclose`, another worker could acquire the sentinel lock, `stream_get_contents` through an empty or stale sentinel (the session marker was still sitting in PHP's userland stream buffer on the first worker's side), decide the session was fresh, and re-truncate the log — wiping any entries earlier workers had already appended via `FILE_APPEND | LOCK_EX`.

Reproduced by mijntp during 0.4.3 verification with 100% consistency on macOS/APFS:

- Baseline scenario (5 files, 3 convert + 2 array-form bail, default parallel): log had 9 entries, zero from the bail files.
- `--debug` (single-process) on the same inputs: log had all 8 expected entries.
- Parallel runs of only the 2 bail files: log file did not exist at all across 3 consecutive runs (each worker raced to truncate through the unflushed window).

Scenarios 2 (dirty-log preseed) and 3 (run-twice) passed — those exercise only the single-worker hot path, where the `fclose` flush at the end of the process handled the race by accident.

Fix: explicit `fflush($handle)` immediately before `flock($handle, LOCK_UN)` in `ensureLogSessionFreshness()`. Guarantees the session marker is written through to the OS before the next lock-holder reads it. The race window is now zero for correctly-implementing platforms.

#### `@return` docblock emits short alias pre-Pint

0.4.3 added `setDocComment` to pre-empt rector-preset's loose `@return array<string, mixed>`, but wrote the type as `\SanderMuller\FluentValidation\FluentRule` — the fully-qualified name — even though `queueFluentRuleImport()` already registers the short alias in the file's imports. Pint's `fully_qualified_strict_types` fixer cleaned it up post-rector, but the pre-Pint output was chattier than necessary.

Flagged by collectiq during 0.4.3 verification. Fix: emit `FluentRule` short name directly in the Doc string. Same class of polish as the 0.3.0 "synthesized `FluentRule::` uses short name" fix. No fixture updates needed — the test config's `->withImportNames()` was silently normalizing the FQN to short name in fixture assertions, so consumer-facing output now matches what the fixtures already expected.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.3...0.4.4

## 0.4.3 - 2026-04-13

### Fixed

#### Skip-log truncation race under `withParallel()`

0.4.2 introduced the file-sink skip log (`.rector-fluent-validation-skips.log`) to survive Rector's parallel worker STDERR being swallowed. The truncation logic used a per-process `static $logFileTruncated` flag: first write per process truncated, subsequent writes appended. That flag is scoped per-worker, so under `withParallel()` every one of the 15 workers independently decided it was "first" and truncated the file — wiping any entries written by earlier workers.

Caught by mijntp with a deterministic repro:

- Run A (5 files, 3 convert + 2 array-form bail, parallel): log had 7 entries, zero from the 2 bailed files. The last-writing workers' entries survived.
- Run B (same 2 bailed files alone, with `--debug` which disables parallel): log had both bail entries.
- Run C (same 2 bailed files alone, default parallel): log file didn't exist at all.

0.4.3 replaces the per-process flag with a PPID-keyed session sentinel (`.rector-fluent-validation-skips.log.session`) coordinated via `flock(LOCK_EX)`:

- First worker in a Rector run sees a missing sentinel (or one with a stale PPID) → truncates the log, writes the new session marker.
- All subsequent workers in the same run see their PPID matches → skip truncation, append only.
- Next Rector invocation has a new PPID → first worker truncates again → fresh log per run.

Under `withParallel()` all workers share the same PPID (the main Rector process), so the check is authoritative. Each worker runs the sentinel check once per process; subsequent writes skip straight to `FILE_APPEND | LOCK_EX`.

Non-POSIX / Windows (`posix_getppid()` unavailable) falls through to an mtime-based staleness heuristic with a 300-second threshold. Workers `touch()` the sentinel on every session verification, so long-running Rector invocations keep their sentinel mtime fresh. Back-to-back runs within 300s on non-POSIX may share a log (acceptable degradation), but per-worker data loss is eliminated regardless of platform.

`.gitignore` updated to include the `.session` sentinel.

#### `validateOnly()` bypass now triggers hybrid bail

`ConvertLivewireRuleAttributeRector::hasExplicitValidateCall()` previously only matched `$this->validate([...])`. Livewire also exposes `$this->validateOnly($field, $rules = null, ...)` — when called with a second-arg rules array, that call bypasses any generated `rules(): array` method and converting the attributes produces dead code.

0.4.3 extends the check:

- `validate` → rules at arg 0 (unchanged)
- `validateOnly` → rules at arg 1 (new)

`validateOnly($field)` without a rules override keeps converting — it uses `rules()` / attribute rules, so no dead-code risk. Explicit `validateOnly($field, ['x' => '…'])` triggers the bail.

Two new fixtures:

- `bail_on_hybrid_validateOnly_with_rules.php.inc` — attribute + `validateOnly('name', [...])` → bail, attributes preserved.
- `converts_with_plain_validateOnly.php.inc` — attribute + `validateOnly('name')` → converts to `rules()`.

Theoretical today — no peer codebase has exercised the pattern — but the bail is one-line-cheap and prevents silent dead code if it ever hits.

#### Tighter `@return` annotation on generated `rules(): array`

0.4.2's appended `rules()` method had no docblock. Running rector-preset's `DocblockReturnArrayFromDirectArrayInstanceRector` (enabled by `typeDeclarationDocblocks: true` in most Rector configs) would infer and add a loose `@return array<string, mixed>` annotation.

0.4.3 pre-emptively attaches the tighter:

```php
/**
 * @return array<string, FluentRule|string|array<string, mixed>>
 */
protected function rules(): array





















































```
The union accurately describes what the generated array contains:

- `FluentRule` — method-chain builders (the common case)
- `string` — raw rule strings when merged into an existing `rules()` method that used them
- `array<string, mixed>` — Livewire dotted / nested rules

The annotation uses the short name `FluentRule` since the rector already queues the `use` import via `UseNodesToAddCollector`.

Updated 6 existing fixtures to include the new docblock.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.2...0.4.3

## 0.4.2 - 2026-04-13

### Fixed

#### File-sink skip log — visible under `withParallel()`

`LogsSkipReasons` (used by every rector to explain why it skipped a class) used to emit only to STDERR, gated on `FLUENT_VALIDATION_RECTOR_VERBOSE=1`. That worked for single-process Rector runs, but Rector's parallel executor (the default in projects scaffolded by `rector init`) doesn't forward worker STDERR to the parent's STDERR. Effectively, ~100% of production usage saw zero skip-log output regardless of the env var.

The trait now writes each skip line to `.rector-fluent-validation-skips.log` in the consumer's current working directory using `FILE_APPEND | LOCK_EX`, which works correctly across worker processes. STDERR mirroring is preserved when `FLUENT_VALIDATION_RECTOR_VERBOSE=1` is set, for single-process invocations.

The log is truncated at the first write per Rector run so stale entries from previous runs don't leak in. After a Rector run finishes, `cat .rector-fluent-validation-skips.log` shows everything the rector skipped and why. `.gitignore` entry added.

Reported by collectiq's 7-file scan after observing zero skip-log output despite multiple unsupported-args cases in the file set.

#### Blank line before generated `rules(): array`

`ConvertLivewireRuleAttributeRector` used to append the synthesized `rules(): array` method directly after the last class member, leaving them flush. Pint's `class_attributes_separation` fixer would always fire on converted files. The rector now inserts a `Nop` statement between the previous member and the appended method (skipping the Nop when the previous statement is already a Nop).

Same pattern used by the trait rectors in 0.1.1 — applied here to the new attribute rector.

#### Property-type-aware type inference for untyped rule strings

When a `#[Rule]` / `#[Validate]` attribute's rule string has no type token (e.g. `#[Validate('max:2000')]`, `#[Validate('required')]`), 0.4.0 fell back to `FluentRule::field()` and emitted untyped modifiers via the `->rule('...')` escape hatch — because `FieldRule` doesn't have `max()`, `min()`, etc. methods.

0.4.1 reads the PHP property's type declaration and uses it as the factory base when the rule string doesn't specify one:

```php
// Before (0.4.0)
#[Validate('max:2000')]
public string $description = '';
// → 'description' => FluentRule::field()->rule('max:2000')

#[Validate('min:1')]
public int $count = 0;
// → 'count' => FluentRule::field()->rule('min:1')

// After (0.4.1)
#[Validate('max:2000')]
public string $description = '';
// → 'description' => FluentRule::string()->max(2000)

#[Validate('min:1')]
public int $count = 0;
// → 'count' => FluentRule::integer()->min(1)






















































```
Maps:

- `string` → `FluentRule::string()`
- `int` / `integer` → `FluentRule::integer()`
- `bool` / `boolean` → `FluentRule::boolean()`
- `float` → `FluentRule::numeric()`
- `array` → `FluentRule::array()`

Nullable types unwrap (`public ?string $x` → uses `string`). Union types, intersection types, object types, and missing type declarations fall through to the prior `FluentRule::field()` + `->rule(...)` behavior — safe default when the property type doesn't map cleanly.

Inference only applies when the rule string has **no** type token. Explicit `#[Validate('string|max:50')]` continues to use the rule-string token, even on a non-`string` property — the rule string wins for clarity.

Reference fixture pinned from collectiq's `ReportContentButton`.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.1...0.4.2

## 0.4.1 - 2026-04-13

### Fixed

#### File-sink skip log — visible under `withParallel()`

`LogsSkipReasons` (used by every rector to explain why it skipped a class) used to emit only to STDERR, gated on `FLUENT_VALIDATION_RECTOR_VERBOSE=1`. That worked for single-process Rector runs, but Rector's parallel executor (the default in projects scaffolded by `rector init`) doesn't forward worker STDERR to the parent's STDERR. Effectively, ~100% of production usage saw zero skip-log output regardless of the env var.

The trait now writes each skip line to `.rector-fluent-validation-skips.log` in the consumer's current working directory using `FILE_APPEND | LOCK_EX`, which works correctly across worker processes. STDERR mirroring is preserved when `FLUENT_VALIDATION_RECTOR_VERBOSE=1` is set, for single-process invocations.

The log is truncated at the first write per Rector run so stale entries from previous runs don't leak in. After a Rector run finishes, `cat .rector-fluent-validation-skips.log` shows everything the rector skipped and why. `.gitignore` entry added.

Reported by collectiq's 7-file scan after observing zero skip-log output despite multiple unsupported-args cases in the file set.

#### Blank line before generated `rules(): array`

`ConvertLivewireRuleAttributeRector` used to append the synthesized `rules(): array` method directly after the last class member, leaving them flush. Pint's `class_attributes_separation` fixer would always fire on converted files. The rector now inserts a `Nop` statement between the previous member and the appended method (skipping the Nop when the previous statement is already a Nop).

Same pattern used by the trait rectors in 0.1.1 — applied here to the new attribute rector.

#### Property-type-aware type inference for untyped rule strings

When a `#[Rule]` / `#[Validate]` attribute's rule string has no type token (e.g. `#[Validate('max:2000')]`, `#[Validate('required')]`), 0.4.0 fell back to `FluentRule::field()` and emitted untyped modifiers via the `->rule('...')` escape hatch — because `FieldRule` doesn't have `max()`, `min()`, etc. methods.

0.4.1 reads the PHP property's type declaration and uses it as the factory base when the rule string doesn't specify one:

```php
// Before (0.4.0)
#[Validate('max:2000')]
public string $description = '';
// → 'description' => FluentRule::field()->rule('max:2000')

#[Validate('min:1')]
public int $count = 0;
// → 'count' => FluentRule::field()->rule('min:1')

// After (0.4.1)
#[Validate('max:2000')]
public string $description = '';
// → 'description' => FluentRule::string()->max(2000)

#[Validate('min:1')]
public int $count = 0;
// → 'count' => FluentRule::integer()->min(1)























































```
Maps:

- `string` → `FluentRule::string()`
- `int` / `integer` → `FluentRule::integer()`
- `bool` / `boolean` → `FluentRule::boolean()`
- `float` → `FluentRule::numeric()`
- `array` → `FluentRule::array()`

Nullable types unwrap (`public ?string $x` → uses `string`). Union types, intersection types, object types, and missing type declarations fall through to the prior `FluentRule::field()` + `->rule(...)` behavior — safe default when the property type doesn't map cleanly.

Inference only applies when the rule string has **no** type token. Explicit `#[Validate('string|max:50')]` continues to use the rule-string token, even on a non-`string` property — the rule string wins for clarity.

Reference fixture pinned from collectiq's `ReportContentButton`.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.4.0...0.4.1

## 0.4.0 - 2026-04-13

### Added

#### `ConvertLivewireRuleAttributeRector`

Livewire's validation attributes only accept const-expression arguments, so expressing FluentRule chains, closures, or custom rule objects inside them is impossible. The idiomatic migration is to move the rule into a `rules(): array` method where the full FluentRule API is available. This rector automates that migration.

```php
// Before
use Livewire\Attributes\Rule;
use Livewire\Component;

final class Settings extends Component
{
    #[Rule('nullable|email', as: 'notificatie e-mailadres')]
    public ?string $notification_email = '';
}

// After
use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;

final class Settings extends Component
{
    public ?string $notification_email = '';

    protected function rules(): array
    {
        return [
            'notification_email' => FluentRule::email()->nullable()->label('notificatie e-mailadres'),
        ];
    }
}
























































```
`use HasFluentValidation;` is added separately by `AddHasFluentValidationTraitRector` (the set list runs this rector before the trait rector).

**Handles:**

- Both short `#[Rule]`/`#[Validate]` and fully-qualified `#[\Livewire\Attributes\Rule]`/`#[\Livewire\Attributes\Validate]`.
- The `as:` named argument becomes `->label('...')` on the chain.
- Multiple properties in the same class collect into a single `rules(): array` method (appended in source order), emitted with one item per line via Rector's `NEWLINED_ARRAY_PRINT` attribute — readable regardless of item count.
- An existing `rules(): array` method with a simple `return [...]` is merged into (attribute rules appended); an existing `rules()` method with non-trivial control flow (conditional returns, logic) bails with a skip log — migrate manually.
- Form components (`extends \Livewire\Form`) work the same as regular components.

**Bail on hybrid classes.** Classes that declare `#[Rule]`/`#[Validate]` attributes AND call `$this->validate([...])` with an explicit array argument use the explicit args as the authoritative validation source — Livewire ignores attribute rules once `validate()` is called with explicit rules. Converting the attributes in such classes would produce a `rules(): array` method that's dead code (the explicit `validate([...])` bypasses it) and creates noisy diffs. The rector detects these classes via a `MethodCall name=validate + non-null first arg` scan and skips them with a log reason. Users can still consolidate manually.

**Dropped unsupported args.** The `message:` (singular), `messages:` (plural), and `onUpdate:` named attribute arguments have no direct FluentRule builder equivalents. The rule-string and `as:` label migrate; the unsupported args are dropped and logged via the package's skip-reason mechanism (visible with `FLUENT_VALIDATION_RECTOR_VERBOSE=1`). An in-source `// TODO:` comment beside the converted chain was planned but PhpParser's pretty-printer doesn't reliably render comments on sub-expressions inside array items — that's deferred to a follow-up release with a proper post-rector implementation.

**Array-form `#[Rule([...])]` attributes are deferred.** Array-based attribute arguments require sharing more of `ValidationArrayToFluentRuleRector`'s private helpers via the `ConvertsValidationRules` trait. Tractable; for now the rector logs a skip pointing to the property so manual migration is unambiguous.

Reported by [@kb7vilgo](https://github.com/) (mijntp) with a reference before/after from `InventorizationAdminSettings`. Pre-tag verification on collectiq's 7 `#[Rule]`-using files surfaced the hybrid-class pattern (5 of the 7) and the `message:` singular vs `messages:` plural Livewire-attribute-arg naming, both fixed before tagging.

### Changed

#### `FluentValidationSetList::CONVERT` now includes the new rector

Running `FluentValidationSetList::ALL` (or `CONVERT` alone) picks up attribute conversion automatically. No config change required.

#### Shared trait improvements

- `convertStringToFluentRule()` moved from `ValidationStringToFluentRuleRector` to the `ConvertsValidationRules` trait, so all three converters (string-form, array-form, attribute) share one implementation.
- `wrapInRuleEscapeHatch()` factored out; both callers (string converter + the new attribute path) reuse it.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.3.2...0.4.0

## 0.3.2 - 2026-04-12

### Fixed

#### `FluentValidationSetList::ALL` now actually applies all three rules

0.3.1 moved `ValidationStringToFluentRuleRector` and `ValidationArrayToFluentRuleRector` to register `Namespace_` as their single node type (so they could insert `use FluentRule;` at the alphabetically-sorted position). Unintended consequence: the converters, the grouping rector, and the trait rectors all competed for the same `Namespace_` instance on the same traversal pass, and Rector's format-preserving printer couldn't reconcile their concurrent mutations. Users running `FluentValidationSetList::ALL` saw only the converter's output — `GroupWildcardRulesToEachRector` silently no-op'd (flat wildcards stayed unfolded), and `AddHasFluentRulesTraitRector` silently no-op'd (no `use HasFluentRules;` added).

There was also a second failure mode: the converters emit a short `new Name('FluentRule')` reference, so the grouping rector's `getFluentRuleFactory()` matcher (checking against the fully-qualified `FluentRule::class`) and the trait rectors' `usesFluentRule()` detection both failed to recognize the converted chains.

The fix has three parts:

1. **Revert the converter node types** to the pre-0.3.1 set (`[ClassLike, MethodCall, StaticCall]`). The `use FluentRule;` import is now queued via Rector's `UseNodesToAddCollector` / `UseAddingPostRector` post-rector pipeline instead of sorted insertion. Consumers running Pint's `ordered_imports` fixer see the same final state as 0.3.0 (pre-Pint output is slightly less polished than 0.3.1, but no longer silently broken).
   
2. **Short-name tolerance in downstream rectors.** `GroupWildcardRulesToEachRector::getFluentRuleFactory()` and the trait rectors' `usesFluentRule()` now match both `SanderMuller\FluentValidation\FluentRule` (FQN) and `'FluentRule'` (short), so they recognize converter output within the same traversal pass.
   
3. **Full-pipeline regression test.** New `FullPipelineRectorTest` runs `FluentValidationSetList::ALL` end-to-end against a fixture that exercises the string → FluentRule → wildcard-fold → trait-insertion chain. This is the test that would have caught 0.3.1 before shipping — the existing per-rector configs only exercise one rule at a time and miss cross-rule interaction.
   

Reported by hihaho during 0.3.1 re-verification.

### Trade-off vs 0.3.1

0.3.1 emitted sorted imports from the converters (Pint was a no-op on converter-touched files). 0.3.2 prepends the import via `UseNodesToAddCollector` (Pint's `ordered_imports` fires once per converter-touched file). The trait rectors still use the sorted-insertion path from 0.3.0 / 0.3.1 (Pint no-op on trait-inserted imports).

The trade-off is: correct pipeline behavior (critical) vs Pint being a no-op on the converter pathway (nice-to-have). Since all consumers run Pint or `php-cs-fixer` in practice, the final output is unchanged. A future release may bring the "Pint no-op" property back once the Rector-framework interaction can be revisited without sacrificing pipeline correctness.

## 0.3.1 - 2026-04-12

### Fixed

#### Converter-pathway `FluentRule::` references now use the short name

`ConvertsValidationRules::buildFluentRuleFactory()` (shared by `ValidationStringToFluentRuleRector` and `ValidationArrayToFluentRuleRector`) used to emit `new FullyQualified(FluentRule::class)` for the initial factory call. Pint's `fully_qualified_strict_types` fixer cleaned it up, but pre-Pint output was noisy. The helper now emits `new Name('FluentRule')` and auto-inserts `use SanderMuller\FluentValidation\FluentRule;` at the alphabetically-sorted position when the import isn't already present.

Mirrors the 0.3.0 fix on `GroupWildcardRulesToEachRector`. Now every rector in this package emits consistent, Pint-free output:

- String/array converters (this release)
- Grouping rector's synthesized parent/field (0.3.0)
- Trait rectors' inserted `use` import (0.3.0 via `ManagesNamespaceImports`)

```php
// Before (0.3.0 output, pre-Pint)
'author_notes' => \SanderMuller\FluentValidation\FluentRule::string()->nullable()->max(65535),

// After (0.3.1 output, pre-Pint)
'author_notes' => FluentRule::string()->nullable()->max(65535),


























































```
Reported by hihaho (gap note during 0.3.0 re-verification) and collectiq (Nit A).

### Changed

#### `ValidationStringToFluentRuleRector` and `ValidationArrayToFluentRuleRector` now register `Namespace_` as their node type

Previously they registered `[ClassLike, MethodCall, StaticCall]` — three separate entry points for `rules()` methods, `$request->validate([...])` calls, and `Validator::make([...])` calls. Now they register `[Namespace_]` and traverse the subtree internally, which lets them insert the `FluentRule` import once per namespace at the correct position.

Test configs for both rectors no longer use `withImportNames()` — the rectors produce sorted output on their own.

No behavior change for end users: the same three call patterns are detected and converted. Classes without a namespace (rare in Laravel projects) are now skipped; document as a known limitation.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.3.0...0.3.1

## 0.3.0 - 2026-04-12

### Added

#### Array-tuple rules lower directly to fluent method calls

Previously `['max', 65535]`, `['between', 3, 100]`, `['min', 4]` were wrapped in `->rule([...])` escape-hatch form even when the rule name mapped cleanly to a fluent modifier. Laravel treats the colon form (`'max:65535'`) and the tuple form (`['max', 65535]`) as equivalent, but the rector only lowered the colon form. `ValidationArrayToFluentRuleRector` now dispatches tuples through a new `buildModifierCallFromTupleExprArgs()` helper that reuses the existing rule-name-to-fluent-method mapping.

Covers `NUMERIC_ARG_RULES`, `TWO_NUMERIC_ARG_RULES`, `STRING_ARG_RULES`, and one-arg rules like `regex`, `format`, `startsWith`. Falls back to `->rule([...])` when the rule name isn't in the dispatch table, preserving prior behavior. Per-element lowering is preserved: mixed tuples + closure keep the closure as `->rule(fn)`.

```php
// Before
'author_notes' => ['nullable', 'string', ['max', 65535]]
// After (0.1.x)
'author_notes' => FluentRule::string()->nullable()->rule(['max', 65535])
// After (0.3.0)
'author_notes' => FluentRule::string()->nullable()->max(65535)



























































```
#### Flat wildcard `'items.*'` entries fold into parent `->each(<scalar>)`

`GroupWildcardRulesToEachRector` previously only collapsed dot-notation groups with nested wildcard children (`items.*.field`) or fixed children (`items.field`). A standalone `'items.*' => ...` entry stayed separate, even when the idiomatic form is `FluentRule::array()->each(FluentRule::field()->...)`. The rule now folds the flat wildcard's FluentRule chain into the parent as `->each(<scalar>)` rather than `->each([key => val, …])`.

Synthesizes a bare `FluentRule::array()` parent when no explicit parent exists. Handles const-concat wildcard keys (`self::VIDEO_IDS . '.*'`) via the existing constant-resolution pathway. Parent type is still validated: `each()` only attaches to `FluentRule::array()`.

```php
// Before
'interactions' => FluentRule::array(),
'interactions.*' => FluentRule::field()->filled(),
// After
'interactions' => FluentRule::array()->each(FluentRule::field()->filled()),



























































```
#### Skip trait insertion when an ancestor already declares it

Both trait rectors now walk the class's ancestor chain via `ReflectionClass` and skip insertion when any parent class already uses `HasFluentRules` or `HasFluentValidation`. Complements the existing `base_classes` configuration — codebases with a shared Livewire or FormRequest base don't need to enumerate every intermediate class for the rector to avoid redundant trait additions.

The reflection walk runs against the consumer project's autoloader, so it works whenever the parent class is loadable at rector-run time (effectively always for Laravel apps). Unloadable parents silently fall through to the "add trait" path, preserving prior behavior.

### Fixed

#### Synthesized `FluentRule::` references now use the short name

`GroupWildcardRulesToEachRector` previously emitted `\SanderMuller\FluentValidation\FluentRule::array()` (fully qualified) when synthesizing a parent or nested field wrapper. Pint's `fully_qualified_strict_types` fixer would clean it up, but pre-Pint output was noisy. The rector now emits `FluentRule::array()` (short) and inserts `use SanderMuller\FluentValidation\FluentRule;` at the alphabetically-sorted position when the import isn't already present.

#### Trait `use` imports insert alphabetically instead of prepending

0.1.1 routed the top-of-file trait import through Rector's `UseNodesToAddCollector`, whose `UseAddingPostRector` always prepends new imports regardless of alphabetical order. Pre-Pint output was worse than 0.1.0's (which inserted adjacent to existing `SanderMuller\…` imports). Both trait rectors now insert the `use` statement manually at the alphabetically-sorted position, falling back to "append after the last use" when the existing imports aren't already sorted (preserving intentional user ordering). Shared AST logic lives in a new `Concerns\ManagesNamespaceImports` trait consumed by all three rectors that synthesize imports.

#### PHPStan no longer fails on the `#[FluentRules]` attribute reference

The rector references `SanderMuller\FluentValidation\FluentRules` as a forward-compatible attribute class — it ships in newer `laravel-fluent-validation` releases but isn't present in every version satisfying the `^1.0` constraint. Switched from `FluentRules::class` to a string literal so static analysis doesn't trip on the optional reference. CI-only regression; no runtime behavior change.

### Regression tests locked in

#### `Rule::unique(Model::class)->withoutTrashed()` → fluent `->unique()` callback

The existing `convertChainedDatabaseRule()` pathway already converts `Rule::unique(...)->method()` and `Rule::exists(...)->method()` chains to the fluent callback form (`->unique($table, $column, fn ($rule) => $rule->method())`). An earlier report suggested this pattern wasn't working; verified it does. Added a fixture exercising the exact `Rule::unique(User::class)->withoutTrashed()` shape to prevent regression.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.1.1...0.3.0

## 0.2.0 - 2026-04-12

### Added

#### Array-tuple rules lower directly to fluent method calls

Previously `['max', 65535]`, `['between', 3, 100]`, `['min', 4]` were wrapped in `->rule([...])` escape-hatch form even when the rule name mapped cleanly to a fluent modifier. Laravel treats the colon form (`'max:65535'`) and the tuple form (`['max', 65535]`) as equivalent, but the rector only lowered the colon form. `ValidationArrayToFluentRuleRector` now dispatches tuples through a new `buildModifierCallFromTupleExprArgs()` helper that reuses the existing rule-name-to-fluent-method mapping.

Covers `NUMERIC_ARG_RULES`, `TWO_NUMERIC_ARG_RULES`, `STRING_ARG_RULES`, and one-arg rules like `regex`, `format`, `startsWith`. Falls back to `->rule([...])` when the rule name isn't in the dispatch table, preserving prior behavior. Per-element lowering is preserved: mixed tuples + closure keep the closure as `->rule(fn)`.

```php
// Before
'author_notes' => ['nullable', 'string', ['max', 65535]]
// After (0.1.x)
'author_notes' => FluentRule::string()->nullable()->rule(['max', 65535])
// After (0.2.0)
'author_notes' => FluentRule::string()->nullable()->max(65535)




























































```
Reported from a run against the hihaho codebase (20+ files).

#### Flat wildcard `'items.*'` entries fold into parent `->each(<scalar>)`

`GroupWildcardRulesToEachRector` previously only collapsed dot-notation groups with nested wildcard children (`items.*.field`) or fixed children (`items.field`). A standalone `'items.*' => ...` entry stayed separate, even when the idiomatic form is `FluentRule::array()->each(FluentRule::field()->...)`. The rule now folds the flat wildcard's FluentRule chain into the parent as `->each(<scalar>)` rather than `->each([key => val, …])`.

Synthesizes a bare `FluentRule::array()` parent when no explicit parent exists. Handles const-concat wildcard keys (`self::VIDEO_IDS . '.*'`) via the existing constant-resolution pathway. Parent type is still validated: `each()` only attaches to `FluentRule::array()`.

```php
// Before
'interactions' => FluentRule::array(),
'interactions.*' => FluentRule::field()->filled(),
// After
'interactions' => FluentRule::array()->each(FluentRule::field()->filled()),




























































```
Reported from a run against the hihaho codebase (15+ files).

### Fixed

#### Trait `use` imports insert alphabetically instead of prepending

0.1.1 routed the top-of-file trait import through Rector's `UseNodesToAddCollector`, whose `UseAddingPostRector` always prepends new imports regardless of alphabetical order. Pre-Pint output was worse than 0.1.0's (which inserted adjacent to existing `SanderMuller\…` imports). Both trait rectors now insert the `use` statement manually at the alphabetically-sorted position, falling back to "append after the last use" when the existing imports aren't already sorted (preserving intentional user ordering). The shared AST manipulation logic moves to a new `Concerns\ManagesTraitInsertion` trait consumed by both rectors.

Reported from runs against the mijntp and hihaho codebases.

#### PHPStan no longer fails on the `#[FluentRules]` attribute reference

The rector references `SanderMuller\FluentValidation\FluentRules` as a forward-compatible attribute class — it ships in newer `laravel-fluent-validation` releases but isn't present in every version satisfying the `^1.0` constraint. Switched from `FluentRules::class` to a string literal so static analysis doesn't trip on the optional reference. CI-only regression; no runtime behavior change.

## 0.1.1 - 2026-04-12

### Fixed

- `GroupWildcardRulesToEachRector` no longer injects `->nullable()` on a synthesized parent. Before, a rules array like `'keys.p256dh' => ...->required(), 'keys.auth' => ...->required()` was rewritten to `'keys' => FluentRule::array()->nullable()->children([...])`, which silently accepted payloads without `keys` at all — the `nullable()` short-circuited evaluation so the nested `required()` children never fired. The synthesized parent is now bare (`FluentRule::array()->children([...])`), restoring the original dot-notation semantics where missing `keys` triggers the nested `required` rules. Reported by a peer running 0.1.0 against the collectiq codebase.
- `children()` and `each()` arrays are now always printed one-key-per-line. Before, synthesized nested arrays collapsed onto a single line, producing 200+ character entries when child values contained further arrays (e.g. `->in([...])`) that Pint couldn't reflow. Multi-line printing is now forced via Rector's `NEWLINED_ARRAY_PRINT` attribute regardless of child complexity.
- `AddHasFluentRulesTraitRector` and `AddHasFluentValidationTraitRector` now emit a proper top-of-file `use` import for the trait and reference the short name inside the class body. Before, the rule emitted `use \SanderMuller\FluentValidation\HasFluentRules;` inline, relying on the consumer's `rector.php` to enable `withImportNames()` (or on Pint) to clean it up. The rule now queues the import via Rector's `UseNodesToAddCollector` directly, so out-of-the-box output is polished regardless of downstream formatter configuration.
- `AddHasFluentRulesTraitRector` and `AddHasFluentValidationTraitRector` now emit a blank line between the inserted trait and the next class member. Before, Livewire components whose first member was a docblocked property (`/** @var ... */\npublic array $foo = ...;`) had the trait glued directly to the docblock without separation. Pint doesn't rescue this unless the consumer opts into `class_attributes_separation.trait_import`, so the rule inserts a `Nop` statement to produce the blank line itself. Reported by a peer running 0.1.0 against the mijntp codebase.

**Full Changelog**: https://github.com/SanderMuller/laravel-fluent-validation-rector/compare/0.1.0...0.1.1

## 0.1.0 - 2026-04-12

Initial release.

### Added

- Rector rules for migrating Laravel validation to [sandermuller/laravel-fluent-validation](https://github.com/sandermuller/laravel-fluent-validation):
  
  - `ValidationStringToFluentRuleRector` — converts string-based rules (`'required|email|max:255'`) to the fluent API.
  - `ValidationArrayToFluentRuleRector` — converts array-based rules (`['required', 'email']`) to the fluent API.
  - `SimplifyFluentRuleRector` — collapses redundant or verbose fluent chains.
  - `GroupWildcardRulesToEachRector` — groups wildcard (`items.*`) rules into `FluentRule::each()` blocks.
  - `AddHasFluentRulesTraitRector` and `AddHasFluentValidationTraitRector` — adds the required traits to FormRequests, Livewire components, and custom validators.
  
- Set lists in `FluentValidationSetList` for applying rules individually or as a full migration pipeline.
  
- Covers `Validator::make()`, FormRequest `rules()`, Livewire `$rules` properties, and inline validator calls.
  
