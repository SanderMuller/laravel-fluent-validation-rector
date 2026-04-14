<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class ConcatKeyRequest extends FormRequest
{
    private const ITEMS = 'items';

    public function rules(): array
    {
        return [
            self::ITEMS => FluentRule::array()->required(),
            self::ITEMS . '.*.name' => FluentRule::string()->required(),
            self::ITEMS . '.*.email' => FluentRule::email()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class ConcatKeyRequest extends FormRequest
{
    private const ITEMS = 'items';

    public function rules(): array
    {
        return [
            self::ITEMS => FluentRule::array()->required()->each([
                'name' => FluentRule::string()->required(),
                'email' => FluentRule::email()->required(),
            ]),
        ];
    }
}

?>
