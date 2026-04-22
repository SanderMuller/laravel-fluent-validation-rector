<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipMessageForWrongKey
{
    public function rules(): array
    {
        // Pre-existing user misbinding (->min(3)->messageFor('max', ...))
        // — no rewrite, no skip-log. Chain stays untouched; user bug.
        return [
            'a' => FluentRule::string()->min(3)->messageFor('max', 'Mismatched.'),
            'b' => FluentRule::numeric()->between(1, 10)->messageFor('gt', 'Wrong key.'),
        ];
    }
}

?>
