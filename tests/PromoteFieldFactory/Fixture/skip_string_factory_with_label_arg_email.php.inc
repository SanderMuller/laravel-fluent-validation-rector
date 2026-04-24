<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Email;
use SanderMuller\FluentValidation\FluentRule;

// Safety Gate #1 applied to the Email branch: `FluentRule::string('Email Address')`
// source carries a label positional arg. Even though `FluentRule::email(?string $label)`
// shares the first-arg signature with string, v1 ships zero-arg-source only for
// consistency with the Password branch — structural arg-rebinding deferred.

$rules = [
    'email' => FluentRule::string('Email Address')->required()->rule(Email::default()),
];

?>
