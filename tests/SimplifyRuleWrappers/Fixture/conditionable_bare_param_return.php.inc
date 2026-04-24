<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\Rules\StringRule;

// Bare `fn ($r) => $r` — callback returns the parameter verbatim. Receiver
// provably preserved. Step-through applies and downstream `->rule('max:255')`
// rewrites.

$rules = [
    'field' => FluentRule::string()
        ->when(true, fn (StringRule $r): StringRule => $r)
        ->rule('max:255'),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\Rules\StringRule;

// Bare `fn ($r) => $r` — callback returns the parameter verbatim. Receiver
// provably preserved. Step-through applies and downstream `->rule('max:255')`
// rewrites.

$rules = [
    'field' => FluentRule::string()
        ->when(true, fn (StringRule $r): StringRule => $r)->max(255),
];

?>
