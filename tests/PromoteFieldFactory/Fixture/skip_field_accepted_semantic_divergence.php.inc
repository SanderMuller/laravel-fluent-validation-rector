<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Regression: the method-name intersection alone would promote
// `field()->rule('accepted')` to `boolean()->accepted()` because
// `accepted()` exists only on BooleanRule. But BooleanRule seeds the
// `'boolean'` constraint which rejects `"yes"`, `"on"`, `"true"` — the
// canonical HTML-checkbox submit values. Promoting would silently break
// every TOS/consent checkbox form. Semantic-divergence guard must bail
// and leave the chain untouched so `->rule('accepted')` stays as the
// behavior-preserving escape hatch. Caught by downstream dogfood
// 2026-04-24; every register/impersonate path relied on `value="yes"`.
class SkipFieldAcceptedSemanticDivergence
{
    public function rules(): array
    {
        return [
            'agreed_to_tos_privacy' => FluentRule::field()->required()->rule('accepted'),
        ];
    }
}

?>
