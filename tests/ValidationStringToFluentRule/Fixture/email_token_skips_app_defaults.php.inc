<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// A plain 'email' rule ignores Email::defaults(). FluentRule::email() applies
// them, so the converter opts out to keep the same validation.
class EmailTokenSkipsAppDefaults extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'backup_email' => 'nullable|email',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;

// A plain 'email' rule ignores Email::defaults(). FluentRule::email() applies
// them, so the converter opts out to keep the same validation.
class EmailTokenSkipsAppDefaults extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => FluentRule::email(defaults: false)
                ->required()
                ->max(255),
            'backup_email' => FluentRule::email(defaults: false)
                ->nullable(),
        ];
    }
}

?>
