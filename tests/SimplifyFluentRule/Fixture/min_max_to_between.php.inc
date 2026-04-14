<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;

$rules = [
    'name' => FluentRule::string()->required()->min(2)->max(255),
    'age' => FluentRule::integer()->required()->min(0)->max(120),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;

$rules = [
    'name' => FluentRule::string()->required()->between(2, 255),
    'age' => FluentRule::integer()->required()->between(0, 120),
];

?>
