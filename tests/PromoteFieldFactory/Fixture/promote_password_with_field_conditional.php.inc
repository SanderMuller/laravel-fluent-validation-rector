<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

// With a field name, a conditional compiles to a plain rule string, which
// both factories place the same way. Promotion goes ahead.

$rules = [
    'password' => FluentRule::string()->excludeIf('type', 'guest')->rule(Password::default()),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// With a field name, a conditional compiles to a plain rule string, which
// both factories place the same way. Promotion goes ahead.

$rules = [
    'password' => FluentRule::password()->excludeIf('type', 'guest'),
];

?>
