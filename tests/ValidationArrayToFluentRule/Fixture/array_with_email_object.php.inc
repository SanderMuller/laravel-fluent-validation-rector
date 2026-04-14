<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Email;

class EmailObjectFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'primary' => ['required', Email::default()],
            'secondary' => ['nullable', new Email()],
            'strict' => ['required', Email::default()->rfcCompliant()->validateMxRecord()],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Email;

class EmailObjectFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'primary' => FluentRule::email()->required(),
            'secondary' => FluentRule::email()->nullable(),
            'strict' => FluentRule::email()->required()->rfcCompliant()->validateMxRecord(),
        ];
    }
}

?>
