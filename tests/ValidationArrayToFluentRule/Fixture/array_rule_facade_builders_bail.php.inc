<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RuleFacadeBuildersBailFormRequest extends FormRequest
{
    public function rules(): array
    {
        // None of these convert: when/unless build ConditionalRules, forEach
        // builds NestedRules (no fluent equivalent), and a presence conditional
        // only converts for the unambiguous closure/bool-literal argument form.
        // Each bails its whole field → native array left untouched.
        return [
            'when' => ['nullable', Rule::when(true, 'min:3')],
            'unless' => ['nullable', Rule::unless(true, 'string')],
            'foreach' => ['nullable', Rule::forEach(fn () => ['min:1'])],
            'foreach_lc' => ['nullable', Rule::foreach(fn () => ['min:1'])], // case-insensitive
            'ambiguous_var' => ['nullable', Rule::requiredIf($this->condition())],
            'ambiguous_const' => ['nullable', Rule::requiredIf(ADMIN_MODE)],
            'no_args' => ['nullable', Rule::requiredIf()],
            'extra_args' => ['nullable', Rule::requiredIf(fn () => $this->isAdmin(), 'extra')],
            'spread' => ['nullable', Rule::requiredIf(...$this->conditions())],
        ];
    }
}

?>
