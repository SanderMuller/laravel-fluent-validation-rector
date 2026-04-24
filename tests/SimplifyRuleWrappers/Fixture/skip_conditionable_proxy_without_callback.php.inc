<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Single-arg `->when($c)` returns the HigherOrderWhenProxy. Receiver
// type is dynamic, no callback to invariant-check. Bail.

$rules = [
    'field' => FluentRule::string()->when(true)->rule('max:255'),
];

?>
