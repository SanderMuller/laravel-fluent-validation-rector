<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class NoTypeArrayFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'agree' => ['required', 'accepted'],
            'token' => ['required'],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;

class NoTypeArrayFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'agree' => FluentRule::field()->required()->rule('accepted'),
            'token' => FluentRule::field()->required(),
        ];
    }
}

?>
