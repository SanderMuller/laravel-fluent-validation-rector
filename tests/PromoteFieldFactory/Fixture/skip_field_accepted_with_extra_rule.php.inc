<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Multi-rule chain: dedicated promotion bails because `AcceptedRule` does
// not expose typed methods for arbitrary other rule names. Lowering
// `->rule('max:5')` against AcceptedRule would BadMethodCall in
// SimplifyRuleWrappersRector. Leave the chain on `field()` so the escape
// hatch keeps both constraints alive.
class SkipFieldAcceptedWithExtraRule
{
    public function rules(): array
    {
        return [
            'agreed_to_tos_privacy' => FluentRule::field()->required()->rule('accepted')->rule('max:5'),
        ];
    }
}

?>
