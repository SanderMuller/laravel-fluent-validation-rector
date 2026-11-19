<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// excludeIf() adds a presence constraint, which compiles before the type in both forms.
class PromoteFieldArrayTokenAfterPresenceCondition
{
    public function rules(): array
    {
        return [
            'meta' => FluentRule::field()->nullable()->excludeIf('kind', 'none')->rule('array')->children(['title' => FluentRule::string()]),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// excludeIf() adds a presence constraint, which compiles before the type in both forms.
class PromoteFieldArrayTokenAfterPresenceCondition
{
    public function rules(): array
    {
        return [
            'meta' => FluentRule::array()->nullable()->excludeIf('kind', 'none')->children(['title' => FluentRule::string()]),
        ];
    }
}

?>
