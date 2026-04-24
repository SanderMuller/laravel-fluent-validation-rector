<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

// Chained Password config (->letters()->numbers()) is not a plain
// Password::default() / Password::min($n). Whole chain stays on the
// escape hatch — mapping a chained Password builder onto FluentRule::password()
// would drop the chained modifiers.

$rules = [
    'password' => FluentRule::string()->required()->rule(Password::min(8)->letters()->numbers()),
];

?>
