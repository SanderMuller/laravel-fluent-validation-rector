<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class RequiredWithArrayFormCategoryB
{
    public function rules(): array
    {
        return [
            'a' => FluentRule::string()->rule(['required_with', 'other']),
            'b' => FluentRule::string()->rule(['required_with_all', 'first', 'second', 'third']),
            'c' => FluentRule::string()->rule(['required_without', 'dependency']),
            'd' => FluentRule::string()->rule(['prohibits', 'conflicting']),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class RequiredWithArrayFormCategoryB
{
    public function rules(): array
    {
        return [
            'a' => FluentRule::string()->requiredWith('other'),
            'b' => FluentRule::string()->requiredWithAll('first', 'second', 'third'),
            'c' => FluentRule::string()->requiredWithout('dependency'),
            'd' => FluentRule::string()->prohibits('conflicting'),
        ];
    }
}

?>
