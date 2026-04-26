<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\Parity;

use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Stringable;

/**
 * Runs Laravel's validator over a pre-rector rule shape and a post-rector
 * rule shape with the same payload, then classifies the diff between the
 * two error bags. Pure-ish: depends on Laravel's validator factory + the
 * en locale, but does not invoke the rector itself.
 *
 * @internal
 */
final class ParityHarness
{
    /**
     * @var list<string>
     */
    private const array DB_RULE_TOKENS = ['exists', 'unique'];

    /**
     * @param  array<string, mixed>  $rulesBefore
     * @param  array<string, mixed>  $rulesAfter
     * @param  array<string, mixed>  $payload
     */
    public static function compare(array $rulesBefore, array $rulesAfter, array $payload): ParityOutcome
    {
        $skipReason = self::detectSkipReason($rulesBefore) ?? self::detectSkipReason($rulesAfter);

        if ($skipReason !== null) {
            return new ParityOutcome(ParityType::Skipped, skipReason: $skipReason);
        }

        App::setLocale('en');

        $beforeErrors = self::runValidator($rulesBefore, $payload);
        $afterErrors = self::runValidator($rulesAfter, $payload);

        return new ParityOutcome(
            self::classify($beforeErrors, $afterErrors),
            $beforeErrors,
            $afterErrors,
        );
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $payload
     * @return array<string, list<string>>
     */
    private static function runValidator(array $rules, array $payload): array
    {
        $validator = Validator::make($payload, $rules);
        /** @var array<string, list<string>> $errors */
        $errors = $validator->errors()->toArray();
        ksort($errors);

        return $errors;
    }

    /**
     * @param  array<string, list<string>>  $before
     * @param  array<string, list<string>>  $after
     */
    private static function classify(array $before, array $after): ParityType
    {
        if ($before === $after) {
            return ParityType::Match;
        }

        if ($before === [] && $after !== []) {
            return ParityType::AfterRejectsBeforePasses;
        }

        if ($before !== [] && $after === []) {
            return ParityType::BeforeRejectsAfterPasses;
        }

        if (array_keys($before) !== array_keys($after)) {
            return ParityType::BothRejectDifferentMessages;
        }

        foreach ($before as $key => $messages) {
            $afterMessages = $after[$key];

            if ($messages === $afterMessages) {
                continue;
            }

            $beforeSorted = $messages;
            sort($beforeSorted);
            $afterSorted = $afterMessages;
            sort($afterSorted);

            if ($beforeSorted !== $afterSorted) {
                return ParityType::BothRejectDifferentMessages;
            }
        }

        return ParityType::BothRejectDifferentOrder;
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private static function detectSkipReason(array $rules): ?string
    {
        foreach ($rules as $field => $rule) {
            if (self::containsClosure($rule)) {
                return "skip: closure-bearing rule on '{$field}' (out of harness scope)";
            }

            $token = self::detectDbRule($rule);

            if ($token !== null) {
                return "skip: DB-dependent '{$token}' rule on '{$field}' (out of harness scope)";
            }
        }

        return null;
    }

    private static function containsClosure(mixed $value): bool
    {
        if ($value instanceof Closure) {
            return true;
        }

        if (is_array($value)) {
            foreach ($value as $element) {
                if (self::containsClosure($element)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function detectDbRule(mixed $value): ?string
    {
        $strings = self::flattenToStrings($value);

        foreach ($strings as $candidate) {
            foreach (self::DB_RULE_TOKENS as $token) {
                if (preg_match('/(^|\|)' . preg_quote($token, '/') . '(:|$|\|)/i', $candidate)) {
                    return $token;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function flattenToStrings(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        if ($value instanceof Stringable) {
            return [(string) $value];
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return [(string) $value];
        }

        if (is_array($value)) {
            $strings = [];

            foreach ($value as $element) {
                foreach (self::flattenToStrings($element) as $string) {
                    $strings[] = $string;
                }
            }

            return $strings;
        }

        return [];
    }
}
