<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// The 'array' token is the seed constraint of FluentRule::array(), so the hop is removed.
class PromoteFieldArrayTokenToArray
{
    public function rules(): array
    {
        return [
            'tags' => FluentRule::field()->nullable()->rule('array'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// The 'array' token is the seed constraint of FluentRule::array(), so the hop is removed.
class PromoteFieldArrayTokenToArray
{
    public function rules(): array
    {
        return [
            'tags' => FluentRule::array()->nullable(),
        ];
    }
}

?>
