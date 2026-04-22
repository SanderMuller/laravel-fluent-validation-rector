<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipNonLiteralMessage
{
    public function rules(string $dynamic): array
    {
        // Variables, function calls, and non-flat concat stay chained —
        // inline works identically, the diff churn isn't worth it.
        return [
            'a' => FluentRule::email()->message($dynamic),
            'b' => FluentRule::string()->message(__('validation.custom.name')),
            'c' => FluentRule::url()->message('Prefix: ' . $dynamic),
        ];
    }
}

?>
