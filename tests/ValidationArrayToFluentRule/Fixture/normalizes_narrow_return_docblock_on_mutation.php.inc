<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\StringRule;

class NarrowDocblockFormRequest extends FormRequest
{
    /**
     * @return array<string, StringRule>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Contracts\Validation\ValidationRule;
use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\StringRule;

class NarrowDocblockFormRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->max(255),
        ];
    }
}

?>
