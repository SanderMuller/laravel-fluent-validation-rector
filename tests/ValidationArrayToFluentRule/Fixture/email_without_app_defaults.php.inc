<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Email;

// 'email', Rule::email() and new Email() ignore Email::defaults(), so they
// convert with defaults: false. Email::default() applies them, so it keeps
// the plain factory.
class EmailWithoutAppDefaults extends FormRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'email', 'max:255'],
            'rule_factory' => ['required', Rule::email()],
            'new_object' => ['required', new Email()],
            'app_default' => ['required', Email::default()],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;

// 'email', Rule::email() and new Email() ignore Email::defaults(), so they
// convert with defaults: false. Email::default() applies them, so it keeps
// the plain factory.
class EmailWithoutAppDefaults extends FormRequest
{
    public function rules(): array
    {
        return [
            'token' => FluentRule::email(defaults: false)
                ->required()
                ->max(255),
            'rule_factory' => FluentRule::email(defaults: false)
                ->required(),
            'new_object' => FluentRule::email(defaults: false)
                ->required(),
            'app_default' => FluentRule::email()
                ->required(),
        ];
    }
}

?>
