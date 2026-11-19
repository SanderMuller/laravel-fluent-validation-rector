<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// Email modes map to EmailRule methods. A type token with arguments that have
// no fluent equivalent keeps its rule string.
class EmailTokenModes extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email:rfc,dns|max:255',
            'contact' => 'nullable|email:strict,spoof',
            'native' => 'email:filter',
            'unicode' => 'email:filter_unicode',
            'unknown_mode' => 'required|email:custom',
            'keys' => 'required|array:name,email',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;

// Email modes map to EmailRule methods. A type token with arguments that have
// no fluent equivalent keeps its rule string.
class EmailTokenModes extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => FluentRule::email()
                ->rfcCompliant()
                ->validateMxRecord()
                ->required()
                ->max(255),
            'contact' => FluentRule::email()
                ->strict()
                ->preventSpoofing()
                ->nullable(),
            'native' => FluentRule::email()
                ->withNativeValidation(),
            'unicode' => FluentRule::email()
                ->withNativeValidation(allowUnicode: true),
            'unknown_mode' => 'required|email:custom',
            'keys' => 'required|array:name,email',
        ];
    }
}

?>
