<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Contracts\Validation\DataAwareRule;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentValidation;

class AllFluentLaravelDataAwareRuleShorthand
{
    use HasFluentValidation;

    /**
     * @return DataAwareRule[]
     */
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

use Illuminate\Contracts\Validation\DataAwareRule;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentValidation;

class AllFluentLaravelDataAwareRuleShorthand
{
    use HasFluentValidation;

    /**
     * @return array<string, \SanderMuller\FluentValidation\Contracts\FluentRuleContract>
     */
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required(),
        ];
    }
}

?>
