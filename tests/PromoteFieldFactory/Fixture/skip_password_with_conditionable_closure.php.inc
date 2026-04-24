<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\Rules\StringRule;

// Safety Gate #4: Conditionable hop in chain. Receiver type inside the
// closure could diverge from the outer chain; promoting the root factory
// in isolation is unsafe.

$rules = [
    'password' => FluentRule::string()
        ->when(true, fn (StringRule $r): StringRule => $r->regex('/^[a-z]+$/'))
        ->rule(Password::default()),
];

?>
