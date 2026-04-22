<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipMessageForComposite
{
    public function rules(): array
    {
        // Composite methods emit multiple sub-keys; inline message: binds
        // to the last sub-rule only. Rewriting messageFor on a composite
        // method would misbind even when the key matches — we refuse.
        return [
            'count' => FluentRule::numeric()->digitsBetween(1, 5)->messageFor('digits_between', 'Bad digits range.'),
            'starts' => FluentRule::date()->between('2020-01-01', '2030-12-31')->messageFor('before', 'Bad range.'),
            'size' => FluentRule::numeric()->exactly(5)->messageFor('size', 'Exactly 5.'),
        ];
    }
}

?>
