<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayPresenceOrder\Fixture\RunsFirst;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Email;
use Illuminate\Validation\Rules\ExcludeIf;
use Illuminate\Validation\Rules\Password;

// laravel-fluent-validation 1.45.1+ compiles a closure or bool presence
// conditional first in password() and email() too, the same as the array,
// so these arrays convert.

class PresenceConditionalPasswordEmailRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => [Rule::excludeIf(fn () => true), 'required', Password::default()],
            'pin' => ['required', Password::min(4), Rule::requiredIf(true)],
            'email' => [new ExcludeIf(fn () => true), Email::default()],
            'backup_email' => [Rule::prohibitedIf(false), Email::default()],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayPresenceOrder\Fixture\RunsFirst;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\ExcludeIf;

// laravel-fluent-validation 1.45.1+ compiles a closure or bool presence
// conditional first in password() and email() too, the same as the array,
// so these arrays convert.

class PresenceConditionalPasswordEmailRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => FluentRule::password()
                ->excludeIf(fn() => true)
                ->required(),
            'pin' => FluentRule::password(4)
                ->required()
                ->requiredIf(true),
            'email' => FluentRule::email()
                ->rule(new ExcludeIf(fn () => true)),
            'backup_email' => FluentRule::email()
                ->prohibitedIf(false),
        ];
    }
}

?>
