<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipFieldWithPureFieldRuleAlone
{
    private const string OTHER = 'other';

    public function rules(): array
    {
        return [
            // Pure-field rules (`required_with` and family) are type-agnostic —
            // every typed builder exposes them — so they never narrow the
            // compatible-builder intersection to a singleton. A field() whose
            // only ->rule() hop is a pure-field rule (string-form or concat)
            // has nothing to pin a promotion target, so it stays field().
            // Locks the cross-rector contract after ParsesRulePayloads gained
            // string-form + concat coverage for these rules.
            'a' => FluentRule::field()->rule('required_with:other'),
            'b' => FluentRule::field()->rule('required_with:' . self::OTHER),
        ];
    }
}

?>
