<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

// Safety Gate #2: PasswordRule lacks `same()`. Promotion would BadMethodCall
// at runtime. Chain stays on FluentRule::string() — ->rule(Password::default())
// remains as the escape hatch.

$rules = [
    'newPassword' => FluentRule::string()->required()->same('newPasswordConfirmation')->rule(Password::default()),
];

?>
