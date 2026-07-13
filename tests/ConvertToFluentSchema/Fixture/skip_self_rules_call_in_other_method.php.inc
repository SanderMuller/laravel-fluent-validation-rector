<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

// A method other than rules() calls $this->rules(); renaming rules() to
// schema() would strand that call (no FluentSchema builder is in scope in the
// helper), so the whole class is skipped.
class SelfCallingRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }

    public function debugRules(): array
    {
        return $this->rules();
    }
}

?>
