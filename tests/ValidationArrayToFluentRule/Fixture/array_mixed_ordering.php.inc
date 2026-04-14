<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

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

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use App\Rules\CustomRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MixedOrderingFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'field' => FluentRule::string()->bail()->required()->rule(new CustomRule())->rule(Rule::requiredIf(fn () => true))->max(255),
        ];
    }
}

?>
