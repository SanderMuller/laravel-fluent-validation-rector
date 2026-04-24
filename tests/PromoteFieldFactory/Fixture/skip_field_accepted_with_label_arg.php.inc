<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Labeled-variant regression fixture: `field('Agree to TOS')` already
// routes through the label-arg-safety check, but the semantic-divergence
// guard runs BEFORE that check, so the bail order doesn't matter. What
// matters: both the labeled and unlabeled variants must leave the chain
// untouched. Without a fixture exercising the labeled path, a future
// refactor that reorders the gates could silently drop the guard on the
// labeled case.
class SkipFieldAcceptedWithLabelArg
{
    public function rules(): array
    {
        return [
            'agreed_to_tos_privacy' => FluentRule::field('Agree to TOS')->required()->rule('accepted'),
        ];
    }
}

?>
