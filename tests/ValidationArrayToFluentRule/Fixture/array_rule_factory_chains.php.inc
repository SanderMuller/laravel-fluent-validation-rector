<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RuleFactoryFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'count' => ['required', Rule::numeric()->integer()->min(1)->max(60)],
            'name' => ['required', Rule::string()->alpha()->max(50)],
            'start' => ['required', Rule::date()->afterToday()],
            'contact' => ['required', Rule::email()->rfcCompliant()->validateMxRecord()],
            'doc' => ['required', Rule::file()->max(1024)],
            'avatar' => ['required', Rule::imageFile(true)->maxWidth(500)],
            'photo' => ['nullable', Rule::imageFile()],
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

class RuleFactoryFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'count' => FluentRule::integer()->required()->min(1)->max(60),
            'name' => FluentRule::string()->required()->alpha()->max(50),
            'start' => FluentRule::date()->required()->afterToday(),
            'contact' => FluentRule::email()->required()->rfcCompliant()->validateMxRecord(),
            'doc' => FluentRule::file()->required()->max(1024),
            'avatar' => FluentRule::image()->required()->allowSvg()->maxWidth(500),
            'photo' => FluentRule::image()->nullable(),
        ];
    }
}

?>
