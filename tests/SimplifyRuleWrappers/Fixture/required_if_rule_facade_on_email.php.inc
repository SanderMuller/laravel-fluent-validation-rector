<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class RequiredIfRuleFacadeOnEmail
{
    public function rules(bool $userEmailEnabled): array
    {
        return [
            'email' => FluentRule::email()
                ->bail()
                ->rule(Rule::requiredIf($userEmailEnabled)),
            'alias' => FluentRule::string()->rule(Rule::excludeIf($userEmailEnabled)),
            'token' => FluentRule::string()->rule(Rule::prohibitedIf($userEmailEnabled)),
            'phone' => FluentRule::string()->rule(Rule::requiredUnless($userEmailEnabled)),
            'fax' => FluentRule::string()->rule(Rule::excludeUnless($userEmailEnabled)),
            'pager' => FluentRule::string()->rule(Rule::prohibitedUnless($userEmailEnabled)),
            'note' => FluentRule::string()->rule(Rule::requiredIf(fn () => $userEmailEnabled)),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class RequiredIfRuleFacadeOnEmail
{
    public function rules(bool $userEmailEnabled): array
    {
        return [
            'email' => FluentRule::email()
                ->bail()
                ->requiredIf($userEmailEnabled),
            'alias' => FluentRule::string()
                ->excludeIf($userEmailEnabled),
            'token' => FluentRule::string()
                ->prohibitedIf($userEmailEnabled),
            'phone' => FluentRule::string()
                ->requiredUnless($userEmailEnabled),
            'fax' => FluentRule::string()
                ->excludeUnless($userEmailEnabled),
            'pager' => FluentRule::string()
                ->prohibitedUnless($userEmailEnabled),
            'note' => FluentRule::string()
                ->requiredIf(fn() => $userEmailEnabled),
        ];
    }
}

?>
