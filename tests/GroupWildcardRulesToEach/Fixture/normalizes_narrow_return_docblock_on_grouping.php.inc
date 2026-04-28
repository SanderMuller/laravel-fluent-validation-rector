<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\StringRule;

class NarrowDocblockWildcardFormRequest extends FormRequest
{
    /**
     * @return array<string, StringRule>
     */
    public function rules(): array
    {
        return [
            'items.*.name' => FluentRule::string()->required(),
            'items.*.email' => FluentRule::email()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\StringRule;

class NarrowDocblockWildcardFormRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    public function rules(): array
    {
        return ['items' => FluentRule::array()->each([
            'name' => FluentRule::string()->required(),
            'email' => FluentRule::email()->required(),
        ])];
    }
}

?>
