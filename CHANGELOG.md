# Changelog

All notable changes to `sandermuller/laravel-fluent-validation-rector` will be documented in this file.

## 1.2.1 - 2026-04-29

### Changed
- TIER_ALL stderr summary appends a tip pointing at `FLUENT_VALIDATION_RECTOR_VERBOSE=actionable` to filter informational entries.
- `ConvertLivewireRuleAttributeRector` overlap-bail emits per-property skip-log entries naming each property and whether its keys overlap explicit `$this->validate(...)` keys.

### Fixed
- README doc-sync: `ConvertLivewireRuleAttributeRector` "Bails on" list mentions the 1.2.0 layer-2 ancestor-trait conflict; Diagnostics section adds a TIER_ALL stderr-line example.

## 1.2.0 - 2026-04-29

### Added
- `ConvertLivewireRuleAttributeRector` detects and warns on a Livewire property `#[Rule]`/`#[Validate]` attribute whose abstract ancestor uses `HasFluentValidation` or `HasFluentValidationForFilament`; emits a verbose-mode skip-log entry naming remediation paths (move rule into parent's `rules()` method, or drop the trait from the parent).

### Changed
- Direct-trait use on the class itself is intentionally NOT flagged — that shape converts cleanly.

## 1.1.0 - 2026-04-29

### Added
- `ValidationStringToFluentRuleRector` and `ValidationArrayToFluentRuleRector` descend into `RuleSet::from([...])` regardless of surrounding-class qualification (covers FormRequest with `rules(): RuleSet`, action methods, service-class methods).
- Skip-log emit on non-literal `RuleSet::from($injected)` argument matching the wording used by `GroupWildcardRulesToEachRector`.

### Changed
- The `RuleSet::from(...)` wrapper and chained methods are preserved verbatim — only the inner Array_ argument is rewritten.

## 1.0.0 - 2026-04-29

### Changed
- Stable release. Public API surface in `PUBLIC_API.md` is committed under SemVer 2.0. Tag-only release; no behavior change vs 0.22.3.
- Compatibility: PHP 8.3/8.4, Laravel 11/12/13, `sandermuller/laravel-fluent-validation` ^1.20, Rector 2.x.

### Frozen surface
- 12 rector class FQNs under `SanderMuller\FluentValidationRector\Rector\`.
- 6 set list constants on `FluentValidationSetList` (`ALL`, `CONVERT`, `GROUP`, `TRAITS`, `POLISH`, `SIMPLIFY`).
- Configuration constant names + their string wire keys on configurable rectors.
- Typed-config DTO builders under `Config\` and shared value objects under `Config\Shared\` (`AllowlistedFactories`, `BaseClassRegistry`, `OverlapBehavior`).
- `FLUENT_VALIDATION_RECTOR_VERBOSE` env var name and accepted values (`off`, `actionable`, `all`).
- Skip-log file paths, line format, header shape; trait FQNs referenced by the trait-add rectors.

## 0.22.3 - 2026-04-29

### Changed
- README: `UpdateRulesReturnTypeDocblockRector` config gains a per-rector clarifier; DTO worked example leads with the shared-instance pattern (`AllowlistedFactories` extracted to local, fed to both `SimplifyRuleWrappersRector` and `UpdateRulesReturnTypeDocblockRector`).
- Cross-rector callout reinforces silent-partial-config framing alongside lockstep-update narrative.
- Boost skill (`resources/boost/skills/fluent-validation-rector/SKILL.md`) reframed for consumer use; new "Cross-rector configuration" section.
- `PUBLIC_API.md` adds a constant-reference clarifier paragraph after `ConvertLivewireRuleAttributeRector` example, recommending the self-documenting form.

## 0.22.2 - 2026-04-28

### Fixed
- `class_exists` pre-filter applied at two more sites: `InlineMessageSurface::TYPED_RULE_CLASSES` reflection iteration and `PromoteFieldFactoryRector::TYPED_BUILDER_TO_FACTORY`.

### Changed
- `tests/RectorInternalContractsTest.php` retires site-specific `testFactoryBaselineClassesAllResolve` for a registry-driven `HARDCODED_CLASS_TABLES` sweep (`testEveryHardcodedClassTableResolves`, `testRectorClassesWithHardcodedTablesBootCleanly`).
- `specs/PROCESS.md` candidate-pen gains a runtime-simulation methodology entry and three epistemic anchors.

## 0.22.1 - 2026-04-28

### Fixed
- `SimplifyRuleWrappersRector::bootResolutionTables()` filters `FACTORY_BASELINE` via `array_filter(..., 'class_exists')` at merge point so reflection iteration degrades gracefully when a BASELINE class isn't shipped by the installed sister-package version.

### Changed
- `InlineMessageParamRector::RULE_OBJECT_KEY_OVERRIDES` and `PASSWORD_L11_L12_SKIP_TEMPLATE` constants tagged `@internal`.
- `PUBLIC_API.md` documents per-rector configuration semantics explicitly and adds CI-gating guidance against gating on skip-log entry counts.
- New `tests/PublicConstAuditTest.php` mechanically enforces the tag-or-document rule for public consts.
- New `testFactoryBaselineClassesAllResolve` and `testSimplifyRuleWrappersRectorBootsCleanly` fixtures pin the `FACTORY_BASELINE` correctness invariant.
- `specs/PROCESS.md` candidate-pen section opens with two epistemic anchors and registers the cross-package constraint pre-flight finding.

## 0.22.0 - 2026-04-28

### Removed
- Root-namespace `Diagnostics` / `RunSummary` shims (added 0.20.0). Use `SanderMuller\FluentValidationRector\Internal\Diagnostics` / `Internal\RunSummary` instead — both `@internal`.

### Deprecated
- `LEGACY_FQN_STANDARD_RULES_ANNOTATION_BODY` recognition path in `NormalizesRulesDocblock`. Behavior preserved through 1.x; removal slated for 2.0.

### Changed
- `PUBLIC_API.md` adds a heuristic-boundaries section explicitly carving out detection rules, classification confidence, trace depth, skip-text reason content, fixture coverage, and diagnostic granularity as NOT SemVer-committed.
- `FLUENT_VALIDATION_RECTOR_VERBOSE` documents three canonical values — `off`, `actionable`, `all` — with `1` / `true` as legacy synonyms preserved indefinitely.
- Cold-consumer fixture inspection uses anchored GitHub links to `tests/Parity/Fixture/` etc. instead of clone instructions.
- `Diagnostics::skipLogHeader()` docblock corrected; new `tests/SkipLogHeaderTest` pins the `Composer\InstalledVersions::getPrettyVersion()` resolution path.

## 0.21.1 - 2026-04-28

### Fixed
- Class-wide skip-log line resolves to the `class Foo` declaration line via `Class_::$name->getStartLine()` instead of the `Class_` node's start line (which was the first attached attribute / docblock / use-import).

## 0.21.0 - 2026-04-28

### Changed
- Unsafe-parent heuristic narrowed via depth-1 alias data-flow trace: array op must operate on a `parent::*()` return value (direct, Variable assignment, or ArrayDimFetch receiver). Filament Page subclasses and Livewire wrapper components no longer false-positive on unrelated `parent::canAccess()` + `array_map($users)` shapes.

### Added
- Skip-log line format gains optional `:<line>` suffix on the file path. Per-key/per-item skips emit the offending node's `getStartLine()`; class-wide skips emit the `class Foo` declaration line; name-only sites omit the suffix. IDE click-to-open works on PhpStorm, VS Code terminal, vim quickfix, iTerm2.

## 0.20.2 - 2026-04-28

### Fixed
- Unsafe-parent skip suppressed on classes with no rules-bearing surface (no `rules()` method, no `#[FluentRules]`-attributed method, no auto-detect-qualified rules-shaped method).
- `STANDARD_RULES_ANNOTATION_BODY` in `NormalizesRulesDocblock` now emits short-name `array<string, ValidationRule|string|array<mixed>>` and queues a `Illuminate\Contracts\Validation\ValidationRule` use import via new `queueValidationRuleUseImport()` hook.

### Changed
- `ConvertLivewireRuleAttributeRector` overlap skip message appends `→ see PUBLIC_API.md#convertlivewireruleattributerector`. PUBLIC_API section includes the canonical `withConfiguredRule(... => [KEY_OVERLAP_BEHAVIOR => OVERLAP_BEHAVIOR_PARTIAL])` shape.
- New "Inspecting test fixtures" section in `PUBLIC_API.md` points at `tests/Parity/Fixture/`.

## 0.20.1 - 2026-04-28

### Fixed
- `ConvertsValidationRuleStrings::collectUnsafeParentClass` now skips Closure / ArrowFunction / nested Function_ / ClassLike boundaries; closes false positive on `parent::rules()->modify(self::FOO, function (ArrayRule $r) { ... })` shapes.

### Changed
- Unsafe-parent fallback message rewritten to a single honest line about the heuristic's scan-tier limitation (replaces 3-sentence misleading "re-run with apply" advice).
- `ConvertLivewireRuleAttributeRector` overlap-skip message rewritten to hedge certainty and name the `KEY_OVERLAP_BEHAVIOR=partial` config knob inline.

## 0.20.0 - 2026-04-28

### Changed
- `Diagnostics` and `RunSummary` moved to `SanderMuller\FluentValidationRector\Internal\` namespace. `class_alias` shims at old locations marked `@deprecated since 0.20.0`; removal slated for 1.0.
- Skip-log message rewrites: parent-factory bails split per `each()` vs `children()` case; concat-key bails classified per failure shape via `describeConcatKeyFailure`; unsafe-parent skip names offender (descendant FQCN, method name, matched op).
- Unsafe-parent detector adds `Illuminate\Support\Arr` static-call helpers (`Arr::except`, `Arr::only`, `Arr::add`, `Arr::forget`, `Arr::set`, `Arr::pull`).

### Added
- `tests/InternalAuditTest::testEveryClassUnderInternalNamespaceIsThereByNamespace` asserts every class under `src/Internal/` declares an `Internal\`-prefixed namespace.
- `/pre-release` skill step 5c PUBLIC_API audit; `.ai/guidelines/public-api-discipline.md`.
- `PUBLIC_API.md` namespace-structure section carves out Public, Public-scoped, and Internal tiers.

## 0.19.1 - 2026-04-27

### Fixed
- `RuleSet::from([...])` return shape now folds: rector descends into the inner Array_ argument while preserving the wrapper. New `=actionable` skip-log reasons for non-literal `RuleSet::from()` argument and branched `rules()` body.
- `UpdateRulesReturnTypeDocblockRector` predicate widened to accept both short-name and FQN `FluentRule` forms; post-fold output narrows to `array<string, FluentRuleContract>` end-to-end with `ALL` + `POLISH` loaded.
- Recursive `Return_` traversal in `GroupWildcardRulesToEachRector` anchored on direct-child `Return_` only, with scope-local traversal that skips `FunctionLike` + `ClassLike`.
- Unified ambiguity guard moved ahead of branch-specific paths so mixed `if () return [...]; return RuleSet::from([...]);` bodies bail uniformly.

## 0.19.0 - 2026-04-27

### Added
- `GroupWildcardRulesToEachRector` recognizes wildcard-prefix concat keys: sibling `'*.' . CONST_NAME => rule()` entries fold into `'*' => FluentRule::array()->children([CONST => ...])`, preserving the suffix `ClassConstFetch` verbatim as the children-array key.
- Single-entry fold and custom rule values (`->rule(new ColorRule())`) preserved verbatim.

### Fixed
- Mixed literal-keyed + const-keyed `'*.'` groups: const branch now correctly bails when a literal-keyed parent already exists (was last-write-wins silently dropping a branch).

### Changed
- CI: composer cache (`actions/cache`) added to `run-tests.yml`. `pest --parallel` attempted then reverted (paratest workers re-bootstrap autoloader and conflict on `nikic/php-parser`).

## 0.18.0 - 2026-04-27

### Added
- New `Options::with(AllowlistedFactories|BaseClassRegistry): self` named constructor on `RuleWrapperSimplifyOptions`, `DocblockNarrowOptions`, `HasFluentRulesTraitOptions`. Reads more naturally than `default()->withFoo(...)` for explicit non-default builds.

### Changed
- README §Typed configuration: cross-rector consolidation via shared `$allowlist` is now the canonical multi-rector form.
- README §Formatter integration documents the rector emit as not formatter-clean by design; recommended pipeline is `vendor/bin/rector process && vendor/bin/pint --dirty`.
- New `tests/UpdateRulesReturnTypeDocblock/Fixture/narrows_after_groupwildcard_fold.php.inc` fixture pins post-fold docblock-narrow on `'*' => FluentRule::array()->each(...)` shapes.

## 0.17.1 - 2026-04-27

### Fixed
- `GroupWildcardRulesToEachRector` now bails on Validator subclasses qualifying solely via `#[FluentRules]` whose parent class postprocesses `rulesWithoutPrefix()` output. New `qualifiesForShapeChange(Class_): bool` predicate in `Concerns\QualifiesForRulesProcessing` returns true only for class-wide-qualifying signals (FormRequest ancestry, fluent-validation trait, Livewire component); excludes attribute-only qualifying classes.

### Changed
- README ships a "What `#[FluentRules]` does NOT do" subsection documenting the three guards the attribute does NOT lift.

## 0.17.0 - 2026-04-27

### Added
- Typed configuration DTO builders under `SanderMuller\FluentValidationRector\Config\`: `LivewireConvertOptions`, `RuleWrapperSimplifyOptions`, `DocblockNarrowOptions`, `HasFluentRulesTraitOptions`. Each terminates in `->toArray()`.
- Shared types under `Config\Shared\`: `OverlapBehavior` (backed enum: `Bail`, `Partial`), `AllowlistedFactories` (class FQNs, wildcard patterns, `[Class, methodName]` tuples), `BaseClassRegistry`.
- Magic-constant array configs continue to work unchanged. `AllowlistedFactories` shared by `RuleWrapperSimplifyOptions` + `DocblockNarrowOptions` keeps simplify and docblock rectors in lockstep.

## 0.16.0 - 2026-04-27

### Added
- `#[FluentRules]` opt-in unlocks two previously-skipped class shapes: abstract classes with `rules()` (attribute on `rules()` is the user's audit assertion that subclasses don't manipulate `parent::rules()` as a plain array) and custom Validator subclasses (e.g. `JsonImportValidator extends FluentValidator`).
- 3-layer denylist guard for `#[FluentRules]` on denylisted method names (`casts()`, `messages()`, `attributes()`, `toArray()`, `jsonSerialize()`, etc.): qualification gate, mistake warning (one skip-log entry per class deduped by FQCN), per-method conversion gate. Single denylist on new `Concerns\NonRulesMethodNames` trait used by all three call sites.
- README ships a "Opting in: #[FluentRules] attribute" section.
- Three new fixtures under `tests/Parity/Fixture/Attributed/`. `CoverageTest` extended.

### Fixed
- Abstract-with-rules guard tightened from `hasRulesMethod()` to `hasLiteralRulesMethod()` so the misleading "Add `#[FluentRules]` to the rules() method" log no longer fires when no literal `rules()` method exists.

## 0.15.0 - 2026-04-26

### Added
- `PUBLIC_API.md` enumerates 12 rector class FQNs, 6 `FluentValidationSetList` constants, 10 rector configuration constant names + string values, `FLUENT_VALIDATION_RECTOR_VERBOSE` env var + accepted values, both skip-log file paths, the skip-log line format and header shape, and trait FQNs.
- README §Versioning policy section.
- `@internal` PHPDoc on `SanderMuller\FluentValidationRector\Diagnostics` and `RunSummary`.
- Validation parity harness under `tests/Parity/` runs Laravel's validator over pre- and post-rector rule shapes; in scope: `SimplifyRuleWrappersRector`, `GroupWildcardRulesToEachRector`, `PromoteFieldFactoryRector`. 14 fixtures shipped. Coverage gate asserts every in-scope rector ships ≥1 parity fixture.
- Cross-Laravel-version CI matrix: 11.x / 12.x / 13.x with matching `orchestra/testbench` (9.x / 10.x / 11.x). 24-cell matrix (2 OS × 2 PHP × 2 stability × 3 Laravel). `tests/CIMatrixSanityTest.php` enforces matrix-leg drift detection.
- `composer.json` `orchestra/testbench` constraint widened to `^9.0||^10.11||^11.0`.
- New tests: `InternalAuditTest`, `PublicApiSurfaceTest`, `ParityHarnessTest`, `DivergenceCategoryTest`, `ParityTest`.
- Governance scaffolding: `CONTRIBUTING.md`, `SECURITY.md`, GitHub issue + PR templates.
- `phpstan.neon.dist` pins `featureToggles.internalTag: true`.

### Changed
- Namespace move to `SanderMuller\FluentValidationRector\Internal\` deferred to 1.0 (Composer `--classmap-authoritative` would fatal on stale optimized classmap).

## 0.14.1 - 2026-04-26

### Fixed
- `AddHasFluentRulesTrait` now requires FormRequest ancestry before inserting `HasFluentRules` (its `createDefaultValidator` override is dead code on Controllers, Actions, Nova resources, tests, etc.). Configured `base_classes` still get the trait.
- `AddHasFluentValidationTrait` now requires Livewire-side validation surface (Filament trait, `rules()` method, `#[FluentRules]`-attributed method, `#[Validate]`/`#[Rule]` property, or `$this->validate(...)` / `validateOnly(...)` call body) before inserting `HasFluentValidation` / `HasFluentValidationForFilament`.
- `[#2]` `SEMANTIC_DIVERGENCE_HINTS` no longer fires on `FluentRule::field()->rule(...)` receivers for `accepted` / `declined`.
- `[#3]` Skip log moved from `<cwd>/.rector-fluent-validation-skips.log` to `<cwd>/.cache/rector-fluent-validation-skips.log`. Auto-creates `.cache/`; falls back to cwd on read-only mounts. Legacy paths cleaned up by `unlinkLogArtifacts()`.
- `[#4]` Skip log gains a per-run header with package version (via `Composer\InstalledVersions`), ISO-8601 UTC timestamp, and verbose tier; always emitted under verbose mode regardless of entry count.
- `UpdateRulesReturnTypeDocblock` emits short `FluentRuleContract` name and queues import via `UseNodesToAddCollector` (was emitting FQN inline).
- Blank `*`-only PHPDoc separator lines preserved across rewrites.

### Added
- 13 regression fixtures across 8 false-positive shapes + 5 positive Livewire surfaces.
- Livewire 4 contract fixture for empty `#[Validate]` markers under `OVERLAP_BEHAVIOR_PARTIAL`.

## 0.14.0 - 2026-04-26

### Added
- Three new discovery surfaces: `Validator::validate(...)` static call (same arg layout as `Validator::make`), global `validator(...)` helper (conservative — bails on userland shadows and `use function` imports), and auto-detect rules-shaped methods on qualifying classes (`rules()`, `editorRules()`, `rulesWithoutPrefix()`, etc. with single `return [...]` / literal-string keys / rule-shaped values).
- Class-qualification gate (FormRequest ancestry, `HasFluentRules` / `HasFluentValidation` / `HasFluentValidationForFilament`, Livewire component, or `#[FluentRules]`-attributed method); class-wide auto-detection gated on the first three.
- Method-name denylist for auto-detect: `casts()`, `getCasts()`, `getDates()`, `attributes()`, `validationAttributes()`, `messages()`, `validationMessages()`, `middleware()`, `getRouteKeyName()`, `broadcastOn()`, `broadcastWith()`, `toArray()`, `toJson()`, `jsonSerialize()` (case-insensitive). `ClassConstFetch` keys and multi-statement bodies skip auto-detect.
- Parent-safety guard widened: scans every method on the child for `parent::*()` + array manipulation; recognises `unset($rules['x'])` as `Stmt\Unset_` and `collect()` as a manipulation primitive; multi-class file resolution returns every parent FQCN; alias-aware `use ... as` matching.
- `AddHasFluentRulesTraitRector` and `AddHasFluentValidationTraitRector` now walk every method (not just `rules()`) for FluentRule usage.

## 0.13.3 - 2026-04-25

### Changed
- `GroupWildcardRulesToEachRector` emits specific skip-log reasons under `=actionable` for 5 decision-point bails that previously returned `null` silently (non-FluentRule entries, parent factory missing each/children, type-specific rules in wildcard parent, double-wildcard suffix, complex concat key).

## 0.13.2 - 2026-04-25

### Changed
- `SimplifyRuleWrappersRector` skip log emits an enriched message when bail target is `accepted` or `declined` on a `FieldRule` receiver, naming the boolean implicit-constraint divergence and the `field()->rule('accepted')` escape hatch.
- New `SEMANTIC_DIVERGENCE_HINTS` const on `SimplifyRuleWrappersRector` keyed by method name; checked after polymorphic-verb hint inside the FieldRule-receiver bail.
- README's `PromoteFieldFactoryRector` paragraph lists the accepted/declined bail.

## 0.13.1 - 2026-04-24

### Fixed
- `PromoteFieldFactoryRector` no longer auto-promotes `FluentRule::field()->rule('accepted')` / `'declined'` to `FluentRule::boolean()->accepted()` — boolean's implicit constraint rejects `'yes'`/`'on'`/`'true'` (HTML checkbox defaults) which `accepted` permits. Extends the existing `SEMANTICALLY_DIVERGENT_PROMOTION` pattern.
- `FluentRule::boolean()->rule('accepted')` (user explicitly chose `boolean()`) still rewrites via `SimplifyRuleWrappersRector` zero-arg token rewrite.
- `SimplifyRuleWrappersRector::TREAT_AS_FLUENT_COMPATIBLE` and `::ALLOW_CHAIN_TAIL_ON_ALLOWLISTED` (plus same two on `UpdateRulesReturnTypeDocblockRector`) re-declared directly on each rector to silence `intelephense` / PHPStan "undefined class constant" diagnostics on inherited trait constants.

## 0.13.0 - 2026-04-24

### Added
- `InlineResolvableParentRulesRector` variable-spread resolver: inlines `...$base` when `$base` is the method's only top-level assignment and its RHS is a literal array or `parent::rules()`. Strips dead `$base = ...;` after inline. Bails on peer top-level assignments, nested-scope assignments, multi-use variables, `unset($base)`.
- `ConvertLivewireRuleAttributeRector` `KEY_OVERLAP_BEHAVIOR` config: `'bail'` (default) preserves classwide skip; `'partial'` converts attrs whose predicted emit keys don't appear in any explicit `validate([...])` array. Extraction-unsafe wrappers force classwide bail under both. Keyed-array attrs check internal keys (not property name); named `rules:` arg takes precedence over positional index.
- `PromoteFieldFactoryRector` Password/Email promotion: `FluentRule::string()->rule(Password::default())` / `Email::default()` promotes to `FluentRule::password()` / `::email()`. Gates: zero-arg source factory, single match, no Conditionable hops, target class has every modifier method used. Guards against label-rebinding hazard and colliding signatures (`min(int, ?string)` vs `min(int)`).
- `SimplifyRuleWrappersRector` zero-arg string-token rewrites: `accepted`, `declined`, `present`, `prohibited`, `nullable`, `sometimes`, `required`, `filled` → native methods.
- Conditionable proxy walk: `->when($cond, fn ($r) => $r->rule('...'))` no longer bails when closure body is bare-return / no-return / identity.
- Consumer-declared allowlist via `TREAT_AS_FLUENT_COMPATIBLE` + `ALLOW_CHAIN_TAIL_ON_ALLOWLISTED` (default off). Glob patterns: exact / `*` (single segment) / `**` (recursive).

## 0.12.1 - 2026-04-24

### Fixed
- `UpdateRulesReturnTypeDocblockRector` now narrows `@return array<string, mixed>` (Laravel's IDE-scaffolded default) and recognizes `array<string, FluentRuleContract>` short-name form as already-narrowed.
- `AddHasFluentRulesTraitRector` Livewire-detect skip-log message downgraded to `verboseOnly: true`.

### Changed
- `SimplifyRuleWrappersRector` polymorphic-verb skip line now lists candidate factory names (`FluentRule::string()` / `numeric()` / `array()` / `file()`) for `min` / `max` / `between` / `exactly` / `gt` / `gte` / `lt` / `lte`.
- Internal: `GroupWildcardRulesToEachRector::groupRulesArray()` extracted `indexRuleItem()`; class cognitive complexity drops from 198 to 174. `ConvertsValidationRuleStrings::collectUnsafeParentClassesFromFile` split into `collectExtendingClassesFromFile` + `extractExtendingClasses`.

## 0.12.0 - 2026-04-24

### Added
- `InlineResolvableParentRulesRector` (in `CONVERT`): inlines `parent::rules()` spread at index 0 of a child `rules()` when parent is a plain `return [...];`. Bails on `array_merge`, `+`, method calls on return, multi-statement bodies. Cached by `path + mtime` per invocation.
- `PromoteFieldFactoryRector` (in `SIMPLIFY`): promotes `FluentRule::field()` to `::string()` / `::numeric()` / `::array()` etc. when every `->rule(...)` parses to a v1-scope rule whose target lives on exactly one typed FluentRule subclass. Bails on Conditionable hops, ambiguous intersections, and unconstrained payloads. `V1_REWRITE_TARGETS` and `RULE_NAME_TO_METHOD` consts on `SimplifyRuleWrappersRector` flipped from `private` to `public`.

### Changed
- `SimplifyRuleWrappersRector` verbose-only "rule payload not statically resolvable" skip now includes the rejected payload's AST class and a truncated pretty-print.

## 0.11.0 - 2026-04-24

### Fixed
- Livewire-class detection no longer misfires on `render()`-method carriers (Exceptions, DataObjects, Action classes, Blade view components, Filament `Page` subclasses, Nova tools). `IdentifiesLivewireClasses::isLivewireClass()` walks the parent chain via `ReflectionClass` looking for `Livewire\Component` or `Livewire\Form`, and treats direct `HasFluentValidation` / `HasFluentValidationForFilament` use as a Livewire signal.

### Changed
- Default skip log only contains actionable entries. Demoted to verbose-only: abstract-class skip (gated on `hasMethod('rules')`), already-has-trait, ancestor-uses-trait, configured-base-class, user-customized `@return` tag, "rule payload not statically resolvable", `<method>() not on <ShortClass>`.
- Livewire components with string-form `rules()` are now flagged with a specific actionable hint pointing at `ValidationStringToFluentRuleRector`.

### Added
- `->rule(['required_array_keys', ...])` lowers to `->requiredArrayKeys(...)` on `ArrayRule`.

## 0.10.1 - 2026-04-23

### Added
- `ValidationArrayToFluentRuleRector` converts rules whose tuples contain dynamic expressions (`Ternary`, `MethodCall`, `FuncCall`, `PropertyFetch` on `$this`, `NullsafePropertyFetch`, `ArrayDimFetch`, `Cast`, `Match_`, `StaticCall`). Applied to fluent-method lowering and `->rule([...])` escape-hatch paths.
- `SimplifyRuleWrappersRector` lowers `->rule(['required_if', ...])` and 18 other conditional/pure-variadic forms to native fluent methods when every tuple arg is statically scalar. Bare BackedEnum cases auto-wrapped with `->value`.

### Changed
- COMMA_SEPARATED conditional rules keep the strict whitelist; dynamic tuples fall through to `->rule([...])` escape hatch instead of `->requiredIf(...)`.
- Non-scalar producers (`New_`, `Clone_`, `Closure`, `Array_`, `Yield_`, `Throw_`, `Include_`, `Eval_`, side-effectful mutators, `Concat` containing any of these) still bail.

## 0.10.0 - 2026-04-23

### Added
- `ValidationArrayToFluentRuleRector` accepts `Enum::CASE->value` (PropertyFetch on ClassConstFetch) as a conditional arg.
- In-tuple variadic spread (`...Enum::list()`, `...$values`) preserved on field+variadic and pure-variadic conditional rules.

## 0.9.0 - 2026-04-22

### Added
- New `InlineMessageParamRector` in opt-in `SIMPLIFY` set. Three rewrite predicates: factory-direct (`->message()` immediately on factory), rule-method matched-key `messageFor`, rule-object `messageFor`. Collapses `->message()` / `->messageFor()` chains into inline `message:` named parameter against the `laravel-fluent-validation` 1.20.0 surface.
- Skip-log taxonomy in 6 categories: variadic-trailing methods, composite methods, mode-modifier methods, deferred-key factories, L11/L12-divergent `Password`, no-implicit-constraint factories.
- Literal-only rewrite guard (only `String_` literal or flat `Concat` chain of literals); named-arg always; reflection-based surface discovery via `InlineMessageSurface::load()`; composer floor guard.

### Changed
- Composer floor `sandermuller/laravel-fluent-validation`: `^1.19` → `^1.20`.

## 0.8.1 - 2026-04-22

### Added
- `ValidationEquivalenceTest` runtime parity harness pins string-form ↔ fluent-form equivalence across `setAttributeNames([...])`, `setCustomMessages([...])`, and lang-file overrides. 27 new data rows (43 total, 123 assertions).
- `rector-developer` skill expanded with "Preventing Duplicate Attributes" and "Reducing Rule Risk" patterns; missing frontmatter block added.

## 0.8.0 - 2026-04-22

### Added
- `ConvertLivewireRuleAttributeRector` `MIGRATE_MESSAGES` config flag (default `false`): migrates `message:` attribute args into a generated `messages(): array` method. String form keys by property name; array form keys with `<prop>.<rule>` pattern. Preflight bails on non-trivial existing `messages()` shapes.
- 9 new tokens recognized as direct factory shortcuts in converters: `'ipv4'`, `'ipv6'`, `'mac_address'`, `'json'`, `'timezone'`, `'hex_color'`, `'active_url'`, `'list'`, `'declined'`. Sibling-token promotion: `'string|ipv4'` → `FluentRule::ipv4()` (not `string()->ipv4()`).
- `SimplifyFluentRuleRector` factory-shortcut collapse for the 9 new factories plus `regex` and `enum` (arg-passthrough). Conservative arg-passthrough gate (no `label()` call). Redundant-call removal extended.
- `SimplifyRuleWrappersRector` `enum` rewrite: `->rule(Rule::enum(X::class))` / `->rule(['enum', X::class])` → `->enum(X::class)` on 5 `HasEmbeddedRules` consumers. Literal-zero comparisons: `'gt:0'` → `->positive()`, `'gte:0'` → `->nonNegative()`, `'lt:0'` → `->negative()`, `'lte:0'` → `->nonPositive()` (NumericRule only).

### Changed
- Composer floor `sandermuller/laravel-fluent-validation`: `^1.18` → `^1.19`.

## 0.7.0 - 2026-04-21

### Added
- `SimplifyRuleWrappersRector` (under `SIMPLIFY`): rewrites escape-hatch `->rule(...)` calls into native fluent methods on the correct typed-rule subclass. Covers `in` / `notIn` / `min` / `max` / `between` / `size` (renamed to `exactly`) / `regex` for accepted receivers (String, Numeric, Email, Field, Date, Array, File, Password where applicable).
- `SIMPLIFY` registers `SimplifyRuleWrappersRector` after `SimplifyFluentRuleRector`. `ALL` does not include `SIMPLIFY`.

## 0.6.1 - 2026-04-20

### Fixed
- `UpdateRulesReturnTypeDocblockRector` now narrows `@return` annotations referencing Laravel validation contracts (`ValidationRule`, `DataAwareRule`, `ValidatorAwareRule`, `ImplicitRule`, `Rule`) in both generic-array and `T[]`-shorthand forms.
- `Concat`-keyed array items (`'credentials.' . Class::CONST => ...`) now recognized via recursive walk accepting `String_` / `ClassConstFetch` leaves.
- Skip-log reason for spread items now reads "encountered spread at index N" instead of misleading "key is not String_ / ClassConstFetch".

### Added
- 4 new fixtures + updated `skip_user_annotation`. 50 fixtures total (22 emit + 28 skip).

## 0.6.0 - 2026-04-20

### Added
- New opt-in polish rule `UpdateRulesReturnTypeDocblockRector`: narrows `rules()` `@return` from `array<string, ValidationRule|string|array<mixed>>` to `array<string, FluentRuleContract>` when every value is a `FluentRule::*()` chain. Five qualification gates (method shape, return shape, item shape, existing-annotation respect, class context). Ships behind new `FluentValidationSetList::POLISH` set; not in `ALL`.
- New helpers on `Concerns\DetectsInheritedTraits`: `anyAncestorExtends(Class_, string): bool` (alias-safe), `currentOrAncestorUsesTrait(Class_, string): bool`.
- `STANDARD_RULES_ANNOTATION_BODY` and `RETURN_TAG_PATTERN` flipped from `private` to `protected` on `NormalizesRulesDocblock`. New helper `annotationBodyMatchesStandardUnionExactlyOrProse(string): bool`.

### Changed
- Composer requirement bumped: `sandermuller/laravel-fluent-validation` `^1.8.1` → `^1.17`.

## 0.5.3 - 2026-04-17

### Added
- First release shipping the implementation behind 0.5.0–0.5.2 CHANGELOG entries (which were CHANGELOG-only commits). Surface: `SanderMuller\FluentValidationRector\Diagnostics`, `RunSummary::unlinkLogArtifacts()`, `ConvertLivewireRuleAttributeRector` lowers `new Password($n)`, `new Unique(...)`, `new Exists(...)` in `#[Validate([...])]`.
- Pinning `^0.5.3` is required to actually receive the documented 0.5.x changes.

## 0.5.2 - 2026-04-17

### Added
- `new Password(...)` / `new Unique(...)` / `new Exists(...)` in attribute context lower to `FluentRule::password($n)` / `->unique(...)` / `->exists(...)`. Non-attribute `rules()` arrays still route to the `->rule(new X(...))` escape hatch.

### Fixed
- Skip-log cleanup now sweeps both verbose (cwd) and off-mode (tmp) paths with their sentinels on every parent-init pass; legacy 0.4.x logs disappear automatically.
- `RunSummary::format()` made side-effect-free; cleanup moved to shutdown closure; end-of-run hint includes `--clear-cache`.

## 0.5.1 - 2026-04-17

### Added
- Constructor-form rule objects in Livewire `#[Validate([...])]` attributes lower to fluent factory chains; non-attribute `rules()` arrays unchanged.

## 0.5.0 - 2026-04-17

### Changed
- Skip log default flipped: off by default, opt-in via `FLUENT_VALIDATION_RECTOR_VERBOSE=1`. Default runs still count skips; default end-of-run summary points at the env var. Verbose runs land the log at `.rector-fluent-validation-skips.log` as before.

## 0.4.19 - 2026-04-15

### Fixed
- Keyed-array `#[Validate]` (Livewire v3) now expands per-key into `rules()` (was silently collapsing all values into one chain, dropping `.*` wildcard scoping).
- `#[Validate]` empty marker preserved after conversion to keep `wire:model.live` real-time validation. Aggregate veto on `#[Validate(onUpdate: false)]`.
- `as:` and `attribute:` recognised as `->label()` synonyms; `attribute:` wins on conflict.
- `messages:` (plural) reclassified as "unrecognized arg; likely typo for message:?"; `translate: false` added to dropped list; array-form `message:` gets dedicated "deferred to messages() method generation" log.

### Added
- New config: `ConvertLivewireRuleAttributeRector::PRESERVE_REALTIME_VALIDATION` (bool, default `true`); `AddHasFluentRulesTraitRector::BASE_CLASSES` (list of strings) documented in README.
- 6 shared concerns extracted: `ExpandsKeyedAttributeArrays`, `ExtractsLivewireAttributeLabels`, `ReportsLivewireAttributeArgs`, `ResolvesRealtimeValidationMarker`, `ResolvesInheritedRulesVisibility`, `DetectsLivewireRuleAttributes`.

## 0.4.18 - 2026-04-15

### Fixed
- Body-mutation rectors (`ConvertLivewireRuleAttributeRector`, `ValidationArrayToFluentRuleRector`, `ValidationStringToFluentRuleRector`, `GroupWildcardRulesToEachRector`) normalize stale `@return array<string, StringRule>`-style docblocks to `array<string, ValidationRule|string|array<mixed>>`. Broad/unrelated annotations preserved.
- `ConvertLivewireRuleAttributeRector` hybrid-bail now silent on classes without Livewire rule attributes (fired spuriously on Actions, FormRequests, Controllers, DataObjects with unrelated `validate()` calls).

### Removed
- `vendor/bin/fluent-validation-migrate` regex CLI (corrupted multi-trait `use X, Y, Z { ... }` blocks). `bin` entry dropped from `composer.json`. Hand-fix recipe documented in README Known Limitations.

### Changed
- Three extracted concerns: `DetectsLivewireRuleAttributes`, `IdentifiesLivewireClasses`, `NormalizesRulesDocblock`.

## 0.4.17 - 2026-04-15

### Fixed
- Stale `@return` docblock normalization for body-mutation rectors (same scope as 0.4.18 — 0.4.17 ships the same fixes; both tags are present).
- Hybrid-bail silenced on non-candidate classes.

### Removed
- `vendor/bin/fluent-validation-migrate` removed.

### Changed
- Three extracted concerns added.

## 0.4.16 - 2026-04-15

### Added
- `AddHasFluentValidationTraitRector` on a Livewire component that directly uses a Filament form trait (`InteractsWithForms` / `InteractsWithSchemas`) emits the 4-method `insteadof` block (`validate`, `validateOnly`, `getRules`, `getValidationAttributes`) automatically. `getMessages` intentionally absent (no Filament collision).
- `vendor/bin/fluent-validation-migrate` standalone source-text migrator for `1.7.x → 1.8.1` upgrade. Operates on file bytes only — no class autoload, no Rector, no PhpParser. Idempotent. Default path `app/`; supports `--dry-run` and custom paths.

### Changed
- Ancestor-only Filament now skip-logs (was attempting auto-composition); points at adding `HasFluentValidationForFilament` directly on the concrete subclass.
- Conflict guard widened: class declaring `validate()` / `validateOnly()` / `getRules()` / `getValidationAttributes()` directly skip-logs and refuses trait insertion.
- Swap-on-detect for the wrong variant on Filament classes includes the insteadof adaptation.

### Required
- `sandermuller/laravel-fluent-validation ^1.8.1` (1.8.0 not supported — replaced within hours).

## 0.4.15 - 2026-04-15

### Changed
- `AddHasFluentValidationTraitRector` picks the right trait variant for Filament components: plain Livewire → `HasFluentValidation`; Livewire + Filament (`InteractsWithForms` v3/v4 OR `InteractsWithSchemas` v5, directly or via ancestor) → `HasFluentValidationForFilament`.
- Swap-on-detect: wrong variant gets removed and replaced; orphaned top-level `use` import dropped.
- Conflict guard skips trait insertion when class declares colliding public-surface methods.

### Required
- `sandermuller/laravel-fluent-validation ^1.8`.

### Added
- `DetectsFilamentForms` concern centralises Filament-trait substring match + ancestor walk.
- 4 new fixtures under `tests/AddHasFluentValidationTrait/Fixture/`.

## 0.4.14 - 2026-04-15

### Changed
- `ConvertLivewireRuleAttributeRector::installRulesMethod()` emits `@return array<string, ValidationRule|string|array<mixed>>` and queues `Illuminate\Contracts\Validation\ValidationRule` import via Rector's import-names pass. Replaces the semantically-wrong `array<string, FluentRule>` from 0.4.13.

### Changed
- 10 fixtures under `tests/ConvertLivewireRuleAttribute/Fixture/` updated to match new annotation.

## 0.4.13 - 2026-04-14

### Changed
- `ConvertLivewireRuleAttributeRector` `@return` annotation narrowed from three-way union `array<string, FluentRule|string|array<string, mixed>>` to `array<string, FluentRule>`. Covariance-safe with PHPStan-inferred specific subclass types; pre-empts `DocblockReturnArrayFromDirectArrayInstanceRector`.

## 0.4.12 - 2026-04-14

### Fixed
- `ConvertLivewireRuleAttributeRector` `installRulesMethod()` now inheritance-aware via new `resolveGeneratedRulesVisibility()`: ancestor with `final rules()` → bail with skip log (avoids fatal-on-load); ancestor with `public rules()` → emit `public function rules(): array`; ancestor with `protected`/`private` or none → emit `protected`. Walks parent chain via `ReflectionClass` from AST `$class->extends`.
- Visibility check runs BEFORE `extractAndStripRuleAttribute()` so a bail never strips attributes the rector couldn't replace.

### Added
- 2 new fixtures + 2 helper classes under `tests/ConvertLivewireRuleAttribute/FixtureSupport/`.

## 0.4.11 - 2026-04-14

### Changed
- `GroupWildcardRulesToEachRector` no longer skips Livewire components — `sandermuller/laravel-fluent-validation` 1.7.0 `HasFluentValidation::getRules()` flattens nested `each()`/`children()` back to wildcard keys via `RuleSet::flattenRules()`. Removed `isLivewireClass()` check, `LIVEWIRE_CLASSES` const, and Livewire skip-log message.
- Composer constraint bumped to `sandermuller/laravel-fluent-validation: ^1.7.1`.

### Changed
- `skip_livewire*.php.inc` fixtures converted to `group_livewire_*.php.inc` exercising nested-each form.

## 0.4.10 - 2026-04-14

### Fixed
- Run summary no longer depends on `rector/extension-installer`. Registration moved into each of the 7 rule constructors via `RunSummary::registerShutdownHandler()`. `config/config.php` registration retained as belt-and-suspenders.
- New `isRectorInvocation()` second gate (basename check against `$_SERVER['argv'][0]`) prevents the summary from emitting under pest / phpunit / phpstan / composer / IDE inspection.

### Changed
- Tests reorganized into per-rule folder convention (`tests/<RuleName>/{<RuleName>RectorTest.php, Fixture/, config/}`). Skip fixtures renamed `bail_*` / `*_bails` → `skip_*`.

## 0.4.9 - 2026-04-13

### Added
- Run-summary STDOUT line at end of each Rector invocation when skip log is non-empty: `[fluent-validation] N skip entries written to .rector-fluent-validation-skips.log — see for details`. Implemented via shutdown function from `config/config.php`. Gated on parent process (no `--identifier` in `$_SERVER['argv']`); writes to STDOUT; idempotent.
- Public API: `SanderMuller\FluentValidationRector\RunSummary::registerShutdownHandler()` and `format(): ?string`.

## 0.4.8 - 2026-04-13

### Added
- `tests/ValidationEquivalenceTest.php`: 16 parametrized cases running Laravel's validator over invalid input with both string-form rules and equivalent FluentRule builder; asserts identical error messages. Uses Orchestra Testbench for container + facade bootstrap.
- `tests/RectorInternalContractsTest.php::testNewlinedArrayPrintConstantExists` regression guard against Rector `AttributeKey::NEWLINED_ARRAY_PRINT` constant churn.

### Changed
- `ManagesTraitInsertion::resolveSortedTraitInsertPosition()` inserts new class-body trait at the alphabetically-correct position (parity with the 0.3.0 namespace-import sort behavior).

## 0.4.7 - 2026-04-13

### Changed
- Skip-log noise reduction: `AddHasFluentValidationTraitRector` gates on `isLivewireClass()` first (silent no-op for non-Livewire classes); `AddHasFluentRulesTraitRector` silences "no FluentRule usage in rules() method" bail. Other interesting categories (abstract, already-has-trait, ancestor-trait, validate-conflict, configured base class, isLivewireClass on the FormRequest rector, unsafe parent, attribute-converter skips) untouched.

## 0.4.6 - 2026-04-13

### Added
- `ConvertLivewireRuleAttributeRector` handles array-form attribute rules (`#[Rule(['required', 'string', 'max:255'])]` / `#[Validate(['nullable', 'email'])]`); parity with string-form. `as:` mapping retained. Empty arrays emit specific skip-log entry.
- Rule-object constructors fall through to `->rule(new X(...))` escape hatch (PHP attribute args must be const-expressions).

### Changed
- `ConvertsValidationRules` (1061 lines) split into `ConvertsValidationRuleStrings` and `ConvertsValidationRuleArrays`. `ValidationArrayToFluentRuleRector` drops from 1009 → ~170 lines.

## 0.4.5 - 2026-04-13

### Fixed
- Skip-log `fflush($handle)` before `flock($handle, LOCK_UN)` in `ensureLogSessionFreshness()` closes a data-loss race under `withParallel()` where the session marker sat in PHP's userland stream buffer while another worker re-truncated.
- `@return` docblock emits short alias `FluentRule` pre-Pint (was emitting fully-qualified `\SanderMuller\FluentValidation\FluentRule`).

## 0.4.4 - 2026-04-13

### Fixed
- Skip-log `fflush` before sentinel unlock (data loss under `withParallel()`).
- `@return` docblock emits short `FluentRule` alias pre-Pint.

## 0.4.3 - 2026-04-13

### Fixed
- Skip-log truncation race under `withParallel()`: per-process `static $logFileTruncated` flag replaced with PPID-keyed session sentinel (`.rector-fluent-validation-skips.log.session`) coordinated via `flock(LOCK_EX)`. Non-POSIX falls back to mtime-based staleness heuristic with 300s threshold.
- `validateOnly($field, [rules])` with explicit second-arg rules array now triggers hybrid bail in `ConvertLivewireRuleAttributeRector::hasExplicitValidateCall()`. Plain `validateOnly($field)` keeps converting.
- Generated `rules(): array` method from `ConvertLivewireRuleAttributeRector` pre-emptively gets `@return array<string, FluentRule|string|array<string, mixed>>` to pre-empt rector-preset's loose `array<string, mixed>` inference.
- `.gitignore` updated for `.session` sentinel.

## 0.4.2 - 2026-04-13

### Fixed
- `LogsSkipReasons` now writes each skip line to `.rector-fluent-validation-skips.log` (cwd) via `FILE_APPEND | LOCK_EX` (works across worker processes); STDERR mirroring preserved when `FLUENT_VALIDATION_RECTOR_VERBOSE=1`. Log truncated at first write per Rector run. `.gitignore` entry added.
- `ConvertLivewireRuleAttributeRector` inserts `Nop` statement between previous member and appended `rules(): array` method to prevent Pint's `class_attributes_separation` from always firing.
- Property-type-aware type inference for untyped attribute rule strings (`#[Validate('max:2000')]` on `public string $description` → `FluentRule::string()->max(2000)` instead of `FluentRule::field()->rule('max:2000')`). Maps `string` / `int` / `bool` / `float` / `array`. Nullable types unwrap. Inference only when rule string has no type token.

## 0.4.1 - 2026-04-13

### Fixed
- File-sink skip log under `withParallel()` (same as 0.4.2).
- Blank line before generated `rules(): array` (same as 0.4.2).
- Property-type-aware type inference for untyped rule strings (same as 0.4.2).

## 0.4.0 - 2026-04-13

### Added
- New `ConvertLivewireRuleAttributeRector` migrates `#[Rule]` / `#[Validate]` Livewire validation attributes into a `rules(): array` method using FluentRule chains. Handles short and FQN forms; `as:` named arg becomes `->label()`; multiple properties collect into a single `rules()` method with one item per line; existing simple-return `rules()` is merged into; non-trivial `rules()` bails with skip log; Form components (`extends \Livewire\Form`) work the same.
- Hybrid-class bail: classes with `#[Rule]`/`#[Validate]` AND `$this->validate([...])` skip with a log reason.
- `message:` / `messages:` / `onUpdate:` named attribute args dropped and logged.
- Array-form `#[Rule([...])]` deferred (logs skip).
- `FluentValidationSetList::CONVERT` now includes the new rector.

### Changed
- `convertStringToFluentRule()` moved from `ValidationStringToFluentRuleRector` to `ConvertsValidationRules` trait. `wrapInRuleEscapeHatch()` factored out.

## 0.3.2 - 2026-04-12

### Fixed
- `FluentValidationSetList::ALL` now applies all three rules. Reverted converter node types to `[ClassLike, MethodCall, StaticCall]`; `use FluentRule;` import queued via `UseNodesToAddCollector` / `UseAddingPostRector` instead of sorted insertion. `GroupWildcardRulesToEachRector::getFluentRuleFactory()` and trait rectors' `usesFluentRule()` accept both FQN and short-name forms.
- New `FullPipelineRectorTest` runs `FluentValidationSetList::ALL` end-to-end against a fixture exercising the full chain.

## 0.3.1 - 2026-04-12

### Fixed
- `ConvertsValidationRules::buildFluentRuleFactory()` emits `new Name('FluentRule')` (short) and auto-inserts `use SanderMuller\FluentValidation\FluentRule;` at the alphabetically-sorted position when not already present (mirrors 0.3.0 fix on `GroupWildcardRulesToEachRector`).

### Changed
- `ValidationStringToFluentRuleRector` and `ValidationArrayToFluentRuleRector` register `[Namespace_]` as their node type (was `[ClassLike, MethodCall, StaticCall]`); test configs no longer use `withImportNames()`. Classes without a namespace are now skipped (documented limitation).

## 0.3.0 - 2026-04-12

### Added
- Array-tuple rules lower directly to fluent method calls in `ValidationArrayToFluentRuleRector` via new `buildModifierCallFromTupleExprArgs()`. Covers `NUMERIC_ARG_RULES`, `TWO_NUMERIC_ARG_RULES`, `STRING_ARG_RULES`, plus `regex` / `format` / `startsWith`. Falls back to `->rule([...])` for unknown rule names; mixed tuples + closure preserve closure as `->rule(fn)`.
- Flat wildcard `'items.*'` entries fold into parent `->each(<scalar>)` in `GroupWildcardRulesToEachRector`. Synthesizes bare `FluentRule::array()` parent when none exists. Handles const-concat wildcard keys.
- Both trait rectors walk class's ancestor chain via `ReflectionClass` and skip insertion when any parent already uses the trait.

### Fixed
- `GroupWildcardRulesToEachRector` synthesized parent uses short `FluentRule::array()` and inserts use import at sorted position.
- Trait `use` imports insert alphabetically (manual insertion, falls back to "append after last use" when existing imports aren't sorted). Shared logic in new `Concerns\ManagesNamespaceImports`.
- Switched `FluentRules::class` reference to a string literal so PHPStan doesn't trip on the optional forward-compatible attribute reference.
- Added regression fixture for `Rule::unique(User::class)->withoutTrashed()` → fluent `->unique()` callback.

## 0.2.0 - 2026-04-12

### Added
- Array-tuple rules lower directly to fluent method calls (same scope as 0.3.0).
- Flat wildcard `'items.*'` entries fold into parent `->each(<scalar>)` (same scope as 0.3.0).

### Fixed
- Trait `use` imports insert alphabetically. Shared AST logic in new `Concerns\ManagesTraitInsertion`.
- `FluentRules::class` reference switched to string literal (PHPStan).

## 0.1.1 - 2026-04-12

### Fixed
- `GroupWildcardRulesToEachRector` no longer injects `->nullable()` on synthesized parents (was silently accepting payloads without the parent key, short-circuiting nested `required()` children).
- `children()` and `each()` arrays always print one-key-per-line via `NEWLINED_ARRAY_PRINT`.
- `AddHasFluentRulesTraitRector` and `AddHasFluentValidationTraitRector` now emit a top-of-file `use` import for the trait via `UseNodesToAddCollector` and reference the short name in the class body.
- Trait rectors emit a blank line between the inserted trait and the next class member via a `Nop` statement (Livewire components with docblocked first members previously had the trait glued to the docblock).

## 0.1.0 - 2026-04-12

### Added
- Initial release. Rector rules for migrating Laravel validation to `sandermuller/laravel-fluent-validation`:
  - `ValidationStringToFluentRuleRector` — string-based rules (`'required|email|max:255'`).
  - `ValidationArrayToFluentRuleRector` — array-based rules (`['required', 'email']`).
  - `SimplifyFluentRuleRector` — collapses redundant fluent chains.
  - `GroupWildcardRulesToEachRector` — wildcard (`items.*`) rules into `FluentRule::each()`.
  - `AddHasFluentRulesTraitRector` and `AddHasFluentValidationTraitRector` — trait insertion for FormRequests, Livewire components, custom validators.
- Set lists in `FluentValidationSetList` for individual or full-pipeline runs.
- Covers `Validator::make()`, FormRequest `rules()`, Livewire `$rules`, inline validator calls.
