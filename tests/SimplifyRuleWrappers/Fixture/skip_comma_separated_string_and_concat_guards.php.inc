<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipCommaSeparatedStringAndConcatGuards
{
    private const string FIELD = 'other';

    public function rules(): array
    {
        return [
            // Field-values family with only the field, no value — below the
            // arity-2 floor. Rewriting to `->requiredIf('type')` would
            // arity-fail at runtime.
            'a' => FluentRule::string()->rule('required_if:type'),
            // Empty trailing segment — would lower to an empty value the
            // original string didn't intend.
            'b' => FluentRule::string()->rule('required_if:type,'),
            // Pure-field family with an empty tail — no field to depend on.
            'c' => FluentRule::string()->rule('required_with:'),
            // Concat on the field-values family — the leading `$field` can't
            // be statically separated from trailing values inside a dynamic
            // expression, so it stays an escape hatch.
            'd' => FluentRule::string()->rule('required_if:type,' . self::FIELD),
            // Concat whose left operand carries no rule-name colon.
            'e' => FluentRule::string()->rule('required_with' . self::FIELD),
            // Concat with a non-string-oriented dynamic operand (method call) —
            // binding it into `requiredWith(string ...$fields)` could TypeError
            // under the consumer's strict_types, so it stays an escape hatch.
            'f' => FluentRule::string()->rule('required_with:' . $this->resolveField()),
        ];
    }

    public function resolveField(): string
    {
        return 'other';
    }
}

?>
