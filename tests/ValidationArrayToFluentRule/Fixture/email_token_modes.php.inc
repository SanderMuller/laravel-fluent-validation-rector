<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// Email modes map to EmailRule methods. A type token with arguments that have
// no fluent equivalent keeps its rule array.
class EmailTokenModes extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc,dns', 'max:255'],
            'unicode' => ['email:filter_unicode'],
            'unknown_mode' => ['required', 'email:custom'],
            'keys' => ['required', 'array:name,email'],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;

// Email modes map to EmailRule methods. A type token with arguments that have
// no fluent equivalent keeps its rule array.
class EmailTokenModes extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => FluentRule::email()
                ->required()
                ->rfcCompliant()
                ->validateMxRecord()
                ->max(255),
            'unicode' => FluentRule::email()
                ->withNativeValidation(allowUnicode: true),
            'unknown_mode' => ['required', 'email:custom'],
            'keys' => ['required', 'array:name,email'],
        ];
    }
}

?>
