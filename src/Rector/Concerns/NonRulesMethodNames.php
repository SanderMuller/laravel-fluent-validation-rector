<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

/**
 * Shared denylist of method names that look like rules-shaped methods
 * structurally but are framework hooks (Eloquent `casts()`, Laravel
 * `messages()`, JSON-serializable `toArray()`, etc.) — must NOT be
 * rewritten as validation rules even when their values overlap with
 * rule tokens.
 *
 * Used by:
 *
 * - `DetectsRulesShapedMethods::isRulesShapedMethod()` — auto-detect
 *   shape check at the per-method call site.
 * - `QualifiesForRulesProcessing::hasFluentRulesAttributeOnAnyMethod()` —
 *   class-qualification gate. A misapplied `#[FluentRules]` on a
 *   denylisted method (e.g. `casts()`) must NOT qualify the class for
 *   class-wide auto-detect of unrelated helpers; the gate skips the
 *   attribute check on denylisted method names.
 *
 * Lifting the constant to a shared trait keeps a single source of
 * truth — adding a future denylist entry only touches one place.
 *
 * @internal
 */
trait NonRulesMethodNames
{
    /**
     * Keys MUST be lowercase — PHP method names are case-insensitive at
     * runtime; lookup sites lowercase the source-cased method name
     * before checking. Storing lowercase here keeps the table
     * comparable to that normalized key.
     *
     * @var array<string, true>
     */
    private const array NON_RULES_METHOD_NAMES_DENYLIST = [
        'casts' => true,
        'getcasts' => true,
        'getdates' => true,
        'attributes' => true,
        'validationattributes' => true,
        'messages' => true,
        'validationmessages' => true,
        'middleware' => true,
        'getroutekeyname' => true,
        'broadcaston' => true,
        'broadcastwith' => true,
        'toarray' => true,
        'tojson' => true,
        'jsonserialize' => true,
    ];

    private function isNonRulesMethodName(?string $methodName): bool
    {
        return $methodName !== null
            && isset(self::NON_RULES_METHOD_NAMES_DENYLIST[strtolower($methodName)]);
    }
}
