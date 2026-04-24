<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

$rules = [
    'tos' => FluentRule::boolean()->rule('accepted'),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

$rules = [
    'tos' => FluentRule::boolean()->accepted(),
];

?>
