<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class FluentEachWithInnerArray extends FormRequest
{
    public function rules(): array
    {
        return [
            'products' => FluentRule::array()->each([
                'id' => FluentRule::numeric()->integer()->required(),
                'selected_value' => FluentRule::numeric()->required(),
            ]),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class FluentEachWithInnerArray extends FormRequest
{
    /**
     * @return array<string, FluentRuleContract>
     */
    public function rules(): array
    {
        return [
            'products' => FluentRule::array()->each([
                'id' => FluentRule::numeric()->integer()->required(),
                'selected_value' => FluentRule::numeric()->required(),
            ]),
        ];
    }
}

?>
