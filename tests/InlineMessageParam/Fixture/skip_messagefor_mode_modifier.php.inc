<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipMessageForModeModifier
{
    public function rules(): array
    {
        // Mode-modifier methods don't call addRule() — no message target.
        return [
            'email_strict' => FluentRule::email()->strict()->messageFor('strict', 'Must be strict.'),
            'email_rfc' => FluentRule::email()->rfcCompliant()->messageFor('rfcCompliant', 'RFC only.'),
        ];
    }
}

?>
