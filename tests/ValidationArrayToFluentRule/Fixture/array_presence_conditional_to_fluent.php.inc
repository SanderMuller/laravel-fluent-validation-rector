<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PresenceConditionalToFluentFormRequest extends FormRequest
{
    public function rules(): array
    {
        // All six presence conditionals convert for the closure / bool-literal
        // form, including alongside `nullable` — on the ^1.27.2 floor the
        // fluent self-validation path no longer short-circuits the requirement,
        // so `nullable` + a required-family conditional is behaviour-preserving.
        return [
            'req_closure' => [Rule::requiredIf(fn () => $this->isAdmin())],
            'req_nullable' => ['nullable', Rule::requiredIf(fn () => $this->isAdmin())],
            'req_unless_nullable' => ['nullable', 'string', Rule::requiredUnless(false)],
            'req_with_required' => ['required', Rule::requiredIf(true)],
            'exclude_closure' => ['nullable', Rule::excludeIf(fn () => $this->skip())],
            'exclude_unless' => ['nullable', Rule::excludeUnless(false)],
            'prohibited_bool' => ['nullable', Rule::prohibitedIf(true)],
            'prohibited_unless' => [Rule::prohibitedUnless(fn () => $this->locked())],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PresenceConditionalToFluentFormRequest extends FormRequest
{
    public function rules(): array
    {
        // All six presence conditionals convert for the closure / bool-literal
        // form, including alongside `nullable` — on the ^1.27.2 floor the
        // fluent self-validation path no longer short-circuits the requirement,
        // so `nullable` + a required-family conditional is behaviour-preserving.
        return [
            'req_closure' => FluentRule::field()
                ->requiredIf(fn() => $this->isAdmin()),
            'req_nullable' => FluentRule::field()
                ->nullable()
                ->requiredIf(fn() => $this->isAdmin()),
            'req_unless_nullable' => FluentRule::string()
                ->nullable()
                ->requiredUnless(false),
            'req_with_required' => FluentRule::field()
                ->required()
                ->requiredIf(true),
            'exclude_closure' => FluentRule::field()
                ->nullable()
                ->excludeIf(fn() => $this->skip()),
            'exclude_unless' => FluentRule::field()
                ->nullable()
                ->excludeUnless(false),
            'prohibited_bool' => FluentRule::field()
                ->nullable()
                ->prohibitedIf(true),
            'prohibited_unless' => FluentRule::field()
                ->prohibitedUnless(fn() => $this->locked()),
        ];
    }
}

?>
