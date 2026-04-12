<?php

namespace SanderMuller\FluentValidationRector\Tests\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class NoTypeFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'agree' => 'required|accepted',
            'token' => 'required',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class NoTypeFormRequest extends FormRequest
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
