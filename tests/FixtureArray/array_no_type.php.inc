<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

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

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

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
