<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureSimplify;

use SanderMuller\FluentValidation\FluentRule;

// min/max with messageFor should NOT be collapsed to between
$rules = [
    'name' => FluentRule::string()->required()
        ->min(2)
        ->max(255)
        ->messageFor('min', 'Too short!')
        ->messageFor('max', 'Too long!'),
];

?>
