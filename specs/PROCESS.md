# Spec & dogfood process invariants

Conventions every new spec under `specs/` must satisfy and every
dogfood cycle should follow. The list grows when a real bug surfaces
a gap that should have been caught earlier — each entry pins the
lesson so the next spec or release cycle doesn't repeat it.

§§1–4 are **rector-design invariants** (heuristic boundaries, fixture
shapes, test-config coverage). §§5–9 are **dogfood-process
invariants** (how consumer-shape audits inform release decisions),
codified during the 0.20.x → 0.21.x cycle that drove the project to
1.0-RC distance.

## 1. Cross-fold-path interaction matrix

Every spec that introduces a **new fold path** (a transformation that
emits new top-level keys into a rules array) must ship at least one
fixture per non-trivial intersection cell with each existing fold
path.

A "fold path" is any code path that mutates the structure of a rules
array — string-key folds, wildcard-prefix concat folds, dotted-key
nesting, RuleSet wrapper descent, etc.

A "non-trivial intersection cell" is a fixture where two fold paths
both apply to the same array (or to nested arrays produced by each
other). The matrix grows quadratic with fold paths, but most cells
are trivial — "doesn't apply" — and need no fixture. The few
non-trivial cells are exactly the surface where silent rule loss can
hide (last-write-wins on the same top-level key, predicate misses on
a fold's emit shape, stale-snapshot decision-making).

### Origin

Caught retroactively in 0.19.0 codex review: case-(a) literal-fold +
case-(d) const-fold both emitted `'*' =>` entries into the same
array, last-write-wins silently dropped one branch. The
`tests/GroupWildcardRulesToEach/Fixture/mixed_literal_and_const_wildcard_keys.php.inc`
fixture is the regression pin. Without that fixture, the bug would
have shipped to consumers.

### Workflow

When drafting a new fold-path spec:

1. Enumerate the existing fold paths the rector already implements.
2. For each existing path × the new path, ask: "if both apply to the
   same array, can the result be wrong?" If yes, add a fixture.
3. Cells that are trivially independent (e.g. a fold path that only
   touches `'*.foo'` keys × a fold path that only touches `'foo.bar'`
   keys, with no shared parent emit) need no fixture but get a
   one-line note in the spec's matrix table.
4. List the matrix in the spec under §4.1 (or the equivalent section
   for the spec's structure).

### What this guards against

- **Last-write-wins on shared top-level keys.** Two folds emitting
  into the same key clobber one branch. The 0.19.0 codex find.
- **Stale-snapshot decision-making.** A fold reads its decision input
  from a pre-other-fold snapshot, missing in-flight mutations. Same
  0.19.0 root cause.
- **Predicate / detector blind spots.** A downstream rector's
  predicate accepts the shape one fold emits but rejects the shape
  another emits. Caught by exercising the cross-product fixture
  through both rectors in a parity test or end-to-end fixture.

## 2. Single-source-of-truth for rules() body anchors

When a spec changes how the rector locates the rules array inside a
method body, the anchor must be defined explicitly:

- **Direct-child statements only.** Walk `$method->stmts` for the
  anchoring node (typically `Return_`). Do NOT recurse into closure
  bodies, nested control flow, or method calls — `return`s inside
  `excludeIf(function() { return …; })` validators or other closure
  args are descendants, not control flow on the rules() return.
- **Local assignment statements before the return are fine.**
  `$exclude = …; return [...];` is single-anchor; the assignment is
  preparation, not branched flow.

### Origin

0.19.1 P0 (RuleSet::from descent). hihaho + mijntp dogfood both
flagged false-positive multi-return detection on their codebases when
naive grep counted closure-internal `return` tokens. Direct-child
anchoring is the right level.

## 3. Diagnose-before-patch on multi-pass / pipeline failures

When a downstream rector skips a shape that an upstream rector emits,
the predicate is the *prime suspect*, not the *confirmed cause*.
Before widening the predicate:

1. Write a fixture with the post-fold shape as static input. Run only
   the downstream rector against it.
2. If the static fixture passes, the predicate is fine — the bug is
   somewhere in the multi-rector pipeline (name resolution scope,
   traversal order, fold-emit AST shape, etc.).
3. Build an end-to-end fixture (input pre-fold, both rectors
   configured, expected output post-fold + post-narrow). Reproduce
   the actual failure mode there.
4. Bisect to root cause. Then patch at the right layer.

### Origin

0.19.1 P1. Initial peer report named the predicate as the bug; static
fixture against `'*' => FluentRule::array()->children([Array_])`
passed clean. Real bug is downstream of the predicate. Premature
predicate widening would have papered over the real cause.

## 4. Test config completeness for multi-pass scenarios

Integration tests under `tests/` must load every set list that a
real consumer would load for the scenario being tested. Don't assume
`FluentValidationSetList::ALL` covers the full rector surface — it
deliberately excludes opt-in sets like `POLISH` and `SIMPLIFY` per
the documented set split.

When a multi-pass interaction matters (e.g. fold output flowing
through a downstream narrow), the integration test config must mirror
the consumer config that surfaced the bug:

- `tests/FullPipeline/` covers `ALL` only — converters + grouping +
  traits.
- `tests/FullPipelinePolish/` covers `ALL + POLISH` — adds the
  ergonomic post-rectors that documented consumers opt into after
  initial migration.

Future test directories should make their loaded set explicit in the
test class docblock. If a bug only reproduces under a specific
consumer set combination, add a fixture under the matching test
directory or create a new one — don't expand an existing test's set
list, since that changes the expected output of every fixture in
that directory.

### Origin

0.19.1 P1 diagnosis. The initial reproduction fixture under
`tests/FullPipeline/` masked the bug for 30 minutes because POLISH
wasn't loaded — `UpdateRulesReturnTypeDocblockRector` simply didn't
run, making it impossible to tell whether the predicate fix had
worked. The fix shipped clean once the config gap closed.

## 5. Gating-matrix outcomes pattern for dogfood asks

When designing a dogfood verification ask involving a previously-
flagged shape, **pre-define best/acceptable/concerning outcomes and
the action tree from each.** Don't ask consumers "did the fix work?"
— ask them "the fix is supposed to produce X; here are three
possible outcomes (best / acceptable / concerning) and what each
means for next steps."

### Workflow

For each gating-signal dogfood ask:

1. **Pre-define the matrix** in the ping. Three outcomes minimum:
   - **Best case**: the symptom drops out entirely (fix worked).
   - **Acceptable case**: the symptom remains but with improved
     diagnostic detail (fix partially worked; fall-back path is
     correct).
   - **Concerning case**: the symptom remains AND the diagnostic
     detail is unchanged (fix didn't help; deeper investigation
     needed).
2. **Bind each outcome to a concrete next step**. Best → close the
   work item. Acceptable → file a polish patch. Concerning →
   escalate to the next-tier fix (data-flow analysis, refactor,
   etc.).
3. **The consumer reports which case occurred**, not whether the fix
   worked in abstract terms. Eliminates ambiguity in interpretation.

### What this guards against

- **Ad-hoc dogfood interpretation.** Without pre-defined outcomes,
  "the fix didn't fully work" can mean "fall-back fired correctly"
  or "fix has zero impact" — different action trees, often conflated.
- **Premature 1.0 RC declarations.** A "looks fine" report on a
  single dogfood pass is weaker than "best case fired across N
  consumer lenses."

### Origin

0.20.1 → 0.21.0 cycle on `ServiceProviderAdminPage` false-positive.
The 0.20.1 ping pre-defined the three outcomes; mijntp's reply
unambiguously surfaced "concerning case" → escalated 0.21.0
data-flow tightening to 1.0 RC blocker. The 0.21.0 ping reused the
same matrix; mijntp reported "best case" → closed the work item
cleanly.

## 6. Lens-diversity invariant on dogfood asks

Consumer codebases have shape-coverage blind spots. A single lens
catches what its codebase exercises and misses what it doesn't.
Dogfood asks must cover **≥3 distinct consumer-shape lenses** before
counting toward a 1.0 RC readiness signal.

### Canonical lens types

- **Cold-consumer**: a codebase that just adopted the package, has
  drained surface (most opportunities already converted), and reads
  documentation as a first-time user. Catches: docs gaps, public-
  API surprises, FQN-leaks, unclear error messages.
- **Deep-dogfood**: a codebase with extensive use of every rector
  surface, complex inheritance hierarchies, real production
  patterns. Catches: heuristic false positives/negatives, multi-
  pass interactions, performance issues at scale.
- **Skip-log signal-to-noise**: a codebase that runs the rector
  routinely and treats the skip log as actionable diagnostic.
  Catches: unactionable noise, misleading wording, wrong-line
  resolutions, missing context in error messages.
- **Drained-surface**: a codebase that adopted the package early
  and has converged — most rector runs are no-ops. Catches:
  bump-safety regressions, behavior changes that consumers thought
  were stable.

### Workflow

When pinging for a release verification:
1. Identify which lenses currently engage with the project's
   active consumer set.
2. Frame the ask per-lens: each consumer gets the question framed
   for what their lens uniquely surfaces.
3. Synthesize across lenses: a finding from one lens is a hypothesis;
   the same finding (or its negation) confirmed by a second lens is
   evidence; converged across three lenses is a triggerable signal.

### Origin

The 0.20.x cycle surfaced findings that NO lens caught alone:
collectiq's cold-consumer lens caught FQN-leak + skip-message-
wiring-ambiguity; mijntp's skip-log signal-to-noise lens caught
unsafe-parent-on-Filament-pages noise; hihaho's deep-dogfood lens
caught the 12 spread/method-chain `parent::rules()` shapes that
empirically locked OQ #1 = (a). Three lenses produced three
different findings → three different fixes → 0.21.0 ships with a
heuristic that no single-lens audit could have validated.

## 7. Saturation criteria for 1.0 RC readiness

"Saturation" = the dogfood-finding signal stops producing new
fixable items, indicating the audit lens has covered its surface.
Misinterpretation risk: a "0 findings on this release" report can
mean "lens has saturated" OR "lens didn't engage with the release's
new surface". Distinguish.

### Saturation criteria (all required)

1. **Consumer surface engages with the release's scope**. If a
   release adds a new emit feature, lenses that don't exercise that
   emit don't count toward saturation for that subsurface.
2. **Findings on the release converge across lenses**. Three
   independent lenses producing zero new findings each is weaker
   evidence than three lenses converging on one shared root finding
   that gets fixed in the same cycle. Saturation = converged-
   evidence on shared root, not zero-output-per-lens.
3. **Subsurface clock resets when new emit ships**. Any new emit
   feature in a release re-opens audit-lens for ≥1 dogfood pass
   before counting toward saturation N. The line-number-emit
   surface added in 0.21.0 reset the clock for that subsurface;
   0.21.1's fix + verification establishes the new baseline.

### Stopping rule

When all three criteria hold across all engaged lenses for **two
consecutive release cycles** (N=2), the project is 1.0 RC ready
from the audit-lens perspective.

N=2 chosen over N=1 because one consecutive cycle could be
incidental ("nothing changed") rather than evidence of saturation.
Two consecutive cycles is meaningful evidence the audit lens has
genuinely converged on the project's behavior.

### Origin

0.21.0 → 0.21.1 cycle. mijntp reported "1.0 RC ready from my side"
on 0.21.0 — first formal saturation declaration from any consumer
lens. Hihaho confirmed on 0.21.1 — second lens converged. Collectiq
converged at the same time via the cross-lens line-resolution
finding being fixed in 0.21.1. The N=2 criterion is satisfied for
the 0.21.x release pair across the three-lens panel.

## 8. Audit-evidence baselines vs. code-level expected-state

Dogfood baselines (JSON outputs, skip-log dumps, fixture diffs)
serve **two distinct epistemic roles** that have different
reproducibility requirements:

- **Audit evidence for design-decision support**: "shapes that exist
  in real codebases, used to inform spec OQ resolutions and
  heuristic boundaries." Reproducibility is **not load-bearing** —
  what matters is that the shapes were real at audit time, not
  whether `git checkout && rector process` reproduces the exact
  output today.
- **Code-level expected-state references**: "regression baselines
  used to assert the rector still produces output X for input Y."
  Reproducibility is **strictly load-bearing** — the baseline must
  be regenerable from a documented state.

Conflating the two roles risks invalidating audit conclusions when
a baseline turns out to be working-tree-only or otherwise non-
reproducible. The audit-evidence role is robust to that ambiguity;
the expected-state-reference role is not.

### Workflow

- Use peer-shared `.dogfood-baselines/*.json` outputs as **audit
  evidence**: cite the shape, the consumer codebase, the release
  cycle. Don't import them as fixture inputs.
- For **code-level expected-state**, use `tests/*/Fixture/*.php.inc`
  files committed to this repo. Those are reproducible by
  construction.
- When a peer's baseline turns out to be non-reproducible from
  branch HEAD, the audit conclusions stand if they cited shapes;
  only fixture-imported expected-states would need re-validation.

### Origin

Hihaho 0.21.0 dogfood disclosure (2026-04-28): all
`.dogfood-baselines/0.16-0.20.x/*` JSON outputs reflected working-
tree state never committed to branch HEAD. Audit conclusions held
because peer-shared SHAPES (PHP source pasted in messages) drove
spec OQ resolutions, not the JSON outputs. Going forward each
peer-shared baseline includes a `STATE.md` annotating the exact
branch-and-pin state for reproducibility.

## 9. Fast-turnaround norm on consumer findings

Consumer findings filed during a release cycle should land in a
patch within hours when the fix is small (logger-side, message-
wording, predicate-widen). The fast turnaround:

1. **Maintains consumer trust**: a finding answered with "noted,
   will fold into next minor" loses signal; a finding answered with
   a tagged patch the same day reinforces the dogfood loop.
2. **Closes the audit-lens window before drift**: peers re-dogfood
   while their context is fresh. Day-late patches require peers to
   re-engage, which has friction.
3. **Compounds across cycles**: each fast-turnaround fix shortens
   the next ask's response time, because peers know small findings
   ship fast.

### Threshold

- **Hours**: logger-side bugs, message-wording rewrites, doc-
  pointer additions, single-predicate widens.
- **Same day**: small heuristic adjustments, single-fixture additions.
- **Same cycle (next minor)**: heuristic tightening, multi-rector
  refactors, deprecation cycles.
- **Next major / 1.0 RC**: SemVer-affecting renames, namespace
  moves, removal of deprecated symbols.

Match scope to threshold. Don't fold an hours-class fix into a
next-minor cycle.

### Origin

0.20.0 → 0.20.1 (closure-scope fix shipped within hours of mijntp's
finding). 0.21.0 → 0.21.1 (Class\_ line-resolution fix shipped
within ~10 minutes of collectiq's finding). Both reinforced the
"small findings ship fast" norm; hihaho's arc-retrospective on
0.21.0 explicitly cited fast turnaround as one of four reasons the
release sequence worked cleanly.

## Candidate pen (awaiting second-cycle confirmation)

Findings and methodologies that have surfaced once and are
demonstrably real but not yet generalizable enough to earn a
numbered invariant or methodology slot. Three epistemic anchors gate
promotion:

- **Premature canonicalization is the failure mode.** Codifying a
  candidate as a numbered invariant on N=1 shape risks post-hoc
  broadening when a second shape surfaces. Candidate-pen preserves
  the finding + rationale + retroactive evidence with zero loss;
  promotion drafts from N=2 shapes, not N=1-with-multiple-instances.
- **Invariants earn their slot, they don't get one by appearing.**
  Same gate, more pointed phrasing. A finding being real isn't
  sufficient for canonical-invariant status; the wording has to
  generalize across the failure-mode shape, not just the originating
  consumer.
- **Methodologies earn their slot the same way invariants do** — via
  independent multi-cycle surfacing, not single-cycle utility. A
  novel dogfood methodology that catches a real defect on its first
  use is a *candidate*, not yet a canonical methodology; promotion
  requires the methodology surfacing a defect that the existing
  methodology surface couldn't have caught, across a second cycle.
  Recursive epistemic gate: applies the lens-diversity / multi-cycle
  criterion to methodology additions themselves.

When a second consumer's dogfood independently surfaces the same
class of failure mode (different shape, different bypass, different
manifestation), promote the candidate to a numbered §N invariant
synthesizing both observations. Until then, the entry stays here.

### Implicit corollary on saturation

Saturation requires both **(a) consumer-shape signal-stability AND
(b) methodology-surface stability.** A new methodology surfacing a
new finding mid-cycle is itself a saturation reset condition, not
just a new lens reading. Declaring 1.0 ready while methodology
innovation is still active is the same failure-mode shape as
"premature canonicalization" one level up. The audit-clock-reset
rule already pinned in §7.3 is the consumer-shape side; this is the
methodology-surface side.

<!-- candidate-pen entry; awaiting second-cycle confirmation -->

### Cross-package constraint pre-flight (vendor-rsync methodology)

**Status**: candidate, surfaced 2026-04-28 during 0.22.0 dogfood
(skip-log signal-to-noise lens). Promotes to numbered invariant
when a second consumer surfaces an independent bypass-constraint
failure that broadens the wording shape.

**Observation**: rsync-based dogfood pattern bypasses Composer's
constraint resolution. The 0.22.0 dogfood hit a runtime
`ReflectionException` on `SimplifyRuleWrappersRector::bootResolutionTables()`
because rector 0.22.0's `FACTORY_BASELINE` referenced `AcceptedRule`
(added in `laravel-fluent-validation` 1.20+) while the consumer's
lockfile pinned 1.13.2. A real `composer require` would have
refused with a clear constraint-mismatch message; the
rsync-vendor-swap pattern bypassed that gate.

**Retroactive evidence**: same methodology weakness was present in
4 prior dogfood cycles (0.18 → 0.21.0). Those cycles ran without
fatal because no `FACTORY_BASELINE` additions broke against the
older sister-package version. The absence of fatals was luck (no
hostile additions), not contract compliance.

**Sketch (package-version-shape only)**:

```bash
jq -r '.require | to_entries[] | "\(.key) \(.value)"' \
  /tmp/<new-version-worktree>/composer.json |
while read pkg constraint; do
  locked=$(jq -r ".packages[]|select(.name==\"$pkg\").version" composer.lock)
  composer-semver "$constraint" "$locked" || echo "MISMATCH: $pkg"
done
```

If any line emits `MISMATCH`, abort the rsync. Either skip dogfood
this cycle pending consumer constraint update, or bump consumer
constraints first (in a sandbox), then re-run.

**Why deferred**: the sketch is package-version-mismatch-shaped.
Composer overrides bypass at resolution time (after the `jq .require`
check passes). Path repos with `dev-master` pins yield null version
fields. PHP / extension constraints (`require.php`, `require.ext-*`)
surface as different fatal classes. Symlinked vendor for monorepos,
vendor:prestissimo races, hand-edited `installed.json` — all bypass
paths the sketch doesn't anticipate. Codifying today on the
package-version shape only risks post-hoc broadening when shape #2
lands; the candidate-pen preserves finding + provenance with zero
loss until N=2.

**Companion pattern (constructive paired)**: "verification sandbox
methodology" — when running a vendor-swap dogfood, bump consumer
constraints in a worktree first via `composer update --with X --with Y`,
then swap. Real constraint compliance instead of bypass. Will
co-promote alongside the constraint pre-flight invariant when both
land together.

**Companion fixture (in-repo)**: 0.22.2's
`tests/RectorInternalContractsTest::testEveryHardcodedClassTableResolves`
+ `testRectorClassesWithHardcodedTablesBootCleanly` pin the upstream
correctness invariant. The 0.22.1 site-specific shape was generalized
to a registry-driven sweep across all rector classes that iterate
hardcoded class-typed const tables (current sites:
`SimplifyRuleWrappersRector::FACTORY_BASELINE`,
`InlineMessageSurface::TYPED_RULE_CLASSES`,
`PromoteFieldFactoryRector::TYPED_BUILDER_TO_FACTORY`); future rector
classes adding their own table register via the `HARDCODED_CLASS_TABLES`
constant. The pre-flight invariant is the dogfood-side analogue; the
fixtures are the in-repo analogue. Both gate the same failure class
from different phases.

**Cross-rector observation surfaced 0.22.1 → 0.22.2**: the original
0.22.1 fix scoped Option A to a single rector; the 0.22.2
runtime-simulation pass found the same correctness-symmetry pattern
at two more sites. Operational invariant restated more broadly: *any
rector iterating a hardcoded class-typed const table for reflection
must filter for `class_exists` before reflecting*. The 0.22.2 fixture
pair encodes this; the dogfood-side invariant (this candidate-pen
entry) catches the same class of failure from the methodology side.

<!-- candidate-pen entry; awaiting second-cycle confirmation -->

### Runtime-simulation dogfood methodology (delete-class + re-run)

**Status**: candidate methodology, surfaced 2026-04-28 during
0.22.1 saturation re-converge pass (skip-log signal-to-noise lens
extended their methodology mid-cycle). Promotes to numbered
methodology when an independent dogfood cycle uses the
runtime-simulation surface to catch a defect that real-consumer-
shape lenses couldn't have caught by construction.

**Observation**: real-consumer-shape lenses catch what their
codebase exercises and miss what it doesn't. *Runtime-simulation*
introduces fault injection — temporarily remove a vendor class file
the rector reflects on, re-run rector, observe whether boot
degrades gracefully or fatals. Catches incomplete-coverage gaps
that consumer-shape lenses won't surface because consumers don't
hit the simulated condition under correctly-resolved Composer
constraints.

The 0.22.1 saturation re-converge used this methodology to find
that the prior cycle's Option A fix at site #1 was incomplete —
the same correctness-symmetry pattern existed at sites #2 and #3
(InlineMessageSurface, PromoteFieldFactoryRector). Real-consumer-
shape dogfood couldn't have caught this because the gap is
consumer-impossible-by-constraint; only fault injection exercises
the missing-class branch.

**Why deferred**: novel methodology surfaced once. Per the
"methodologies earn their slot the same way invariants do"
epistemic anchor, single-cycle utility isn't sufficient for
canonical methodology status. Promotion requires the methodology
catching a *second* defect that the existing methodology surface
couldn't have caught, across a second cycle. If the second-cycle
case lands, the methodology gets a numbered §N section codifying:
when to apply, how to construct the simulated condition (vendor
file deletion, classmap mutation, etc.), what defect classes it
catches, and how it relates to the lens-diversity invariant (§6).

**Companion fixture (in-repo)**: the 0.22.2 fixture pair
(`testEveryHardcodedClassTableResolves` +
`testRectorClassesWithHardcodedTablesBootCleanly`) is the static
+ current-surface coverage. The full simulated-missing-class
runtime fixture (delete vendor file in setUp / scaffold minimal
vendor stub, run boot path, assert graceful degradation) would
land alongside this methodology when it promotes to numbered
status. Until then, the dogfood-side simulation is the only place
the runtime guard's actual behavior gets exercised; the in-repo
fixtures cover existence + current-surface boot only.
