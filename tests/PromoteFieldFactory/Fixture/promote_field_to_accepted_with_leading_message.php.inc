<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Companion to `skip_field_accepted_with_trailing_message`: a `->message()`
// hop that PRECEDES the spliced `->rule('accepted')` is safe. Source order
// binds the message to `required` (its preceding `lastConstraint`), and
// after promotion the chain `accepted()->required()->message(...)` still
// binds to `required` — preserved verbatim.
class PromoteFieldToAcceptedWithLeadingMessage
{
    public function rules(): array
    {
        return [
            'agreed_to_tos_privacy' => FluentRule::field()->required()->message('Required.')->rule('accepted'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Companion to `skip_field_accepted_with_trailing_message`: a `->message()`
// hop that PRECEDES the spliced `->rule('accepted')` is safe. Source order
// binds the message to `required` (its preceding `lastConstraint`), and
// after promotion the chain `accepted()->required()->message(...)` still
// binds to `required` — preserved verbatim.
class PromoteFieldToAcceptedWithLeadingMessage
{
    public function rules(): array
    {
        return [
            'agreed_to_tos_privacy' => FluentRule::accepted()->required()->message('Required.'),
        ];
    }
}

?>
