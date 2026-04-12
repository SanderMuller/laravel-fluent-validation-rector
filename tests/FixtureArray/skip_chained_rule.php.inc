<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChainedRuleFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', Rule::unique('users')->ignore($this->user()->id)],
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

class ChainedRuleFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => FluentRule::email()->required()->unique('users', null, fn($rule) => $rule->ignore($this->user()->id)),
        ];
    }
}

?>
