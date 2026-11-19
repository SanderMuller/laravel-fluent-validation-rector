<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Internal;

use Illuminate\Validation\Rules\RequiredIf;
use SanderMuller\FluentValidation\FluentRule;
use Throwable;

/**
 * Whether the installed laravel-fluent-validation compiles a closure or bool
 * presence conditional (`requiredIf()`, `excludeIf()`, `prohibitedIf()` and
 * the `*Unless` forms) BEFORE the embedded Password / Email rule of
 * `FluentRule::password()` / `FluentRule::email()`.
 *
 * Up to 1.45.0 both classes compiled that rule object after their own check,
 * while `string()` (and a plain rules array) ran it first. Converting such a
 * chain or array to `password()` / `email()` then let an excluded value fail
 * the password or email check. 1.45.1 runs the conditional first, the same as
 * every other rule.
 *
 * The answer comes from the installed library's behavior, not its version: a
 * chain is compiled once per process and the result is memoized.
 *
 * @internal
 */
final class PresenceConditionalOrder
{
    /**
     * @var array<string, bool>
     */
    private static array $cache = [];

    /**
     * @param  'password'|'email'  $factory
     */
    public static function runsFirst(string $factory): bool
    {
        return self::$cache[$factory] ??= self::probe($factory);
    }

    /**
     * @param  'password'|'email'  $factory
     */
    private static function probe(string $factory): bool
    {
        try {
            $rule = $factory === 'password' ? FluentRule::password() : FluentRule::email();

            // requiredIf() with a closure always adds a RequiredIf object, on
            // every supported Laravel version (excludeIf() has an eager Laravel
            // 11 branch that adds a plain string instead).
            $compiled = $rule->requiredIf(static fn (): bool => true)->compiledRules();
        } catch (Throwable) {
            return false;
        }

        // email() without app defaults compiles to a pipe-joined string, where
        // the RequiredIf object is stringified to `required`.
        $first = is_string($compiled) ? explode('|', $compiled)[0] : ($compiled[0] ?? null);

        return $first instanceof RequiredIf || $first === 'required';
    }
}
