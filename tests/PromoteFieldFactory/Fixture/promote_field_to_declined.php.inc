<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Sibling of `promote_field_to_accepted`. Same rationale: `DeclinedRule`'s
// constructor seeds `'declined'`, so the dedicated factory captures intent
// without the BooleanRule semantic divergence.
class PromoteFieldToDeclined
{
    public function rules(): array
    {
        return [
            'opted_out' => FluentRule::field()->required()->rule('declined'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Sibling of `promote_field_to_accepted`. Same rationale: `DeclinedRule`'s
// constructor seeds `'declined'`, so the dedicated factory captures intent
// without the BooleanRule semantic divergence.
class PromoteFieldToDeclined
{
    public function rules(): array
    {
        return [
            'opted_out' => FluentRule::declined()->required(),
        ];
    }
}

?>
