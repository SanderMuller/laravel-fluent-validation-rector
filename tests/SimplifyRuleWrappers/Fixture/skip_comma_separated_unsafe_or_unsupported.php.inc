<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipCommaSeparatedUnsafeOrUnsupported
{
    public function rules(): array
    {
        return [
            // Category C (field + optional message) — not in scope. Stay as escape hatch.
            'a' => FluentRule::string()->rule(['required_if_accepted', 'terms']),
            // Category D (single field) — not in scope.
            'b' => FluentRule::string()->rule(['exclude_with', 'other']),
            // Unsafe arg: MethodCall in Category A second position — strict
            // whitelist rejects, rewrite skipped.
            'c' => FluentRule::string()->rule(['required_if', 'type', $this->roleValue()]),
            // Unsafe arg: Closure in field position.
            'd' => FluentRule::string()->rule(['required_if', fn (): bool => true]),
            // Unsafe arg: Ternary.
            'e' => FluentRule::string()->rule(['required_if', 'type', $this->flag ? 'a' : 'b']),
            // Category A with only field (no value) — min arity 2 not met.
            'f' => FluentRule::string()->rule(['required_if', 'field']),
            // Category B with empty tail — min arity 1 not met.
            'g' => FluentRule::string()->rule(['required_with']),
            // Float_ arg — fluent value union is string|int|bool|BackedEnum,
            // rewriting to ->requiredIf('field', 1.5) would TypeError.
            'h' => FluentRule::string()->rule(['required_if', 'ratio', 1.5]),
            // null arg — also not in fluent value union.
            'i' => FluentRule::string()->rule(['required_if', 'state', null]),
        ];
    }

    public function roleValue(): string
    {
        return 'admin';
    }
}

?>
