<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

$rules = [
    'optIn' => FluentRule::boolean()->rule('declined'),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

$rules = [
    'optIn' => FluentRule::boolean()->declined(),
];

?>
