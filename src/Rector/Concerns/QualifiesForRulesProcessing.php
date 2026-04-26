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
