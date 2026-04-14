<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class ComplexRealWorldRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'interactions' => FluentRule::array()->required()->min(1),
            'interactions.*.type' => FluentRule::string()->required(),
            'interactions.*.name' => FluentRule::string()->required(),
            'interactions.*.start' => FluentRule::numeric()->required()->integer(),
            'interactions.*.style' => FluentRule::field()->nullable(),
            'interactions.*.style.top' => FluentRule::string()->nullable(),
            'interactions.*.style.left' => FluentRule::string()->nullable(),
            'interactions.*.action' => FluentRule::field()->nullable(),
            'interactions.*.action.type' => FluentRule::string()->nullable(),
            'interactions.*.action.value' => FluentRule::string()->nullable(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class ComplexRealWorldRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'interactions' => FluentRule::array()->required()->min(1)->each([
                'type' => FluentRule::string()->required(),
                'name' => FluentRule::string()->required(),
                'start' => FluentRule::numeric()->required()->integer(),
                'style' => FluentRule::field()->nullable()->children([
                    'top' => FluentRule::string()->nullable(),
                    'left' => FluentRule::string()->nullable(),
                ]),
                'action' => FluentRule::field()->nullable()->children([
                    'type' => FluentRule::string()->nullable(),
                    'value' => FluentRule::string()->nullable(),
                ]),
            ]),
        ];
    }
}

?>
