<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PresenceConditionalCaseInsensitiveFormRequest extends FormRequest
{
    public function rules(): array
    {
        // PHP method calls are case-insensitive, so Rule::requiredif /
        // requiredunless match and convert to the canonical camelCase fluent
        // methods. Not nullable, so the required-family conversion is safe.
        return [
            'role' => [Rule::requiredif(fn () => $this->isAdmin())],
            'guest' => [Rule::requiredunless(fn () => $this->isGuest())],
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

class PresenceConditionalCaseInsensitiveFormRequest extends FormRequest
{
    public function rules(): array
    {
        // PHP method calls are case-insensitive, so Rule::requiredif /
        // requiredunless match and convert to the canonical camelCase fluent
        // methods. Not nullable, so the required-family conversion is safe.
        return [
            'role' => FluentRule::field()->requiredIf(fn () => $this->isAdmin()),
            'guest' => FluentRule::field()->requiredUnless(fn () => $this->isGuest()),
        ];
    }
}

?>
