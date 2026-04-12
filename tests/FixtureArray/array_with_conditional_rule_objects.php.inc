<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConditionalRuleObjectFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', Rule::when(true, 'min:3')],
            'role' => ['required', Rule::requiredIf(fn () => $this->isAdmin())],
            'notes' => ['nullable', 'string', Rule::excludeIf(fn () => ! $this->hasNotes())],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class ConditionalRuleObjectFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->rule(Rule::when(true, 'min:3')),
            'role' => FluentRule::field()->required()->rule(Rule::requiredIf(fn () => $this->isAdmin())),
            'notes' => FluentRule::string()->nullable()->rule(Rule::excludeIf(fn () => ! $this->hasNotes())),
        ];
    }
}

?>
