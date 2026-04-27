<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use Illuminate\Foundation\Http\FormRequest;
use PhpParser\Node\Stmt\Class_;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidation\FluentRules;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidation\HasFluentValidation;
use SanderMuller\FluentValidation\HasFluentValidationForFilament;

/**
 * Class-qualification gate shared between the converter, grouping, and
 * docblock-polish rectors. Returns true when the class is one this
 * package's rectors are willing to process for rule-array conversion or
 * docblock narrowing.
 *
 * Originally lived inline on `UpdateRulesReturnTypeDocblockRector` as
 * `classQualifiesForPolish()`. Extracted so the converter and grouping
 * rectors can share the same proven boundary before they widen their
 * method-discovery to include rules-shaped methods beyond literal
 * `rules()` (the auto-detection path was unsafe to ship without this
 * gate — without it, every Domain entity / Service / Action class with a
 * string-keyed array return became a false-positive surface).
 *
 * Important properties preserved from the original:
 *
 * - **Operates on `Class_`, not `ClassLike`.** Interface, trait, and enum
 *   bodies bail at the entry; the gate is concrete-class-only.
 * - **Silent rejection of unrelated classes.** No skip-log entry. Logging
 *   would emit one entry per non-qualifying class in a typical Laravel
 *   codebase — flooding the actionable tier with noise consumers can't
 *   act on (the class is unrelated to validation).
 * - **Includes Livewire ancestry as a fourth qualifying condition.**
 *   The original docblock-only gate did not need this — by the time the
 *   POLISH set runs, a Livewire component using FluentRule has already
 *   had `HasFluentValidation` added by the trait rectors and qualifies
 *   via the trait check. The CONVERTER rectors run BEFORE the trait
 *   rectors in the `ALL` set, so Livewire components have not yet
 *   acquired the trait when conversion fires. Excluding Livewire here
 *   would silently break Livewire string-rule conversion. Adding it is
 *   a no-op for the docblock rector — its downstream checks
 *   (`singleLiteralArrayReturn` / `allItemsAreFluentChains`) reject
 *   non-FluentRule bodies regardless.
 *
 * Using class must also use `DetectsInheritedTraits` (provides
 * `anyAncestorExtends` + `currentOrAncestorUsesTrait`) and
 * `IdentifiesLivewireClasses` (provides `isLivewireClass`).
 *
 * @internal
 *
 * @phpstan-require-extends AbstractRector
 */
trait QualifiesForRulesProcessing
{
    use NonRulesMethodNames;

    /**
     * The fluent-validation traits that mark a class as a rules-bearing
     * candidate even when it doesn't extend FormRequest. Mirrors the
     * `QUALIFYING_TRAIT_FQNS` constant that previously lived on
     * `UpdateRulesReturnTypeDocblockRector`.
     *
     * @var list<string>
     */
    private const array RULES_PROCESSING_QUALIFYING_TRAIT_FQNS = [
        HasFluentRules::class,
        HasFluentValidation::class,
        HasFluentValidationForFilament::class,
    ];

    /**
     * Static dedup set for layer-2 misapplied-`#[FluentRules]` warnings.
     * Keyed by class FQCN. Ensures each class emits the warning at most
     * once across the rector run, even when multiple rectors all call
     * `qualifiesForRulesProcessing()` on the same class.
     *
     * @var array<string, true>
     */
    private static array $denylistedAttributedWarnings = [];

    /**
     * Returns true when the class qualifies as a rules-bearing class.
     *
     * Qualifying conditions (any one is sufficient):
     *
     * 1. Extends `Illuminate\Foundation\Http\FormRequest` anywhere in the
     *    ancestor chain (aliased imports included; `anyAncestorExtends`
     *    handles the alias resolution).
     * 2. Uses one of the fluent-validation traits — `HasFluentRules`,
     *    `HasFluentValidation`, `HasFluentValidationForFilament` —
     *    directly OR via an ancestor.
     */
    private function qualifiesForRulesProcessing(Class_ $class): bool
    {
        // Layer-2 misapplied-attribute warning. Runs unconditionally —
        // if a class has `#[FluentRules]` on a denylisted method
        // (`casts()`, `messages()`, etc.) the user gets the diagnostic
        // regardless of whether the class otherwise qualifies. Static
        // dedup keys on class FQCN so each class emits at most once
        // across the 5 rectors that call this gate.
        $this->warnDenylistedAttributedMethods($class);

        if ($this->anyAncestorExtends($class, FormRequest::class)) {
            return true;
        }

        foreach (self::RULES_PROCESSING_QUALIFYING_TRAIT_FQNS as $traitFqn) {
            if ($this->currentOrAncestorUsesTrait($class, $traitFqn)) {
                return true;
            }
        }

        if ($this->isLivewireClass($class)) {
            return true;
        }

        // Opt-in marker for custom validator classes that hold rules under
        // a non-`rules()` method name. The string form avoids requiring
        // the class to exist at static-analysis time — `FluentRules`
        // ships in newer laravel-fluent-validation releases.
        return $this->hasFluentRulesAttributeOnAnyMethod($class);
    }

