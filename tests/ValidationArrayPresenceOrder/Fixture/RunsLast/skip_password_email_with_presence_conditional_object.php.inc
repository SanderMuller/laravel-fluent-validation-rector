<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayPresenceOrder\Fixture\RunsLast;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Email;
use Illuminate\Validation\Rules\ExcludeIf;
use Illuminate\Validation\Rules\Password;

// Up to laravel-fluent-validation 1.45.0, password() and email() compile a
// closure or bool presence conditional after the Password / Email rule, so
// an exclude would no longer stop that check. These arrays stay as they are.

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
