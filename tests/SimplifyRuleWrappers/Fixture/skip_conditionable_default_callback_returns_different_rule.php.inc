<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\Rules\StringRule;

// Hihaho peer must-have: 3-arg `when(value, callback, default)` — callback
// satisfies the invariant but `default` returns a different rule type.
// Invariant must apply to BOTH callbacks. Bail.

$rules = [
    'field' => FluentRule::string()
        ->when(
            true,
            fn (StringRule $r): StringRule => $r->nullable(),
            fn () => FluentRule::array(),
        )
        ->rule('max:255'),
];

?>
