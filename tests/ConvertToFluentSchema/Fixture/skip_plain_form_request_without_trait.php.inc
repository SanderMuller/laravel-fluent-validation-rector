<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// No HasFluentRules trait — a plain FormRequest never dispatches schema(),
// so renaming rules() would silently disable validation. Leave alone.
class SkipPlainFormRequestWithoutTrait extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>
