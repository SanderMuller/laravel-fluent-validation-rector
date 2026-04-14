<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class PasswordStringFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => 'required|password|min:8|confirmed',
            'pin' => 'required|password',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;

class PasswordStringFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => FluentRule::password()->required()->min(8)->confirmed(),
            'pin' => FluentRule::password()->required(),
        ];
    }
}

?>
