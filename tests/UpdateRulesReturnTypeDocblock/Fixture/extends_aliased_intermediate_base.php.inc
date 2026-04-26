<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Tests\Concerns\Fixture\IntermediateBaseRequest as Base;

class ExtendsAliasedIntermediateBase extends Base
{
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
use SanderMuller\FluentValidationRector\Tests\Concerns\Fixture\IntermediateBaseRequest as Base;

class ExtendsAliasedIntermediateBase extends Base
{
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
