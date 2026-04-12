<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureSimplify;

use SanderMuller\FluentValidation\FluentRule;

// integer(true) has strict mode — should NOT be simplified to FluentRule::integer()
$rules = [
    'count' => FluentRule::numeric()->integer(true)->required(),
];

?>
