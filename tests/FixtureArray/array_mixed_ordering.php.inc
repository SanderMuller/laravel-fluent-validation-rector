<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MixedOrderingFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'field' => ['bail', 'required', new \App\Rules\CustomRule(), Rule::requiredIf(fn () => true), 'string', 'max:255'],
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

class MixedOrderingFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'field' => FluentRule::string()->bail()->required()->rule(new \App\Rules\CustomRule())->rule(Rule::requiredIf(fn () => true))->max(255),
        ];
    }
}

?>
