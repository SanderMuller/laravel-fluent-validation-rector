<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\HasFluentRules;

// String rules only — nothing to hang off the builder. Run the converters
// first; there is no FluentRule chain to move onto a FluentSchema receiver.
class SkipNoFluentRuleUsage extends FormRequest
{
    use HasFluentRules;

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'email' => 'required|email',
        ];
    }
}

?>
