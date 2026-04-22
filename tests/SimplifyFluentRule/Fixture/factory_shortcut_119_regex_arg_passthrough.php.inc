<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;

$rules = [
    // Arg-carrying shortcut: regex method's pattern arg promotes to
    // the new factory. No label() in chain → shortcut fires.
    'code' => FluentRule::string()->regex('/^\d+$/')->required(),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;

$rules = [
    // Arg-carrying shortcut: regex method's pattern arg promotes to
    // the new factory. No label() in chain → shortcut fires.
    'code' => FluentRule::regex('/^\d+$/')->required(),
];

?>
