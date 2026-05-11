<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Regression: dedicated promotion must bail when a `->message(...)` hop
// trails the spliced `->rule('accepted')` call. `HasFieldModifiers::message()`
// binds to `$lastConstraint`, which the `->rule('accepted')` hop sets to
// `'accepted'` in the source chain. Splicing the rule away leaves
// `lastConstraint` pointing at whatever preceded — `'required'` here — so
// the custom message would silently retarget. Codex adversarial review
// flagged this 2026-05-11. Sibling case: trailing `->message()` after
// `->rule('declined')`.
class SkipFieldAcceptedWithTrailingMessage
{
    public function rules(): array
    {
        return [
            'agreed_to_tos_privacy' => FluentRule::field()->required()->rule('accepted')->message('You must accept the terms.'),
        ];
    }
}

?>
