<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

// rules() with a parameter is a non-standard override — Laravel calls rules()
// with no arguments, so a custom signature signals a shape this rule must not
// reshape into schema(). Leave it.
class NonStandardRulesSignatureRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(string $context): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>