    /**
     * Layer-2 denylist warning. Emits one skip-log entry per
     * denylisted-method-with-`#[FluentRules]` per class, deduped by
     * class FQCN so the same class doesn't generate 5 identical
     * warnings as it passes through 5 rectors. Lives here (in the
     * shared qualification gate) rather than in the converter trait so
     * the warning fires even when the class fails layer-1 qualification
     * (i.e. the misapplied attribute was the only would-be qualifier).
     */
    private function warnDenylistedAttributedMethods(Class_ $class): void
    {
        $fqcn = $this->getName($class);

        if ($fqcn === null || isset(self::$denylistedAttributedWarnings[$fqcn])) {
            return;
        }

        $hasAnyAttribute = false;

        foreach ($class->getMethods() as $method) {
            if ($method->attrGroups !== []) {
                $hasAnyAttribute = true;
                break;
            }
        }

        if (! $hasAnyAttribute) {
            self::$denylistedAttributedWarnings[$fqcn] = true;

            return;
        }

        foreach ($class->getMethods() as $method) {
            $methodName = $this->getName($method);

            if (! $this->isNonRulesMethodName($methodName)) {
                continue;
            }

            if (! $this->hasFluentRulesAttribute($method)) {
                continue;
            }

            $this->logSkip(
                $class,
                sprintf(
                    '#[FluentRules] on method "%s" — this method name is in the non-rules denylist (Eloquent / Laravel framework method). The attribute is silently ignored. Did you mean to apply it to a different method?',
                    $methodName ?? '<unknown>',
                ),
            );
        }

        self::$denylistedAttributedWarnings[$fqcn] = true;
    }

    /**
     * Returns true when the class qualifies via a strong, class-wide
     * signal (FormRequest ancestry, fluent-validation trait, Livewire
     * component) — i.e. when class-wide auto-detection of rules-shaped
     * methods is safe.
     *
     * Codex 2026-04-26 catch: a class that qualifies ONLY via the
     * method-level `#[FluentRules]` attribute used to enable class-wide
     * auto-detection too. That converted a per-method opt-in into a
     * class-wide trust boundary bypass — sibling helpers with a single
     * rule-like token could be rewritten as validation rules. The
     * consumer rectors gate the auto-detect path on this stricter
     * predicate so the attribute path stays per-method-scoped.
     */
    private function qualifiesForRulesProcessingClassWide(Class_ $class): bool
    {
        if ($this->anyAncestorExtends($class, FormRequest::class)) {
            return true;
        }

        foreach (self::RULES_PROCESSING_QUALIFYING_TRAIT_FQNS as $traitFqn) {
            if ($this->currentOrAncestorUsesTrait($class, $traitFqn)) {
                return true;
            }
        }

        return $this->isLivewireClass($class);
    }

    private function hasFluentRulesAttributeOnAnyMethod(Class_ $class): bool
    {
        foreach ($class->getMethods() as $method) {
            // Skip denylisted method names — `#[FluentRules]` on
            // Eloquent's `casts()`, Laravel's `messages()`, etc. is a
            // mistake. The qualification signal is invalid; pretend the
            // attribute isn't there for class-qualification purposes.
            // Without this, a class qualifying ONLY via a misapplied
            // `#[FluentRules]` on `casts()` would unlock class-wide
            // auto-detect of unrelated helpers — exactly the regression
            // class 0.14.1 closed for non-attributed paths.
            // `DetectsRulesShapedMethods::isRulesShapedMethod()` also
            // honours the denylist at the per-method shape check.
            if ($this->isNonRulesMethodName($this->getName($method))) {
                continue;
            }

            foreach ($method->attrGroups as $attrGroup) {
                foreach ($attrGroup->attrs as $attr) {
                    if ($this->getName($attr->name) === FluentRules::class) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
