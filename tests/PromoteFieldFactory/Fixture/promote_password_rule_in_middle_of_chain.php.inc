<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

// Matched `->rule(Password::default())` is in the MIDDLE of the chain
// (not outermost). Exercises the splice-rewire path where the hop above
// the matched node gets its `->var` redirected to the matched node's
// `->var`, so the spliced hop drops out without breaking the chain tail.

$rules = [
    'password' => FluentRule::string()
        ->required()
        ->rule(Password::default())
        ->bail()
        ->label('Password'),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

// Matched `->rule(Password::default())` is in the MIDDLE of the chain
// (not outermost). Exercises the splice-rewire path where the hop above
// the matched node gets its `->var` redirected to the matched node's
// `->var`, so the spliced hop drops out without breaking the chain tail.

$rules = [
    'password' => FluentRule::password()
        ->required()
        ->bail()
        ->label('Password'),
];

?>
