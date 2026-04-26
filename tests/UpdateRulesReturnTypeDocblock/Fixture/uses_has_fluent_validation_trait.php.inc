<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentValidation;

class UsesHasFluentValidationTrait
{
    use HasFluentValidation;

    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentValidation;

class UsesHasFluentValidationTrait
{
    use HasFluentValidation;

    /**
     * @return array<string, FluentRuleContract>
     */
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required(),
        ];
    }
}

?>
