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
