<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class NotInSnakeCaseStringForm
{
    public function rules(): array
    {
        // Laravel's string form is snake_case (`not_in:...`); the native
        // method is camelCase (`notIn(...)`). Rewrite must bridge both.
        return [
            'role' => FluentRule::string()->rule('not_in:guest,banned'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class NotInSnakeCaseStringForm
{
    public function rules(): array
    {
        // Laravel's string form is snake_case (`not_in:...`); the native
        // method is camelCase (`notIn(...)`). Rewrite must bridge both.
        return [
            'role' => FluentRule::string()->notIn(['guest', 'banned']),
        ];
    }
}

?>
