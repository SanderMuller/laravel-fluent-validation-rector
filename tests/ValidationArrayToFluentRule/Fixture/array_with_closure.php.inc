<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class ClosureRuleFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', function ($attribute, $value, $fail) {
                if ($value === 'forbidden') {
                    $fail('This name is forbidden.');
                }
            }],
            'code' => ['required', 'string', fn ($attribute, $value, $fail) => $value === '' ? $fail('Empty.') : null],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;

class ClosureRuleFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->rule(function ($attribute, $value, $fail) {
                if ($value === 'forbidden') {
                    $fail('This name is forbidden.');
                }
            }),
            'code' => FluentRule::string()->required()->rule(fn ($attribute, $value, $fail) => $value === '' ? $fail('Empty.') : null),
        ];
    }
}

?>
