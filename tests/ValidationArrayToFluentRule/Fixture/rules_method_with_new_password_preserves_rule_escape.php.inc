<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;

class NewRuleObjectsInRulesMethodRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', new Password(8)],
            'email' => ['required', 'string', new Unique('users', 'email')],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;

class NewRuleObjectsInRulesMethodRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => FluentRule::field()->required()->rule(new Password(8)),
            'email' => FluentRule::string()->required()->rule(new Unique('users', 'email')),
        ];
    }
}

?>
