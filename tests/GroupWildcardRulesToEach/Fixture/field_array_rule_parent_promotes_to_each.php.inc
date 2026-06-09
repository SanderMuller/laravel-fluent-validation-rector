<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class FieldArrayRuleParentPromotesToEach extends FormRequest
{
    private const string ITEMS = 'items';

    public function rules(): array
    {
        return [
            self::ITEMS => FluentRule::field()
                ->nullable()
                ->rule(Rule::array()),
            self::ITEMS . '.*' => FluentRule::string()
                ->nullable()
                ->max(255),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class FieldArrayRuleParentPromotesToEach extends FormRequest
{
    private const string ITEMS = 'items';

    public function rules(): array
    {
        return [
            self::ITEMS => FluentRule::array()->nullable()->each(FluentRule::string()
                ->nullable()
                ->max(255)),
        ];
    }
}

?>
