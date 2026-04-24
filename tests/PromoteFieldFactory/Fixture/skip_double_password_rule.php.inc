<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

// Two password rulesets stacked — ambiguous intent. Bail rather than guess.
$rules = [
    'password' => FluentRule::string()->required()->rule(Password::default())->rule(Password::min(12)),
];

?>
