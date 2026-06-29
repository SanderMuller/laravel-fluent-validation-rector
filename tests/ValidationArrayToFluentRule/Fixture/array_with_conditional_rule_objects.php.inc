<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

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

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConditionalRuleObjectFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', Rule::when(true, 'min:3')],
            'role' => FluentRule::field()
                ->required()
                ->requiredIf(fn() => $this->isAdmin()),
            'notes' => FluentRule::string()
                ->nullable()
                ->excludeIf(fn() => ! $this->hasNotes()),
        ];
    }
}

?>
