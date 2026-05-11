<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Dedicated-factory promotion: the chain has exactly one `->rule('accepted')`
// hop and no other rule constraints, so the safe path is
// `FluentRule::accepted()` (whose constructor seeds `'accepted'`). The
// previously skipped intersection-only promotion to `FluentRule::boolean()`
// remains forbidden — boolean's implicit constraint rejects `"yes"`/`"on"`/
// `"true"`, which `accepted` permits.
class PromoteFieldToAccepted
{
    public function rules(): array
    {
        return [
            'agreed_to_tos_privacy' => FluentRule::field()->required()->rule('accepted'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Dedicated-factory promotion: the chain has exactly one `->rule('accepted')`
// hop and no other rule constraints, so the safe path is
// `FluentRule::accepted()` (whose constructor seeds `'accepted'`). The
// previously skipped intersection-only promotion to `FluentRule::boolean()`
// remains forbidden — boolean's implicit constraint rejects `"yes"`/`"on"`/
// `"true"`, which `accepted` permits.
class PromoteFieldToAccepted
{
    public function rules(): array
    {
        return [
            'agreed_to_tos_privacy' => FluentRule::accepted()->required(),
        ];
    }
}

?>
