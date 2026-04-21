<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipDateBetweenSemanticMismatch
{
    public function rules(): array
    {
        // DateRule has a native between() that expands to ->after()->before()
        // — chronological. Laravel's `between:` rule (even on a date field)
        // computes via getSize() → falls back to mb_strlen() — a size check.
        // Different semantics; refuse to rewrite to avoid silent behavior
        // change.
        return [
            'birthday' => FluentRule::date()->rule('between:8,10'),
            'event' => FluentRule::date()->rule(['between', 5, 20]),
        ];
    }
}

?>
