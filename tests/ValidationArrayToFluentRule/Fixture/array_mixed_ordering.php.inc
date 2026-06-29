<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class MixedOrderingFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'field' => ['bail', 'required', new \App\Rules\CustomRule(), new \App\Rules\AnotherRule(), 'string', 'max:255'],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use App\Rules\CustomRule;
use App\Rules\AnotherRule;
use Illuminate\Foundation\Http\FormRequest;

class MixedOrderingFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'field' => FluentRule::string()
                ->bail()
                ->required()
                ->rule(new CustomRule())
                ->rule(new AnotherRule())
                ->max(255),
        ];
    }
}

?>
