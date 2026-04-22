<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use Illuminate\Validation\Rules\In;
use SanderMuller\FluentValidation\FluentRule;

class SkipRuleObjectWrongKey
{
    public function rules(): array
    {
        // `In` emits key 'in', but user wrote 'not_in' — pre-existing
        // misbinding. Chain stays untouched with no skip-log (§3 non-goal).
        return [
            'role' => FluentRule::string()->rule(new In(['a', 'b']))->messageFor('not_in', 'Wrong key.'),
        ];
    }
}

?>
