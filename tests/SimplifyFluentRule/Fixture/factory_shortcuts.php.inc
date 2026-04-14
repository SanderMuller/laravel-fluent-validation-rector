<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;

$rules = [
    'url' => FluentRule::string()->url()->required(),
    'uuid' => FluentRule::string()->uuid()->nullable(),
    'ulid' => FluentRule::string()->ulid(),
    'ip' => FluentRule::string()->ip()->required(),
    'count' => FluentRule::numeric()->integer()->required()->min(0),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;

$rules = [
    'url' => FluentRule::url()->required(),
    'uuid' => FluentRule::uuid()->nullable(),
    'ulid' => FluentRule::ulid(),
    'ip' => FluentRule::ip()->required(),
    'count' => FluentRule::integer()->required()->min(0),
];

?>
