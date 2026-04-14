<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SimpleChildrenRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'address' => FluentRule::field()->required(),
            'address.street' => FluentRule::string()->required(),
            'address.city' => FluentRule::string()->required(),
            'address.zip' => FluentRule::string()->required()->max(10),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SimpleChildrenRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'address' => FluentRule::field()->required()->children([
                'street' => FluentRule::string()->required(),
                'city' => FluentRule::string()->required(),
                'zip' => FluentRule::string()->required()->max(10),
            ]),
        ];
    }
}

?>
