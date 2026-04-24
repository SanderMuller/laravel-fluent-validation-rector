<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Email;
use SanderMuller\FluentValidation\FluentRule;

$rules = [
    'email' => FluentRule::string()->required()->rule(Email::default()),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Email;
use SanderMuller\FluentValidation\FluentRule;

$rules = [
    'email' => FluentRule::email()->required(),
];

?>
