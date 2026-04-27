# Spec process invariants

Conventions every new spec under `specs/` must satisfy. The list grows
when a real bug surfaces a gap that should have been caught earlier;
each entry pins the lesson so the next spec doesn't repeat it.

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
