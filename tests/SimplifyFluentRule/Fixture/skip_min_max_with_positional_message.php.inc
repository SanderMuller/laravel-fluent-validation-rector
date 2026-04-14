<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Positional message() after min — should NOT collapse to between
$rules = [
    'name' => FluentRule::string()->required()
        ->min(2)->message('Too short!')
        ->max(255),
];

?>
