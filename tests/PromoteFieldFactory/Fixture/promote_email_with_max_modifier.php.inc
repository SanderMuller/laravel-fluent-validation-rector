<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Email;
use SanderMuller\FluentValidation\FluentRule;

// `EmailRule::max(int, ?string)` matches `StringRule::max` in both signature
// and semantic (string-length check). Email is NOT in the divergent blocklist,
// so promotion proceeds.

$rules = [
    'email' => FluentRule::string()->required()->max(255)->rule(Email::default()),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Email;
use SanderMuller\FluentValidation\FluentRule;

// `EmailRule::max(int, ?string)` matches `StringRule::max` in both signature
// and semantic (string-length check). Email is NOT in the divergent blocklist,
// so promotion proceeds.

$rules = [
    'email' => FluentRule::email()->required()->max(255),
];

?>
