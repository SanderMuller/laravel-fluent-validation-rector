<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class RequiredArrayKeysArrayForm
{
    public function rules(): array
    {
        return [
            'meta' => FluentRule::array()->rule(['required_array_keys', 'id', 'slug']),
            'single' => FluentRule::array()->rule(['required_array_keys', 'key']),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class RequiredArrayKeysArrayForm
{
    public function rules(): array
    {
        return [
            'meta' => FluentRule::array()->requiredArrayKeys('id', 'slug'),
            'single' => FluentRule::array()->requiredArrayKeys('key'),
        ];
    }
}

?>
