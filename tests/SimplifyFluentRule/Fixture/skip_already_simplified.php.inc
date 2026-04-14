<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Already using shortcuts — no change expected
$rules = [
    'url' => FluentRule::url()->required(),
    'count' => FluentRule::integer()->required()->min(0),
    'name' => FluentRule::string('Name')->required()->between(2, 255),
    'email' => FluentRule::email()->required(),
];

?>
