<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class PasswordBailFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', Password::min(8)->rules('custom_rule')],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

class PasswordBailFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => FluentRule::field()->required()->rule(Password::min(8)->rules('custom_rule')),
        ];
    }
}

?>
