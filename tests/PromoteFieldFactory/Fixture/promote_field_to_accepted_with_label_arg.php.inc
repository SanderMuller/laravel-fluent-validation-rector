<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Labeled-variant: `FluentRule::field(?string $label)` rebinds cleanly to
// `FluentRule::accepted(?string $label, ?string $message)` because both
// targets are in `LABEL_FIRST_TARGETS`. The label arg survives the splice.
class PromoteFieldToAcceptedWithLabelArg
{
    public function rules(): array
    {
        return [
            'agreed_to_tos_privacy' => FluentRule::field('Agree to TOS')->required()->rule('accepted'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Labeled-variant: `FluentRule::field(?string $label)` rebinds cleanly to
// `FluentRule::accepted(?string $label, ?string $message)` because both
// targets are in `LABEL_FIRST_TARGETS`. The label arg survives the splice.
class PromoteFieldToAcceptedWithLabelArg
{
    public function rules(): array
    {
        return [
            'agreed_to_tos_privacy' => FluentRule::accepted('Agree to TOS')->required(),
        ];
    }
}

?>
