<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class FieldArrayStringRuleParentPromotesToEach extends FormRequest
{
    private const string TAGS = 'tags';

    public function rules(): array
    {
        return [
            self::TAGS => FluentRule::field()->rule('array'),
            self::TAGS . '.*' => FluentRule::string()->max(50),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class FieldArrayStringRuleParentPromotesToEach extends FormRequest
{
    private const string TAGS = 'tags';

    public function rules(): array
    {
        return [
            self::TAGS => FluentRule::array()
                ->each(FluentRule::string()->max(50)),
        ];
    }
}

?>
