<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class PasswordFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', Password::min(8)->letters()->numbers()->symbols()],
            'pin' => ['required', Password::min(4)],
            'secret' => ['required', Password::default()],
            'admin_pass' => ['required', Password::min(12)->mixedCase()->uncompromised()],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class PasswordFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => FluentRule::password(8)->required()->letters()->numbers()->symbols(),
            'pin' => FluentRule::password(4)->required(),
            'secret' => FluentRule::password()->required(),
            'admin_pass' => FluentRule::password(12)->required()->mixedCase()->uncompromised(),
        ];
    }
}

?>
