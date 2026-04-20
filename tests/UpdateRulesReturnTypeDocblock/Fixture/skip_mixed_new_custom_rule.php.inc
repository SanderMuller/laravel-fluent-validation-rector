<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

final class CustomRuleForFixture implements ValidationRule
{
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
    }
}

class SkipMixedNewCustomRule extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required(),
            'custom' => new CustomRuleForFixture(),
        ];
    }
}

?>
